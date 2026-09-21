<?php
session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

$id_expediente = (int) ($_GET["id"] ?? 0);

if ($id_expediente <= 0) {
    header("Location: index.php");
    exit;
}

$sql = "
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
        t.nombre_tipo,
        a.nombre_area AS nombre_area_emisora
    FROM expedientes e
    LEFT JOIN tipos_documento t ON t.id_tipo_documento = e.id_tipo_documento
    LEFT JOIN areas a ON a.id_area = e.id_area_emisora
    WHERE e.id_expediente = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_expediente);
$stmt->execute();
$exp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exp) {
    $_SESSION["mensaje_error"] = "Expediente no encontrado.";
    header("Location: index.php");
    exit;
}

$meses = [
    1 => "ENERO", 2 => "FEBRERO", 3 => "MARZO", 4 => "ABRIL",
    5 => "MAYO", 6 => "JUNIO", 7 => "JULIO", 8 => "AGOSTO",
    9 => "SEPTIEMBRE", 10 => "OCTUBRE", 11 => "NOVIEMBRE", 12 => "DICIEMBRE"
];
$ts = strtotime($exp["fecha_registro"]);
$fechaLarga = "Aguaytía, " . date("j", $ts) . " de " . $meses[(int)date("n", $ts)] . " del " . date("Y", $ts);
$fechaCorta = date("d/m/Y H:i", $ts);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Imprimir - <?= htmlspecialchars($exp["numero_expediente"]) ?> | RedSalud</title>

    <link rel="stylesheet" href="../../assets/css/imprimir.css">

</head>
<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">🖨 Imprimir ahora</button>
        <a href="ver.php?id=<?= (int)$id_expediente ?>" class="btn btn-back">← Volver al expediente</a>
        <a href="index.php" class="btn btn-back">Ir a listado</a>
    </div>

    <div class="print-sheet">
        <div class="header-oficial">
            <img class="logo-left" src="../../assets/img/logo_izquierda.png" alt="Logo institucional">
            <div class="header-text">
                <div class="entidad">GOBIERNO REGIONAL DE UCAYALI</div>
                <div class="sub">DIRECCIÓN REGIONAL DE SALUD</div>
                <div class="sub">RED INTEGRADA DE SALUD 4 AGUAYTÍA</div>
                <div class="zona">ZONA SANITARIA AGUAYTÍA</div>
            </div>
            <img class="logo-right" src="../../assets/img/logo_derecha.png" alt="Logo región">
        </div>

        <div class="lema">"Año de la Esperanza y el Fortalecimiento de la Democracia"</div>

        <div class="fecha-lugar"><?= htmlspecialchars($fechaLarga) ?>.</div>

        <div class="numero-doc">
            <?= htmlspecialchars($exp["numero_expediente"]) ?>
            &nbsp;–&nbsp;
            <?= htmlspecialchars($exp["nombre_tipo"] ?? "DOCUMENTO") ?>
        </div>

        <div class="campo">
            <span class="etiqueta">REMITENTE</span>
            <span class="valor">: <?= htmlspecialchars($exp["remitente"]) ?></span>
        </div>

        <?php if (!empty($exp["nombre_area_emisora"])): ?>
        <div class="campo">
            <span class="etiqueta">DIRECCIÓN EMISORA</span>
            <span class="valor">: <?= htmlspecialchars($exp["nombre_area_emisora"]) ?></span>
        </div>
        <?php endif; ?>

        <div class="campo">
            <span class="etiqueta">ORIGEN</span>
            <span class="valor">: <?= htmlspecialchars($exp["origen_documento"]) ?></span>
        </div>

        <div class="asunto-line">
            <span class="etiqueta">ASUNTO</span>
            <span class="valor">: <?= htmlspecialchars($exp["asunto"]) ?></span>
        </div>

        <div class="cuerpo">
            Por medio del presente se deja constancia del registro del documento
            <strong><?= htmlspecialchars($exp["numero_expediente"]) ?></strong>
            en el Sistema de Gestión Documental de la Red Integrada de Salud 4 Aguaytía,
            con fecha de registro <?= htmlspecialchars($fechaCorta) ?>,
            conformado por <?= (int)$exp["numero_folios"] ?> folio(s).
            El expediente se encuentra en estado
            <strong><?= htmlspecialchars(str_replace("_", " ", $exp["estado_expediente"])) ?></strong>.
        </div>

        <div class="meta-interno">
            <div><strong>Código de documento:</strong> <?= htmlspecialchars($exp["numero_expediente"]) ?></div>
            <div><strong>Fecha de emisión/registro:</strong> <?= htmlspecialchars($fechaCorta) ?></div>
            <div><strong>Número de folios:</strong> <?= (int)$exp["numero_folios"] ?></div>
            <div><strong>Tipo:</strong> <?= htmlspecialchars($exp["nombre_tipo"] ?? "—") ?></div>
        </div>

        <!-- Espacio en blanco para sello y firma (sin recuadros) -->
        <div class="espacio-firma"></div>

        <div class="footer-oficial">
            <div>Documento generado por la Red de Salud N° 04 Aguaytía - San Alejandro</div>
            <div class="dir">Av. Mz. “K” Lt. “04” – Puerto Azul – Aguaytía – Ucayali – Perú</div>
        </div>
    </div>
</body>
</html>
