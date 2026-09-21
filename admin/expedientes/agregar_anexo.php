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
|--------------------------------------------------------------------------
|
| Mesa de Partes (rol 2) y Administrador (rol 1)
| pueden agregar anexos.
|
*/

if (
    (int) $usuario["id_rol"] !== 1 &&
    (int) $usuario["id_rol"] !== 2
) {
    $_SESSION["mensaje_error"] =
        "No tienes permisos para agregar anexos.";

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
        numero_expediente
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
    $_SESSION["mensaje_error"] =
        "El expediente no existe.";

    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDAR ARCHIVO
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES["archivo_anexo"]) ||
    $_FILES["archivo_anexo"]["error"] !== UPLOAD_ERR_OK
) {
    $_SESSION["mensaje_error"] =
        "Debe seleccionar un archivo PDF.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$archivo = $_FILES["archivo_anexo"];

/*
|--------------------------------------------------------------------------
| VALIDAR PDF
|--------------------------------------------------------------------------
*/

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $archivo["tmp_name"]);
finfo_close($finfo);

if ($mime !== "application/pdf") {
    $_SESSION["mensaje_error"] =
        "El archivo debe ser un PDF válido.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| LÍMITE DE 10 MB
|--------------------------------------------------------------------------
*/

if ($archivo["size"] > 10 * 1024 * 1024) {
    $_SESSION["mensaje_error"] =
        "El anexo no debe superar los 10 MB.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| AÑO Y CARPETA
|--------------------------------------------------------------------------
*/

$anio = date("Y");

$carpetaBase = "../../uploads/anexos/" . $anio . "/";

if (!is_dir($carpetaBase)) {
    mkdir($carpetaBase, 0775, true);
}

/*
|--------------------------------------------------------------------------
| OBTENER NÚMERO DEL SIGUIENTE ANEXO
|--------------------------------------------------------------------------
*/

$sqlUltimo = "
    SELECT id_anexo
    FROM anexos_expediente
    ORDER BY id_anexo DESC
    LIMIT 1
";

$resultadoUltimo = $conn->query($sqlUltimo);

if ($resultadoUltimo && $filaUltimo = $resultadoUltimo->fetch_assoc()) {
    $correlativo = (int) $filaUltimo["id_anexo"] + 1;
} else {
    $correlativo = 1;
}

/*
|--------------------------------------------------------------------------
| NOMBRE DEL ARCHIVO
|--------------------------------------------------------------------------
*/

$nombreOriginal = pathinfo(
    $archivo["name"],
    PATHINFO_FILENAME
);

$nombreSeguro = preg_replace(
    '/[^a-zA-Z0-9_-]/',
    '_',
    $nombreOriginal
);

if ($nombreSeguro === "") {
    $nombreSeguro = "anexo";
}

$nombreArchivo = sprintf(
    "%05d_%s.pdf",
    $correlativo,
    $nombreSeguro
);

$rutaCompleta =
    $carpetaBase . $nombreArchivo;

$rutaRelativa =
    "/uploads/anexos/" .
    $anio .
    "/" .
    $nombreArchivo;

/*
|--------------------------------------------------------------------------
| GUARDAR ARCHIVO
|--------------------------------------------------------------------------
*/

if (!move_uploaded_file(
    $archivo["tmp_name"],
    $rutaCompleta
)) {

    $_SESSION["mensaje_error"] =
        "No se pudo guardar el archivo.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| GUARDAR EN BASE DE DATOS
|--------------------------------------------------------------------------
*/

$sqlInsert = "
    INSERT INTO anexos_expediente (
        id_expediente,
        nombre_archivo,
        ruta_archivo,
        fecha_subida
    ) VALUES (?, ?, ?, NOW())
";

$stmtInsert = $conn->prepare($sqlInsert);

if (!$stmtInsert) {

    if (file_exists($rutaCompleta)) {
        unlink($rutaCompleta);
    }

    $_SESSION["mensaje_error"] =
        "No se pudo preparar el registro del anexo.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$stmtInsert->bind_param(
    "iss",
    $id_expediente,
    $nombreArchivo,
    $rutaRelativa
);

if (!$stmtInsert->execute()) {

    $stmtInsert->close();

    if (file_exists($rutaCompleta)) {
        unlink($rutaCompleta);
    }

    $_SESSION["mensaje_error"] =
        "No se pudo registrar el anexo.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$stmtInsert->close();

/*
|--------------------------------------------------------------------------
| FINAL
|--------------------------------------------------------------------------
*/

$_SESSION["mensaje_exito"] =
    "Anexo agregado correctamente.";

header("Location: ver.php?id=" . $id_expediente);
exit;