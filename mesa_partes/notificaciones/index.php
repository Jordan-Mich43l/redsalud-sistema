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
| ACCIONES
|--------------------------------------------------------------------------
*/

// Marcar una notificación como leída
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";

    if ($accion === "marcar_leida") {

        $id_notificacion = (int) ($_POST["id_notificacion"] ?? 0);

        if ($id_notificacion > 0) {

            $stmt = $conn->prepare("
                UPDATE notificaciones
                SET leido = 1
                WHERE id_notificacion = ?
                  AND id_usuario = ?
            ");

            $stmt->bind_param(
                "ii",
                $id_notificacion,
                $id_usuario
            );

            $stmt->execute();
            $stmt->close();
        }

        header("Location: index.php");
        exit;
    }

    // Marcar todas las notificaciones como leídas
    if ($accion === "marcar_todas") {

        $stmt = $conn->prepare("
            UPDATE notificaciones
            SET leido = 1
            WHERE id_usuario = ?
              AND leido = 0
        ");

        $stmt->bind_param(
            "i",
            $id_usuario
        );

        $stmt->execute();
        $stmt->close();

        header("Location: index.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| DATOS DEL USUARIO
|--------------------------------------------------------------------------
*/

$stmtUsuario = $conn->prepare("
    SELECT
        u.nombres,
        u.apellidos,
        u.correo,
        r.nombre_rol
    FROM usuarios u
    LEFT JOIN roles r
        ON r.id_rol = u.id_rol
    WHERE u.id_usuario = ?
    LIMIT 1
");

$stmtUsuario->bind_param(
    "i",
    $id_usuario
);

$stmtUsuario->execute();

$resultadoUsuario = $stmtUsuario->get_result();
$usuario = $resultadoUsuario->fetch_assoc();

$stmtUsuario->close();


if (!$usuario) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| VARIABLES DEL USUARIO
|--------------------------------------------------------------------------
*/

$nombre_completo = trim(
    $usuario["nombres"] . " " . $usuario["apellidos"]
);

$correo = $usuario["correo"];
$nombre_rol = $usuario["nombre_rol"] ?? "Usuario";

/*
|--------------------------------------------------------------------------
| INICIALES
|--------------------------------------------------------------------------
*/

$iniciales = "";

foreach (preg_split('/\s+/', $nombre_completo) as $parte) {

    if ($parte !== "") {
        $iniciales .= strtoupper(substr($parte, 0, 1));
    }
}

$iniciales = substr($iniciales, 0, 2);

/*
|--------------------------------------------------------------------------
| NOTIFICACIONES
|--------------------------------------------------------------------------
*/

$notificaciones = [];

$sqlNotificaciones = "
    SELECT
        n.id_notificacion,
        n.id_expediente,
        n.mensaje,
        n.leido,
        n.fecha_creacion,
        e.numero_expediente AS numero_expediente

    FROM notificaciones n

    LEFT JOIN expedientes e
        ON e.id_expediente = n.id_expediente

    WHERE n.id_usuario = ?

    ORDER BY
        n.leido ASC,
        n.fecha_creacion DESC
";

$stmtNotificaciones = $conn->prepare($sqlNotificaciones);

$stmtNotificaciones->bind_param(
    "i",
    $id_usuario
);

$stmtNotificaciones->execute();

$resultadoNotificaciones = $stmtNotificaciones->get_result();

while ($fila = $resultadoNotificaciones->fetch_assoc()) {
    $notificaciones[] = $fila;
}

$stmtNotificaciones->close();

/*
|--------------------------------------------------------------------------
| CONTADORES
|--------------------------------------------------------------------------
*/

$notificaciones_no_leidas = 0;

foreach ($notificaciones as $notificacion) {

    if ((int) $notificacion["leido"] === 0) {
        $notificaciones_no_leidas++;
    }
}

$total_notificaciones = count($notificaciones);

/*
|--------------------------------------------------------------------------
| FORMATEAR FECHAS
|--------------------------------------------------------------------------
*/

function formatearFechaNotificacion($fecha)
{
    if (empty($fecha)) {
        return "Fecha no disponible";
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return "Fecha no disponible";
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

    <title>Notificaciones | RedSalud</title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/notifications.css"
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

                <span>Gestión Documentaria</span>

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

            <a href="../movimientos/historial.php" class="nav-item">
                <span class="nav-icon"><img src="../../assets/img/icon_movimientos.png" alt=""></span>
                <span>Movimientos</span>
            </a>

            <a href="index.php" class="nav-item active">
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

                <span>Cerrar sesión</span>

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
                    Notificaciones
                </h1>

                <p>
                    Avisos y actualizaciones de tus expedientes
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

            <!-- =================================================
                 RESUMEN
                 ================================================= -->

            <section class="notification-summary">

                <div class="notification-summary-icon">
                   ✉
                </div>

                <div class="notification-summary-text">

                    <span>
                        CENTRO DE NOTIFICACIONES
                    </span>

                    <strong>
                        <?= $total_notificaciones ?>
                        <?= $total_notificaciones === 1
                            ? "notificación"
                            : "notificaciones" ?>
                    </strong>

                </div>

                <?php if ($notificaciones_no_leidas > 0): ?>

                    <div class="notification-summary-pending">

                        <?= $notificaciones_no_leidas ?>

                        <?= $notificaciones_no_leidas === 1
                            ? "pendiente"
                            : "pendientes" ?>

                    </div>

                <?php endif; ?>

            </section>

            <!-- =================================================
                 LISTADO
                 ================================================= -->

            <section class="dashboard-section notification-section">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            BANDEJA DE AVISOS
                        </span>

                        <h2>
                            Tus notificaciones
                        </h2>

                    </div>

                    <?php if ($notificaciones_no_leidas > 0): ?>

                        <form
                            method="POST"
                            action="index.php"
                        >

                            <input
                                type="hidden"
                                name="accion"
                                value="marcar_todas"
                            >

                            <button
                                type="submit"
                                class="mark-all-button"
                            >
                                Marcar todas como leídas
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

                <?php if (empty($notificaciones)): ?>

                    <div class="notifications-empty">

                        <div class="notifications-empty-icon">
                            ✔
                        </div>

                        <strong>
                            No tienes notificaciones
                        </strong>

                        <p>
                            Cuando recibas nuevos avisos aparecerán aquí.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="notifications-list">

                        <?php foreach ($notificaciones as $notificacion): ?>

                            <?php
                            $no_leida =
                                ((int) $notificacion["leido"] === 0);
                            ?>

                            <article
                                class="
                                    notification-card
                                    <?= $no_leida
                                        ? "notification-unread"
                                        : "notification-read" ?>
                                "
                            >

                                <div class="notification-icon">

                                    <?= $no_leida ? "●" : "✔" ?>

                                </div>

                                <div class="notification-content">

                                    <div class="notification-top">

                                        <span class="notification-label">

                                            <?= $no_leida
                                                ? "NUEVA"
                                                : "LEÍDA" ?>

                                        </span>

                                        <time>
                                            <?= htmlspecialchars(
                                                formatearFechaNotificacion(
                                                    $notificacion["fecha_creacion"]
                                                )
                                            ) ?>
                                        </time>

                                    </div>

                                    <p class="notification-message">

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $notificacion["mensaje"]
                                            )
                                        ) ?>

                                    </p>

                                    <?php if (
                                        !empty(
                                            $notificacion["numero_expediente"]
                                        )
                                    ): ?>

                                    <div class="notification-expediente">
                                        <span>
                                            Expediente
                                        </span>

                                        <a
                                            href="../expedientes/ver.php?id=<?= (int) $notificacion["id_expediente"] ?>"
                                            class="notification-expediente-link"
                                        >
                                            <?= htmlspecialchars(
                                                $notificacion["numero_expediente"]
                                            ) ?>
                                        </a>
                                    </div>

                                    <?php endif; ?>

                                    <?php if ($no_leida): ?>

                                        <form
                                            method="POST"
                                            action="index.php"
                                            class="notification-action"
                                        >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="marcar_leida"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_notificacion"
                                                value="<?= (int) $notificacion["id_notificacion"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="mark-read-button"
                                            >
                                                Marcar como leída
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>


        </div>

    </main>

</div>

</body>

</html>