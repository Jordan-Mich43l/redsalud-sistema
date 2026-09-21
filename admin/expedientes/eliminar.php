<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];

$sqlUsuario = "
    SELECT id_usuario, id_rol, estado
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $id_usuario);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();
$stmtUsuario->close();

if (!$usuario || (int) $usuario["estado"] !== 1) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

// Solo administrador
if ((int) $usuario["id_rol"] !== 1) {
    $_SESSION["mensaje_error"] = "No tienes permisos para eliminar expedientes.";
    header("Location: index.php");
    exit;
}

$id_expediente = (int) ($_GET["id"] ?? 0);

if ($id_expediente <= 0) {
    header("Location: index.php");
    exit;
}

$stmtExp = $conn->prepare("
    SELECT id_expediente, numero_expediente, archivo_principal_pdf
    FROM expedientes
    WHERE id_expediente = ?
    LIMIT 1
");
$stmtExp->bind_param("i", $id_expediente);
$stmtExp->execute();
$exp = $stmtExp->get_result()->fetch_assoc();
$stmtExp->close();

if (!$exp) {
    $_SESSION["mensaje_error"] = "Expediente no encontrado.";
    header("Location: index.php");
    exit;
}

$conn->begin_transaction();

try {
    // Proveídos ligados a movimientos de este expediente
    $conn->query("
        DELETE p FROM proveidos p
        INNER JOIN movimientos_documento m ON m.id_movimiento = p.id_movimiento
        WHERE m.id_expediente = " . (int) $id_expediente
    );

    // Movimientos
    $stmt = $conn->prepare("DELETE FROM movimientos_documento WHERE id_expediente = ?");
    $stmt->bind_param("i", $id_expediente);
    $stmt->execute();
    $stmt->close();

    // Notificaciones
    $stmt = $conn->prepare("DELETE FROM notificaciones WHERE id_expediente = ?");
    $stmt->bind_param("i", $id_expediente);
    $stmt->execute();
    $stmt->close();

    // Anexos (también CASCADE, pero limpiamos archivos)
    $stmtAn = $conn->prepare("SELECT ruta_archivo FROM anexos_expediente WHERE id_expediente = ?");
    $stmtAn->bind_param("i", $id_expediente);
    $stmtAn->execute();
    $resAn = $stmtAn->get_result();
    while ($an = $resAn->fetch_assoc()) {
        $ruta = "../../" . ltrim($an["ruta_archivo"], "/");
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
    $stmtAn->close();

    $stmt = $conn->prepare("DELETE FROM anexos_expediente WHERE id_expediente = ?");
    $stmt->bind_param("i", $id_expediente);
    $stmt->execute();
    $stmt->close();

    // Expediente
    $stmt = $conn->prepare("DELETE FROM expedientes WHERE id_expediente = ?");
    $stmt->bind_param("i", $id_expediente);
    $stmt->execute();
    $stmt->close();

    // Archivo principal
    if (!empty($exp["archivo_principal_pdf"])) {
        $ruta = "../../" . ltrim($exp["archivo_principal_pdf"], "/");
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    $conn->commit();

    $_SESSION["mensaje_exito"] = "Expediente " . $exp["numero_expediente"] . " eliminado correctamente.";
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION["mensaje_error"] = "No se pudo eliminar el expediente. " . $e->getMessage();
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}
