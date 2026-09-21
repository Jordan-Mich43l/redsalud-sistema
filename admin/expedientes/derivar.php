<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];
$id_expediente = (int) ($_GET["id"] ?? 0);


/*
|--------------------------------------------------------------------------
| VALIDAR EXPEDIENTE
|--------------------------------------------------------------------------
*/

if ($id_expediente <= 0) {
    header("Location: index.php");
    exit;
}


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
$correo = $usuario["correo"];

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
| DATOS DEL EXPEDIENTE
|--------------------------------------------------------------------------
*/

$sqlExpediente = "
    SELECT
        e.id_expediente,
        e.numero_expediente,
        e.origen_documento,
        e.remitente,
        e.asunto,
        e.numero_folios,
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
$stmtExpediente->bind_param("i", $id_expediente);
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
| ÁREAS DISPONIBLES
|--------------------------------------------------------------------------
|
| No mostramos el área actual como destino.
|--------------------------------------------------------------------------
*/

$sqlAreas = "
    SELECT
        id_area,
        nombre_area
    FROM areas
    WHERE id_area <> ?
    ORDER BY nombre_area ASC
";

$stmtAreas = $conn->prepare($sqlAreas);
$stmtAreas->bind_param("i", $usuario["id_area"]);
$stmtAreas->execute();

$resultadoAreas = $stmtAreas->get_result();

$areas = [];

while ($fila = $resultadoAreas->fetch_assoc()) {
    $areas[] = $fila;
}

$stmtAreas->close();


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

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Derivar expediente | RedSalud</title>

    <link
        rel="stylesheet"
        href="../../assets/css/dashboard.css"
    >

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
                    Derivar expediente
                </h1>

                <p>
                    Envía el expediente al área correspondiente
                    para continuar con su atención.
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
                 PRESENTACIÓN
                 ================================================= -->

            <section class="welcome-card">

                <div class="welcome-content">

                    <span class="welcome-label">
                        GESTIÓN DOCUMENTAL
                    </span>

                    <h2>
                        Derivar expediente
                    </h2>

                    <p>
                        Selecciona el área que deberá recibir
                        el expediente para continuar con su trámite.
                    </p>

                </div>

                <div class="welcome-decoration"></div>

            </section>


            <!-- =================================================
                 INFORMACIÓN DEL EXPEDIENTE
                 ================================================= -->

            <section class="dashboard-section">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            EXPEDIENTE
                        </span>

                        <h2>
                            Información del documento
                        </h2>

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
                            Estado actual
                        </span>

                        <strong>

                            <?php

                            $estado = $expediente["estado_expediente"] ?? "";

                            $claseEstado = match (strtoupper($estado)) {

                                "EN_TRAMITE",
                                "EN TRÁMITE"
                                    => "status-warning",

                                "ATENDIDO",
                                "FINALIZADO"
                                    => "status-success",

                                "OBSERVADO",
                                "RECHAZADO"
                                    => "status-danger",

                                default
                                    => "status-neutral",

                            };

                            ?>

                            <span class="status <?= $claseEstado ?>">
                                <?= htmlspecialchars(
                                    str_replace("_", " ", $estado)
                                ) ?>
                            </span>

                        </strong>

                    </div>


                    <div class="detail-item detail-item-wide">

                        <span>
                            Asunto
                        </span>

                        <strong>
                            <?= htmlspecialchars(
                                $expediente["asunto"]
                            ) ?>
                        </strong>

                    </div>

                </div>

            </section>


            <!-- =================================================
                 FORMULARIO DE DERIVACIÓN
                 ================================================= -->

            <section class="dashboard-section">

                <div class="section-header">

                    <div>

                        <span class="section-kicker">
                            DESTINO
                        </span>

                        <h2>
                            Dirección receptora
                        </h2>

                    </div>

                </div>


                <form
                    action="guardar_derivar.php"
                    method="POST"
                    class="expediente-form"
                >

                    <input
                        type="hidden"
                        name="id_expediente"
                        value="<?= $id_expediente ?>"
                    >


                    <div class="filter-group">

                        <label for="area_destino">
                            Dirección de destino
                        </label>

                        <select
                            id="area_destino"
                            name="area_destino"
                            required
                        >

                            <option value="">
                                Seleccione un área
                            </option>

                            <?php foreach ($areas as $area): ?>

                                <option
                                    value="<?= (int) $area["id_area"] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $area["nombre_area"]
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="filter-group">

                        <label for="observacion">
                            Observación o indicación
                        </label>

                        <textarea
                            id="observacion"
                            name="observacion"
                            rows="4"
                            maxlength="500"
                            placeholder="Ingrese una indicación para el área receptora..."
                        ></textarea>

                    </div>

                    <div class="expediente-form-actions">

                        <a href="ver.php?id=<?= (int) $id_expediente ?>" class="btn-secondary">
                            Cancelar
                        </a>

                        <button type="submit" class="btn-primary">
                            Derivar expediente
                        </button>

                    </div>

                </form>

            </section>


        </div>

    </main>

</div>


<script src="../../assets/js/app.js"></script>

</body>

</html>