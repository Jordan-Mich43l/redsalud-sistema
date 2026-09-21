<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];

$id_expediente = (int) ($_POST["id_expediente"] ?? 0);
$area_destino = (int) ($_POST["area_destino"] ?? 0);
$observacion = trim($_POST["observacion"] ?? "");


/*
|--------------------------------------------------------------------------
| VALIDACIÓN BÁSICA
|--------------------------------------------------------------------------
*/

if ($id_expediente <= 0 || $area_destino <= 0) {

    $_SESSION["mensaje_error"] =
        "Los datos de la derivación no son válidos.";

    header("Location: index.php");
    exit;
}


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

if (!$stmtUsuario) {
    $_SESSION["mensaje_error"] =
        "No se pudo validar el usuario.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$stmtUsuario->bind_param("i", $id_usuario);
$stmtUsuario->execute();

$resultadoUsuario = $stmtUsuario->get_result();
$usuario = $resultadoUsuario->fetch_assoc();

$stmtUsuario->close();


/*
|--------------------------------------------------------------------------
| VALIDAR USUARIO
|--------------------------------------------------------------------------
*/

if (!$usuario || (int) $usuario["estado"] !== 1) {

    session_destroy();

    header("Location: ../../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| SOLO MESA DE PARTES PUEDE DERIVAR
|--------------------------------------------------------------------------
*/

if ((int) $usuario["id_rol"] !== 2) {

    $_SESSION["mensaje_error"] =
        "No tienes permisos para derivar expedientes.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}


$area_origen = (int) $usuario["id_area"];


/*
|--------------------------------------------------------------------------
| OBTENER EXPEDIENTE
|--------------------------------------------------------------------------
*/

$sqlExpediente = "
    SELECT
        id_expediente,
        numero_expediente,
        estado_expediente
    FROM expedientes
    WHERE id_expediente = ?
    LIMIT 1
";

$stmtExpediente = $conn->prepare($sqlExpediente);

if (!$stmtExpediente) {

    $_SESSION["mensaje_error"] =
        "No se pudo validar el expediente.";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}

$stmtExpediente->bind_param("i", $id_expediente);
$stmtExpediente->execute();

$resultadoExpediente = $stmtExpediente->get_result();
$expediente = $resultadoExpediente->fetch_assoc();

$stmtExpediente->close();


/*
|--------------------------------------------------------------------------
| VALIDAR EXPEDIENTE
|--------------------------------------------------------------------------
*/

if (!$expediente) {

    $_SESSION["mensaje_error"] =
        "El expediente no existe.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDAR ESTADO DEL EXPEDIENTE
|--------------------------------------------------------------------------
*/

if (
    $expediente["estado_expediente"] !== "EN_TRAMITE"
) {

    $_SESSION["mensaje_error"] =
        "Este expediente no puede ser derivado porque actualmente está en estado " .
        $expediente["estado_expediente"] . ".";

    header("Location: ver.php?id=" . $id_expediente);
    exit;
}


/*
|--------------------------------------------------------------------------
| EVITAR DERIVAR AL MISMO ÁREA
|--------------------------------------------------------------------------
*/

if ($area_origen === $area_destino) {

    $_SESSION["mensaje_error"] =
        "El expediente no puede ser derivado a la misma área.";

    header("Location: derivar.php?id=" . $id_expediente);
    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDAR ÁREA DESTINO
|--------------------------------------------------------------------------
*/

$sqlArea = "
    SELECT
        id_area,
        nombre_area
    FROM areas
    WHERE id_area = ?
    LIMIT 1
";

$stmtArea = $conn->prepare($sqlArea);

if (!$stmtArea) {

    $_SESSION["mensaje_error"] =
        "No se pudo validar el área destino.";

    header("Location: derivar.php?id=" . $id_expediente);
    exit;
}

$stmtArea->bind_param("i", $area_destino);
$stmtArea->execute();

$resultadoArea = $stmtArea->get_result();
$area = $resultadoArea->fetch_assoc();

$stmtArea->close();


if (!$area) {

    $_SESSION["mensaje_error"] =
        "El área seleccionada no existe.";

    header("Location: derivar.php?id=" . $id_expediente);
    exit;
}


/*
|--------------------------------------------------------------------------
| INICIAR TRANSACCIÓN
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | REGISTRAR MOVIMIENTO
    |--------------------------------------------------------------------------
    */

    $sqlMovimiento = "
        INSERT INTO movimientos_documento (
            id_expediente,
            area_origen,
            area_destino,
            usuario_mesa_partes,
            estado_recepcion
        )
        VALUES (?, ?, ?, ?, 'PENDIENTE')
    ";

    $stmtMovimiento = $conn->prepare($sqlMovimiento);

    if (!$stmtMovimiento) {
        throw new Exception("Error al preparar el movimiento.");
    }

    $stmtMovimiento->bind_param(
        "iiii",
        $id_expediente,
        $area_origen,
        $area_destino,
        $id_usuario
    );

    if (!$stmtMovimiento->execute()) {
        throw new Exception("Error al registrar el movimiento.");
    }

    $stmtMovimiento->close();


    /*
    |--------------------------------------------------------------------------
    | OBTENER USUARIOS DEL ÁREA DESTINO
    |--------------------------------------------------------------------------
    */

    $sqlUsuariosDestino = "
        SELECT id_usuario
        FROM usuarios
        WHERE id_area = ?
          AND estado = 1
    ";

    $stmtUsuariosDestino = $conn->prepare(
        $sqlUsuariosDestino
    );

    if (!$stmtUsuariosDestino) {
        throw new Exception(
            "Error al consultar usuarios del área destino."
        );
    }

    $stmtUsuariosDestino->bind_param(
        "i",
        $area_destino
    );

    $stmtUsuariosDestino->execute();

    $resultadoUsuariosDestino =
        $stmtUsuariosDestino->get_result();


    /*
    |--------------------------------------------------------------------------
    | PREPARAR NOTIFICACIÓN
    |--------------------------------------------------------------------------
    */

    $numeroExpediente =
        $expediente["numero_expediente"];

    $nombreArea =
        $area["nombre_area"];

    $mensaje =
        "El expediente " .
        $numeroExpediente .
        " ha sido derivado al área " .
        $nombreArea .
        ".";

    if ($observacion !== "") {

        $mensaje .=
            " Indicación: " .
            $observacion;
    }


    $sqlNotificacion = "
        INSERT INTO notificaciones (
            id_usuario,
            id_expediente,
            mensaje,
            leido
        )
        VALUES (?, ?, ?, 0)
    ";

    $stmtNotificacion = $conn->prepare(
        $sqlNotificacion
    );

    if (!$stmtNotificacion) {
        throw new Exception(
            "Error al preparar la notificación."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREAR NOTIFICACIONES
    |--------------------------------------------------------------------------
    */

    while (
        $usuarioDestino =
        $resultadoUsuariosDestino->fetch_assoc()
    ) {

        $id_usuario_destino =
            (int) $usuarioDestino["id_usuario"];

        $stmtNotificacion->bind_param(
            "iis",
            $id_usuario_destino,
            $id_expediente,
            $mensaje
        );

        if (!$stmtNotificacion->execute()) {
            throw new Exception(
                "Error al crear una notificación."
            );
        }
    }


    $stmtNotificacion->close();
    $stmtUsuariosDestino->close();


    /*
    |--------------------------------------------------------------------------
    | CONFIRMAR
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | MENSAJE DE ÉXITO
    |--------------------------------------------------------------------------
    */

    $_SESSION["mensaje_exito"] =
        "Documento derivado correctamente al área " .
        $nombreArea . ".";


    /*
    |--------------------------------------------------------------------------
    | VOLVER AL EXPEDIENTE
    |--------------------------------------------------------------------------
    */

    header(
        "Location: ver.php?id=" .
        $id_expediente
    );

    exit;


} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | DESHACER TRANSACCIÓN
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    $_SESSION["mensaje_error"] =
        "No se pudo completar la derivación. Inténtalo nuevamente.";


    header(
        "Location: derivar.php?id=" .
        $id_expediente
    );

    exit;
}