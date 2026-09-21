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

$stmt->bind_param(
    "i",
    $id_usuario
);

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

$id_area =
    (int) ($_POST["id_area"] ?? 0);

$nombre_programa =
    trim($_POST["nombre_programa"] ?? "");

$codigo_programa =
    strtoupper(
        trim($_POST["codigo_programa"] ?? "")
    );


/*
|--------------------------------------------------------------------------
| VALIDACIONES
|--------------------------------------------------------------------------
*/

if (
    $id_area <= 0 ||
    $nombre_programa === "" ||
    mb_strlen($nombre_programa) > 150 ||
    $codigo_programa === "" ||
    mb_strlen($codigo_programa) > 20
) {

    $_SESSION["mensaje_error"] =
        "Los datos del programa no son válidos.";

    header("Location: crear.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFICAR ÁREA
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

    header("Location: crear.php");
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
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $codigo_programa
);

$stmt->execute();

$duplicado =
    $stmt->get_result()->fetch_assoc();

$stmt->close();


if ($duplicado) {

    $_SESSION["mensaje_error"] =
        "El código del programa ya existe.";

    header("Location: crear.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| INSERTAR PROGRAMA
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    INSERT INTO programas_internos
        (
            id_area,
            nombre_programa,
            codigo_programa
        )
    VALUES
        (?, ?, ?)
");

$stmt->bind_param(
    "iss",
    $id_area,
    $nombre_programa,
    $codigo_programa
);


if ($stmt->execute()) {

    $_SESSION["mensaje_exito"] =
        "Programa creado correctamente.";

} else {

    $_SESSION["mensaje_error"] =
        "No se pudo crear el programa.";
}

$stmt->close();

header("Location: index.php");
exit;