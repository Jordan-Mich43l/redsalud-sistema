<?php
session_start();

/* |--------------------------------------------------------------------------
   | VALIDAR SESIÓN
   |-------------------------------------------------------------------------- */
if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_usuario = (int) $_SESSION["id_usuario"];

/* |--------------------------------------------------------------------------
   | VALIDAR ADMINISTRADOR
   |-------------------------------------------------------------------------- */
if (!isset($_SESSION["id_rol"]) || (int) $_SESSION["id_rol"] !== 1) {
    header("Location: ../../auth/login.php");
    exit;
}

/* |--------------------------------------------------------------------------
   | DATOS DEL ADMINISTRADOR
   |-------------------------------------------------------------------------- */
$stmtUsuario = $conn->prepare("
    SELECT u.nombres, u.apellidos, u.correo, r.nombre_rol
    FROM usuarios u
    LEFT JOIN roles r ON r.id_rol = u.id_rol
    WHERE u.id_usuario = ? AND u.id_rol = 1
    LIMIT 1
");
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

$nombre_completo = trim($usuario["nombres"] . " " . $usuario["apellidos"]);
$correo         = $usuario["correo"];
$nombre_rol     = $usuario["nombre_rol"] ?? "Administrador";

$iniciales = "";
foreach (preg_split('/\s+/', $nombre_completo) as $parte) {
    if ($parte !== "") {
        $iniciales .= strtoupper(substr($parte, 0, 1));
    }
}
$iniciales = substr($iniciales, 0, 2);

$mensaje_exito = "";
$mensaje_error = "";

/* |--------------------------------------------------------------------------
   | CONTADORES
   |-------------------------------------------------------------------------- */
$notificaciones_no_leidas = 0;
$sqlPendientes = "
    SELECT COUNT(*) AS total
    FROM notificaciones n
    INNER JOIN usuarios u ON u.id_usuario = n.id_usuario
    WHERE u.id_rol = 2 AND n.leido = 0
";
$resultadoPendientes = $conn->query($sqlPendientes);
if ($resultadoPendientes) {
    $filaPendientes = $resultadoPendientes->fetch_assoc();
    $notificaciones_no_leidas = (int) ($filaPendientes["total"] ?? 0);
}

$total_enviadas = 0;
$sqlTotal = "
    SELECT COUNT(*) AS total
    FROM notificaciones n
    INNER JOIN usuarios u ON u.id_usuario = n.id_usuario
    WHERE u.id_rol = 2
";
$resultadoTotal = $conn->query($sqlTotal);
if ($resultadoTotal) {
    $filaTotal = $resultadoTotal->fetch_assoc();
    $total_enviadas = (int) ($filaTotal["total"] ?? 0);
}

/* |--------------------------------------------------------------------------
   | PROCESAR FORMULARIO
   |-------------------------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";

    if ($accion === "enviar") {

        $destinatario   = $_POST["destinatario"] ?? "";
        $mensaje        = trim($_POST["mensaje"] ?? "");
        $tipo_vinculo   = $_POST["tipo_vinculo"] ?? "ninguno";
        $id_vinculo     = isset($_POST["id_vinculo"]) && $_POST["id_vinculo"] !== ""
                            ? (int) $_POST["id_vinculo"]
                            : 0;

        $id_expediente  = null;
        $etiqueta_extra = "";

        if ($mensaje === "") {
            $mensaje_error = "Debe escribir un mensaje.";
        } elseif (mb_strlen($mensaje) > 500) {
            $mensaje_error = "El mensaje no puede superar los 500 caracteres.";
        } else {

            /* Resolver el id_expediente según el tipo de vínculo */
            if ($tipo_vinculo === "expediente" && $id_vinculo > 0) {

                $stmt = $conn->prepare("
                    SELECT 
                        p.id_proveido,
                        m.id_expediente,
                        e.numero_expediente
                    FROM proveidos p
                    INNER JOIN movimientos_documento m
                        ON m.id_movimiento = p.id_movimiento
                    INNER JOIN expedientes e
                        ON e.id_expediente = m.id_expediente
                    WHERE p.id_proveido = ?
                    LIMIT 1
                ");
                $stmt->bind_param("i", $id_vinculo);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $id_expediente  = (int) $row["id_expediente"];
                    $etiqueta_extra = " [Expediente: " . $row["numero_expediente"] . "]";
                } else {
                    $mensaje_error = "El expediente seleccionado no existe.";
                }

            } elseif ($tipo_vinculo === "anexo" && $id_vinculo > 0) {

                $stmt = $conn->prepare("
                    SELECT 
                        a.id_anexo,
                        a.id_expediente,
                        e.numero_expediente
                    FROM anexos_expediente a
                    INNER JOIN expedientes e 
                        ON e.id_expediente = a.id_expediente
                    WHERE a.id_anexo = ?
                    LIMIT 1
                ");

                $stmt->bind_param("i", $id_vinculo);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $id_expediente  = (int) $row["id_expediente"];
                    $etiqueta_extra = " [Anexo #" . $row["id_anexo"] . " del expediente " . $row["numero_expediente"] . "]";
                } else {
                    $mensaje_error = "El anexo seleccionado no existe.";
                }

            } elseif ($tipo_vinculo === "proveido" && $id_vinculo > 0) {

                // Intentar estructura común de proveídos
                $stmt = $conn->prepare("
                    SELECT p.id_proveido, p.id_expediente, e.numero_expediente
                    FROM proveidos p
                    INNER JOIN expedientes e ON e.id_expediente = p.id_expediente
                    WHERE p.id_proveido = ?
                    LIMIT 1
                ");
                $stmt->bind_param("i", $id_vinculo);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $id_expediente  = (int) $row["id_expediente"];
                    $etiqueta_extra = " [Proveído #" . $row["id_proveido"] . " del expediente " . $row["numero_expediente"] . "]";
                } else {
                    $mensaje_error = "El proveído seleccionado no existe.";
                }
            }

            if ($mensaje_error === "") {

                // Añadir la etiqueta al mensaje para que quede claro en el historial
                if ($etiqueta_extra !== "") {
                    $mensaje = $mensaje . $etiqueta_extra;
                }

                if ($destinatario === "todos") {

                    $stmtUsuarios = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_rol = 2");
                    $stmtUsuarios->execute();
                    $resultadoUsuarios = $stmtUsuarios->get_result();

                    $cantidad_enviada = 0;

                    $stmtInsertar = $conn->prepare("
                        INSERT INTO notificaciones (
                            id_usuario, id_expediente, mensaje, leido, fecha_creacion
                        ) VALUES (?, ?, ?, 0, NOW())
                    ");

                    while ($filaUsuario = $resultadoUsuarios->fetch_assoc()) {
                        $id_destinatario = (int) $filaUsuario["id_usuario"];
                        $stmtInsertar->bind_param("iis", $id_destinatario, $id_expediente, $mensaje);
                        if ($stmtInsertar->execute()) {
                            $cantidad_enviada++;
                        }
                    }

                    $stmtInsertar->close();
                    $stmtUsuarios->close();

                    if ($cantidad_enviada > 0) {
                        $mensaje_exito = "La notificación fue enviada a " . $cantidad_enviada . " usuario(s) de Mesa de Partes.";
                        $total_enviadas += $cantidad_enviada;
                        $notificaciones_no_leidas += $cantidad_enviada;
                    } else {
                        $mensaje_error = "No se encontraron usuarios de Mesa de Partes.";
                    }

                } else {

                    $id_destinatario = (int) $destinatario;

                    if ($id_destinatario <= 0) {
                        $mensaje_error = "Debe seleccionar un destinatario.";
                    } else {

                        $stmtValidar = $conn->prepare("
                            SELECT id_usuario FROM usuarios
                            WHERE id_usuario = ? AND id_rol = 2 LIMIT 1
                        ");
                        $stmtValidar->bind_param("i", $id_destinatario);
                        $stmtValidar->execute();
                        $usuarioValido = $stmtValidar->get_result()->fetch_assoc();
                        $stmtValidar->close();

                        if (!$usuarioValido) {
                            $mensaje_error = "El usuario seleccionado no es válido.";
                        } else {

                            $stmtInsertar = $conn->prepare("
                                INSERT INTO notificaciones (
                                    id_usuario, id_expediente, mensaje, leido, fecha_creacion
                                ) VALUES (?, ?, ?, 0, NOW())
                            ");
                            $stmtInsertar->bind_param("iis", $id_destinatario, $id_expediente, $mensaje);

                            if ($stmtInsertar->execute()) {
                                $mensaje_exito = "La notificación fue enviada correctamente.";
                                $total_enviadas++;
                                $notificaciones_no_leidas++;
                            } else {
                                $mensaje_error = "No se pudo enviar la notificación.";
                            }
                            $stmtInsertar->close();
                        }
                    }
                }
            }
        }
    }
}

/* |--------------------------------------------------------------------------
   | USUARIOS DE MESA DE PARTES
   |-------------------------------------------------------------------------- */
$usuarios_mesa = [];
$stmtUsuariosMesa = $conn->prepare("
    SELECT u.id_usuario, u.nombres, u.apellidos, u.correo, a.nombre_area
    FROM usuarios u
    LEFT JOIN areas a ON a.id_area = u.id_area
    WHERE u.id_rol = 2
    ORDER BY u.apellidos ASC, u.nombres ASC
");
$stmtUsuariosMesa->execute();
$resultadoUsuariosMesa = $stmtUsuariosMesa->get_result();
while ($fila = $resultadoUsuariosMesa->fetch_assoc()) {
    $usuarios_mesa[] = $fila;
}
$stmtUsuariosMesa->close();

/* |--------------------------------------------------------------------------
   | LISTADOS PARA VINCULAR
   |-------------------------------------------------------------------------- */
$expedientes_recientes = [];
$res = $conn->query("
    SELECT id_expediente, numero_expediente
    FROM expedientes
    ORDER BY fecha_registro DESC
    LIMIT 40
");
if ($res) {
    while ($fila = $res->fetch_assoc()) {
        $expedientes_recientes[] = $fila;
    }
}

$anexos_recientes = [];

$res = $conn->query("
    SELECT 
        a.id_anexo,
        a.id_expediente,
        a.nombre_archivo,
        e.numero_expediente
    FROM anexos_expediente a
    INNER JOIN expedientes e 
        ON e.id_expediente = a.id_expediente
    ORDER BY a.id_anexo DESC
    LIMIT 40
");

if ($res) {
    while ($fila = $res->fetch_assoc()) {
        $anexos_recientes[] = $fila;
    }
}

$proveidos_recientes = [];

$res = $conn->query("
    SELECT 
        p.id_proveido,
        p.id_movimiento,
        m.id_expediente,
        e.numero_expediente
    FROM proveidos p
    INNER JOIN movimientos_documento m
        ON m.id_movimiento = p.id_movimiento
    INNER JOIN expedientes e
        ON e.id_expediente = m.id_expediente
    ORDER BY p.id_proveido DESC
    LIMIT 40
");

if ($res) {
    while ($fila = $res->fetch_assoc()) {
        $proveidos_recientes[] = $fila;
    }
}

/* |--------------------------------------------------------------------------
   | HISTORIAL
   |-------------------------------------------------------------------------- */
$notificaciones_enviadas = [];
$sqlNotificaciones = "
    SELECT 
        n.id_notificacion,
        n.id_expediente,
        n.mensaje,
        n.leido,
        n.fecha_creacion,
        u.nombres,
        u.apellidos,
        u.correo,
        e.numero_expediente
    FROM notificaciones n
    INNER JOIN usuarios u ON u.id_usuario = n.id_usuario
    LEFT JOIN expedientes e ON e.id_expediente = n.id_expediente
    WHERE u.id_rol = 2
    ORDER BY n.fecha_creacion DESC
    LIMIT 50
";
$resultadoNotificaciones = $conn->query($sqlNotificaciones);
if ($resultadoNotificaciones) {
    while ($fila = $resultadoNotificaciones->fetch_assoc()) {
        $notificaciones_enviadas[] = $fila;
    }
}

function formatearFechaNotificacion($fecha)
{
    if (empty($fecha)) return "Fecha no disponible";
    $timestamp = strtotime($fecha);
    if (!$timestamp) return "Fecha no disponible";
    return date("d/m/Y H:i", $timestamp);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones | RedSalud</title>

    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=5">
    <link rel="stylesheet" href="../../assets/css/notifications.css?v=5">
    <script src="../../assets/js/app.js?v=5" defer></script>
</head>
<body>
<div class="app-layout">

    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand-mark">
                <img src="../../assets/img/icon_redsalud.png" alt="RedSalud">
            </div>
            <div class="brand-text">
                <strong>RedSalud</strong>
                <span>Gestión Documentaria</span>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Navegación principal">
            <div class="nav-title">ADMINISTRACIÓN</div>
            <a href="../dashboard.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_inicio.png" alt="">
                </span><span>Inicio</span></a>
            <a href="../usuarios/index.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_usuarios.png" alt="">
                </span><span>Usuarios</span></a>
            <a href="../areas/index.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_direcciones.png" alt="">
                </span><span>Direcciones</span></a>
            <a href="../programas/index.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_programas.png" alt="">
                </span><span>Programas</span></a>
            <a href="../tipos_documento/index.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_tipos_documento.png" alt="">
                </span><span>Tipos de documento</span></a>

            <div class="nav-title">SUPERVISIÓN</div>
            <a href="../expedientes/index.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_expedientes.png" alt="">
                </span><span>Expedientes</span></a>
            <a href="../movimientos/historial.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_movimientos.png" alt="">
                </span><span>Movimientos</span></a>
            
            <a href="index.php" class="nav-item active">
                <span class="nav-icon">
                    <img src="../../assets/img/icon_notificaciones.png" alt="">
                </span>
                <span>Notificaciones</span>
            </a>

            <div class="nav-title">CUENTA</div>
            <a href="../perfil/index.php" class="nav-item"><span class="nav-icon">
                    <img src="../../assets/img/icon_perfil.png" alt="">
                </span><span>Mi perfil</span></a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="avatar avatar-small"><?= htmlspecialchars($iniciales) ?></div>
                <div class="sidebar-user-data">
                    <strong><?= htmlspecialchars($nombre_completo) ?></strong>
                    <span><?= htmlspecialchars($nombre_rol) ?></span>
                </div>
            </div>
            <a href="../../logout.php" class="logout-link">
                <span class="nav-icon">↪</span><span>Cerrar sesión</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div class="header-title">
                <span class="eyebrow">RED DE SALUD AGUAYTÍA</span>
                <h1>Notificaciones</h1>
                <p>Envío de avisos a los usuarios de Mesa de Partes</p>
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

            <?php if ($mensaje_exito !== ""): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
            <?php endif; ?>
            <?php if ($mensaje_error !== ""): ?>
                <div class="alert alert-error"><?= htmlspecialchars($mensaje_error) ?></div>
            <?php endif; ?>

            <section class="notification-summary">
                <div class="notification-summary-icon">✉</div>
                <div class="notification-summary-text">
                    <span>CENTRO DE NOTIFICACIONES</span>
                    <strong>
                        <?= $total_enviadas ?>
                        <?= $total_enviadas === 1 ? "notificación enviada" : "notificaciones enviadas" ?>
                    </strong>
                </div>
                <?php if ($notificaciones_no_leidas > 0): ?>
                    <div class="notification-summary-pending" title="Pendientes de lectura por Mesa de Partes">
                        <?= $notificaciones_no_leidas ?> pendientes de lectura
                    </div>
                <?php endif; ?>
            </section>

            <!-- FORMULARIO -->
            <section class="dashboard-section">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">NUEVO AVISO</span>
                        <h2>Enviar notificación</h2>
                    </div>
                </div>

                <form method="POST" action="index.php" class="notification-form" id="formNotificacion">
                    <input type="hidden" name="accion" value="enviar">

                    <!-- DESTINATARIO -->
                    <div class="notification-form-group">
                        <label for="destinatario">Destinatario:</label>
                        <select name="destinatario" id="destinatario" required>
                            <option value="">Seleccione un destinatario</option>
                            <option value="todos">Todos los usuarios de Mesa de Partes</option>
                            <?php foreach ($usuarios_mesa as $u): ?>
                                <option value="<?= (int) $u["id_usuario"] ?>">
                                    <?= htmlspecialchars($u["apellidos"] . ", " . $u["nombres"]) ?>
                                    <?php if (!empty($u["nombre_area"])): ?>
                                        - <?= htmlspecialchars($u["nombre_area"]) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- TIPO DE VÍNCULO -->
                    <div class="notification-form-group">
                        <label for="tipo_vinculo">Vincular a (opcional):</label>
                        <select name="tipo_vinculo" id="tipo_vinculo">
                            <option value="ninguno">— Sin vínculo —</option>
                            <option value="expediente">Expediente</option>
                            <option value="anexo">Anexo</option>
                            <option value="proveido">Proveído</option>
                        </select>
                    </div>

                    <!-- SELECTOR DINÁMICO -->
                    <div class="notification-form-group" id="grupoVinculo" style="display:none;">
                        <label for="id_vinculo" id="labelVinculo">Seleccione:</label>
                        <select name="id_vinculo" id="id_vinculo">
                            <option value="">— Seleccione —</option>
                        </select>
                        <p class="form-hint" id="hintVinculo"></p>
                    </div>

                    <!-- MENSAJE -->
                    <div class="notification-form-group">
                        <label for="mensaje">Mensaje:</label>
                        <textarea
                            name="mensaje"
                            id="mensaje"
                            rows="5"
                            maxlength="500"
                            placeholder="Escriba el aviso que desea enviar a Mesa de Partes..."
                            required
                        ></textarea>
                        <small class="char-counter" id="charCounter">0 / 500</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="mark-all-button">Enviar notificación</button>
                    </div>
                </form>
            </section>

            <!-- HISTORIAL -->
            <section class="dashboard-section notification-section">
                <div class="section-header">
                    <div>
                        <span class="section-kicker">HISTORIAL</span>
                        <h2>Notificaciones enviadas</h2>
                        <?php if ($total_enviadas > 50): ?>
                            <p class="form-hint" style="margin-top:4px;">
                                Mostrando las 50 más recientes de un total de <?= $total_enviadas ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($notificaciones_enviadas)): ?>
                    <div class="notifications-empty">
                        <div class="notifications-empty-icon">✔</div>
                        <strong>No hay notificaciones enviadas</strong>
                        <p>Las notificaciones enviadas a Mesa de Partes aparecerán aquí.</p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($notificaciones_enviadas as $notificacion): ?>
                            <?php
                            $es_leida = ((int) $notificacion["leido"] === 1);
                            $clase_notificacion = $es_leida ? "notification-read" : "notification-unread";
                            $tiene_expediente = !empty($notificacion["id_expediente"]);
                            $numero_exp = $notificacion["numero_expediente"] ?? null;
                            ?>
                            <article class="notification-card <?= $clase_notificacion ?>">
                                <div class="notification-icon">
                                    <?= $es_leida ? "✔" : "●" ?>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-top">
                                        <span class="notification-label">DESTINATARIO</span>
                                        <time><?= htmlspecialchars(formatearFechaNotificacion($notificacion["fecha_creacion"])) ?></time>
                                    </div>
                                    <p>
                                        <strong><?= htmlspecialchars($notificacion["apellidos"] . ", " . $notificacion["nombres"]) ?></strong>
                                    </p>
                                    <p class="notification-message">
                                        <?= nl2br(htmlspecialchars($notificacion["mensaje"])) ?>
                                    </p>
                                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:8px;">
                                        <?php if ($tiene_expediente): ?>
                                            <span class="notification-type-badge notification-type-sistema">Vinculado</span>
                                            <div class="notification-expediente">
                                                <a href="../expedientes/ver.php?id=<?= (int) $notificacion["id_expediente"] ?>"
                                                   class="notification-expediente-link"
                                                   title="Ver expediente">
                                                    📂 <?= htmlspecialchars($numero_exp ?: ("#" . $notificacion["id_expediente"])) ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="notification-type-badge notification-type-manual">Aviso manual</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-action" style="margin-top:10px;">
                                        <?php if ($es_leida): ?>
                                            <span class="status-pill status-leida">LEÍDA</span>
                                        <?php else: ?>
                                            <span class="status-pill status-pendiente">PENDIENTE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>
</div>

<script>
window.datosVinculo = {
    expediente: [
        <?php foreach ($expedientes_recientes as $e): ?>
        { id: <?= (int)$e["id_expediente"] ?>, texto: <?= json_encode($e["numero_expediente"]) ?> },
        <?php endforeach; ?>
    ],
    anexo: [
        <?php foreach ($anexos_recientes as $a): ?>
        { id: <?= (int)$a["id_anexo"] ?>, texto: <?= json_encode("Anexo #".$a["id_anexo"]." → ".$a["numero_expediente"]) ?> },
        <?php endforeach; ?>
    ],
    proveido: [
        <?php foreach ($proveidos_recientes as $p): ?>
        { id: <?= (int)$p["id_proveido"] ?>, texto: <?= json_encode("Proveído #".$p["id_proveido"]." → ".$p["numero_expediente"]) ?> },
        <?php endforeach; ?>
    ]
};
</script>
</body>
</html>