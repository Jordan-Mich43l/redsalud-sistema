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

$id_usuario = $_SESSION["id_usuario"];

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

$nombre_area = $usuario["nombre_area"] ?? "Sin área";
$nombre_rol = $usuario["nombre_rol"] ?? "Sin rol";
$id_area = (int) $usuario["id_area"];

$correo = $usuario["correo"];

$iniciales = "";

foreach (preg_split('/\s+/', $nombre_completo) as $parte) {

    if ($parte !== "") {
        $iniciales .= strtoupper(substr($parte, 0, 1));
    }

}

$iniciales = substr($iniciales, 0, 2);


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$busqueda = trim($_GET["buscar"] ?? "");
$estado = $_GET["estado"] ?? "";
$origen = $_GET["origen"] ?? "";


/*
|--------------------------------------------------------------------------
| CONSULTA DE EXPEDIENTES
|--------------------------------------------------------------------------
*/

$sqlExpedientes = "
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
    WHERE 1 = 1
";

$tipos = "";
$parametros = [];


/*
|--------------------------------------------------------------------------
| BÚSQUEDA
|--------------------------------------------------------------------------
*/

if ($busqueda !== "") {
    $sqlExpedientes .= "
        AND (
            e.numero_expediente LIKE ?
            OR e.remitente LIKE ?
            OR e.asunto LIKE ?
        )
    ";

    $termino = "%" . $busqueda . "%";

    $tipos .= "sss";
    $parametros[] = $termino;
    $parametros[] = $termino;
    $parametros[] = $termino;
}


/*
|--------------------------------------------------------------------------
| FILTRO ESTADO
|--------------------------------------------------------------------------
*/

if ($estado !== "") {
    $sqlExpedientes .= "
        AND e.estado_expediente = ?
    ";

    $tipos .= "s";
    $parametros[] = $estado;
}


/*
|--------------------------------------------------------------------------
| FILTRO ORIGEN
|--------------------------------------------------------------------------
*/

if ($origen !== "") {
    $sqlExpedientes .= "
        AND e.origen_documento = ?
    ";

    $tipos .= "s";
    $parametros[] = $origen;
}


/*
|--------------------------------------------------------------------------
| ORDEN
|--------------------------------------------------------------------------
*/

$sqlExpedientes .= " ORDER BY e.id_expediente ASC";


$stmtExpedientes = $conn->prepare($sqlExpedientes);

if (!$stmtExpedientes) {
    die("Error en la consulta de expedientes: " . $conn->error);
}

if (!empty($tipos)) {
    $stmtExpedientes->bind_param(
        $tipos,
        ...$parametros
    );
}

$stmtExpedientes->execute();

$resultadoExpedientes = $stmtExpedientes->get_result();

$expedientes = [];

while ($fila = $resultadoExpedientes->fetch_assoc()) {
    $expedientes[] = $fila;
}

$stmtExpedientes->close();


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
$notificaciones_no_leidas = (int) $resultadoNoLeidas->fetch_assoc()["total"];

$stmtNoLeidas->close();

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Expedientes | RedSalud</title>

    <!-- Estilos generales del dashboard -->
    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <!-- Estilos propios de expedientes -->
    <link
        rel="stylesheet"
        href="../../assets/css/expediente.css"
    >

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
                MENÚ PRINCIPAL
            </div>

            <a href="../dashboard.php" class="nav-item">
                <span class="nav-icon"><img src="../../assets/img/icon_inicio.png" alt=""></span>
                <span>Inicio</span>
            </a>

            <a href="index.php" class="nav-item active">
                <span class="nav-icon"><img src="../../assets/img/icon_expedientes.png" alt=""></span>
                <span>Expedientes</span>
            </a>

            <a href="../movimientos/historial.php" class="nav-item">
                <span class="nav-icon"><img src="../../assets/img/icon_movimientos.png" alt=""></span>
                <span>Movimientos</span>
            </a>

            <a href="../notificaciones/index.php" class="nav-item">
                <span class="nav-icon"><img src="../../assets/img/icon_notificaciones.png" alt=""></span>
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
                <span class="nav-icon"><img src="../../assets/img/icon_perfil.png" alt=""></span>
                <span>Mi perfil</span>
            </a>

        </nav>

        <!-- =====================================================
             USUARIO SIDEBAR
             ===================================================== -->

        <div class="sidebar-footer">

            <div class="sidebar-user">

                <div class="avatar avatar-small">

                    <?= htmlspecialchars($nombre_completo[0] ?? "") ?>

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
                    Consulta y seguimiento de los expedientes
                    registrados en el sistema.
                </p>

            </div>


            <div class="header-user">

                <div class="avatar">
                    <?= htmlspecialchars(
                        strtoupper(
                            substr($usuario["nombres"], 0, 1) .
                            substr($usuario["apellidos"], 0, 1)
                        )
                    ) ?>
                </div>


                <div class="header-user-data">

                    <strong>
                        <?= htmlspecialchars($nombre_completo) ?>
                    </strong>

                    <span>
                        <?= htmlspecialchars($usuario["correo"]) ?>
                    </span>

                </div>

            </div>

        </header>



        <!-- =====================================================
             CONTENIDO DE EXPEDIENTES
             ===================================================== -->

        <div class="dashboard-content">
            <?php if ($mensaje_exito !== ""): ?>

                <div class="alert alert-success">
                    <span>✓</span>
                    <div>
                        <strong>Expediente registrado</strong>
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
                 CABECERA DE LA SECCIÓN
                 ================================================= -->
            <section class="welcome-card">
                <div class="welcome-content">
                    <span class="welcome-label">
                        GESTIÓN DOCUMENTAL
                    </span>

                    <h2>
                        Expedientes registrados
                    </h2>

                    <p>
                        Consulta, búsqueda y seguimiento de los expedientes registrados
                        en el sistema.
                    </p>
                </div>

                <div class="welcome-decoration"></div>
            </section>

            <section class="expedientes-page-header">

                <a
                    href="crear.php"
                    class="btn-primary"
                >

                    <span>+</span>

                    Nuevo expediente

                </a>

            </section>



            <!-- =================================================
                 FILTROS
                 ================================================= -->

            <section class="expedientes-filters-card">

                <form
                    method="GET"
                    action="index.php"
                    class="filters-form"
                >


                    <div class="filter-group">

                        <label for="buscar">
                            Buscar expediente
                        </label>

                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            placeholder="Número, remitente o asunto..."
                            value="<?= htmlspecialchars($busqueda) ?>"
                        >

                    </div>



                    <div class="filter-group">

                        <label for="estado">
                            Estado
                        </label>

                        <select
                            id="estado"
                            name="estado"
                        >

                            <option value="">
                                Todos
                            </option>

                            <option
                                value="EN_TRAMITE"
                                <?= $estado === "EN_TRAMITE" ? "selected" : "" ?>
                            >
                                En trámite
                            </option>

                            <option
                                value="OBSERVADO"
                                <?= $estado === "OBSERVADO" ? "selected" : "" ?>
                            >
                                Observado
                            </option>

                            <option
                                value="ATENDIDO"
                                <?= $estado === "ATENDIDO" ? "selected" : "" ?>
                            >
                                Atendido
                            </option>

                            <option
                                value="ARCHIVADO"
                                <?= $estado === "ARCHIVADO" ? "selected" : "" ?>
                            >
                                Archivado
                            </option>

                        </select>

                    </div>



                    <div class="filter-group">

                        <label for="origen">
                            Origen
                        </label>

                        <select
                            id="origen"
                            name="origen"
                        >

                            <option value="">
                                Todos
                            </option>

                            <option
                                value="EXTERNO"
                                <?= $origen === "EXTERNO" ? "selected" : "" ?>
                            >
                                Externo
                            </option>

                            <option
                                value="INTERNO"
                                <?= $origen === "INTERNO" ? "selected" : "" ?>
                            >
                                Interno
                            </option>

                        </select>

                    </div>



                    <div class="filter-actions">

                        <button
                            type="submit"
                            class="btn-filter"
                        >
                            Buscar
                        </button>


                        <a
                            href="index.php"
                            class="btn-clear"
                        >
                            Limpiar
                        </a>

                    </div>

                </form>

            </section>



            <!-- =================================================
                 RESULTADOS
                 ================================================= -->

            <section class="dashboard-section expedientes-results-section">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            RESULTADOS
                        </span>

                        <h2>
                            Expedientes registrados
                        </h2>

                    </div>


                    <span class="results-count">

                        <?= count($expedientes) ?>

                        resultado<?= count($expedientes) === 1 ? "" : "s" ?>

                    </span>

                </div>



                <!-- =============================================
                     TABLA
                     ============================================= -->

                <?php if (!empty($expedientes)): ?>

                    <div class="table-wrapper">

                        <table class="expedientes-table">

                            <thead>

                                <tr>

                                    <th>
                                        Expediente
                                    </th>

                                    <th>
                                        Origen
                                    </th>

                                    <th>
                                        Tipo
                                    </th>

                                    <th>
                                        Remitente
                                    </th>

                                    <th>
                                        Asunto
                                    </th>

                                    <th>
                                        Fecha
                                    </th>

                                    <th>
                                        Estado
                                    </th>

                                    <th>
                                        Acción
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($expedientes as $expediente): ?>

                                    <tr>


                                        <!-- EXPEDIENTE -->

                                        <td>

                                            <span class="exp-number">

                                                <?= htmlspecialchars(
                                                    $expediente["numero_expediente"]
                                                ) ?>

                                            </span>

                                        </td>



                                        <!-- ORIGEN -->

                                        <td>

                                            <span class="origin">

                                                <?= htmlspecialchars(
                                                    obtenerTextoOrigen(
                                                        $expediente["origen_documento"]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>



                                        <!-- TIPO -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $expediente["tipo_documento"]
                                                    ?? "Sin tipo"
                                            ) ?>

                                        </td>



                                        <!-- REMITENTE -->

                                        <td>

                                            <span class="exp-remitente">

                                                <?= htmlspecialchars(
                                                    $expediente["remitente"]
                                                ) ?>

                                            </span>

                                        </td>



                                        <!-- ASUNTO -->

                                        <td>

                                            <div class="exp-asunto">

                                                <?= htmlspecialchars(
                                                    $expediente["asunto"]
                                                ) ?>

                                            </div>

                                        </td>



                                        <!-- FECHA -->

                                        <td>

                                            <span class="date-text">

                                                <?= date(
                                                    "d/m/Y",
                                                    strtotime(
                                                        $expediente["fecha_registro"]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>



                                        <!-- ESTADO -->

                                        <td>

                                            <span
                                                class="<?= obtenerClaseEstado(
                                                    $expediente["estado_expediente"]
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    obtenerTextoEstado(
                                                        $expediente["estado_expediente"]
                                                    )
                                                ) ?>

                                            </span>

                                        </td>



                                        <!-- ACCIÓN -->

                                        <td>

                                            <a
                                                href="ver.php?id=<?= (int) $expediente["id_expediente"] ?>"
                                                class="action-link"
                                            >
                                                Ver
                                            </a>

                                        </td>


                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <!-- =========================================
                         SIN RESULTADOS
                         ========================================= -->

                    <div class="empty-state">

                        <div class="empty-state-icon">
                            🗒
                        </div>

                        <h3>
                            No se encontraron expedientes
                        </h3>

                        <p>
                            No existen expedientes que coincidan
                            con los filtros seleccionados.
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