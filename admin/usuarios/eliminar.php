<?php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario_admin = (int) $_SESSION["id_usuario"];

$stmt = $conn->prepare("SELECT id_usuario, id_rol, estado FROM usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param("i", $id_usuario_admin);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || (int)$admin["estado"] !== 1 || (int)$admin["id_rol"] !== 1) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$id = (int)($_POST["id_usuario"] ?? 0);

if ($id <= 0 || $id === $id_usuario_admin) {
    $_SESSION["mensaje_error"] = "No se puede eliminar este usuario.";
    header("Location: index.php");
    exit;
}

// Soft delete: desactivar usuario y su credencial (evita problemas de FK)
$conn->begin_transaction();
try {
    $stmt1 = $conn->prepare("UPDATE usuarios SET estado = 0 WHERE id_usuario = ?");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    $stmt1->close();

    $stmt2 = $conn->prepare("UPDATE credenciales_acceso SET password_hash = CONCAT('DISABLED_', password_hash) WHERE id_usuario = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $stmt2->close();

    $conn->commit();
    $_SESSION["mensaje_exito"] = "Usuario eliminado (desactivado) correctamente.";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION["mensaje_error"] = "No se pudo eliminar el usuario: " . $e->getMessage();
}

header("Location: index.php");
exit;
