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
| VERIFICAR MÉTODO
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    $_SESSION["mensaje_error"] =
        "Solicitud no válida.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DATOS
|--------------------------------------------------------------------------
*/

$id_programa =
    (int) ($_POST["id_programa"] ?? 0);


/*
|--------------------------------------------------------------------------
| VALIDAR ID
|--------------------------------------------------------------------------
*/

if ($id_programa <= 0) {

    $_SESSION["mensaje_error"] =
        "Programa no válido.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFICAR QUE EL PROGRAMA EXISTA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_programa
    FROM programas_internos
    WHERE id_programa = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $id_programa
);

$stmt->execute();

$programa =
    $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$programa) {

    $_SESSION["mensaje_error"] =
        "El programa no existe.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFICAR SI ESTÁ ASIGNADO A USUARIOS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM usuarios
    WHERE id_programa = ?
");

$stmt->bind_param(
    "i",
    $id_programa
);

$stmt->execute();

$usado =
    (int) $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


if ($usado > 0) {

    $_SESSION["mensaje_error"] =
        "No se puede eliminar el programa porque está asignado a uno o más usuarios.";

    header("Location: index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| ELIMINAR PROGRAMA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM programas_internos
    WHERE id_programa = ?
");

$stmt->bind_param(
    "i",
    $id_programa
);


if ($stmt->execute()) {

    $_SESSION["mensaje_exito"] =
        "Programa eliminado correctamente.";

} else {

    $_SESSION["mensaje_error"] =
        "No se pudo eliminar el programa.";
}

$stmt->close();

header("Location: index.php");
exit;