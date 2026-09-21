<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| Si ya existe una sesión activa
|--------------------------------------------------------------------------
*/

if (isset($_SESSION["id_usuario"])) {
    if (isset($_SESSION["id_rol"]) && $_SESSION["id_rol"] == 1) {
        header("Location: ../admin/dashboard.php");
    } elseif (isset($_SESSION["id_rol"]) && $_SESSION["id_rol"] == 2) {
        header("Location: ../mesa_partes/dashboard.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}

$error = "";

/*
|--------------------------------------------------------------------------
| Procesar inicio de sesión
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $password = $_POST["password"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | Validar campos
    |--------------------------------------------------------------------------
    */

    if ($usuario === "" || $password === "") {

        $error = "Ingrese su usuario y contraseña.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Buscar usuario por nombre_usuario
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT
                    u.id_usuario,
                    u.id_area,
                    u.id_programa,
                    u.id_rol,
                    u.dni,
                    u.nombres,
                    u.apellidos,
                    u.correo,
                    u.estado,
                    c.id_credencial,
                    c.password_hash
                FROM usuarios u
                INNER JOIN credenciales_acceso c
                    ON c.id_usuario = u.id_usuario
                WHERE c.nombre_usuario = ?
                LIMIT 1
            ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $usuario);
            $stmt->execute();

            $resultado = $stmt->get_result();
            $usuario_db = $resultado->fetch_assoc();

            /*
            |--------------------------------------------------------------------------
            | Verificar usuario y contraseña
            |--------------------------------------------------------------------------
            */

            if (
                $usuario_db &&
                $usuario_db["estado"] == 1 &&
                $password === $usuario_db["password_hash"]
            ) {

                /*
                |--------------------------------------------------------------------------
                | Regenerar sesión
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                /*
                |--------------------------------------------------------------------------
                | Guardar información del usuario en sesión
                |--------------------------------------------------------------------------
                */

                $_SESSION["id_usuario"]     = $usuario_db["id_usuario"];
                $_SESSION["id_credencial"]  = $usuario_db["id_credencial"];
                $_SESSION["nombres"]        = $usuario_db["nombres"];
                $_SESSION["apellidos"]      = $usuario_db["apellidos"];
                $_SESSION["dni"]            = $usuario_db["dni"];
                $_SESSION["correo"]         = $usuario_db["correo"];
                $_SESSION["id_area"]        = $usuario_db["id_area"];
                $_SESSION["id_programa"]    = $usuario_db["id_programa"];
                $_SESSION["id_rol"]         = $usuario_db["id_rol"];

                /*
                |--------------------------------------------------------------------------
                | Actualizar último ingreso
                |--------------------------------------------------------------------------
                */

                $sqlIngreso = "
                    UPDATE credenciales_acceso
                    SET ultimo_ingreso = NOW()
                    WHERE id_credencial = ?
                ";

                $stmtIngreso = $conn->prepare($sqlIngreso);
                $stmtIngreso->bind_param("i", $usuario_db["id_credencial"]);
                $stmtIngreso->execute();

                /*
                |--------------------------------------------------------------------------
                | Entrar al sistema según el rol
                |--------------------------------------------------------------------------
                */

                if ($_SESSION["id_rol"] == 1) {
                    header("Location: ../admin/dashboard.php");
                } elseif ($_SESSION["id_rol"] == 2) {
                    header("Location: ../mesa_partes/dashboard.php");
                } else {
                    header("Location: ../index.php");
                }
                exit;

            } else {

                $error = "Usuario o contraseña incorrectos.";

            }

        } catch (mysqli_sql_exception $e) {

            $error = "Ocurrió un error al intentar iniciar sesión.";
            error_log($e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | RedSalud</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<main class="login-container">

    <section class="login-card">

        <div class="login-header">
            <h1>RedSalud</h1>
            <p>Sistema Integral de Gestión Documentaria</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <!-- USUARIO -->
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    placeholder="Ingrese su usuario"
                    autocomplete="username"
                    required
                    value="<?= htmlspecialchars($_POST["usuario"] ?? "") ?>"
                >
            </div>

            <!-- CONTRASEÑA -->
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Ingrese su contraseña"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">
                Iniciar sesión
            </button>

        </form>

    </section>

</main>

</body>
</html>