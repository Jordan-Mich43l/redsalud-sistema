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
        id_usuario,
        id_rol,
        estado
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
| Solo permitir POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Datos recibidos
|--------------------------------------------------------------------------
*/

$id = (int) ($_POST["id_tipo_documento"] ?? 0);

$nombre = trim(
    $_POST["nombre_tipo"] ?? ""
);

$dias = (int) (
    $_POST["dias_limite_atencion"] ?? 0
);


/*
|--------------------------------------------------------------------------
| Validación
|--------------------------------------------------------------------------
*/

if (
    $id <= 0 ||
    $nombre === "" ||
    mb_strlen($nombre) > 100 ||
    $dias < 1 ||
    $dias > 365
) {
    $_SESSION["mensaje_error"] =
        "Los datos no son válidos.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Verificar que exista el tipo
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_tipo_documento
    FROM tipos_documento
    WHERE id_tipo_documento = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$tipo = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$tipo) {

    $_SESSION["mensaje_error"] =
        "El tipo de documento no existe.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Verificar nombre duplicado
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_tipo_documento
    FROM tipos_documento
    WHERE nombre_tipo = ?
      AND id_tipo_documento <> ?
    LIMIT 1
");

$stmt->bind_param(
    "si",
    $nombre,
    $id
);

$stmt->execute();

$duplicado = $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($duplicado) {

    $_SESSION["mensaje_error"] =
        "Ya existe otro tipo con ese nombre.";

    header("Location: editar.php?id=" . $id);
    exit;
}


/*
|--------------------------------------------------------------------------
| Actualizar
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE tipos_documento
    SET
        nombre_tipo = ?,
        dias_limite_atencion = ?
    WHERE id_tipo_documento = ?
");

$stmt->bind_param(
    "sii",
    $nombre,
    $dias,
    $id
);

$ok = $stmt->execute();

$stmt->close();


if ($ok) {

    $_SESSION["mensaje_exito"] =
        "Tipo de documento actualizado correctamente.";

} else {

    $_SESSION["mensaje_error"] =
        "No se pudo actualizar el tipo de documento.";
}


header("Location: index.php");
exit;