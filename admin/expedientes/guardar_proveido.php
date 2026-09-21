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
    $_SESSION["mensaje_error"] = "No tienes permisos para registrar proveídos.";
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$id_expediente = (int)($_POST["id_expediente"] ?? 0);
$id_movimiento = (int)($_POST["id_movimiento"] ?? 0);
$descripcion = trim($_POST["descripcion_proveido"] ?? "");

if ($id_expediente <= 0 || $descripcion === "") {
    $_SESSION["mensaje_error"] = "Debe indicar el expediente y la descripción del proveído.";
    header("Location: ver.php?id=" . max(1, $id_expediente));
    exit;
}

// Si no se envía movimiento, tomar el último del expediente
if ($id_movimiento <= 0) {
    $stmtMov = $conn->prepare("SELECT id_movimiento FROM movimientos_documento WHERE id_expediente = ? ORDER BY id_movimiento DESC LIMIT 1");
    $stmtMov->bind_param("i", $id_expediente);
    $stmtMov->execute();
    $row = $stmtMov->get_result()->fetch_assoc();
    $stmtMov->close();
    $id_movimiento = $row ? (int)$row["id_movimiento"] : 0;
}

if ($id_movimiento <= 0) {
    $_SESSION["mensaje_error"] = "No se encontró un movimiento asociado al expediente para el proveído.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$archivo_ruta = null;
if (isset($_FILES["archivo_proveido_pdf"]) && $_FILES["archivo_proveido_pdf"]["error"] === UPLOAD_ERR_OK) {
    $archivo = $_FILES["archivo_proveido_pdf"];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo["tmp_name"]);
    finfo_close($finfo);
    if ($mime === "application/pdf" && $archivo["size"] <= 10 * 1024 * 1024) {
        $anio = date("Y");
        $carpeta = "../../uploads/proveidos/" . $anio . "/";
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $nombre = "prov_" . $id_expediente . "_" . time() . ".pdf";
        $rutaCompleta = $carpeta . $nombre;
        if (move_uploaded_file($archivo["tmp_name"], $rutaCompleta)) {
            $archivo_ruta = "/uploads/proveidos/" . $anio . "/" . $nombre;
        }
    }
}

$stmt = $conn->prepare("
    INSERT INTO proveidos (id_movimiento, id_usuario_emisor, descripcion_proveido, archivo_proveido_pdf)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("iiss", $id_movimiento, $id_usuario, $descripcion, $archivo_ruta);

if ($stmt->execute()) {
    // Notificación simple al usuario mesa si existe
    $_SESSION["mensaje_exito"] = "Proveído registrado correctamente.";
} else {
    $_SESSION["mensaje_error"] = "No se pudo registrar el proveído: " . $stmt->error;
}
$stmt->close();

header("Location: ver.php?id=" . $id_expediente);
exit;
