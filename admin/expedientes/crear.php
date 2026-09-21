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
$correo = $usuario["correo"] ?? "";

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
| TIPOS DE DOCUMENTO
|--------------------------------------------------------------------------
*/

$sqlTipos = "
    SELECT
        id_tipo_documento,
        nombre_tipo
    FROM tipos_documento
    ORDER BY nombre_tipo ASC
";

$resultadoTipos = $conn->query($sqlTipos);

$tipos_documento = [];

if ($resultadoTipos) {

    while ($fila = $resultadoTipos->fetch_assoc()) {
        $tipos_documento[] = $fila;
    }

}

/*
|--------------------------------------------------------------------------
| ÁREAS
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

    <title>Nuevo expediente | RedSalud</title>

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
                    Registro y gestión de documentos institucionales.
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
                 CABECERA DE LA PÁGINA
                 ================================================= -->

            <section class="welcome-card">
                <div class="welcome-content">
                    <span class="welcome-label">
                        GESTIÓN DOCUMENTAL
                    </span>

                    <h2>
                        Nuevo expediente
                    </h2>

                    <p>
                        Registra un nuevo documento para iniciar su seguimiento
                        dentro del sistema.
                    </p>
                </div>

                <div class="welcome-decoration"></div>
            </section>

            <!-- =================================================
                 FORMULARIO
                 ================================================= -->

            <form
                id="formCrearExpediente"
                action="guardar.php"
                method="POST"
                enctype="multipart/form-data"
                class="expediente-form"
            >

                <!-- =============================================
                     INFORMACIÓN DEL DOCUMENTO
                     ============================================= -->

                <section class="expediente-form-card">

                    <div class="expediente-card-header">

                        <div>

                            <span class="section-kicker">
                                INFORMACIÓN DEL DOCUMENTO
                            </span>

                            <h3>
                                Datos principales
                            </h3>

                        </div>

                    </div>

                    <div class="expediente-form-grid">

                        <!-- ORIGEN -->

                        <div class="form-group">

                            <label for="origen_documento">
                                Origen
                            </label>

                            <select
                                id="origen_documento"
                                name="origen_documento"
                                required
                            >

                                <option value="">
                                    Seleccionar origen
                                </option>

                                <option value="EXTERNO">
                                    Externo
                                </option>

                                <option value="INTERNO">
                                    Interno
                                </option>

                            </select>

                            <small>
                                Indica si el documento proviene del exterior
                                o de la institución.
                            </small>

                        </div>

                        <!-- TIPO -->

                        <div class="form-group">

                            <label for="id_tipo_documento">
                                Tipo de documento
                            </label>

                            <select
                                id="id_tipo_documento"
                                name="id_tipo_documento"
                                required
                            >

                                <option value="">
                                    Seleccionar tipo
                                </option>

                                <?php foreach ($tipos_documento as $tipo): ?>

                                    <option
                                        value="<?= (int) $tipo["id_tipo_documento"] ?>"
                                    >
                                        <?= htmlspecialchars($tipo["nombre_tipo"]) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- DIRECCIÓN EMISORA -->

                        <div
                            class="form-group form-group-full"
                            id="areaEmisoraGroup"
                        >

                            <label for="id_area_emisora">
                                Dirección emisora
                            </label>

                            <select
                                id="id_area_emisora"
                                name="id_area_emisora"
                            >

                                <option value="">
                                    Seleccionar dirección emisora
                                </option>

                                <?php foreach ($areas as $area): ?>

                                    <option
                                        value="<?= (int) $area["id_area"] ?>"
                                    >
                                        <?= htmlspecialchars($area["nombre_area"]) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <small>
                                Este campo corresponde únicamente a documentos internos.
                            </small>

                        </div>

                        <!-- REMITENTE -->

                        <div class="form-group form-group-full">

                            <label for="remitente">
                                Remitente
                            </label>

                            <input
                                type="text"
                                id="remitente"
                                name="remitente"
                                maxlength="255"
                                placeholder="Nombre de la persona, entidad o área del remitente"
                                required
                            >

                        </div>

                        <!-- ASUNTO -->

                        <div class="form-group form-group-full">

                            <label for="asunto">
                                Asunto
                            </label>

                            <textarea
                                id="asunto"
                                name="asunto"
                                rows="4"
                                maxlength="500"
                                placeholder="Describe brevemente el asunto del expediente..."
                                required
                            ></textarea>

                            <small>
                                Ingresa una descripción clara y concisa.
                            </small>

                        </div>

                        <!-- FOLIOS -->

                        <div class="form-group">

                            <label for="numero_folios">
                                Número de folios
                            </label>

                            <input
                                type="number"
                                id="numero_folios"
                                name="numero_folios"
                                min="1"
                                max="9999"
                                placeholder="Ej. 15"
                                required
                            >

                        </div>

                        <!-- FECHA LÍMITE -->

                        <div class="form-group">

                            <label for="fecha_limite_atencion">
                                Fecha límite de atención
                            </label>

                            <input
                                type="datetime-local"
                                id="fecha_limite_atencion"
                                name="fecha_limite_atencion"
                                required
                            >

                        </div>

                    </div>

                </section>

                <!-- =================================================
                    DOCUMENTO PRINCIPAL
                    ================================================= -->

                <section class="expediente-form-card">

                    <div class="expediente-card-header">

                        <div>

                            <span class="section-kicker">
                                DOCUMENTO PRINCIPAL
                            </span>

                            <h3>
                                Archivo del expediente
                            </h3>

                            <p>
                                Adjunta el documento principal en formato PDF, si se encuentra disponible.
                                Este campo es opcional y no es necesario para registrar el expediente.
                            </p>

                        </div>

                    </div>

                    <div class="file-upload-group">

                        <label
                            for="archivo_principal_pdf"
                            class="file-upload-box"
                        >

                            <span class="file-upload-icon">
                                ⬆
                            </span>

                            <span class="file-upload-title">
                                Seleccionar documento PDF
                            </span>

                            <span class="file-upload-text">
                                Haz clic para seleccionar el archivo
                            </span>

                        </label>

                        <input
                            type="file"
                            id="archivo_principal_pdf"
                            name="archivo_principal_pdf"
                            accept="application/pdf,.pdf"
                        >

                        <div
                            id="selectedFile"
                            class="selected-file"
                            hidden
                        >

                            <span class="selected-file-icon">
                                PDF
                            </span>

                            <div>

                                <strong id="selectedFileName">
                                    -
                                </strong>

                                <span id="selectedFileSize">
                                    -
                                </span>

                            </div>

                        </div>

                        <div 
                            id="errorArchivo"
                            class="form-error"
                            style="display: none;
                            margin-top: 8px;
                            color: var(--danger);
                            font-size: 0.75rem;"
                        >
                            El archivo debe ser un PDF válido (opcional).
                        </div>

                    </div>

                </section>

                <!-- =================================================
                ACCIONES
                ================================================= -->

                <div class="expediente-form-actions" style="flex-wrap: wrap; gap: 12px; align-items: center;">

                    <a href="index.php" class="btn-secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="btn-primary" id="btnGuardarExpediente">
                        Guardar expediente
                    </button>

                </div>

                            </form>

                        </div>

                    </main>

                </div>

<script src="../../assets/js/app.js"></script>

</body>

</html>