<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    header("Location: ../../auth/login.php");
    exit;
}

require_once "../../config/database.php";

/*
|--------------------------------------------------------------------------
| Verificar administrador
|--------------------------------------------------------------------------
*/

$id_usuario_admin = (int) $_SESSION["id_usuario"];

$stmt = $conn->prepare("
    SELECT
        id_usuario,
        id_rol,
        estado
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
");

$stmt->bind_param("i", $id_usuario_admin);
$stmt->execute();

$admin_usuario = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (
    !$admin_usuario ||
    (int) $admin_usuario["estado"] !== 1 ||
    (int) $admin_usuario["id_rol"] !== 1
) {
    session_destroy();
    header("Location: ../../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Solo POST
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

$dni = trim($_POST["dni"] ?? "");
$nombres = trim($_POST["nombres"] ?? "");
$apellidos = trim($_POST["apellidos"] ?? "");
$correo = trim($_POST["correo"] ?? "");
$area = (int) ($_POST["id_area"] ?? 0);
$programa = (int) ($_POST["id_programa"] ?? 0);
$rol = (int) ($_POST["id_rol"] ?? 0);
$username = trim($_POST["nombre_usuario"] ?? "");
$password = $_POST["password"] ?? "";

$roles_permitidos = [1, 2, 3, 4];

/*
|--------------------------------------------------------------------------
| Validaciones
|--------------------------------------------------------------------------
*/

if (
    !preg_match('/^\d{8}$/', $dni) ||
    $nombres === "" ||
    mb_strlen($nombres) > 100 ||
    $apellidos === "" ||
    mb_strlen($apellidos) > 100 ||
    !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
    mb_strlen($correo) > 100 ||
    $area <= 0 ||
    !in_array(
        $rol,
        $roles_permitidos,
        true
    ) ||
    $username === "" ||
    mb_strlen($username) > 50 ||
    strlen($password) < 8
) {
    $_SESSION["mensaje_error"] =
        "Revise los datos obligatorios del usuario.";

    header("Location: crear.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Validar programa según área
|--------------------------------------------------------------------------
*/

if ($programa > 0) {

    $stmt = $conn->prepare("
        SELECT id_programa
        FROM programas_internos
        WHERE id_programa = ?
          AND id_area = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ii",
        $programa,
        $area
    );

    $stmt->execute();

    $valid = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$valid) {

        $_SESSION["mensaje_error"] =
            "El programa seleccionado no pertenece al área indicada.";

        header("Location: crear.php");
        exit;
    }

} else {
    $programa = null;
}

/*
|--------------------------------------------------------------------------
| Verificar duplicados
|--------------------------------------------------------------------------
*/

$checks = [
    [
        "SELECT id_usuario
         FROM usuarios
         WHERE dni = ?
         LIMIT 1",
        "s",
        $dni,
        "El DNI ya está registrado."
    ],
    [
        "SELECT id_usuario
         FROM usuarios
         WHERE correo = ?
         LIMIT 1",
        "s",
        $correo,
        "El correo ya está registrado."
    ],
    [
        "SELECT id_usuario
         FROM credenciales_acceso
         WHERE nombre_usuario = ?
         LIMIT 1",
        "s",
        $username,
        "El nombre de usuario ya está registrado."
    ]
];

foreach ($checks as $check) {

    $stmt = $conn->prepare($check[0]);

    $stmt->bind_param(
        $check[1],
        $check[2]
    );

    $stmt->execute();

    $exists = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($exists) {

        $_SESSION["mensaje_error"] =
            $check[3];

        header("Location: crear.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Crear usuario y credencial
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    | Crear usuario
    */

    $stmt = $conn->prepare("
        INSERT INTO usuarios
        (
            id_area,
            id_programa,
            id_rol,
            dni,
            nombres,
            apellidos,
            correo,
            estado
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->bind_param(
        "iiissss",
        $area,
        $programa,
        $rol,
        $dni,
        $nombres,
        $apellidos,
        $correo
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "No se pudo crear el usuario."
        );
    }

    $id_usuario = $stmt->insert_id;

    $stmt->close();

    /*
    | Crear credencial
    */

    $hash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $stmt = $conn->prepare("
        INSERT INTO credenciales_acceso
        (
            id_usuario,
            nombre_usuario,
            password_hash
        )
        VALUES (?, ?, ?)
    ");

    $stmt->bind_param(
        "iss",
        $id_usuario,
        $username,
        $hash
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "No se pudo crear la credencial."
        );
    }

    $stmt->close();

    $conn->commit();

    $_SESSION["mensaje_exito"] =
        "Usuario creado correctamente.";

} catch (Throwable $e) {

    $conn->rollback();

    error_log($e->getMessage());

    $_SESSION["mensaje_error"] =
        "No se pudo crear el usuario. Verifique que DNI, correo y usuario sean únicos.";
}

header("Location: index.php");
exit;