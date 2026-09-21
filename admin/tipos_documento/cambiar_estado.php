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
| ID recibido
|--------------------------------------------------------------------------
*/

$id = (int) ($_POST["id_tipo_documento"] ?? 0);

if ($id <= 0) {

    $_SESSION["mensaje_error"] =
        "Tipo de documento no válido.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Verificar que exista
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
| Verificar si está siendo utilizado
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM expedientes
    WHERE id_tipo_documento = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result()->fetch_assoc();

$usado = (int) ($resultado["total"] ?? 0);

$stmt->close();


if ($usado > 0) {

    $_SESSION["mensaje_error"] =
        "No se puede eliminar el tipo porque está utilizado por expedientes. La tabla no tiene campo estado.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Eliminar
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM tipos_documento
    WHERE id_tipo_documento = ?
");

$stmt->bind_param("i", $id);

$ok = $stmt->execute();

$stmt->close();


if ($ok) {

    $_SESSION["mensaje_exito"] =
        "Tipo de documento eliminado correctamente.";

} else {

    $_SESSION["mensaje_error"] =
        "No se pudo eliminar el tipo de documento.";
}


header("Location: index.php");
exit;