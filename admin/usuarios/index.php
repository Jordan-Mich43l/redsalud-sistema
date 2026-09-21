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
| Notificaciones no leídas
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
| Filtros
|--------------------------------------------------------------------------
*/

$buscar = trim($_GET["buscar"] ?? "");
$rol = (int) ($_GET["id_rol"] ?? 0);
$estado = $_GET["estado"] ?? "";

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
| Consulta de usuarios
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id_usuario,
        u.dni,
        u.nombres,
        u.apellidos,
        u.correo,
        u.estado,
        u.id_area,
        u.id_programa,
        u.id_rol,
        a.nombre_area,
        p.nombre_programa,
        r.nombre_rol,
        c.nombre_usuario
    FROM usuarios u
    INNER JOIN roles r
        ON r.id_rol = u.id_rol
    LEFT JOIN areas a
        ON a.id_area = u.id_area
    LEFT JOIN programas_internos p
        ON p.id_programa = u.id_programa
    LEFT JOIN credenciales_acceso c
        ON c.id_usuario = u.id_usuario
    WHERE 1 = 1
";

$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Buscar
|--------------------------------------------------------------------------
*/

if ($buscar !== "") {

    $sql .= "
        AND (
            u.dni LIKE ?
            OR u.nombres LIKE ?
            OR u.apellidos LIKE ?
            OR u.correo LIKE ?
            OR c.nombre_usuario LIKE ?
        )
    ";

    $q = "%" . $buscar . "%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $q;
    }

    $types .= "sssss";
}

/*
|--------------------------------------------------------------------------
| Filtrar por rol
|--------------------------------------------------------------------------
*/

if ($rol > 0) {

    $sql .= " AND u.id_rol = ?";

    $params[] = $rol;
    $types .= "i";
}

/*
|--------------------------------------------------------------------------
| Filtrar por estado
|--------------------------------------------------------------------------
*/

if (
    $estado === "1" ||
    $estado === "0"
) {

    $sql .= " AND u.estado = ?";

    $params[] = (int) $estado;
    $types .= "i";
}

$sql .= "
    ORDER BY
        u.apellidos,
        u.nombres
";

/*
|--------------------------------------------------------------------------
| Ejecutar consulta
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

if ($types !== "") {
    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$res = $stmt->get_result();

$usuarios = [];

while ($row = $res->fetch_assoc()) {
    $usuarios[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Usuarios | RedSalud</title>

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
                    Usuarios
                </h1>

                <p>
                    Administración de cuentas, roles y accesos
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

            <!-- =================================================
                 MENSAJES
            ================================================== -->

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

            <!-- =================================================
                 FILTROS
            ================================================== -->

            <section class="admin-card">

                <div class="admin-toolbar">

                    <div>

                        <span class="section-kicker">
                            SEGURIDAD
                        </span>

                        <h2>
                            Usuarios registrados
                        </h2>

                    </div>

                    <a
                        class="admin-btn admin-btn-primary"
                        href="crear.php"
                    >
                        ＋ Nuevo usuario
                    </a>

                </div>

                <form
                    class="admin-filters"
                    method="GET"
                >

                    <div class="admin-field">

                        <label for="buscar">
                            Buscar
                        </label>

                        <input
                            id="buscar"
                            name="buscar"
                            value="<?= admin_h($buscar) ?>"
                            placeholder="DNI, nombre, correo o usuario"
                        >

                    </div>

                    <div class="admin-field">

                        <label for="id_rol">
                            Rol
                        </label>

                        <select
                            id="id_rol"
                            name="id_rol"
                        >

                            <option value="0">
                                Todos
                            </option>

                            <?php foreach ($roles as $rol_item): ?>

                                <option
                                    value="<?= $rol_item["id_rol"] ?>"
                                    <?= (
                                        $rol ===
                                        (int) $rol_item["id_rol"]
                                    )
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    <?= admin_h(
                                        $rol_item["nombre_rol"]
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="admin-field">

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
                                value="1"
                                <?= $estado === "1"
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                Activos
                            </option>

                            <option
                                value="0"
                                <?= $estado === "0"
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                Inactivos
                            </option>

                        </select>

                    </div>

                    <div class="admin-actions">

                        <button
                            class="admin-btn admin-btn-primary"
                            type="submit"
                        >
                            Filtrar
                        </button>

                        <a
                            class="admin-btn"
                            href="index.php"
                        >
                            Limpiar
                        </a>

                    </div>

                </form>

            </section>

            <!-- =================================================
                 TABLA
            ================================================== -->

            <section class="admin-card">

                <?php if ($usuarios): ?>

                    <div class="admin-table-wrap">

                        <table class="admin-table">

                            <thead>

                                <tr>

                                    <th>
                                        Usuario
                                    </th>

                                    <th>
                                        Persona
                                    </th>

                                    <th>
                                        Dirección / programa
                                    </th>

                                    <th>
                                        Rol
                                    </th>

                                    <th>
                                        Estado
                                    </th>

                                    <th>
                                        Acciones
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($usuarios as $usuario): ?>

                                    <tr>

                                        <td>

                                            <strong>
                                                <?= admin_h(
                                                    $usuario["nombre_usuario"]
                                                    ?? "Sin cuenta"
                                                ) ?>
                                            </strong>

                                            <br>

                                            <span class="admin-muted">
                                                <?= admin_h(
                                                    $usuario["correo"]
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <?= admin_h(
                                                $usuario["nombres"] .
                                                " " .
                                                $usuario["apellidos"]
                                            ) ?>

                                            <br>

                                            <span class="admin-muted">
                                                DNI
                                                <?= admin_h(
                                                    $usuario["dni"]
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <?= admin_h(
                                                $usuario["nombre_area"]
                                                ?? "Sin área"
                                            ) ?>

                                            <br>

                                            <span class="admin-muted">
                                                <?= admin_h(
                                                    $usuario["nombre_programa"]
                                                    ?? "Sin programa"
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span class="admin-badge admin-badge-info">
                                                <?= admin_h(
                                                    $usuario["nombre_rol"]
                                                ) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span
                                                class="admin-badge
                                                <?= $usuario["estado"]
                                                    ? "admin-badge-success"
                                                    : "admin-badge-danger"
                                                ?>"
                                            >
                                                <?= $usuario["estado"]
                                                    ? "Activo"
                                                    : "Inactivo"
                                                ?>
                                            </span>

                                        </td>

                                        <td>

                                            <div class="admin-row-actions">

                                                <a
                                                    class="admin-btn admin-btn-small"
                                                    href="editar.php?id=<?= $usuario["id_usuario"] ?>"
                                                >
                                                    Editar
                                                </a>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="admin-empty">
                        No se encontraron usuarios.
                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</div>

</body>
</html>