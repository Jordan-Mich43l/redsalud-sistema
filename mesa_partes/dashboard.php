<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];
$id_area = isset($_SESSION["id_area"]) ? (int) $_SESSION["id_area"] : 0;

$nombres = $_SESSION["nombres"] ?? "";
$apellidos = $_SESSION["apellidos"] ?? "";
$correo = $_SESSION["correo"] ?? "";
$nombre_completo = trim($nombres . " " . $apellidos);

$iniciales = "";
foreach (preg_split('/\s+/', $nombre_completo) as $parte) {
    if ($parte !== "") {
        $iniciales .= strtoupper(substr($parte, 0, 1));
    }
}
$iniciales = substr($iniciales, 0, 2);

$nombre_area = "Dirección no asignada";
$nombre_rol = "Usuario";

$total_expedientes = 0;
$pendientes = 0;
$recibidos = 0;
$derivados = 0;
$notificaciones_no_leidas = 0;

try {
    /* Datos descriptivos del usuario */
    $sqlUsuario = "
        SELECT
            u.id_area,
            u.id_rol,
            a.nombre_area,
            r.nombre_rol
        FROM usuarios u
        LEFT JOIN areas a ON a.id_area = u.id_area
        LEFT JOIN roles r ON r.id_rol = u.id_rol
        WHERE u.id_usuario = ?
        LIMIT 1
    ";

    $stmtUsuario = $conn->prepare($sqlUsuario);
    $stmtUsuario->bind_param("i", $id_usuario);
    $stmtUsuario->execute();
    $datosUsuario = $stmtUsuario->get_result()->fetch_assoc();

    if ($datosUsuario) {
        $id_area = (int) ($datosUsuario["id_area"] ?? 0);
        $nombre_area = $datosUsuario["nombre_area"] ?? $nombre_area;
        $nombre_rol = $datosUsuario["nombre_rol"] ?? $nombre_rol;
    }

    /* Total de expedientes registrados */
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM expedientes");
    if ($resultado) {
        $total_expedientes = (int) $resultado->fetch_assoc()["total"];
    }

    /* Expedientes actualmente en trámite */
    $resultado = $conn->query("SELECT COUNT(*) AS total FROM expedientes WHERE estado_expediente = 'EN_TRAMITE'");
    if ($resultado) {
        $pendientes = (int) $resultado->fetch_assoc()["total"];
    }

    /* Expedientes recibidos por el área del usuario */
    if ($id_area > 0) {
        $stmtRecibidos = $conn->prepare(" 
            SELECT COUNT(DISTINCT id_expediente) AS total
            FROM movimientos_documento
            WHERE area_destino = ?
              AND estado_recepcion = 'RECEPCIONADO'
        ");
        $stmtRecibidos->bind_param("i", $id_area);
        $stmtRecibidos->execute();
        $recibidos = (int) $stmtRecibidos->get_result()->fetch_assoc()["total"];

        /* Expedientes derivados desde el área del usuario */
        $stmtDerivados = $conn->prepare(" 
            SELECT COUNT(DISTINCT id_expediente) AS total
            FROM movimientos_documento
            WHERE area_origen = ?
        ");
        $stmtDerivados->bind_param("i", $id_area);
        $stmtDerivados->execute();
        $derivados = (int) $stmtDerivados->get_result()->fetch_assoc()["total"];
    }

    /* Notificaciones pendientes de lectura */
    $stmtNotificaciones = $conn->prepare(" 
        SELECT COUNT(*) AS total
        FROM notificaciones
        WHERE id_usuario = ?
          AND leido = 0
    ");
    $stmtNotificaciones->bind_param("i", $id_usuario);
    $stmtNotificaciones->execute();
    $notificaciones_no_leidas = (int) $stmtNotificaciones->get_result()->fetch_assoc()["total"];

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | RedSalud</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="app-layout">

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-mark">
                <img src="../assets/img/icon_redsalud.png" alt="RedSalud">
            </div>
            <div class="brand-text">
                <strong>RedSalud</strong>
                <span>Gestión Documentaria</span>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Navegación principal">
            <div class="nav-title">MENÚ PRINCIPAL</div>

            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon"><img src="../assets/img/icon_inicio.png" alt=""></span>
                <span>Inicio</span>
            </a>

            <a href="expedientes/index.php" class="nav-item">
                <span class="nav-icon"><img src="../assets/img/icon_expedientes.png" alt=""></span>
                <span>Expedientes</span>
            </a>

            <a href="movimientos/historial.php" class="nav-item">
                <span class="nav-icon"><img src="../assets/img/icon_movimientos.png" alt=""></span>
                <span>Movimientos</span>
            </a>

            <a href="notificaciones/index.php" class="nav-item">
                <span class="nav-icon"><img src="../assets/img/icon_notificaciones.png" alt=""></span>
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

            <a href="perfil/index.php" class="nav-item">
                <span class="nav-icon"><img src="../assets/img/icon_perfil.png" alt=""></span>
                <span>Mi perfil</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="avatar avatar-small"><?= htmlspecialchars($iniciales) ?></div>
                <div class="sidebar-user-data">
                    <strong><?= htmlspecialchars($nombre_completo) ?></strong>
                    <span><?= htmlspecialchars($nombre_rol) ?></span>
                </div>
            </div>

            <a href="../logout.php" class="logout-link">
                <span class="nav-icon">↪</span>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </aside>

    <main class="main-content">

        <header class="main-header">
            <div class="header-title">
                <span class="eyebrow">RED DE SALUD AGUAYTÍA</span>
                <h1>Panel principal</h1>
                <p>Sistema Integral de Gestión Documentaria</p>
            </div>

            <div class="header-user">
                <div class="avatar"><?= htmlspecialchars($iniciales) ?></div>
                <div class="header-user-data">
                    <strong><?= htmlspecialchars($nombre_completo) ?></strong>
                    <span><?= htmlspecialchars($correo) ?></span>
                </div>
            </div>
        </header>

        <div class="dashboard-content">

            <section class="welcome-card">
                <div class="welcome-content">
                    <span class="welcome-label">PANEL DE CONTROL</span>
                    <h2>Bienvenido, <?= htmlspecialchars($nombres) ?></h2>
                    <p>
                        Desde este panel puedes consultar expedientes, revisar movimientos
                        y realizar las principales acciones de gestión documentaria.
                    </p>
                </div>
                <div class="welcome-decoration"></div>
            </section>

            <section class="stats-grid" aria-label="Resumen del sistema">

                <article class="stat-card stat-primary">
                    <div class="stat-icon">
                        <img src="../assets/img/icon_expedientes.png" alt="">
                    </div>

                    <div class="stat-content">
                        <span class="stat-label">
                            Expedientes registrados
                        </span>

                        <strong class="stat-value">
                            <?= $total_expedientes ?>
                        </strong>

                        <span class="stat-caption">
                            Total en el sistema
                        </span>
                    </div>
                </article>

                <article class="stat-card stat-warning">
                    <div class="stat-icon">⏱</div>
                    <div class="stat-content">
                        <span class="stat-label">Expedientes pendientes</span>
                        <strong class="stat-value"><?= $pendientes ?></strong>
                        <span class="stat-caption">En trámite</span>
                    </div>
                </article>

                <article class="stat-card stat-info">
                    <div class="stat-icon">⬇</div>
                    <div class="stat-content">
                        <span class="stat-label">Expedientes recibidos</span>
                        <strong class="stat-value"><?= $recibidos ?></strong>
                        <span class="stat-caption">En tu área</span>
                    </div>
                </article>

                <article class="stat-card stat-success">
                    <div class="stat-icon">⬆</div>
                    <div class="stat-content">
                        <span class="stat-label">Expedientes derivados</span>
                        <strong class="stat-value"><?= $derivados ?></strong>
                        <span class="stat-caption">Desde tu área</span>
                    </div>
                </article>

            </section>

            <!-- NOTIFICACIONES -->
            <section class="dashboard-notificaciones">

                <div class="notificaciones-info">

                    <div class="notificaciones-icono">
                        <img src="../assets/img/icon_notificaciones.png" alt="">
                    </div>

                    <div>
                        <h3>
                            Notificaciones
                        </h3>

                        <?php if ($notificaciones_no_leidas > 0): ?>

                            <p>
                                Tienes
                                <strong><?= $notificaciones_no_leidas ?></strong>
                                <?= $notificaciones_no_leidas == 1
                                    ? 'notificación pendiente de revisar.'
                                    : 'notificaciones pendientes de revisar.' ?>
                            </p>

                        <?php else: ?>

                            <p>
                                No tienes notificaciones pendientes.
                            </p>

                        <?php endif; ?>
                    </div>

                </div>

                <a href="notificaciones/index.php"
                class="btn-notificaciones">

                    Ver notificaciones

                </a>

            </section>

            <section class="dashboard-section">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">ACCESOS</span>
                        <h2>Acciones rápidas</h2>
                    </div>
                </div>

                <div class="quick-actions">
                    <a href="expedientes/crear.php" class="action-card action-featured">
                        <span class="action-icon">＋</span>
                        <span class="action-text">
                            <strong>Nuevo expediente</strong>
                            <small>Registrar un nuevo documento</small>
                        </span>
                        <span class="action-arrow">➤</span>
                    </a>

                    <a href="expedientes/index.php" class="action-card">
                        <span class="action-icon">
                            <img src="../assets/img/icon_expedientes.png" alt="">
                        </span>
                        <span class="action-text">
                            <strong>Ver expedientes</strong>
                            <small>Consultar documentos registrados</small>
                        </span>
                        <span class="action-arrow">➤</span>
                    </a>

                    <a href="movimientos/historial.php" class="action-card">
                        <span class="action-icon">
                            <img src="../assets/img/icon_historial.png" alt="">
                        </span>
                        <span class="action-text">
                            <strong>Historial</strong>
                            <small>Consultar movimientos documentarios</small>
                        </span>
                        <span class="action-arrow">➤</span>
                    </a>
                </div>
            </section>

        </div>
    </main>
</div>

</body>
</html>