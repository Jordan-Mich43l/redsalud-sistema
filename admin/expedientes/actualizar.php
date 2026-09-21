<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];

$sqlUsuario = "
    SELECT id_usuario, id_area, id_rol, estado
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

if (!in_array((int) $usuario["id_rol"], [1, 2], true)) {
    $_SESSION["mensaje_error"] = "No tienes permisos para editar expedientes.";
    header("Location: index.php");
    exit;
}

$id_expediente         = (int) ($_POST["id_expediente"] ?? 0);
$origen_documento      = strtoupper(trim($_POST["origen_documento"] ?? ""));
$id_tipo_documento     = (int) ($_POST["id_tipo_documento"] ?? 0);
$id_area_emisora       = (int) ($_POST["id_area_emisora"] ?? 0);
$remitente             = trim($_POST["remitente"] ?? "");
$asunto                = trim($_POST["asunto"] ?? "");
$numero_folios         = (int) ($_POST["numero_folios"] ?? 0);
$fecha_limite_atencion = trim($_POST["fecha_limite_atencion"] ?? "");

$errores = [];

if ($id_expediente <= 0) {
    $errores[] = "Expediente no válido.";
}

if ($origen_documento !== "EXTERNO" && $origen_documento !== "INTERNO") {
    $errores[] = "El origen del documento no es válido.";
}

if ($id_tipo_documento <= 0) {
    $errores[] = "Debe seleccionar un tipo de documento.";
}

if ($origen_documento === "INTERNO" && $id_area_emisora <= 0) {
    $errores[] = "Debe seleccionar la dirección emisora.";
}

if ($origen_documento === "EXTERNO") {
    $id_area_emisora = null;
}

if ($remitente === "" || mb_strlen($remitente) > 150) {
    $errores[] = "El remitente es obligatorio y no debe superar 150 caracteres.";
}

if ($asunto === "") {
    $errores[] = "El asunto es obligatorio.";
}

if ($numero_folios < 1 || $numero_folios > 9999) {
    $errores[] = "El número de folios debe estar entre 1 y 9999.";
}

if ($fecha_limite_atencion === "") {
    $errores[] = "La fecha límite de atención es obligatoria.";
}

// Verificar que el expediente existe
$stmtCheck = $conn->prepare("SELECT id_expediente FROM expedientes WHERE id_expediente = ? LIMIT 1");
$stmtCheck->bind_param("i", $id_expediente);
$stmtCheck->execute();
$existe = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$existe) {
    $errores[] = "El expediente no existe.";
}

if (!empty($errores)) {
    $_SESSION["mensaje_error"] = implode(" ", $errores);
    header("Location: editar.php?id=" . $id_expediente);
    exit;
}

$sqlUpdate = "
    UPDATE expedientes SET
        origen_documento = ?,
        id_area_emisora = ?,
        id_tipo_documento = ?,
        remitente = ?,
        asunto = ?,
        numero_folios = ?,
        fecha_limite_atencion = ?
    WHERE id_expediente = ?
";

$stmt = $conn->prepare($sqlUpdate);
if (!$stmt) {
    $_SESSION["mensaje_error"] = "Error al preparar la actualización.";
    header("Location: editar.php?id=" . $id_expediente);
    exit;
}

// mysqli requiere variable para NULL
if ($id_area_emisora === null || $id_area_emisora === 0) {
    $id_area_emisora = null;
}

$stmt->bind_param(
    "siissisi",
    $origen_documento,
    $id_area_emisora,
    $id_tipo_documento,
    $remitente,
    $asunto,
    $numero_folios,
    $fecha_limite_atencion,
    $id_expediente
);

if (!$stmt->execute()) {
    $_SESSION["mensaje_error"] = "No se pudo actualizar el expediente: " . $stmt->error;
    $stmt->close();
    header("Location: editar.php?id=" . $id_expediente);
    exit;
}

$stmt->close();

$_SESSION["mensaje_exito"] = "Expediente actualizado correctamente.";
header("Location: ver.php?id=" . $id_expediente);
exit;
