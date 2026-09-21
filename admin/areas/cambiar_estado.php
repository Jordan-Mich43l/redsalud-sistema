<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];


/* VERIFICAR QUE EL USUARIO SEA ADMINISTRADOR */
$sqlAdmin = "
    SELECT
        id_usuario,
        nombres,
        apellidos,
        correo,
        id_rol,
        estado
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
";

$stmtAdmin = $conn->prepare($sqlAdmin);

if (!$stmtAdmin) {
    $_SESSION["mensaje_error"] = "No se pudo verificar el usuario.";

    header("Location: index.php");
    exit;
}

$stmtAdmin->bind_param("i", $id_usuario);
$stmtAdmin->execute();

$admin_usuario = $stmtAdmin->get_result()->fetch_assoc();

$stmtAdmin->close();

if (
    !$admin_usuario ||
    (int) $admin_usuario["id_rol"] !== 1 ||
    $admin_usuario["estado"] !== "ACTIVO"
) {
    header("Location: ../../auth/login.php");
    exit;
}


/* SOLO SE ACEPTAN PETICIONES POST */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}


/* ID DEL ÁREA */
$id = (int) ($_POST["id_area"] ?? 0);

if ($id <= 0) {
    $_SESSION["mensaje_error"] = "Dirección no válida.";

    header("Location: index.php");
    exit;
}


/* VERIFICAR QUE EL ÁREA EXISTA */
$stmt = $conn->prepare("
    SELECT
        id_area,
        nombre_area
    FROM areas
    WHERE id_area = ?
    LIMIT 1
");

if (!$stmt) {
    $_SESSION["mensaje_error"] = "No se pudo verificar el área.";

    header("Location: index.php");
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();

$area = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$area) {
    $_SESSION["mensaje_error"] = "El área no existe.";

    header("Location: index.php");
    exit;
}


/*
 * VERIFICAR REGISTROS RELACIONADOS
 *
 * No se puede eliminar un área que esté siendo utilizada
 * por otras tablas del sistema.
 */
$checks = [
    ["usuarios", "id_area"],
    ["programas_internos", "id_area"],
    ["expedientes", "id_area_emisora"],
    ["movimientos_documento", "area_origen"],
    ["movimientos_documento", "area_destino"]
];

$total = 0;

foreach ($checks as [$tabla, $campo]) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM $tabla
        WHERE $campo = ?
    ");

    if (!$stmt) {
        $_SESSION["mensaje_error"] =
            "No se pudo verificar si el área tiene registros relacionados.";

        header("Location: index.php");
        exit;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $resultado = $stmt->get_result()->fetch_assoc();

    $total += (int) ($resultado["total"] ?? 0);

    $stmt->close();
}


/* NO PERMITIR ELIMINAR SI ESTÁ RELACIONADA */
if ($total > 0) {

    $_SESSION["mensaje_error"] =
        "No se puede eliminar el área porque tiene registros relacionados.";

    header("Location: index.php");
    exit;
}


/* ELIMINAR ÁREA */
$stmt = $conn->prepare("
    DELETE FROM areas
    WHERE id_area = ?
");

if (!$stmt) {
    $_SESSION["mensaje_error"] = "No se pudo preparar la eliminación.";

    header("Location: index.php");
    exit;
}

$stmt->bind_param("i", $id);

$ok = $stmt->execute();

$stmt->close();


/* MENSAJE */
if ($ok) {
    $_SESSION["mensaje_exito"] = "Dirección eliminada correctamente.";
} else {
    $_SESSION["mensaje_error"] = "No se pudo eliminar el área.";
}


/* VOLVER AL LISTADO */
header("Location: index.php");
exit;