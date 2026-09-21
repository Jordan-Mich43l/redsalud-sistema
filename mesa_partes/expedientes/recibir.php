<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario   = (int) $_SESSION["id_usuario"];
$id_movimiento = (int) ($_POST["id_movimiento"] ?? 0);
$accion       = $_POST["accion"] ?? "";

$redirectFallback = "../movimientos/historial.php";

/*
|--------------------------------------------------------------------------
| VALIDACIÓN BÁSICA
|--------------------------------------------------------------------------
*/

if ($id_movimiento <= 0) {
    $_SESSION["mensaje_error"] = "El movimiento seleccionado no es válido.";
    header("Location: " . $redirectFallback);
    exit;
}

if (!in_array($accion, ["RECEPCIONAR", "OBSERVAR"], true)) {
    $_SESSION["mensaje_error"] = "La acción de recepción no es válida.";
    header("Location: " . $redirectFallback);
    exit;
}

/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| OBTENER MOVIMIENTO
|--------------------------------------------------------------------------
*/

$sqlMovimiento = "
    SELECT
        m.id_movimiento,
        m.id_expediente,
        m.area_origen,
        m.area_destino,
        m.usuario_mesa_partes,
        m.estado_recepcion,
        e.numero_expediente,
        ao.nombre_area AS nombre_area_origen,
        ad.nombre_area AS nombre_area_destino
    FROM movimientos_documento m
    INNER JOIN expedientes e ON e.id_expediente = m.id_expediente
    LEFT JOIN areas ao ON ao.id_area = m.area_origen
    LEFT JOIN areas ad ON ad.id_area = m.area_destino
    WHERE m.id_movimiento = ?
    LIMIT 1
";

$stmtMovimiento = $conn->prepare($sqlMovimiento);
$stmtMovimiento->bind_param("i", $id_movimiento);
$stmtMovimiento->execute();
$movimiento = $stmtMovimiento->get_result()->fetch_assoc();
$stmtMovimiento->close();

if (!$movimiento) {
    $_SESSION["mensaje_error"] = "El movimiento no existe.";
    header("Location: " . $redirectFallback);
    exit;
}

// A partir de aquí ya podemos redirigir al expediente
$redirectExpediente = "../expedientes/ver.php?id=" . (int) $movimiento["id_expediente"];

/*
|--------------------------------------------------------------------------
| VALIDAR PERMISOS
|--------------------------------------------------------------------------
*/

$esAdministrador        = ((int) $usuario["id_rol"] === 1);
$perteneceAlAreaDestino = ((int) $usuario["id_area"] === (int) $movimiento["area_destino"]);

if (!$esAdministrador && !$perteneceAlAreaDestino) {
    $_SESSION["mensaje_error"] = "No tienes permisos para recibir este movimiento.";
    header("Location: " . $redirectExpediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDAR ESTADO
|--------------------------------------------------------------------------
*/

if ($movimiento["estado_recepcion"] !== "PENDIENTE") {
    $_SESSION["mensaje_error"] = "Este movimiento ya fue procesado.";
    header("Location: " . $redirectExpediente);
    exit;
}

$idUsuarioMesa = (int) $movimiento["usuario_mesa_partes"];

if ($idUsuarioMesa <= 0) {
    $_SESSION["mensaje_error"] = "El movimiento no tiene un usuario de Mesa de Partes asociado.";
    header("Location: " . $redirectExpediente);
    exit;
}

/*
|--------------------------------------------------------------------------
| DETERMINAR NUEVO ESTADO
|--------------------------------------------------------------------------
*/

$nuevoEstado = ($accion === "RECEPCIONAR") ? "RECEPCIONADO" : "OBSERVADO";

/*
|--------------------------------------------------------------------------
| TRANSACCIÓN
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    // 1. Actualizar movimiento
    $sqlActualizar = "
        UPDATE movimientos_documento
        SET estado_recepcion = ?,
            fecha_recepcion = NOW()
        WHERE id_movimiento = ?
          AND estado_recepcion = 'PENDIENTE'
    ";

    $stmtActualizar = $conn->prepare($sqlActualizar);
    $stmtActualizar->bind_param("si", $nuevoEstado, $id_movimiento);

    if (!$stmtActualizar->execute()) {
        throw new Exception("No se pudo actualizar el movimiento.");
    }

    if ($stmtActualizar->affected_rows !== 1) {
        throw new Exception("El movimiento ya fue procesado por otro usuario.");
    }

    $stmtActualizar->close();

    // 2. Crear mensaje
    if ($nuevoEstado === "RECEPCIONADO") {
        $mensaje = "El expediente " . $movimiento["numero_expediente"] .
                   " fue recepcionado por el área " . $movimiento["nombre_area_destino"] . ".";
    } else {
        $mensaje = "El expediente " . $movimiento["numero_expediente"] .
                   " fue observado por el área " . $movimiento["nombre_area_destino"] . ".";
    }

    // 3. Insertar notificación
    $sqlNotificacion = "
        INSERT INTO notificaciones (
            id_usuario,
            id_expediente,
            mensaje,
            leido,
            fecha_creacion
        ) VALUES (?, ?, ?, 0, NOW())
    ";

    $stmtNotificacion = $conn->prepare($sqlNotificacion);
    $idExpediente = (int) $movimiento["id_expediente"];

    $stmtNotificacion->bind_param(
        "iis",
        $idUsuarioMesa,
        $idExpediente,
        $mensaje
    );

    if (!$stmtNotificacion->execute()) {
        throw new Exception("No se pudo registrar la notificación: " . $stmtNotificacion->error);
    }

    if ($stmtNotificacion->affected_rows !== 1) {
        throw new Exception("La notificación no fue registrada.");
    }

    $stmtNotificacion->close();

    // 4. Confirmar
    $conn->commit();

    if ($nuevoEstado === "RECEPCIONADO") {
        $_SESSION["mensaje_exito"] = "El expediente fue recepcionado correctamente.";
    } else {
        $_SESSION["mensaje_exito"] = "El expediente fue marcado como observado.";
    }

} catch (Exception $e) {

    $conn->rollback();
    $_SESSION["mensaje_error"] = $e->getMessage();
}

header("Location: " . $redirectExpediente);
exit;