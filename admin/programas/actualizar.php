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
| DATOS DEL FORMULARIO
|--------------------------------------------------------------------------
*/

$id_programa = (int) ($_POST["id_programa"] ?? 0);

$id_area = (int) ($_POST["id_area"] ?? 0);

$nombre_programa =
    trim($_POST["nombre_programa"] ?? "");

$codigo_programa =
    strtoupper(
        trim($_POST["codigo_programa"] ?? "")
    );


/*
|--------------------------------------------------------------------------
| VALIDACIONES BÁSICAS
|--------------------------------------------------------------------------
*/

if ($id_programa <= 0) {

    $_SESSION["mensaje_error"] =
        "Programa no válido.";

    header("Location: index.php");
    exit;
}


if ($id_area <= 0) {

    $_SESSION["mensaje_error"] =
        "El área seleccionada no es válida.";

    header(
        "Location: editar.php?id=" . $id_programa
    );

    exit;
}


if ($nombre_programa === "") {

    $_SESSION["mensaje_error"] =
        "El nombre del programa es obligatorio.";

    header(
        "Location: editar.php?id=" . $id_programa
    );

    exit;
}


if (mb_strlen($nombre_programa) > 150) {

    $_SESSION["mensaje_error"] =
        "El nombre del programa no puede superar los 150 caracteres.";

    header(
        "Location: editar.php?id=" . $id_programa
    );

    exit;
}


if ($codigo_programa === "") {

    $_SESSION["mensaje_error"] =
        "El código del programa es obligatorio.";

    header(
        "Location: editar.php?id=" . $id_programa
    );

    exit;
}


if (mb_strlen($codigo_programa) > 20) {

    $_SESSION["mensaje_error"] =
        "El código del programa no puede superar los 20 caracteres.";

    header(
        "Location: editar.php?id=" . $id_programa
    );

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
| VERIFICAR QUE EL ÁREA EXISTA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_area
    FROM areas
    WHERE id_area = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $id_area
);

$stmt->execute();

$area =
    $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$area) {

    $_SESSION["mensaje_error"] =
        "El área seleccionada no existe.";

    header(
        "Location: editar.php?id=" . $id_programa
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EVITAR CÓDIGO DUPLICADO
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_programa
    FROM programas_internos
    WHERE codigo_programa = ?
      AND id_programa <> ?
    LIMIT 1
");

$stmt->bind_param(
    "si",
    $codigo_programa,
    $id_programa
);

$stmt->execute();

$duplicado =
    $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($duplicado) {

    $_SESSION["mensaje_error"] =
        "El código del programa ya está utilizado.";

    header(
        "Location: editar.php?id=" . $id_programa
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ACTUALIZAR PROGRAMA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE programas_internos
    SET
        id_area = ?,
        nombre_programa = ?,
        codigo_programa = ?
    WHERE id_programa = ?
");

$stmt->bind_param(
    "issi",
    $id_area,
    $nombre_programa,
    $codigo_programa,
    $id_programa
);


if ($stmt->execute()) {

    $_SESSION["mensaje_exito"] =
        "Programa actualizado correctamente.";

} else {

    $_SESSION["mensaje_error"] =
        "No se pudo actualizar el programa.";
}

$stmt->close();

header("Location: index.php");
exit;