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

$fechaEmision = date("d/m/Y H:i", strtotime($exp["fecha_registro"]));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir - <?= htmlspecialchars($exp["numero_expediente"]) ?> | RedSalud</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #1a1a1a;
            line-height: 1.5;
            padding: 24px;
        }
        .no-print {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-print { background: #0d6efd; color: #fff; }
        .btn-back { background: #6c757d; color: #fff; }
        .print-sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 40px 48px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 16px;
            margin-bottom: 28px;
        }
        .header h1 {
            font-size: 1.35rem;
            color: #0d6efd;
            margin-bottom: 4px;
        }
        .header p { font-size: 0.9rem; color: #555; }
        .meta-box {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .meta-item strong {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6c757d;
            margin-bottom: 4px;
        }
        .meta-item span {
            font-size: 1.05rem;
            font-weight: 700;
            color: #212529;
        }
        .codigo {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem !important;
            color: #0d6efd !important;
        }
        .section { margin-bottom: 22px; }
        .section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6c757d;
            margin-bottom: 6px;
        }
        .section-content {
            font-size: 1rem;
            padding: 10px 14px;
            background: #fafafa;
            border-left: 3px solid #0d6efd;
            border-radius: 0 6px 6px 0;
        }
        .sello-firma {
            margin-top: 48px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
            flex-wrap: wrap;
        }
        .sello-box, .firma-box {
            flex: 1;
            min-width: 220px;
            text-align: center;
        }
        .sello-box .space, .firma-box .space {
            height: 110px;
            border: 2px dashed #adb5bd;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 0.85rem;
        }
        .sello-box p, .firma-box p {
            font-size: 0.85rem;
            color: #495057;
            font-weight: 600;
        }
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 0.8rem;
            color: #6c757d;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .print-sheet {
                box-shadow: none;
                border: none;
                max-width: 100%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-print" onclick="window.print()">🖨 Imprimir ahora</button>
        <a href="ver.php?id=<?= (int)$id_expediente ?>" class="btn btn-back">← Volver al expediente</a>
        <a href="index.php" class="btn btn-back">Ir a listado</a>
    </div>

    <div class="print-sheet">
        <div class="header">
            <h1>RED DE SALUD INTEGRAL</h1>
            <p>Sistema de Gestión Documental – Comprobante de Registro</p>
        </div>

        <div class="meta-box">
            <div class="meta-item">
                <strong>Código de documento</strong>
                <span class="codigo"><?= htmlspecialchars($exp["numero_expediente"]) ?></span>
            </div>
            <div class="meta-item">
                <strong>Fecha de emisión / registro</strong>
                <span><?= htmlspecialchars($fechaEmision) ?></span>
            </div>
            <div class="meta-item">
                <strong>Estado</strong>
                <span><?= htmlspecialchars($exp["estado_expediente"]) ?></span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Tipo de documento</div>
            <div class="section-content"><?= htmlspecialchars($exp["nombre_tipo"] ?? "—") ?></div>
        </div>

        <div class="section">
            <div class="section-title">Origen</div>
            <div class="section-content"><?= htmlspecialchars($exp["origen_documento"]) ?></div>
        </div>

        <?php if (!empty($exp["nombre_area_emisora"])): ?>
        <div class="section">
            <div class="section-title">Dirección emisora</div>
            <div class="section-content"><?= htmlspecialchars($exp["nombre_area_emisora"]) ?></div>
        </div>
        <?php endif; ?>

        <div class="section">
            <div class="section-title">Remitente</div>
            <div class="section-content"><?= htmlspecialchars($exp["remitente"]) ?></div>
        </div>

        <div class="section">
            <div class="section-title">Asunto</div>
            <div class="section-content"><?= nl2br(htmlspecialchars($exp["asunto"])) ?></div>
        </div>

        <div class="section">
            <div class="section-title">Número de folios</div>
            <div class="section-content"><?= (int)$exp["numero_folios"] ?></div>
        </div>

        <div class="sello-firma">
            <div class="sello-box">
                <div class="space">Espacio para sello institucional</div>
                <p>Sello</p>
            </div>
            <div class="firma-box">
                <div class="space">Espacio para firma</div>
                <p>Firma y postfirma</p>
            </div>
        </div>

        <div class="footer">
            Documento generado automáticamente por el Sistema RedSalud Integral · <?= date("d/m/Y H:i") ?>
        </div>
    </div>

    <script>
        // Auto-print opcional si se desea (comentado por defecto para que el usuario decida)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
