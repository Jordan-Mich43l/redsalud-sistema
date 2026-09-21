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

$usuario = $stmtUsuario->get_result()->fetch_assoc();
$stmtUsuario->close();

if (!$usuario) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

$nombre_completo = trim(
    $usuario["nombres"] . " " . $usuario["apellidos"]
);

$nombre_rol = $usuario["nombre_rol"] ?? "Sin rol";
$correo = $usuario["correo"] ?? "";

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
$estado_recepcion = $_GET["estado_recepcion"] ?? "";
$area_destino = (int) ($_GET["area_destino"] ?? 0);


/*
|--------------------------------------------------------------------------
| CONSULTA DE MOVIMIENTOS
|--------------------------------------------------------------------------
*/

$sqlMovimientos = "
    SELECT
        m.id_movimiento,
        m.id_expediente,
        m.area_origen,
        m.area_destino,
        m.usuario_mesa_partes,
        m.fecha_envio,
        m.fecha_recepcion,
        m.estado_recepcion,

        e.numero_expediente,
        e.asunto,
        e.estado_expediente,

        ao.nombre_area AS nombre_area_origen,
        ad.nombre_area AS nombre_area_destino,

        CONCAT(
            u.nombres,
            ' ',
            u.apellidos
        ) AS usuario_responsable

    FROM movimientos_documento m

    INNER JOIN expedientes e
        ON e.id_expediente = m.id_expediente

    LEFT JOIN areas ao
        ON ao.id_area = m.area_origen

    LEFT JOIN areas ad
        ON ad.id_area = m.area_destino

    LEFT JOIN usuarios u
        ON u.id_usuario = m.usuario_mesa_partes

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
    $sqlMovimientos .= "
        AND (
            e.numero_expediente LIKE ?
            OR e.asunto LIKE ?
            OR ao.nombre_area LIKE ?
            OR ad.nombre_area LIKE ?
        )
    ";

    $termino = "%" . $busqueda . "%";

    $tipos .= "ssss";

    $parametros[] = $termino;
    $parametros[] = $termino;
    $parametros[] = $termino;
    $parametros[] = $termino;
}


/*
|--------------------------------------------------------------------------
| FILTRO DE RECEPCIÓN
|--------------------------------------------------------------------------
*/

if ($estado_recepcion !== "") {
    $sqlMovimientos .= "
        AND m.estado_recepcion = ?
    ";

    $tipos .= "s";
    $parametros[] = $estado_recepcion;
}


/*
|--------------------------------------------------------------------------
| FILTRO DE ÁREA DESTINO
|--------------------------------------------------------------------------
*/

if ($area_destino > 0) {
    $sqlMovimientos .= "
        AND m.area_destino = ?
    ";

    $tipos .= "i";
    $parametros[] = $area_destino;
}


/*
|--------------------------------------------------------------------------
| ORDEN
|--------------------------------------------------------------------------
*/

$sqlMovimientos .= "
    ORDER BY e.id_expediente ASC, m.fecha_envio DESC
";


$stmtMovimientos = $conn->prepare($sqlMovimientos);

if (!$stmtMovimientos) {
    die("Error en la consulta de movimientos: " . $conn->error);
}

if (!empty($tipos)) {
    $stmtMovimientos->bind_param(
        $tipos,
        ...$parametros
    );
}

$stmtMovimientos->execute();

$resultadoMovimientos = $stmtMovimientos->get_result();

$movimientos = [];

while ($fila = $resultadoMovimientos->fetch_assoc()) {
    $movimientos[] = $fila;
}

$stmtMovimientos->close();


/*
|--------------------------------------------------------------------------
| ÁREAS PARA FILTRO
|--------------------------------------------------------------------------
*/

$sqlAreas = "
    SELECT
        id_area,
        nombre_area
    FROM areas
    ORDER BY nombre_area ASC
";

$resultadoAreas = $conn->query($sqlAreas);

$areas = [];

if ($resultadoAreas) {
    while ($fila = $resultadoAreas->fetch_assoc()) {
        $areas[] = $fila;
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

$notificaciones_no_leidas =
    (int) $stmtNoLeidas->get_result()->fetch_assoc()["total"];

$stmtNoLeidas->close();


/*
|--------------------------------------------------------------------------
| FUNCIONES AUXILIARES
|--------------------------------------------------------------------------
*/

function obtenerClaseRecepcion($estado)
{
    switch ($estado) {
        case "RECEPCIONADO":
            return "movement-status movement-status-success";

        case "OBSERVADO":
            return "movement-status movement-status-danger";

        case "PENDIENTE":
        default:
            return "movement-status movement-status-warning";
    }
}


function obtenerTextoRecepcion($estado)
{
    switch ($estado) {
        case "RECEPCIONADO":
            return "Recepcionado";

        case "OBSERVADO":
            return "Observado";

        case "PENDIENTE":
        default:
            return "Pendiente";
    }
}


function formatearFecha($fecha)
{
    if (empty($fecha)) {
        return "—";
    }

    $timestamp = strtotime($fecha);

    if ($timestamp === false) {
        return "—";
    }

    return date("d/m/Y H:i", $timestamp);
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Movimientos | RedSalud</title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/movimientos.css"
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

            <a href="../expedientes/index.php" class="nav-item">
                <span class="nav-icon"><img src="../../assets/img/icon_expedientes.png" alt=""></span>
                <span>Expedientes</span>
            </a>

            <a href="historial.php" class="nav-item active">
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
                <span>Cerrar sesión</span>
            </a>

        </div>

    </aside>


    <!-- =========================================================
         CONTENIDO PRINCIPAL
         ========================================================= -->

    <main class="main-content">


        <header class="main-header">

            <div class="header-title">

                <span class="eyebrow">
                    RED DE SALUD AGUAYTÍA
                </span>

                <h1>
                    Movimientos
                </h1>

                <p>
                    Consulta el historial de derivaciones y recepción
                    de los expedientes.
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


        <div class="dashboard-content">


            <!-- =================================================
                 PRESENTACIÓN
                 ================================================= -->

            <section class="welcome-card">

                <div class="welcome-content">

                    <span class="welcome-label">
                        GESTIÓN DOCUMENTAL
                    </span>

                    <h2>
                        Historial de movimientos
                    </h2>

                    <p>
                        Revisa las áreas de origen y destino,
                        fechas de envío y recepción y estado de cada movimiento.
                    </p>

                </div>

                <div class="welcome-decoration"></div>

            </section>


            <!-- =================================================
                 FILTROS
                 ================================================= -->

            <section class="movements-filters-card">

                <form
                    method="GET"
                    action="historial.php"
                    class="movements-filters"
                >

                    <div class="filter-group">
                        <label for="buscar">
                            Buscar movimiento
                        </label>

                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            placeholder="Expediente, asunto o área..."
                            value="<?= htmlspecialchars($busqueda) ?>"
                        >
                    </div>


                    <div class="filter-group">
                        <label for="estado_recepcion">
                            Recepción
                        </label>

                        <select
                            id="estado_recepcion"
                            name="estado_recepcion"
                        >

                            <option value="">
                                Todos
                            </option>

                            <option
                                value="PENDIENTE"
                                <?= $estado_recepcion === "PENDIENTE" ? "selected" : "" ?>
                            >
                                Pendiente
                            </option>

                            <option
                                value="RECEPCIONADO"
                                <?= $estado_recepcion === "RECEPCIONADO" ? "selected" : "" ?>
                            >
                                Recepcionado
                            </option>

                            <option
                                value="OBSERVADO"
                                <?= $estado_recepcion === "OBSERVADO" ? "selected" : "" ?>
                            >
                                Observado
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="area_destino">
                            Dirección destino
                        </label>
                        <select
                            id="area_destino"
                            name="area_destino"
                        >
                            <option value="">
                                Todas
                            </option>

                            <?php foreach ($areas as $area): ?>
                                <option
                                    value="<?= (int) $area["id_area"] ?>"
                                    <?= $area_destino === (int) $area["id_area"] ? "selected" : "" ?>
                                >
                                    <?= htmlspecialchars($area["nombre_area"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="movement-filter-actions">
                        <button
                            type="submit"
                            class="btn-filter"
                        >
                            Buscar
                        </button>

                        <a
                            href="historial.php"
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

            <section class="dashboard-section movements-results-section">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">
                            TRAZABILIDAD
                        </span>
                        <h2>
                            Movimientos registrados
                        </h2>
                    </div>

                    <span class="results-count">
                        <?= count($movimientos) ?>
                        movimiento<?= count($movimientos) === 1 ? "" : "s" ?>
                    </span>
                </div>

                <?php if (!empty($movimientos)): ?>
                    <div class="table-wrapper">
                        <table class="movements-table">
                            <thead>
                                <tr>
                                    <th>Expediente</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Responsable</th>
                                    <th>Envío</th>
                                    <th>Recepción</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($movimientos as $movimiento): ?>
                                    <tr>
                                        <td>
                                            <a
                                                href="../expedientes/ver.php?id=<?= (int) $movimiento["id_expediente"] ?>"
                                                class="movement-expediente"
                                            >
                                                <?= htmlspecialchars(
                                                    $movimiento["numero_expediente"]
                                                ) ?>
                                            </a>
                                        </td>

                                        <td>
                                            <span class="area-text">
                                                <?= htmlspecialchars(
                                                    $movimiento["nombre_area_origen"]
                                                    ?? "Sin área"
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="area-text area-destination">
                                                <?= htmlspecialchars(
                                                    $movimiento["nombre_area_destino"]
                                                    ?? "Sin área"
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="responsible-text">
                                                <?= htmlspecialchars(
                                                    $movimiento["usuario_responsable"]
                                                    ?? "Sin responsable"
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="date-text">
                                                <?= htmlspecialchars(
                                                    formatearFecha(
                                                        $movimiento["fecha_envio"]
                                                    )
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="date-text">
                                                <?= htmlspecialchars(
                                                    formatearFecha(
                                                        $movimiento["fecha_recepcion"]
                                                    )
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="<?= obtenerClaseRecepcion(
                                                $movimiento["estado_recepcion"]
                                            ) ?>">
                                                <?= htmlspecialchars(
                                                    obtenerTextoRecepcion(
                                                        $movimiento["estado_recepcion"]
                                                    )
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <a
                                                href="../expedientes/ver.php?id=<?= (int) $movimiento["id_expediente"] ?>"
                                                class="movement-view"
                                            >
                                                Ver expediente
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <div class="movement-empty">
                        <div class="movement-empty-icon">
                            ↔
                        </div>
                        <h3>
                            No hay movimientos registrados
                        </h3>
                        <p>
                            No se encontraron movimientos con los filtros seleccionados.
                        </p>
                    </div>
                <?php endif; ?>

            </section>


        </div>

    </main>

</div>


<script src="../../assets/js/app.js"></script>

</body>

</html>