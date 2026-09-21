<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Verificar que el usuario sea Administrador
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["id_rol"]) || (int)$_SESSION["id_rol"] !== 1) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/database.php";

$id_usuario = (int)$_SESSION["id_usuario"];

$nombres = $_SESSION["nombres"] ?? "";
$apellidos = $_SESSION["apellidos"] ?? "";
$correo = $_SESSION["correo"] ?? "";

$nombre_completo = trim($nombres . " " . $apellidos);

/*
|--------------------------------------------------------------------------
| Iniciales
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
| Valores iniciales
|--------------------------------------------------------------------------
*/
$total_usuarios = 0;
$usuarios_activos = 0;
$total_expedientes = 0;
$expedientes_tramite = 0;
$total_areas = 0;
$total_movimientos = 0;
try {

    /*
    |--------------------------------------------------------------------------
    | Total de usuarios
    |--------------------------------------------------------------------------
    */
    $resultado = $conn->query("
        SELECT COUNT(*) AS total
        FROM usuarios
    ");

    if ($resultado) {
        $total_usuarios = (int)$resultado->fetch_assoc()["total"];
    }

    /*
    |--------------------------------------------------------------------------
    | Usuarios activos
    |--------------------------------------------------------------------------
    */
    $resultado = $conn->query("
        SELECT COUNT(*) AS total
        FROM usuarios
        WHERE estado = 1
    ");

    if ($resultado) {
        $usuarios_activos = (int)$resultado->fetch_assoc()["total"];
    }

    /*
    |--------------------------------------------------------------------------
    | Total de expedientes
    |--------------------------------------------------------------------------
    */
    $resultado = $conn->query("
        SELECT COUNT(*) AS total
        FROM expedientes
    ");

    if ($resultado) {
        $total_expedientes = (int)$resultado->fetch_assoc()["total"];
    }

    /*
    |--------------------------------------------------------------------------
    | Expedientes en trámite
    |--------------------------------------------------------------------------
    */
    $resultado = $conn->query("
        SELECT COUNT(*) AS total
        FROM expedientes
        WHERE estado_expediente = 'EN_TRAMITE'
    ");

    if ($resultado) {
        $expedientes_tramite = (int)$resultado->fetch_assoc()["total"];
    }

    /*
    |--------------------------------------------------------------------------
    | Total de áreas
    |--------------------------------------------------------------------------
    */
    $resultado = $conn->query("
        SELECT COUNT(*) AS total
        FROM areas
    ");

    if ($resultado) {
        $total_areas = (int)$resultado->fetch_assoc()["total"];
    }

    /*
    |--------------------------------------------------------------------------
    | Total de movimientos
    |--------------------------------------------------------------------------
    */
    $resultado = $conn->query("
        SELECT COUNT(*) AS total
        FROM movimientos_documento
    ");

    if ($resultado) {
        $total_movimientos = (int)$resultado->fetch_assoc()["total"];
    }


} catch (mysqli_sql_exception $e) {

    error_log($e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrador | RedSalud</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">

</head>

<body>

<div class="app-layout">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

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


        <!-- MENÚ -->

        <nav class="sidebar-nav" aria-label="Navegación principal">

            <div class="nav-title">
                ADMINISTRACIÓN
            </div>


            <!-- INICIO -->

            <a href="dashboard.php" class="nav-item active">

                <span class="nav-icon">
                    <img src="../assets/img/icon_inicio.png" alt="">
                </span>

                <span>Inicio</span>

            </a>


            <!-- USUARIOS -->

            <a href="usuarios/index.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_usuarios.png" alt="">
                </span>

                <span>Usuarios</span>

            </a>


            <!-- DIRECCIONES -->

            <a href="areas/index.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_direcciones.png" alt="">
                </span>

                <span>Direcciones</span>

            </a>


            <!-- PROGRAMAS -->

            <a href="programas/index.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_programas.png" alt="">
                </span>

                <span>Programas</span>

            </a>


            <!-- TIPOS DE DOCUMENTO -->

            <a href="tipos_documento/index.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_tipos_documento.png" alt="">
                </span>

                <span>Tipos de documento</span>

            </a>


            <div class="nav-title">
                SUPERVISIÓN
            </div>


            <!-- EXPEDIENTES -->

            <a href="expedientes/index.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_expedientes.png" alt="">
                </span>

                <span>Expedientes</span>

            </a>


            <!-- MOVIMIENTOS -->

            <a href="movimientos/historial.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_movimientos.png" alt="">
                </span>

                <span>Movimientos</span>

            </a>


            <!-- NOTIFICACIONES -->

            <a href="notificaciones/index.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_notificaciones.png" alt="">
                </span>

                <span>Notificaciones</span>

            </a>


            <!-- PERFIL -->

            <div class="nav-title">
                CUENTA
            </div>

            <a href="perfil/index.php" class="nav-item">

                <span class="nav-icon">
                    <img src="../assets/img/icon_perfil.png" alt="">
                </span>

                <span>Mi perfil</span>

            </a>

        </nav>


        <!-- USUARIO -->

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
                        Administrador
                    </span>

                </div>

            </div>


            <a href="../logout.php" class="logout-link">

                <span class="nav-icon">↪</span>

                <span>Cerrar sesión</span>

            </a>

        </div>

    </aside>


    <!-- =========================================================
         CONTENIDO PRINCIPAL
    ========================================================== -->

    <main class="main-content">


        <!-- HEADER -->

        <header class="main-header">

            <div class="header-title">

                <span class="eyebrow">
                    RED DE SALUD AGUAYTÍA
                </span>

                <h1>
                    Panel de Administrador
                </h1>

                <p>
                    Administración y supervisión del sistema de gestión documentaria
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
                 BIENVENIDA
            ================================================== -->

            <section class="welcome-card">

                <div class="welcome-content">

                    <span class="welcome-label">
                        PANEL DE CONTROL
                    </span>

                    <h2>
                        Bienvenido, <?= htmlspecialchars($nombres) ?>
                    </h2>

                    <p>
                        Desde este panel puedes administrar los usuarios,
                        áreas y configuraciones del sistema, además de
                        supervisar los expedientes y movimientos documentarios.
                    </p>

                </div>

                <div class="welcome-decoration"></div>

            </section>


            <!-- =================================================
                 ESTADÍSTICAS
            ================================================== -->

            <section class="stats-grid" aria-label="Resumen administrativo">


                <!-- USUARIOS -->

                <article class="stat-card stat-primary">

                    <div class="stat-icon">
                        <img src="../assets/img/icon_usuarios.png" alt="">
                    </div>

                    <div class="stat-content">

                        <span class="stat-label">
                            Usuarios registrados
                        </span>

                        <strong class="stat-value">
                            <?= $total_usuarios ?>
                        </strong>

                        <span class="stat-caption">
                            Usuarios en el sistema
                        </span>

                    </div>

                </article>


                <!-- USUARIOS ACTIVOS -->

                <article class="stat-card stat-success">

                    <div class="stat-icon">
                        ✔
                    </div>

                    <div class="stat-content">

                        <span class="stat-label">
                            Usuarios activos
                        </span>

                        <strong class="stat-value">
                            <?= $usuarios_activos ?>
                        </strong>

                        <span class="stat-caption">
                            Cuentas activas
                        </span>

                    </div>

                </article>


                <!-- EXPEDIENTES -->

                <article class="stat-card stat-info">

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


                <!-- EN TRÁMITE -->

                <article class="stat-card stat-warning">

                    <div class="stat-icon">
                        ⏱
                    </div>

                    <div class="stat-content">

                        <span class="stat-label">
                            Expedientes en trámite
                        </span>

                        <strong class="stat-value">
                            <?= $expedientes_tramite ?>
                        </strong>

                        <span class="stat-caption">
                            Actualmente en proceso
                        </span>

                    </div>

                </article>


                <!-- ÁREAS -->

                <article class="stat-card stat-primary">

                <div class="stat-icon">
                    <img src="../assets/img/icon_direcciones.png" alt="">
                </div>

                    <div class="stat-content">

                        <span class="stat-label">
                            Direcciones registradas
                        </span>

                        <strong class="stat-value">
                            <?= $total_areas ?>
                        </strong>

                        <span class="stat-caption">
                            Direcciones del sistema
                        </span>

                    </div>

                </article>


                <!-- MOVIMIENTOS -->

                <article class="stat-card stat-info">

                <div class="stat-icon">
                    <img src="../assets/img/icon_movimientos.png" alt="">
                </div>

                    <div class="stat-content">

                        <span class="stat-label">
                            Movimientos
                        </span>

                        <strong class="stat-value">
                            <?= $total_movimientos ?>
                        </strong>

                        <span class="stat-caption">
                            Trazabilidad documentaria
                        </span>

                    </div>

                </article>

            </section>


            <!-- =================================================
                 NOTIFICACIONES
            ================================================== -->

            <section class="dashboard-notificaciones">

                <div class="notificaciones-info">

                    <div class="notificaciones-icono">
                        <img src="../assets/img/icon_notificaciones.png" alt="">
                    </div>

                    <div>

                        <h3>
                            Notificaciones
                        </h3>

                        <p>
                            Consulta las notificaciones enviadas a Mesa de Partes
                            y verifica su estado de lectura.
                        </p>

                    </div>

                </div>

                <a href="notificaciones/index.php"
                   class="btn-notificaciones">

                    Ver notificaciones enviadas

                </a>

            </section>


            <!-- =================================================
                ACCIONES ADMINISTRATIVAS
            ================================================== -->

            <section class="dashboard-section">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            ADMINISTRACIÓN
                        </span>

                        <h2>
                            Acciones rápidas
                        </h2>

                    </div>

                </div>


                <div class="quick-actions">


                    <!-- USUARIOS -->

                    <a href="usuarios/index.php"
                    class="action-card action-featured">

                        <span class="action-icon">
                            <img src="../assets/img/icon_usuarios.png" alt="">
                        </span>

                        <span class="action-text">

                            <strong>
                                Gestionar usuarios
                            </strong>

                            <small>
                                Registrar y administrar usuarios
                            </small>

                        </span>

                        <span class="action-arrow">
                            ➤
                        </span>

                    </a>


                    <!-- ÁREAS -->

                    <a href="areas/index.php"
                    class="action-card">

                        <span class="action-icon">
                            <img src="../assets/img/icon_direcciones.png" alt="">
                        </span>

                        <span class="action-text">

                            <strong>
                                Gestionar áreas
                            </strong>

                            <small>
                                Administrar áreas de la institución
                            </small>

                        </span>

                        <span class="action-arrow">
                            ➤
                        </span>

                    </a>


                    <!-- PROGRAMAS -->

                    <a href="programas/index.php"
                    class="action-card">

                        <span class="action-icon">
                            <img src="../assets/img/icon_programas.png" alt="">
                        </span>

                        <span class="action-text">

                            <strong>
                                Gestionar programas
                            </strong>

                            <small>
                                Administrar programas registrados
                            </small>

                        </span>

                        <span class="action-arrow">
                            ➤
                        </span>

                    </a>


                    <!-- TIPOS DE DOCUMENTO -->

                    <a href="tipos_documento/index.php"
                    class="action-card">

                        <span class="action-icon">
                            <img src="../assets/img/icon_tipos_documento.png" alt="">
                        </span>

                        <span class="action-text">

                            <strong>
                                Tipos de documento
                            </strong>

                            <small>
                                Administrar tipos documentarios
                            </small>

                        </span>

                        <span class="action-arrow">
                            ➤
                        </span>

                    </a>


                    <!-- EXPEDIENTES -->

                    <a href="expedientes/index.php"
                    class="action-card">

                        <span class="action-icon">
                            <img src="../assets/img/icon_expedientes.png" alt="">
                        </span>

                        <span class="action-text">

                            <strong>
                                Supervisar expedientes
                            </strong>

                            <small>
                                Consultar expedientes registrados
                            </small>

                        </span>

                        <span class="action-arrow">
                            ➤
                        </span>

                    </a>


                    <!-- MOVIMIENTOS -->

                    <a href="movimientos/historial.php"
                    class="action-card">

                        <span class="action-icon">
                            <img src="../assets/img/icon_movimientos.png" alt="">
                        </span>

                        <span class="action-text">

                            <strong>
                                Ver movimientos
                            </strong>

                            <small>
                                Consultar la trazabilidad documental
                            </small>

                        </span>

                        <span class="action-arrow">
                            ➤
                        </span>

                    </a>

                </div>

            </section>

        </div>

    </main>

</div>

</body>

</html>