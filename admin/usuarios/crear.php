<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

/*
|--------------------------------------------------------------------------
| Verificar que el usuario sea administrador
|--------------------------------------------------------------------------
*/

$id_usuario = (int) $_SESSION["id_usuario"];

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
    LEFT JOIN roles r ON r.id_rol = u.id_rol
    WHERE u.id_usuario = ?
    LIMIT 1
");

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
| Funciones auxiliares
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

    foreach (
        preg_split('/\s+/', trim($name))
        as $part
    ) {
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
| Mensajes
|--------------------------------------------------------------------------
*/

$mensaje_exito = $_SESSION["mensaje_exito"] ?? "";
$mensaje_error = $_SESSION["mensaje_error"] ?? "";

unset(
    $_SESSION["mensaje_exito"],
    $_SESSION["mensaje_error"]
);

/*
|--------------------------------------------------------------------------
| Información del administrador
|--------------------------------------------------------------------------
*/

$nombre_admin = trim(
    $admin_usuario["nombres"] . " " .
    $admin_usuario["apellidos"]
);

$iniciales_admin = admin_initials($nombre_admin);

/*
|--------------------------------------------------------------------------
| Notificaciones
|--------------------------------------------------------------------------
*/

$notificaciones_no_leidas = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM notificaciones
    WHERE id_usuario = ?
      AND leido = 0
");

$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$resultado = $stmt->get_result()->fetch_assoc();

$notificaciones_no_leidas =
    (int) ($resultado["total"] ?? 0);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
*/

$roles = [];

$resultado_roles = $conn->query("
    SELECT
        id_rol,
        nombre_rol
    FROM roles
    ORDER BY id_rol
");

while ($row = $resultado_roles->fetch_assoc()) {
    $roles[] = $row;
}

/*
|--------------------------------------------------------------------------
| Direcciones
|--------------------------------------------------------------------------
*/

$areas = [];

$resultado_areas = $conn->query("
    SELECT
        id_area,
        nombre_area
    FROM areas
    ORDER BY nombre_area
");

while ($row = $resultado_areas->fetch_assoc()) {
    $areas[] = $row;
}

/*
|--------------------------------------------------------------------------
| Programas
|--------------------------------------------------------------------------
*/

$programas = [];

$resultado_programas = $conn->query("
    SELECT
        id_programa,
        id_area,
        nombre_programa,
        codigo_programa
    FROM programas_internos
    ORDER BY nombre_programa
");

while ($row = $resultado_programas->fetch_assoc()) {
    $programas[] = $row;
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

    <title>Nuevo usuario | RedSalud</title>

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
                <strong>RedSalud</strong>
                <span>Gestión Documentaria</span>
            </div>

        </div>

        <nav
            class="sidebar-nav"
            aria-label="Navegación principal"
        >

            <div class="nav-title">
                ADMINISTRACIÓN
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
                href="index.php"
                class="nav-item active"
            >
                <span class="nav-icon">
                    <img src="../../assets/img/icon_usuarios.png" alt="">
                </span>
                <span>Usuarios</span>
            </a>

            <a
                href="../areas/index.php"
                class="nav-item"
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

        <!-- =================================================
             USUARIO
        ================================================== -->

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
                        <?= admin_h(
                            $admin_usuario["nombre_rol"]
                            ?? "Administrador"
                        ) ?>
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
                    Nuevo usuario
                </h1>

                <p>
                    Crear una cuenta y asignar sus permisos
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
                        <?= admin_h(
                            $admin_usuario["correo"]
                        ) ?>
                    </span>

                </div>

            </div>

        </header>

        <div class="dashboard-content">

            <?php if ($mensaje_exito): ?>

                <div class="admin-alert admin-alert-success">
                    <?= admin_h($mensaje_exito) ?>
                </div>

            <?php endif; ?>

            <?php if ($mensaje_error): ?>

                <div class="admin-alert admin-alert-error">
                    <?= admin_h($mensaje_error) ?>
                </div>

            <?php endif; ?>

            <section class="admin-card">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            USUARIOS
                        </span>

                        <h2>
                            Datos personales y acceso
                        </h2>

                    </div>

                </div>

                <form
                    method="POST"
                    action="guardar.php"
                >

                    <div class="admin-form-grid">

                        <div class="admin-field">

                            <label for="dni">
                                DNI *
                            </label>

                            <input
                                id="dni"
                                name="dni"
                                maxlength="8"
                                minlength="8"
                                pattern="[0-9]{8}"
                                required
                                inputmode="numeric"
                            >

                        </div>

                        <div class="admin-field">

                            <label for="correo">
                                Correo *
                            </label>

                            <input
                                id="correo"
                                type="email"
                                name="correo"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="admin-field">

                            <label for="nombres">
                                Nombres *
                            </label>

                            <input
                                id="nombres"
                                name="nombres"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="admin-field">

                            <label for="apellidos">
                                Apellidos *
                            </label>

                            <input
                                id="apellidos"
                                name="apellidos"
                                maxlength="100"
                                required
                            >

                        </div>

                        <div class="admin-field">

                            <label for="id_area">
                                Dirección *
                            </label>

                            <select
                                id="id_area"
                                name="id_area"
                                required
                            >

                                <option value="">
                                    Seleccione
                                </option>

                                <?php foreach ($areas as $area): ?>

                                    <option
                                        value="<?= $area["id_area"] ?>"
                                    >
                                        <?= admin_h(
                                            $area["nombre_area"]
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="admin-field">

                            <label for="id_programa">
                                Programa
                            </label>

                            <select
                                id="id_programa"
                                name="id_programa"
                            >

                                <option value="">
                                    Sin programa
                                </option>

                                <?php foreach ($programas as $programa): ?>

                                    <option
                                        value="<?= $programa["id_programa"] ?>"
                                        data-area="<?= $programa["id_area"] ?>"
                                    >
                                        <?= admin_h(
                                            $programa["nombre_programa"]
                                        ) ?>

                                        (<?= admin_h(
                                            $programa["codigo_programa"]
                                        ) ?>)

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <small>
                                Opcional. Debe pertenecer al área seleccionada.
                            </small>

                        </div>

                        <div class="admin-field">

                            <label for="id_rol">
                                Rol *
                            </label>

                            <select
                                id="id_rol"
                                name="id_rol"
                                required
                            >

                                <option value="">
                                    Seleccione
                                </option>

                                <?php foreach ($roles as $rol): ?>

                                    <option
                                        value="<?= $rol["id_rol"] ?>"
                                    >
                                        <?= admin_h(
                                            $rol["nombre_rol"]
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="admin-field">

                            <label for="nombre_usuario">
                                Nombre de usuario *
                            </label>

                            <input
                                id="nombre_usuario"
                                name="nombre_usuario"
                                maxlength="50"
                                required
                                autocomplete="username"
                            >

                        </div>

                        <div class="admin-field">

                            <label for="password">
                                Contraseña *
                            </label>

                            <input
                                id="password"
                                type="password"
                                name="password"
                                minlength="8"
                                maxlength="72"
                                required
                                autocomplete="new-password"
                            >

                            <small>
                                Se almacena mediante hash seguro.
                            </small>

                        </div>

                    </div>

                    <div class="admin-form-footer">

                        <a
                            class="admin-btn"
                            href="index.php"
                        >
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            class="admin-btn admin-btn-primary"
                        >
                            Crear usuario
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>

</div>

<script>

const area = document.getElementById("id_area");
const programa = document.getElementById("id_programa");

function filtrarProgramas() {

    const idArea = area.value;

    for (const opcion of programa.options) {

        if (!opcion.value) {
            opcion.hidden = false;
            continue;
        }

        opcion.hidden =
            !!idArea &&
            opcion.dataset.area !== idArea;

        if (
            opcion.hidden &&
            opcion.selected
        ) {
            programa.value = "";
        }
    }
}

area.addEventListener(
    "change",
    filtrarProgramas
);

filtrarProgramas();

</script>

</body>
</html>