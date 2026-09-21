<?php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];

$sqlUsuario = "SELECT id_usuario, id_rol, estado FROM usuarios WHERE id_usuario = ? LIMIT 1";
$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $id_usuario);
$stmtUsuario->execute();
$usuario = $stmtUsuario->get_result()->fetch_assoc();
$stmtUsuario->close();

if (!$usuario || (int)$usuario["estado"] !== 1) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

// Solo Admin (1) y Jefatura (3)
if (!in_array((int)$usuario["id_rol"], [1, 3], true)) {
    $_SESSION["mensaje_error"] = "No tienes permisos para cambiar el estado del expediente.";
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$id_expediente = (int)($_POST["id_expediente"] ?? 0);
$nuevo_estado = strtoupper(trim($_POST["nuevo_estado"] ?? ""));

$estados_validos = ["EN_TRAMITE", "OBSERVADO", "ATENDIDO", "ARCHIVADO"];

if ($id_expediente <= 0 || !in_array($nuevo_estado, $estados_validos, true)) {
    $_SESSION["mensaje_error"] = "Datos inválidos para cambiar el estado.";
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("UPDATE expedientes SET estado_expediente = ? WHERE id_expediente = ?");
$stmt->bind_param("si", $nuevo_estado, $id_expediente);

if ($stmt->execute()) {
    $_SESSION["mensaje_exito"] = "Estado del expediente actualizado a " . str_replace("_", " ", $nuevo_estado) . ".";
} else {
    $_SESSION["mensaje_error"] = "No se pudo actualizar el estado.";
}
$stmt->close();

header("Location: ver.php?id=" . $id_expediente);
exit;
