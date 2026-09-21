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
| VERIFICAR ADMINISTRADOR
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        u.id_usuario,
        u.nombres,
        u.apellidos,
        u.correo,
        u.id_area,
        u.id_programa,
        u.id_rol,
        u.estado,
        r.nombre_rol
    FROM usuarios u
    LEFT JOIN roles r
        ON r.id_rol = u.id_rol
    WHERE u.id_usuario = ?
    LIMIT 1
");

if (!$stmt) {
    header("Location: index.php");
    exit;
}

$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$admin_usuario = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (
    !$admin_usuario ||
    (int) $admin_usuario["estado"] !== 1 ||
    (int) $admin_usuario["id_rol"] !== 1
) {
    session_destroy();

    header("Location: ../../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| FUNCIONES
|--------------------------------------------------------------------------
*/

function admin_h($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function admin_initials(string $name): string
{
    $initials = "";

    foreach (preg_split('/\s+/', trim($name)) as $part) {

        if ($part !== "") {
            $initials .= strtoupper(
                substr($part, 0, 1)
            );
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return substr($initials, 0, 2);
}


/*
|--------------------------------------------------------------------------
| ID DEL ÁREA
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {

    $_SESSION["mensaje_error"] =
        "Dirección no válida.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| OBTENER ÁREA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id_area,
        nombre_area,
        tipo_mesa_partes
    FROM areas
    WHERE id_area = ?
    LIMIT 1
");

if (!$stmt) {

    $_SESSION["mensaje_error"] =
        "No se pudo consultar el área.";

    header("Location: index.php");
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();

$area = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$area) {

    $_SESSION["mensaje_error"] =
        "El área no existe.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| MENSAJES
|--------------------------------------------------------------------------
*/

$mensaje_error =
    $_SESSION["mensaje_error"] ?? "";

unset($_SESSION["mensaje_error"]);


/*
|--------------------------------------------------------------------------
| DATOS DEL ADMINISTRADOR
|--------------------------------------------------------------------------
*/

$nombre_admin = trim(
    $admin_usuario["nombres"] . " " .
    $admin_usuario["apellidos"]
);

$iniciales_admin =
    admin_initials($nombre_admin);

$correo_admin =
    $admin_usuario["correo"] ?? "";


/*
|--------------------------------------------------------------------------
| NOTIFICACIONES
|--------------------------------------------------------------------------
*/

$notificaciones_no_leidas = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM notificaciones
    WHERE id_usuario = ?
      AND leido = 0
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $id_usuario
    );

    $stmt->execute();

    $resultado =
        $stmt->get_result()->fetch_assoc();

    $notificaciones_no_leidas =
        (int) ($resultado["total"] ?? 0);

    $stmt->close();
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

    <title>Editar área | RedSalud</title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../../assets/css/admin.css"
    >

</head>

<body>

<div class="app-layout">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="sidebar-header">

            <div class="brand-mark">

                <img
                    src="../../assets/img/icon_redsalud.png"
                    alt="RedSalud"
                >

            </div>

            <div class="brand-text">

                <strong>
                    RedSalud
                </strong>

                <span>
                    Gestión Documentaria
                </span>

            </div>

        </div>


        <nav
            class="sidebar-nav"
            aria-label="Navegación principal"
        >

            <div class="nav-title">
                MENÚ PRINCIPAL
            </div>


            <a
                href="../dashboard.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_inicio.png" alt="">
                </span>
                <span>Inicio</span>
            </a>


            <a
                href="../usuarios/index.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_usuarios.png" alt="">
                </span>
                <span>Usuarios</span>
            </a>


            <a
                href="index.php"
                class="nav-item active"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_direcciones.png" alt="">
                </span>
                <span>Direcciones</span>
            </a>


            <a
                href="../programas/index.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_programas.png" alt="">
                </span>
                <span>Programas</span>
            </a>


            <a
                href="../tipos_documento/index.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_tipos_documento.png" alt="">
                </span>
                <span>Tipos de documento</span>
            </a>


            <div class="nav-title">
                SUPERVISIÓN
            </div>


            <a
                href="../expedientes/index.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_expedientes.png" alt="">
                </span>
                <span>Expedientes</span>
            </a>


            <a
                href="../movimientos/historial.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_movimientos.png" alt="">
                </span>
                <span>Movimientos</span>
            </a>


            <a
                href="../notificaciones/index.php"
                class="nav-item"
            >

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


            <a
                href="../perfil/index.php"
                class="nav-item"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_perfil.png" alt="">
                </span>
                <span>Mi perfil</span>
            </a>

        </nav>


        <div class="sidebar-footer">

            <div class="sidebar-user">

                <div class="avatar avatar-small">
                    <?= admin_h($iniciales_admin) ?>
                </div>

                <div class="sidebar-user-data">

                    <strong>
                        <?= admin_h($nombre_admin) ?>
                    </strong>

                    <span>
                        Administrador
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


    <!-- =====================================================
         CONTENIDO PRINCIPAL
    ====================================================== -->

    <main class="main-content">

        <header class="main-header">

            <div class="header-title">

                <span class="eyebrow">
                    RED DE SALUD AGUAYTÍA
                </span>

                <h1>
                    Editar área
                </h1>

                <p>
                    Actualizar los datos del área registrada.
                </p>

            </div>


            <div class="header-user">

                <div class="avatar">
                    <?= admin_h($iniciales_admin) ?>
                </div>

                <div class="header-user-data">

                    <strong>
                        <?= admin_h($nombre_admin) ?>
                    </strong>

                    <span>
                        <?= admin_h($correo_admin) ?>
                    </span>

                </div>

            </div>

        </header>


        <div class="dashboard-content">


            <?php if ($mensaje_error): ?>

                <div class="admin-alert admin-alert-error">
                    <?= admin_h($mensaje_error) ?>
                </div>

            <?php endif; ?>


            <!-- =================================================
                 TARJETA EDITAR ÁREA
            ================================================== -->

            <section class="admin-card">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            ÁREA #<?= (int) $area["id_area"] ?>
                        </span>

                        <h2>
                            Editar datos
                        </h2>

                    </div>

                </div>


                <form
                    method="POST"
                    action="actualizar.php"
                >

                    <input
                        type="hidden"
                        name="id_area"
                        value="<?= (int) $area["id_area"] ?>"
                    >


                    <div class="admin-form-grid">


                        <!-- NOMBRE DEL ÁREA -->

                        <div class="admin-field full">

                            <label for="nombre_area">
                                Nombre del área *
                            </label>

                            <input
                                type="text"
                                id="nombre_area"
                                name="nombre_area"
                                maxlength="150"
                                value="<?= admin_h($area["nombre_area"]) ?>"
                                required
                            >

                        </div>


                        <!-- TIPO DE MESA -->

                        <div class="admin-field">

                            <label for="tipo_mesa_partes">
                                Tipo de mesa de partes *
                            </label>

                            <select
                                id="tipo_mesa_partes"
                                name="tipo_mesa_partes"
                                required
                            >

                                <?php
                                $tipos_area = [
                                    "GENERAL",
                                    "INTERNA",
                                    "NINGUNA"
                                ];
                                ?>

                                <?php foreach ($tipos_area as $tipo): ?>

                                    <option
                                        value="<?= admin_h($tipo) ?>"
                                        <?= $area["tipo_mesa_partes"] === $tipo
                                            ? "selected"
                                            : "" ?>
                                    >
                                        <?= admin_h($tipo) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <small>
                                Indica la participación del área
                                en la mesa de partes.
                            </small>

                        </div>

                    </div>


                    <!-- ACCIONES -->

                    <div class="admin-form-footer">

                        <a
                            class="admin-btn"
                            href="index.php"
                        >
                            Cancelar
                        </a>

                        <button
                            class="admin-btn admin-btn-primary"
                            type="submit"
                        >
                            Guardar cambios
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>

</div>

</body>
</html>