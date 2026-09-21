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
| DATOS
|--------------------------------------------------------------------------
*/

$nombre_area = trim($_POST["nombre_area"] ?? "");

$tipo_mesa_partes =
    strtoupper(trim($_POST["tipo_mesa_partes"] ?? ""));


$tipos_validos = [
    "GENERAL",
    "INTERNA",
    "NINGUNA"
];


if ($nombre_area === "") {

    $_SESSION["mensaje_error"] =
        "El nombre del área es obligatorio.";

    header("Location: crear.php");
    exit;
}


if (!in_array($tipo_mesa_partes, $tipos_validos, true)) {

    $_SESSION["mensaje_error"] =
        "El tipo de mesa de partes no es válido.";

    header("Location: crear.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| EVITAR ÁREAS DUPLICADAS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_area
    FROM areas
    WHERE nombre_area = ?
    LIMIT 1
");

$stmt->bind_param("s", $nombre_area);
$stmt->execute();

$existe = $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($existe) {

    $_SESSION["mensaje_error"] =
        "Ya existe un área con ese nombre.";

    header("Location: crear.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| INSERTAR
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    INSERT INTO areas
        (nombre_area, tipo_mesa_partes)
    VALUES
        (?, ?)
");

if (!$stmt) {

    $_SESSION["mensaje_error"] =
        "No se pudo preparar el registro.";

    header("Location: crear.php");
    exit;
}

$stmt->bind_param(
    "ss",
    $nombre_area,
    $tipo_mesa_partes
);

if ($stmt->execute()) {

    $_SESSION["mensaje_exito"] =
        "Dirección registrada correctamente.";

} else {

    $_SESSION["mensaje_error"] =
        "No se pudo registrar el área.";
}

$stmt->close();

header("Location: index.php");
exit;