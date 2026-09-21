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
| Datos del usuario
|--------------------------------------------------------------------------
*/

$usuario = null;

$sql = "
    SELECT
        u.id_usuario,
        u.dni,
        u.nombres,
        u.apellidos,
        u.correo,
        u.estado,

        a.nombre_area,

        p.codigo_programa,
        p.nombre_programa,

        r.nombre_rol,

        c.nombre_usuario,
        c.fecha_creacion_cuenta,
        c.ultimo_ingreso

    FROM usuarios u

    LEFT JOIN areas a
        ON a.id_area = u.id_area

    LEFT JOIN programas_internos p
        ON p.id_programa = u.id_programa

    LEFT JOIN roles r
        ON r.id_rol = u.id_rol

    LEFT JOIN credenciales_acceso c
        ON c.id_usuario = u.id_usuario

    WHERE u.id_usuario = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

if (!$usuario) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$nombre_completo = trim(
    $usuario["nombres"] . " " . $usuario["apellidos"]
);

$nombres = $usuario["nombres"];
$apellidos = $usuario["apellidos"];
$correo = $usuario["correo"];
$dni = $usuario["dni"];

$nombre_area = $usuario["nombre_area"] ?? "No asignada";
$nombre_programa = $usuario["nombre_programa"] ?? "No asignado";
$nombre_rol = $usuario["nombre_rol"] ?? "Usuario";

$nombre_usuario = $usuario["nombre_usuario"] ?? "No disponible";

$estado = ((int) $usuario["estado"] === 1)
    ? "Activo"
    : "Inactivo";

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
| Fechas
|--------------------------------------------------------------------------
*/

function formatearFecha($fecha)
{
    if (empty($fecha)) {
        return "No registrado";
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return "No registrado";
    }

    return date("d/m/Y H:i", $timestamp);
}

$fecha_creacion = formatearFecha(
    $usuario["fecha_creacion_cuenta"]
);

$ultimo_ingreso = formatearFecha(
    $usuario["ultimo_ingreso"]
);

/*
|--------------------------------------------------------------------------
| Notificaciones no leídas
|--------------------------------------------------------------------------
*/

$notificaciones_no_leidas = 0;

$stmtNotificaciones = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM notificaciones
    WHERE id_usuario = ?
      AND leido = 0
");

$stmtNotificaciones->bind_param(
    "i",
    $id_usuario
);

$stmtNotificaciones->execute();

$resultadoNotificaciones = $stmtNotificaciones->get_result();

if ($fila = $resultadoNotificaciones->fetch_assoc()) {
    $notificaciones_no_leidas = (int) $fila["total"];
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

    <title>Mi perfil | RedSalud</title>

    <!-- Estilos generales del dashboard -->
    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <!-- Estilos propios del perfil -->
    <link rel="stylesheet" href="../../assets/css/profile.css">

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

            <a href="index.php" class="nav-item active">
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
                    Mi perfil
                </h1>

                <p>
                    Información de tu cuenta institucional
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
             CONTENIDO DEL PERFIL
             ===================================================== -->

        <div class="dashboard-content">


            <!-- =================================================
                 ENCABEZADO DEL PERFIL
                 ================================================= -->

            <section class="profile-hero">

                <div class="profile-hero-logo">

                    <img
                        src="../../assets/img/abigail_icon.png"
                        alt="RedSalud"
                    >

                </div>


                <div class="profile-hero-info">

                    <span class="profile-kicker">
                        CUENTA INSTITUCIONAL
                    </span>

                    <h2>
                        <?= htmlspecialchars($nombre_completo) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars($nombre_rol) ?>
                    </p>

                    <span class="profile-status">
                        <span class="status-dot"></span>
                        <?= htmlspecialchars($estado) ?>
                    </span>

                </div>

            </section>


            <!-- =================================================
                 INFORMACIÓN PERSONAL
                 ================================================= -->

            <section class="dashboard-section">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            INFORMACIÓN PERSONAL
                        </span>

                        <h2>
                            Datos personales
                        </h2>

                    </div>

                </div>


                <div class="profile-detail-grid">


                    <div class="profile-detail">

                        <span>Nombres</span>

                        <strong>
                            <?= htmlspecialchars($nombres) ?>
                        </strong>

                    </div>


                    <div class="profile-detail">

                        <span>Apellidos</span>

                        <strong>
                            <?= htmlspecialchars($apellidos) ?>
                        </strong>

                    </div>


                    <div class="profile-detail">

                        <span>DNI</span>

                        <strong>
                            <?= htmlspecialchars($dni) ?>
                        </strong>

                    </div>


                    <div class="profile-detail">

                        <span>Correo electrónico</span>

                        <strong>
                            <?= htmlspecialchars($correo) ?>
                        </strong>

                    </div>

                </div>

            </section>


            <!-- =================================================
                 INFORMACIÓN INSTITUCIONAL
                 ================================================= -->

            <section class="dashboard-section">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            INFORMACIÓN INSTITUCIONAL
                        </span>

                        <h2>
                            Dirección y cargo
                        </h2>

                    </div>

                </div>


                <div class="profile-detail-grid">


                    <div class="profile-detail profile-detail-wide">

                        <span>Dirección</span>

                        <strong>
                            <?= htmlspecialchars($nombre_area) ?>
                        </strong>

                    </div>


                    <div class="profile-detail">

                        <span>Rol</span>

                        <strong>
                            <?= htmlspecialchars($nombre_rol) ?>
                        </strong>

                    </div>


                    <div class="profile-detail profile-detail-wide">

                        <span>Programa interno</span>

                        <strong>
                            <?= htmlspecialchars($nombre_programa) ?>
                        </strong>

                    </div>

                </div>

            </section>


            <!-- =================================================
                 INFORMACIÓN DE CUENTA
                 ================================================= -->

            <section class="dashboard-section profile-section-last">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            INFORMACIÓN DE CUENTA
                        </span>

                        <h2>
                            Datos de acceso
                        </h2>

                    </div>

                </div>


                <div class="profile-detail-grid">


                    <div class="profile-detail">

                        <span>Nombre de usuario</span>

                        <strong>
                            <?= htmlspecialchars($nombre_usuario) ?>
                        </strong>

                    </div>


                    <div class="profile-detail">

                        <span>Estado de la cuenta</span>

                        <strong class="account-active">
                            <?= htmlspecialchars($estado) ?>
                        </strong>

                    </div>


                    <div class="profile-detail">

                        <span>Cuenta creada</span>

                        <strong>
                            <?= htmlspecialchars($fecha_creacion) ?>
                        </strong>

                    </div>


                    <div class="profile-detail">

                        <span>Último ingreso</span>

                        <strong>
                            <?= htmlspecialchars($ultimo_ingreso) ?>
                        </strong>

                    </div>

                </div>

            </section>

        </div>

    </main>

</div>

</body>

</html>