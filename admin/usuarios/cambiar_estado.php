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

$id_usuario_admin = (int) $_SESSION["id_usuario"];

$stmt = $conn->prepare("
    SELECT
        u.id_usuario,
        u.nombres,
        u.apellidos,
        u.correo,
        u.id_area,
        u.id_programa,
        u.id_rol,
        u.estado,
        r.nombre_rol
    FROM usuarios u
    LEFT JOIN roles r ON r.id_rol = u.id_rol
    WHERE u.id_usuario = ?
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

$id = (int) ($_POST["id_usuario"] ?? 0);
$dni = trim($_POST["dni"] ?? "");
$nombres = trim($_POST["nombres"] ?? "");
$apellidos = trim($_POST["apellidos"] ?? "");
$correo = trim($_POST["correo"] ?? "");
$area = (int) ($_POST["id_area"] ?? 0);
$programa = (int) ($_POST["id_programa"] ?? 0);
$rol = (int) ($_POST["id_rol"] ?? 0);
$username = trim($_POST["nombre_usuario"] ?? "");
$password = $_POST["password"] ?? "";

/*
|--------------------------------------------------------------------------
| Validaciones
|--------------------------------------------------------------------------
*/

if (
    $id <= 0 ||
    !preg_match('/^\d{8}$/', $dni) ||
    $nombres === "" ||
    mb_strlen($nombres) > 100 ||
    $apellidos === "" ||
    mb_strlen($apellidos) > 100 ||
    !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
    mb_strlen($correo) > 100 ||
    $area <= 0 ||
    !in_array($rol, [1, 2, 3, 4], true) ||
    $username === "" ||
    mb_strlen($username) > 50 ||
    (strlen($password) > 0 && strlen($password) < 8)
) {
    $_SESSION["mensaje_error"] = "Revise los datos del usuario.";
    header("Location: editar.php?id=" . $id);
    exit;
}

/*
|--------------------------------------------------------------------------
| No permitir que el administrador se quite su propio rol
|--------------------------------------------------------------------------
*/

if (
    $id === (int) $admin_usuario["id_usuario"] &&
    $rol !== 1
) {
    $_SESSION["mensaje_error"] =
        "No puedes cambiar tu propio rol de administrador.";

    header("Location: editar.php?id=" . $id);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validar programa según el área
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

    $stmt->bind_param("ii", $programa, $area);
    $stmt->execute();

    $valid = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$valid) {
        $_SESSION["mensaje_error"] =
            "El programa no pertenece al área indicada.";

        header("Location: editar.php?id=" . $id);
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
           AND id_usuario <> ?
         LIMIT 1",
        "si",
        $dni,
        $id,
        "El DNI ya está registrado."
    ],
    [
        "SELECT id_usuario
         FROM usuarios
         WHERE correo = ?
           AND id_usuario <> ?
         LIMIT 1",
        "si",
        $correo,
        $id,
        "El correo ya está registrado."
    ],
    [
        "SELECT id_usuario
         FROM credenciales_acceso
         WHERE nombre_usuario = ?
           AND id_usuario <> ?
         LIMIT 1",
        "si",
        $username,
        $id,
        "El nombre de usuario ya está registrado."
    ]
];

foreach ($checks as $check) {

    $stmt = $conn->prepare($check[0]);

    $stmt->bind_param(
        $check[1],
        $check[2],
        $check[3]
    );

    $stmt->execute();

    $duplicado = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($duplicado) {
        $_SESSION["mensaje_error"] = $check[4];

        header("Location: editar.php?id=" . $id);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Actualizar usuario y credencial
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    /*
    | Actualizar datos del usuario
    */

    $stmt = $conn->prepare("
        UPDATE usuarios
        SET
            id_area = ?,
            id_programa = ?,
            id_rol = ?,
            dni = ?,
            nombres = ?,
            apellidos = ?,
            correo = ?
        WHERE id_usuario = ?
    ");

    $stmt->bind_param(
        "iiissssi",
        $area,
        $programa,
        $rol,
        $dni,
        $nombres,
        $apellidos,
        $correo,
        $id
    );

    if (!$stmt->execute()) {
        throw new Exception("No se pudo actualizar usuario.");
    }

    $stmt->close();

    /*
    | Buscar credencial
    */

    $stmt = $conn->prepare("
        SELECT id_credencial
        FROM credenciales_acceso
        WHERE id_usuario = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $credencial = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    /*
    | Actualizar o crear credencial
    */

    if ($credencial) {

        if ($password !== "") {

            $hash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare("
                UPDATE credenciales_acceso
                SET
                    nombre_usuario = ?,
                    password_hash = ?
                WHERE id_usuario = ?
            ");

            $stmt->bind_param(
                "ssi",
                $username,
                $hash,
                $id
            );

        } else {

            $stmt = $conn->prepare("
                UPDATE credenciales_acceso
                SET nombre_usuario = ?
                WHERE id_usuario = ?
            ");

            $stmt->bind_param(
                "si",
                $username,
                $id
            );
        }

        if (!$stmt->execute()) {
            throw new Exception(
                "No se pudo actualizar la credencial."
            );
        }

        $stmt->close();

    } else {

        $hash = password_hash(
            $password !== ""
                ? $password
                : bin2hex(random_bytes(8)),
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
            $id,
            $username,
            $hash
        );

        if (!$stmt->execute()) {
            throw new Exception(
                "No se pudo crear la credencial."
            );
        }

        $stmt->close();
    }

    $conn->commit();

    $_SESSION["mensaje_exito"] =
        "Usuario actualizado correctamente.";

} catch (Throwable $e) {

    $conn->rollback();

    error_log($e->getMessage());

    $_SESSION["mensaje_error"] =
        "No se pudo actualizar el usuario. Verifique que los datos sean únicos.";
}

header("Location: index.php");
exit;