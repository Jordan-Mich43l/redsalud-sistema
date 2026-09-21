<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];

/*
|--------------------------------------------------------------------------
| OBTENER DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$sqlUsuario = "
    SELECT
        id_usuario,
        id_area,
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

// Admin (rol 1) y Mesa de Partes (rol 2) pueden registrar expedientes
if (!in_array((int) $usuario["id_rol"], [1, 2], true)) {
    $_SESSION["mensaje_error"] = "No tienes permisos para registrar expedientes.";
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| RECIBIR DATOS DEL FORMULARIO
|--------------------------------------------------------------------------
*/

$origen_documento      = strtoupper(trim($_POST["origen_documento"] ?? ""));
$id_tipo_documento     = (int) ($_POST["id_tipo_documento"] ?? 0);
$id_area_emisora       = (int) ($_POST["id_area_emisora"] ?? 0);
$remitente             = trim($_POST["remitente"] ?? "");
$asunto                = trim($_POST["asunto"] ?? "");
$numero_folios         = (int) ($_POST["numero_folios"] ?? 0);
$fecha_limite_atencion = trim($_POST["fecha_limite_atencion"] ?? "");

/*
|--------------------------------------------------------------------------
| VALIDACIONES
|--------------------------------------------------------------------------
*/

$errores = [];

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

/*
|--------------------------------------------------------------------------
| VALIDAR ARCHIVO PDF (OPCIONAL)
|--------------------------------------------------------------------------
*/

$tieneArchivo = false;
$archivo = null;

if (
    isset($_FILES["archivo_principal_pdf"]) &&
    $_FILES["archivo_principal_pdf"]["error"] === UPLOAD_ERR_OK
) {
    $archivo = $_FILES["archivo_principal_pdf"];
    $tieneArchivo = true;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $archivo["tmp_name"]);
    finfo_close($finfo);

    if ($mime !== "application/pdf") {
        $errores[] = "El archivo debe ser un PDF válido.";
    }

    if ($archivo["size"] > 10 * 1024 * 1024) {
        $errores[] = "El archivo no debe superar los 10 MB.";
    }
} elseif (
    isset($_FILES["archivo_principal_pdf"]) &&
    $_FILES["archivo_principal_pdf"]["error"] !== UPLOAD_ERR_NO_FILE
) {
    $errores[] = "Error al subir el archivo. Intente nuevamente.";
}

if (!empty($errores)) {
    $_SESSION["mensaje_error"] = implode(" ", $errores);
    header("Location: crear.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GENERAR NÚMERO DE EXPEDIENTE
|--------------------------------------------------------------------------
*/

$anio = date("Y");

$sqlUltimo = "
    SELECT MAX(CAST(SUBSTRING_INDEX(numero_expediente, '-', -1) AS UNSIGNED)) AS max_corr
    FROM expedientes
    WHERE numero_expediente LIKE ?
";

$patron = "EXP-$anio-%";
$stmtUltimo = $conn->prepare($sqlUltimo);
$stmtUltimo->bind_param("s", $patron);
$stmtUltimo->execute();
$ultimo = $stmtUltimo->get_result()->fetch_assoc();
$stmtUltimo->close();

$maxCorr = isset($ultimo["max_corr"]) && $ultimo["max_corr"] !== null
    ? (int) $ultimo["max_corr"]
    : 0;
$correlativo = $maxCorr + 1;

$numero_expediente = sprintf("EXP-%s-%05d", $anio, $correlativo);

/*
|--------------------------------------------------------------------------
| PREPARAR CARPETA Y NOMBRE DEL ARCHIVO (si hay archivo)
|--------------------------------------------------------------------------
*/

$rutaCompleta = null;
$rutaRelativa = "";

if ($tieneArchivo) {
    $carpetaBase = "../../uploads/expedientes/" . $anio . "/";

    if (!is_dir($carpetaBase)) {
        mkdir($carpetaBase, 0775, true);
    }

    // Nombre limpio: 00003_solicitud.pdf (ejemplo)
    $nombreLimpio = sprintf("%05d_%s.pdf", $correlativo, 
        preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($archivo["name"], PATHINFO_FILENAME))
    );

    $rutaCompleta = $carpetaBase . $nombreLimpio;
    $rutaRelativa = "/uploads/expedientes/" . $anio . "/" . $nombreLimpio;
}

/*
|--------------------------------------------------------------------------
| INICIAR TRANSACCIÓN
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    // Subir el archivo solo si se adjuntó
    if ($tieneArchivo) {
        if (!move_uploaded_file($archivo["tmp_name"], $rutaCompleta)) {
            throw new Exception("No se pudo guardar el archivo PDF en el servidor.");
        }
    }

    /*
    |----------------------------------------------------------------------
    | INSERTAR EXPEDIENTE
    |----------------------------------------------------------------------
    */

    $sqlInsert = "
        INSERT INTO expedientes (
            numero_expediente,
            origen_documento,
            id_area_emisora,
            id_tipo_documento,
            remitente,
            asunto,
            numero_folios,
            archivo_principal_pdf,
            fecha_limite_atencion,
            estado_expediente
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'EN_TRAMITE'
        )
    ";

    $stmtInsert = $conn->prepare($sqlInsert);

    if (!$stmtInsert) {
        throw new Exception("Error al preparar la inserción: " . $conn->error);
    }

    // id_area_emisora puede ser NULL
    $stmtInsert->bind_param(
        "ssiississ",
        $numero_expediente,
        $origen_documento,
        $id_area_emisora,
        $id_tipo_documento,
        $remitente,
        $asunto,
        $numero_folios,
        $rutaRelativa,
        $fecha_limite_atencion
    );

    if (!$stmtInsert->execute()) {
        throw new Exception("Error al guardar el expediente: " . $stmtInsert->error);
    }

    $id_expediente = $conn->insert_id;
    $stmtInsert->close();

    /*
    |----------------------------------------------------------------------
    | REGISTRAR MOVIMIENTO INICIAL (opcional pero útil)
    |----------------------------------------------------------------------
    */

    $area_usuario = (int) $usuario["id_area"];

    $sqlMovimiento = "
        INSERT INTO movimientos_documento (
            id_expediente,
            area_origen,
            area_destino,
            usuario_mesa_partes,
            estado_recepcion
        ) VALUES (?, ?, ?, ?, 'RECEPCIONADO')
    ";

    $stmtMov = $conn->prepare($sqlMovimiento);
    $stmtMov->bind_param(
        "iiii",
        $id_expediente,
        $area_usuario,
        $area_usuario,
        $id_usuario
    );

    if (!$stmtMov->execute()) {
        throw new Exception("Error al registrar el movimiento inicial.");
    }

    $stmtMov->close();

    // Confirmar
    $conn->commit();

    $_SESSION["mensaje_exito"] = "Expediente $numero_expediente registrado correctamente.";

    // Al guardar, redirigir automáticamente a la plantilla de impresión
    header("Location: imprimir.php?id=" . $id_expediente);
    exit;

} catch (Exception $e) {

    $conn->rollback();

    // Eliminar el archivo si se llegó a subir
    if (isset($rutaCompleta) && file_exists($rutaCompleta)) {
        unlink($rutaCompleta);
    }

    $_SESSION["mensaje_error"] = "No se pudo registrar el expediente. " . $e->getMessage();
    header("Location: crear.php");
    exit;
}