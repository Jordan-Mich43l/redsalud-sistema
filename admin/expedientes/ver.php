<?php

session_start();

$mensaje_exito = $_SESSION["mensaje_exito"] ?? "";
$mensaje_error = $_SESSION["mensaje_error"] ?? "";

unset($_SESSION["mensaje_exito"]);
unset($_SESSION["mensaje_error"]);

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];


/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$sqlUsuario = "
    SELECT
        u.nombres,
        u.apellidos,
        u.correo,
        u.id_area,
        u.id_rol,
        a.nombre_area,
        r.nombre_rol
    FROM usuarios u
    LEFT JOIN areas a
        ON a.id_area = u.id_area
    LEFT JOIN roles r
        ON r.id_rol = u.id_rol
    WHERE u.id_usuario = ?
    LIMIT 1
";

$stmtUsuario = $conn->prepare($sqlUsuario);
$stmtUsuario->bind_param("i", $id_usuario);
$stmtUsuario->execute();

$resultadoUsuario = $stmtUsuario->get_result();
$usuario = $resultadoUsuario->fetch_assoc();

$stmtUsuario->close();


if (!$usuario) {
    session_destroy();

    header("Location: ../../auth/login.php");
    exit;
}


$nombre_completo = trim(
    $usuario["nombres"] . " " . $usuario["apellidos"]
);

$correo = $usuario["correo"];
$nombre_rol = $usuario["nombre_rol"] ?? "Sin rol";


/*
|--------------------------------------------------------------------------
| INICIALES
|--------------------------------------------------------------------------
*/

$iniciales = "";

foreach (
    preg_split('/\s+/', $nombre_completo) as $parte
) {
    if ($parte !== "") {
        $iniciales .= strtoupper(substr($parte, 0, 1));
    }
}

$iniciales = substr($iniciales, 0, 2);


/*
|--------------------------------------------------------------------------
| ID DEL EXPEDIENTE
|--------------------------------------------------------------------------
*/

$id_expediente = (int) ($_GET["id"] ?? 0);
$volver_a_movimientos = ($_GET["origen"] ?? "") === "movimientos";

if ($id_expediente <= 0) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| EXPEDIENTE
|--------------------------------------------------------------------------
*/

$sqlExpediente = "
    SELECT
        e.id_expediente,
        e.numero_expediente,
        e.origen_documento,
        e.id_area_emisora,
        e.id_tipo_documento,
        e.remitente,
        e.asunto,
        e.numero_folios,
        e.archivo_principal_pdf,
        e.sello_digital_hash,
        e.fecha_registro,
        e.fecha_limite_atencion,
        e.estado_expediente,

        t.nombre_tipo AS tipo_documento,

        a.nombre_area AS area_emisora

    FROM expedientes e

    LEFT JOIN tipos_documento t
        ON t.id_tipo_documento = e.id_tipo_documento

    LEFT JOIN areas a
        ON a.id_area = e.id_area_emisora

    WHERE e.id_expediente = ?

    LIMIT 1
";

$stmtExpediente = $conn->prepare($sqlExpediente);

if (!$stmtExpediente) {
    die("Error al preparar la consulta del expediente.");
}

$stmtExpediente->bind_param(
    "i",
    $id_expediente
);

$stmtExpediente->execute();

$resultadoExpediente = $stmtExpediente->get_result();

$expediente = $resultadoExpediente->fetch_assoc();

$stmtExpediente->close();


if (!$expediente) {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| MOVIMIENTOS
|--------------------------------------------------------------------------
*/

$sqlMovimientos = "
    SELECT
        m.id_movimiento,
        m.area_origen,
        m.area_destino,
        m.usuario_mesa_partes,
        m.fecha_envio,
        m.fecha_recepcion,
        m.estado_recepcion,

        ao.nombre_area AS nombre_area_origen,
        ad.nombre_area AS nombre_area_destino,

        CONCAT(
            u.nombres,
            ' ',
            u.apellidos
        ) AS usuario_responsable

    FROM movimientos_documento m

    LEFT JOIN areas ao
        ON ao.id_area = m.area_origen

    LEFT JOIN areas ad
        ON ad.id_area = m.area_destino

    LEFT JOIN usuarios u
        ON u.id_usuario = m.usuario_mesa_partes

    WHERE m.id_expediente = ?

    ORDER BY m.fecha_envio DESC, m.id_movimiento DESC
";

$stmtMovimientos = $conn->prepare($sqlMovimientos);

if (!$stmtMovimientos) {
    die("Error al preparar el historial de movimientos.");
}

$stmtMovimientos->bind_param(
    "i",
    $id_expediente
);

$stmtMovimientos->execute();

$resultadoMovimientos = $stmtMovimientos->get_result();

$movimientos = [];

while ($fila = $resultadoMovimientos->fetch_assoc()) {
    $movimientos[] = $fila;
}

$stmtMovimientos->close();


/*
|--------------------------------------------------------------------------
| PROVEÍDOS
|--------------------------------------------------------------------------
*/

$sqlProveidos = "
    SELECT
        p.id_proveido,
        p.id_movimiento,
        p.id_usuario_emisor,
        p.descripcion_proveido,
        p.archivo_proveido_pdf,
        p.fecha_emision,

        CONCAT(
            u.nombres,
            ' ',
            u.apellidos
        ) AS usuario_emisor

    FROM proveidos p

    LEFT JOIN usuarios u
        ON u.id_usuario = p.id_usuario_emisor

    INNER JOIN movimientos_documento m
        ON m.id_movimiento = p.id_movimiento

    WHERE m.id_expediente = ?

    ORDER BY p.fecha_emision DESC, p.id_proveido DESC
";

$stmtProveidos = $conn->prepare($sqlProveidos);

if (!$stmtProveidos) {
    die("Error al preparar la consulta de proveídos.");
}

$stmtProveidos->bind_param(
    "i",
    $id_expediente
);

$stmtProveidos->execute();

$resultadoProveidos = $stmtProveidos->get_result();

$proveidos = [];

while ($fila = $resultadoProveidos->fetch_assoc()) {
    $proveidos[] = $fila;
}

$stmtProveidos->close();


/*
|--------------------------------------------------------------------------
| NOTIFICACIONES NO LEÍDAS
|--------------------------------------------------------------------------
*/

$sqlNoLeidas = "
    SELECT COUNT(*) AS total
    FROM notificaciones
    WHERE id_usuario = ?
      AND leido = 0
";

$stmtNoLeidas = $conn->prepare($sqlNoLeidas);
$stmtNoLeidas->bind_param("i", $id_usuario);
$stmtNoLeidas->execute();

$resultadoNoLeidas = $stmtNoLeidas->get_result();

$notificaciones_no_leidas =
    (int) $resultadoNoLeidas->fetch_assoc()["total"];

$stmtNoLeidas->close();

/*
|--------------------------------------------------------------------------
| ANEXOS
|--------------------------------------------------------------------------
*/

$sqlAnexos = "
    SELECT
        id_anexo,
        nombre_archivo,
        ruta_archivo,
        fecha_subida
    FROM anexos_expediente
    WHERE id_expediente = ?
    ORDER BY fecha_subida ASC, id_anexo ASC
";

$stmtAnexos = $conn->prepare($sqlAnexos);

if (!$stmtAnexos) {
    die("Error al preparar los anexos.");
}

$stmtAnexos->bind_param(
    "i",
    $id_expediente
);

$stmtAnexos->execute();

$resultadoAnexos = $stmtAnexos->get_result();

$anexos = [];

while ($fila = $resultadoAnexos->fetch_assoc()) {
    $anexos[] = $fila;
}

$stmtAnexos->close();

/*
|--------------------------------------------------------------------------
| FUNCIONES AUXILIARES
|--------------------------------------------------------------------------
*/

function obtenerClaseEstado($estado)
{
    switch ($estado) {

        case "EN_TRAMITE":
            return "status status-warning";

        case "OBSERVADO":
            return "status status-danger";

        case "ATENDIDO":
            return "status status-success";

        case "ARCHIVADO":
            return "status status-neutral";

        default:
            return "status status-neutral";
    }
}


function obtenerTextoEstado($estado)
{
    switch ($estado) {

        case "EN_TRAMITE":
            return "En trámite";

        case "OBSERVADO":
            return "Observado";

        case "ATENDIDO":
            return "Atendido";

        case "ARCHIVADO":
            return "Archivado";

        default:
            return $estado;
    }
}


function obtenerTextoOrigen($origen)
{
    switch ($origen) {

        case "EXTERNO":
            return "Externo";

        case "INTERNO":
            return "Interno";

        default:
            return $origen;
    }
}


function obtenerTextoRecepcion($estado)
{
    switch ($estado) {

        case "PENDIENTE":
            return "Pendiente";

        case "RECEPCIONADO":
            return "Recepcionado";

        case "OBSERVADO":
            return "Observado";

        default:
            return $estado;
    }
}


/*
|--------------------------------------------------------------------------
| FECHAS
|--------------------------------------------------------------------------
*/

$fechaRegistro = date(
    "d/m/Y H:i",
    strtotime($expediente["fecha_registro"])
);

$fechaLimite = $expediente["fecha_limite_atencion"]
    ? date(
        "d/m/Y H:i",
        strtotime($expediente["fecha_limite_atencion"])
    )
    : "Sin plazo";


/*
|--------------------------------------------------------------------------
| DOCUMENTO PDF
|--------------------------------------------------------------------------
*/

$rutaPdf = "";
$archivoExiste = false;

if (!empty($expediente["archivo_principal_pdf"])) {

    $archivoPdf = trim($expediente["archivo_principal_pdf"]);

    // Normalizar separadores
    $archivoPdf = str_replace("\\", "/", $archivoPdf);

    // Quitar posibles "/" iniciales
    $archivoPdf = ltrim($archivoPdf, "/");

    // Si ya viene como uploads/... 
    if (strpos($archivoPdf, "uploads/") === 0) {
        $rutaPdf = "../../" . $archivoPdf;
    } 
    // Si viene como 2026/archivo.pdf o ruta relativa
    else {
        $rutaPdf = "../../uploads/" . $archivoPdf;
    }

    // Verificar si el archivo físico existe en el servidor
    $rutaFisica = realpath(__DIR__ . "/" . $rutaPdf);
    if ($rutaFisica && is_file($rutaFisica)) {
        $archivoExiste = true;
    } else {
        // Intentar ruta alternativa desde la raíz del proyecto
        $rutaAlt = realpath(__DIR__ . "/../../" . $archivoPdf);
        if ($rutaAlt && is_file($rutaAlt)) {
            $rutaPdf = "../../" . $archivoPdf;
            $archivoExiste = true;
        } else {
            $rutaPdf = ""; // No disponible si no existe el archivo
        }
    }
}


/*
|--------------------------------------------------------------------------
| PERMISO PARA DERIVAR
|--------------------------------------------------------------------------
*/

$puedeDerivar =
    in_array((int) $usuario["id_rol"], [1, 2], true) &&
    $expediente["estado_expediente"] === "EN_TRAMITE";

$puedeGestionarEstado =
    in_array((int) $usuario["id_rol"], [1, 3], true);

$puedeAgregarProveido =
    in_array((int) $usuario["id_rol"], [1, 3], true);

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars(
            $expediente["numero_expediente"]
        ) ?>
        | RedSalud
    </title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css">

    <link rel="stylesheet" href="../../assets/css/expediente.css">
    
<style>
    .redsalud-swal-popup {
        border-radius: 18px !important;
        padding: 1.5rem !important;
        font-family: inherit !important;
    }

    .redsalud-swal-title {
        color: #17324d !important;
        font-size: 1.35rem !important;
        font-weight: 700 !important;
    }

    .redsalud-swal-text {
        font-size: 0.92rem !important;
    }

    .redsalud-swal-confirm,
    .redsalud-swal-cancel {
        border-radius: 9px !important;
        padding: 0.7rem 1.2rem !important;
        font-weight: 600 !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>


<body>

<div class="app-layout">


    <!-- =========================================================
         SIDEBAR
         ========================================================= -->

    <aside class="sidebar">

        <div class="sidebar-header">

            <div class="brand-mark">

                <img
                    src="../../assets/img/icon_redsalud.png"
                    alt="RedSalud"
                >

            </div>

            <div class="brand-text">

                <strong>RedSalud</strong>

                <span>
                    Gestión Documentaria
                </span>

            </div>

        </div>

        <nav class="sidebar-nav" aria-label="Navegación principal">

            <div class="nav-title">
                ADMINISTRACIÓN
            </div>

            <a href="../dashboard.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_inicio.png" alt="">
                </span>
                <span>Inicio</span>
            </a>

            <a href="../usuarios/index.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_usuarios.png" alt="">
                </span>
                <span>Usuarios</span>
            </a>

            <a href="../areas/index.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_direcciones.png" alt="">
                </span>
                <span>Direcciones</span>
            </a>

            <a href="../programas/index.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_programas.png" alt="">
                </span>
                <span>Programas</span>
            </a>

            <a href="../tipos_documento/index.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_tipos_documento.png" alt="">
                </span>
                <span>Tipos de documento</span>
            </a>


            <div class="nav-title">
                SUPERVISIÓN
            </div>

            <a href="index.php" class="nav-item active">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_expedientes.png" alt="">
                </span>
                <span>Expedientes</span>
            </a>

            <a href="../movimientos/historial.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_movimientos.png" alt="">
                </span>
                <span>Movimientos</span>
            </a>

            <a href="../notificaciones/index.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_notificaciones.png" alt="">
                </span>
                <span>Notificaciones</span>

                <?php if ($notificaciones_no_leidas > 0): ?>
                    <span class="notification-count">
                        <?= $notificaciones_no_leidas ?>
                    </span>
                <?php endif; ?>
            </a>


            <div class="nav-title">
                CUENTA
            </div>

            <a href="../perfil/index.php" class="nav-item">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_perfil.png" alt="">
                </span>
                <span>Mi perfil</span>
            </a>

        </nav>

        <!-- =====================================================
             USUARIO
             ===================================================== -->

        <div class="sidebar-footer">

            <div class="sidebar-user">

                <div class="avatar avatar-small">
                    <?= htmlspecialchars($iniciales) ?>
                </div>

                <div class="sidebar-user-data">

                    <strong>
                        <?= htmlspecialchars($nombre_completo) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($nombre_rol) ?>
                    </span>

                </div>

            </div>


            <a
                href="../../logout.php"
                class="logout-link"
            >

                <span class="nav-icon">↪</span>

                <span>
                    Cerrar sesión
                </span>

            </a>

        </div>

    </aside>



    <!-- =========================================================
         CONTENIDO PRINCIPAL
         ========================================================= -->

    <main class="main-content">


        <!-- =====================================================
             CABECERA
             ===================================================== -->

        <header class="main-header">

            <div class="header-title">

                <span class="eyebrow">
                    RED DE SALUD AGUAYTÍA
                </span>

                <h1>
                    Expedientes
                </h1>

                <p>
                    Consulta y seguimiento documental.
                </p>

            </div>


            <div class="header-user">

                <div class="avatar">
                    <?= htmlspecialchars($iniciales) ?>
                </div>

                <div class="header-user-data">

                    <strong>
                        <?= htmlspecialchars($nombre_completo) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($correo) ?>
                    </span>

                </div>

            </div>

        </header>



        <!-- =====================================================
             CONTENIDO
             ===================================================== -->

        <div class="dashboard-content">
            
        <?php if ($mensaje_exito !== ""): ?>

            <div class="alert alert-success">
                <span>✓</span>

                <div>
                    <strong>Documento derivado</strong>

                    <p>
                        <?= htmlspecialchars($mensaje_exito) ?>
                    </p>
                </div>
            </div>

        <?php endif; ?>


        <?php if ($mensaje_error !== ""): ?>

            <div class="alert alert-error">
                <span>!</span>

                <div>
                    <strong>No se pudo completar la operación</strong>

                    <p>
                        <?= htmlspecialchars($mensaje_error) ?>
                    </p>
                </div>
            </div>

        <?php endif; ?>

        <!-- =================================================
            CABECERA DEL EXPEDIENTE
            ================================================= -->

        <section class="welcome-card">

            <div class="welcome-content">

                <span class="welcome-label">
                    GESTIÓN DOCUMENTAL
                </span>

                <div class="expediente-title-row">

                    <h2>
                        <?= htmlspecialchars($expediente["numero_expediente"]) ?>
                    </h2>

                    <span class="<?= obtenerClaseEstado($expediente["estado_expediente"]) ?>">
                        <?= htmlspecialchars(
                            obtenerTextoEstado($expediente["estado_expediente"])
                        ) ?>
                    </span>

                </div>

                <p>
                    Detalle y seguimiento del expediente.
                </p>

            </div>

            <div class="expediente-actions">

                <?php if ($puedeDerivar): ?>

                    <a
                        href="derivar.php?id=<?= (int) $id_expediente ?>"
                        class="btn-primary"
                    >
                        Derivar expediente
                    </a>

                <?php endif; ?>

                <?php if ($puedeGestionarEstado): ?>
                    <form method="POST" action="cambiar_estado_expediente.php" class="estado-expediente-form">
                        <input type="hidden" name="id_expediente" value="<?= (int)$id_expediente ?>">
                        <select name="nuevo_estado" class="estado-expediente-select" required>
                            <option value="EN_TRAMITE" <?= $expediente["estado_expediente"]==="EN_TRAMITE"?"selected":"" ?>>En trámite</option>
                            <option value="OBSERVADO" <?= $expediente["estado_expediente"]==="OBSERVADO"?"selected":"" ?>>Observado</option>
                            <option value="ATENDIDO" <?= $expediente["estado_expediente"]==="ATENDIDO"?"selected":"" ?>>Atendido</option>
                            <option value="ARCHIVADO" <?= $expediente["estado_expediente"]==="ARCHIVADO"?"selected":"" ?>>Archivado</option>
                        </select>
                        <button type="submit" class="btn-primary estado-expediente-btn">Cambiar estado</button>
                    </form>
                <?php endif; ?>

                <a
                    href="<?= $volver_a_movimientos ? "../movimientos/historial.php" : "index.php" ?>"
                    class="btn-secondary"
                >
                    Volver
                </a>

            </div>

            <div class="welcome-decoration"></div>

        </section>

            <!-- =================================================
                 INFORMACIÓN PRINCIPAL
                 ================================================= -->

            <section class="detail-card">

                <div class="detail-card-header">

                    <div>

                        <span class="section-kicker">
                            INFORMACIÓN DEL DOCUMENTO
                        </span>

                        <h3>
                            Datos principales
                        </h3>

                    </div>

                </div>


                <div class="detail-grid">


                    <div class="detail-item">

                        <span>
                            Número de expediente
                        </span>

                        <strong class="detail-highlight">
                            <?= htmlspecialchars(
                                $expediente["numero_expediente"]
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Origen
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                obtenerTextoOrigen(
                                    $expediente["origen_documento"]
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Tipo de documento
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                $expediente["tipo_documento"]
                                ?? "Sin tipo"
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Dirección emisora
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                $expediente["area_emisora"]
                                ?? (
                                    $expediente["origen_documento"]
                                    === "EXTERNO"
                                        ? "Documento externo"
                                        : "Sin área"
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item detail-item-wide">

                        <span>
                            Remitente
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                $expediente["remitente"]
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Número de folios
                        </span>

                        <strong>
                            <?= (int) $expediente["numero_folios"] ?>
                            folios
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Fecha de registro
                        </span>

                        <strong>
                            <?= htmlspecialchars($fechaRegistro) ?>
                        </strong>

                    </div>


                    <div class="detail-item detail-deadline">

                        <span>
                            Fecha límite de atención
                        </span>

                        <strong>
                            <?= htmlspecialchars($fechaLimite) ?>
                        </strong>

                    </div>

                </div>


                <!-- ASUNTO -->

                <div class="detail-subsection">

                    <span>
                        ASUNTO
                    </span>

                    <div class="detail-subject">

                        <?= nl2br(
                            htmlspecialchars(
                                $expediente["asunto"]
                            )
                        ) ?>

                    </div>

                    <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
                        <a href="editar.php?id=<?= (int)$expediente['id_expediente'] ?>" 
                           class="btn-secondary" 
                           style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                            ✏ Editar
                        </a>
                        <a href="imprimir.php?id=<?= (int)$expediente['id_expediente'] ?>" 
                           class="btn-primary" 
                           target="_blank"
                           style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                            🖨 Imprimir documento
                        </a>
                        <a href="eliminar.php?id=<?= (int)$expediente['id_expediente'] ?>"
                        class="btn-secondary btn-delete"
                        onclick="return confirmarEliminacion(this.href);">
                            🗑 Eliminar
                        </a>
                    </div>

                </div>

            </section>



            <!-- =================================================
                 DOCUMENTO PRINCIPAL
                 ================================================= -->

            <section class="detail-card">

                <div class="detail-card-header">

                    <div>

                        <span class="section-kicker">
                            DOCUMENTO PRINCIPAL
                        </span>

                        <h3>
                            Archivo registrado
                        </h3>

                    </div>

                </div>


                <div class="document-file">

                    <div class="document-file-icon">
                        PDF
                    </div>


                    <div class="document-file-info">

                        <strong>
                            Documento principal
                        </strong>

                        <span>
                            Archivo PDF asociado al expediente.
                        </span>

                    </div>


                    <?php if ($archivoExiste && $rutaPdf !== ""): ?>

                        <a
                            href="<?= htmlspecialchars($rutaPdf) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="action-link"
                        >
                            Abrir PDF
                        </a>

                    <?php else: ?>

                        <span class="file-unavailable">
                            No disponible
                        </span>

                    <?php endif; ?>

                </div>

                <?php
                // Roles 1 (Admin) y 2 (Mesa de Partes) pueden agregar/reemplazar el documento principal
                $puedeAgregarDocumento = in_array((int)($usuario["id_rol"] ?? 0), [1, 2], true);
                if ($puedeAgregarDocumento):
                ?>
                <div class="anexo-upload" style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid #e5e7eb;">
                    <form action="agregar_documento_principal.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id_expediente" value="<?= (int)$expediente['id_expediente'] ?>">
                        <div class="anexo-upload-row" style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
                            <label for="archivo_principal_pdf_nuevo" class="file-upload-box" style="flex: 1; min-width: 200px; cursor: pointer; margin: 0;">
                                <span class="file-upload-icon">📄</span>
                                <span class="file-upload-title"><?= $archivoExiste ? 'Reemplazar PDF principal' : 'Agregar documento principal (PDF)' ?></span>
                                <span class="file-upload-text">Seleccione un archivo PDF</span>
                                <input
                                    type="file"
                                    id="archivo_principal_pdf_nuevo"
                                    name="archivo_principal_pdf"
                                    accept="application/pdf,.pdf"
                                    required
                                    style="display: none;"
                                >
                            </label>
                            <button type="submit" class="btn-primary">
                                <?= $archivoExiste ? 'Reemplazar' : 'Subir documento' ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </section>

<!-- =================================================
     ANEXOS
     ================================================= -->

<section class="detail-card">

    <div class="detail-card-header">

        <div>

            <span class="section-kicker">
                ANEXOS
            </span>

            <h3>
                Archivos adicionales
            </h3>

        </div>

        <span class="results-count">

            <?= count($anexos) ?>

            anexo<?= count($anexos) === 1 ? "" : "s" ?>

        </span>

    </div>


    <?php if (!empty($anexos)): ?>

        <div class="anexos-list">

            <?php foreach ($anexos as $anexo): 
                $rutaAnexo = trim($anexo["ruta_archivo"] ?? "");
                $rutaAnexo = str_replace("\\", "/", $rutaAnexo);
                $rutaAnexo = ltrim($rutaAnexo, "/");
                $hrefAnexo = "";
                $anexoExiste = false;
                if ($rutaAnexo !== "") {
                    if (strpos($rutaAnexo, "uploads/") === 0) {
                        $hrefAnexo = "../../" . $rutaAnexo;
                    } else {
                        $hrefAnexo = "../../uploads/" . $rutaAnexo;
                    }
                    $fisicaAnexo = realpath(__DIR__ . "/" . $hrefAnexo);
                    if ($fisicaAnexo && is_file($fisicaAnexo)) {
                        $anexoExiste = true;
                    } else {
                        $alt = realpath(__DIR__ . "/../../" . $rutaAnexo);
                        if ($alt && is_file($alt)) {
                            $hrefAnexo = "../../" . $rutaAnexo;
                            $anexoExiste = true;
                        }
                    }
                }
            ?>

                <div class="anexo-item">

                    <div class="anexo-icon">
                        PDF
                    </div>

                    <div class="anexo-info">

                        <strong>
                            <?= htmlspecialchars(
                                $anexo["nombre_archivo"]
                            ) ?>
                        </strong>

                        <span>
                            Archivo PDF adjunto al expediente.
                        </span>

                    </div>

                    <?php if ($anexoExiste && $hrefAnexo !== ""): ?>
                    <a
                        href="<?= htmlspecialchars($hrefAnexo) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="action-link"
                    >
                        Ver PDF
                    </a>
                    <?php else: ?>
                        <span class="file-unavailable">No disponible</span>
                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-state compact">

            <div class="empty-state-icon">
                📎
            </div>

            <h3>
                Sin anexos registrados
            </h3>

            <p>
                Este expediente todavía no tiene
                archivos anexos.
            </p>

        </div>

    <?php endif; ?>


    <?php if (
        (int) $usuario["id_rol"] === 1 ||
        (int) $usuario["id_rol"] === 2
    ): ?>

        <div class="anexo-upload">

            <form
                action="agregar_anexo.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="id_expediente"
                    value="<?= (int) $id_expediente ?>"
                >

                <label for="archivo_anexo">
                    Agregar anexo PDF
                </label>

                <div class="anexo-upload-row">

                    <input
                        type="file"
                        id="archivo_anexo"
                        name="archivo_anexo"
                        accept="application/pdf,.pdf"
                        required
                    >

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Agregar anexo
                    </button>

                </div>

                <small>
                    Solo PDF. Tamaño máximo: 10 MB.
                </small>

            </form>

        </div>

    <?php endif; ?>

</section>

            <!-- =================================================
                 HISTORIAL
                 ================================================= -->

            <section class="detail-card">

                <div class="detail-card-header">

                    <div>

                        <span class="section-kicker">
                            SEGUIMIENTO
                        </span>

                        <h3>
                            Historial del expediente
                        </h3>

                    </div>

                    <span class="results-count">

                        <?= count($movimientos) ?>

                        movimiento<?= count($movimientos) === 1 ? "" : "s" ?>

                    </span>

                </div>


                <?php if (!empty($movimientos)): ?>

                    <div class="movement-list">

                        <?php foreach ($movimientos as $movimiento): ?>

                            <article class="movement-item">

                                <div class="movement-marker">
                                    ↗
                                </div>


                                <div class="movement-content">

                                    <div class="movement-top">

                                        <strong>
                                            Derivación de expediente
                                        </strong>

                                        <span class="date-text">

                                            <?= date(
                                                "d/m/Y H:i",
                                                strtotime(
                                                    $movimiento["fecha_envio"]
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                    <div class="movement-route">

                                        <div>

                                            <span>
                                                Origen
                                            </span>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $movimiento[
                                                        "nombre_area_origen"
                                                    ]
                                                    ?? "Sin área"
                                                ) ?>
                                            </strong>

                                        </div>


                                        <span class="route-arrow">
                                            →
                                        </span>


                                        <div>

                                            <span>
                                                Destino
                                            </span>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $movimiento[
                                                        "nombre_area_destino"
                                                    ]
                                                    ?? "Sin área"
                                                ) ?>
                                            </strong>

                                        </div>

                                    </div>


                                    <div class="movement-meta">

                                        <span>

                                            Estado:

                                            <strong>
                                                <?= htmlspecialchars(
                                                    obtenerTextoRecepcion(
                                                        $movimiento[
                                                            "estado_recepcion"
                                                        ]
                                                    )
                                                ) ?>
                                            </strong>

                                        </span>


                                        <span>

                                            Responsable:

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $movimiento[
                                                        "usuario_responsable"
                                                    ]
                                                    ?? "Sin información"
                                                ) ?>
                                            </strong>

                                        </span>

                                    </div>


                                    <?php if (
                                        !empty(
                                            $movimiento[
                                                "fecha_recepcion"
                                            ]
                                        )
                                    ): ?>

                                        <div class="movement-received">

                                            Recepcionado el

                                            <strong>

                                                <?= date(
                                                    "d/m/Y H:i",
                                                    strtotime(
                                                        $movimiento[
                                                            "fecha_recepcion"
                                                        ]
                                                    )
                                                ) ?>

                                            </strong>

                                        </div>

                                    <?php endif; ?>

                                    <?php
                                    $puedeRecepcionar = (
                                        $movimiento["estado_recepcion"] === "PENDIENTE"
                                        && (
                                            (int) $usuario["id_area"] === (int) $movimiento["area_destino"]
                                            || (int) $usuario["id_rol"] === 1
                                        )
                                    );
                                    ?>

                                    <?php if ($puedeRecepcionar): ?>

                                        <div class="movement-actions">

                                            <form id="formRecepcion" action="recibir.php" method="POST">

                                                <input
                                                    type="hidden"
                                                    name="id_movimiento"
                                                    value="<?= (int) $movimiento["id_movimiento"] ?>"
                                                >

                                                <input type="hidden" name="accion" id="accionRecepcion" value="">

                                                <button
                                                    type="button"
                                                    class="btn-primary"
                                                    onclick="confirmarAccion('RECEPCIONAR')"
                                                >
                                                    Recepcionar
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn-secondary"
                                                    onclick="confirmarAccion('OBSERVAR')"
                                                >
                                                    Observar
                                                </button>

                                            </form>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-state compact">

                        <div class="empty-state-icon">
                            ↗
                        </div>

                        <h3>
                            Sin movimientos registrados
                        </h3>

                        <p>
                            Este expediente todavía no presenta
                            movimientos de derivación.
                        </p>

                    </div>

                <?php endif; ?>

            </section>



            <!-- =================================================
                 PROVEÍDOS
                 ================================================= -->

            <section class="detail-card">

                <div class="detail-card-header">

                    <div>

                        <span class="section-kicker">
                            PROVEÍDOS
                        </span>

                        <h3>
                            Indicaciones registradas
                        </h3>

                    </div>

                    <span class="results-count">

                        <?= count($proveidos) ?>

                        proveído<?= count($proveidos) === 1 ? "" : "s" ?>

                    </span>

                </div>

                <?php if ($puedeAgregarProveido): ?>
                <div class="anexo-upload">
                    <form method="POST" action="guardar_proveido.php" enctype="multipart/form-data">
                        <input type="hidden" name="id_expediente" value="<?= (int)$id_expediente ?>">
                        <label for="descripcion_proveido">Nuevo proveído / indicación</label>
                        <textarea
                            id="descripcion_proveido"
                            name="descripcion_proveido"
                            rows="3"
                            required
                            maxlength="1000"
                            placeholder="Escriba la indicación o proveído..."
                            style="width:100%; box-sizing:border-box; margin-bottom:10px; padding:10px 12px; border:1px solid var(--border); border-radius:var(--radius-sm); font-family:inherit; font-size:0.76rem; resize:vertical;"
                        ></textarea>
                        <div class="anexo-upload-row">
                            <input
                                type="file"
                                id="archivo_proveido_pdf"
                                name="archivo_proveido_pdf"
                                accept="application/pdf,.pdf"
                            >
                            <button type="submit" class="btn-primary">
                                Registrar proveído
                            </button>
                        </div>
                        <small>
                            El PDF del proveído es opcional. Tamaño máximo: 10 MB.
                        </small>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (!empty($proveidos)): ?>

                    <div class="proveido-list">

                        <?php foreach ($proveidos as $proveido): ?>

                            <article class="proveido-item">

                                <div class="proveido-icon">
                                    ✔
                                </div>


                                <div class="proveido-content">

                                    <div class="proveido-top">

                                        <strong>
                                            Proveído registrado
                                        </strong>

                                        <span class="date-text">

                                            <?= date(
                                                "d/m/Y H:i",
                                                strtotime(
                                                    $proveido[
                                                        "fecha_emision"
                                                    ]
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                    <p>

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $proveido[
                                                    "descripcion_proveido"
                                                ]
                                            )
                                        ) ?>

                                    </p>


                                    <span class="proveido-author">

                                        Emitido por:

                                        <strong>

                                            <?= htmlspecialchars(
                                                $proveido[
                                                    "usuario_emisor"
                                                ]
                                                ?? "Sin información"
                                            ) ?>

                                        </strong>

                                    </span>


                                    <?php 
                                    $rutaProv = trim($proveido["archivo_proveido_pdf"] ?? "");
                                    $rutaProv = str_replace("\\", "/", $rutaProv);
                                    $rutaProv = ltrim($rutaProv, "/");
                                    $hrefProv = "";
                                    $provExiste = false;
                                    if ($rutaProv !== "") {
                                        if (strpos($rutaProv, "uploads/") === 0) {
                                            $hrefProv = "../../" . $rutaProv;
                                        } else {
                                            $hrefProv = "../../uploads/" . $rutaProv;
                                        }
                                        $fisicaProv = realpath(__DIR__ . "/" . $hrefProv);
                                        if ($fisicaProv && is_file($fisicaProv)) {
                                            $provExiste = true;
                                        } else {
                                            $altP = realpath(__DIR__ . "/../../" . $rutaProv);
                                            if ($altP && is_file($altP)) {
                                                $hrefProv = "../../" . $rutaProv;
                                                $provExiste = true;
                                            }
                                        }
                                    }
                                    if ($provExiste && $hrefProv !== ""): ?>

                                        <a
                                            href="<?= htmlspecialchars($hrefProv) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="action-link"
                                        >
                                            Ver PDF
                                        </a>

                                    <?php else: ?>
                                        <span class="file-unavailable">No disponible</span>
                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-state compact">

                        <div class="empty-state-icon">
                            ✓
                        </div>

                        <h3>
                            Sin proveídos registrados
                        </h3>

                        <p>
                            Este expediente todavía no tiene
                            proveídos asociados.
                        </p>

                    </div>

                <?php endif; ?>

            </section>


        </div>

    </main>

</div>

</body>

<script src="../../assets/js/app.js"></script>

</html>