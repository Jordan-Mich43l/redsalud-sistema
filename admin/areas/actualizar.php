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
    SELECT id_usuario, id_rol, estado
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
");

$stmt->bind_param("i", $id_usuario);
$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (
    !$admin ||
    (int) $admin["estado"] !== 1 ||
    (int) $admin["id_rol"] !== 1
) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DATOS DEL FORMULARIO
|--------------------------------------------------------------------------
*/

$id_area = (int) ($_POST["id_area"] ?? 0);

$nombre_area =
    trim($_POST["nombre_area"] ?? "");

$tipo_mesa_partes =
    strtoupper(
        trim($_POST["tipo_mesa_partes"] ?? "")
    );


$tipos_validos = [
    "GENERAL",
    "INTERNA",
    "NINGUNA"
];


/*
|--------------------------------------------------------------------------
| VALIDACIONES
|--------------------------------------------------------------------------
*/

if ($id_area <= 0) {

    $_SESSION["mensaje_error"] =
        "Dirección no válida.";

    header("Location: index.php");
    exit;
}


if ($nombre_area === "") {

    $_SESSION["mensaje_error"] =
        "El nombre del área es obligatorio.";

    header(
        "Location: editar.php?id=" . $id_area
    );

    exit;
}


if (!in_array($tipo_mesa_partes, $tipos_validos, true)) {

    $_SESSION["mensaje_error"] =
        "El tipo de mesa de partes no es válido.";

    header(
        "Location: editar.php?id=" . $id_area
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFICAR QUE EL ÁREA EXISTA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_area
    FROM areas
    WHERE id_area = ?
    LIMIT 1
");

$stmt->bind_param("i", $id_area);
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
| EVITAR NOMBRE DUPLICADO
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_area
    FROM areas
    WHERE nombre_area = ?
      AND id_area <> ?
    LIMIT 1
");

$stmt->bind_param(
    "si",
    $nombre_area,
    $id_area
);

$stmt->execute();

$duplicado =
    $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($duplicado) {

    $_SESSION["mensaje_error"] =
        "Ya existe otra área con ese nombre.";

    header(
        "Location: editar.php?id=" . $id_area
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ACTUALIZAR
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE areas
    SET
        nombre_area = ?,
        tipo_mesa_partes = ?
    WHERE id_area = ?
");

$stmt->bind_param(
    "ssi",
    $nombre_area,
    $tipo_mesa_partes,
    $id_area
);

if ($stmt->execute()) {

    $_SESSION["mensaje_exito"] =
        "Dirección actualizada correctamente.";

} else {

    $_SESSION["mensaje_error"] =
        "No se pudo actualizar el área.";
}

$stmt->close();

header("Location: index.php");
exit;