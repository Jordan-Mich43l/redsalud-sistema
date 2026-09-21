<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];
$id_expediente = (int) ($_POST["id_expediente"] ?? 0);

if ($id_expediente <= 0) {
    $_SESSION["mensaje_error"] = "Expediente no válido.";
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| USUARIO
|--------------------------------------------------------------------------
*/

$sqlUsuario = "
    SELECT
        id_usuario,
        id_rol,
        estado
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

/*
|--------------------------------------------------------------------------
| PERMISOS
| Mesa de Partes (rol 2) y Administrador (rol 1)
| pueden agregar/reemplazar el documento principal.
|--------------------------------------------------------------------------
*/

if (
    (int) $usuario["id_rol"] !== 1 &&
    (int) $usuario["id_rol"] !== 2
) {
    $_SESSION["mensaje_error"] =
        "No tienes permisos para agregar el documento principal.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| VERIFICAR EXPEDIENTE
|--------------------------------------------------------------------------
*/

$sqlExpediente = "
    SELECT
        id_expediente,
        numero_expediente,
        archivo_principal_pdf
    FROM expedientes
    WHERE id_expediente = ?
    LIMIT 1
";

$stmtExpediente = $conn->prepare($sqlExpediente);
$stmtExpediente->bind_param("i", $id_expediente);
$stmtExpediente->execute();

$expediente = $stmtExpediente->get_result()->fetch_assoc();

$stmtExpediente->close();

if (!$expediente) {
    $_SESSION["mensaje_error"] = "El expediente no existe.";
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDAR ARCHIVO
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES["archivo_principal_pdf"]) ||
    $_FILES["archivo_principal_pdf"]["error"] !== UPLOAD_ERR_OK
) {
    $_SESSION["mensaje_error"] = "Debe seleccionar un archivo PDF.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$archivo = $_FILES["archivo_principal_pdf"];

/*
|--------------------------------------------------------------------------
| VALIDAR PDF
|--------------------------------------------------------------------------
*/

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $archivo["tmp_name"]);
finfo_close($finfo);

if ($mime !== "application/pdf") {
    $_SESSION["mensaje_error"] = "El archivo debe ser un PDF válido.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| LÍMITE DE 15 MB
|--------------------------------------------------------------------------
*/

if ($archivo["size"] > 15 * 1024 * 1024) {
    $_SESSION["mensaje_error"] = "El documento no debe superar los 15 MB.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| AÑO Y CARPETA
|--------------------------------------------------------------------------
*/

$anio = date("Y");
$carpetaBase = "../../uploads/expedientes/" . $anio . "/";

if (!is_dir($carpetaBase)) {
    mkdir($carpetaBase, 0775, true);
}

/*
|--------------------------------------------------------------------------
| NOMBRE DEL ARCHIVO (basado en id_expediente)
|--------------------------------------------------------------------------
*/

$nombreOriginal = pathinfo($archivo["name"], PATHINFO_FILENAME);
$nombreSeguro = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombreOriginal);

if ($nombreSeguro === "") {
    $nombreSeguro = "documento";
}

$nombreArchivo = sprintf(
    "%05d_%s.pdf",
    (int) $expediente["id_expediente"],
    $nombreSeguro
);

$rutaCompleta = $carpetaBase . $nombreArchivo;
$rutaRelativa = "/uploads/expedientes/" . $anio . "/" . $nombreArchivo;

/*
|--------------------------------------------------------------------------
| GUARDAR ARCHIVO
|--------------------------------------------------------------------------
*/

if (!move_uploaded_file($archivo["tmp_name"], $rutaCompleta)) {
    $_SESSION["mensaje_error"] = "No se pudo guardar el archivo.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| ACTUALIZAR EN BASE DE DATOS
|--------------------------------------------------------------------------
*/

$sqlUpdate = "
    UPDATE expedientes
    SET archivo_principal_pdf = ?
    WHERE id_expediente = ?
";

$stmtUpdate = $conn->prepare($sqlUpdate);

if (!$stmtUpdate) {
    if (file_exists($rutaCompleta)) {
        unlink($rutaCompleta);
    }
    $_SESSION["mensaje_error"] = "No se pudo actualizar el registro.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$stmtUpdate->bind_param("si", $rutaRelativa, $id_expediente);

if (!$stmtUpdate->execute()) {
    if (file_exists($rutaCompleta)) {
        unlink($rutaCompleta);
    }
    $_SESSION["mensaje_error"] = "Error al guardar en la base de datos.";
    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$stmtUpdate->close();

$_SESSION["mensaje_exito"] = "Documento principal registrado correctamente.";
header("Location: ver.php?id=" . $id_expediente);
exit;
