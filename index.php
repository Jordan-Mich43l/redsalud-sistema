<?php
session_start();

if (isset($_SESSION["id_usuario"])) {

    if ($_SESSION["id_rol"] == 1) {
        header("Location: admin/dashboard.php");
        exit;
    }

    if ($_SESSION["id_rol"] == 2) {
        header("Location: mesa_partes/dashboard.php");
        exit;
    }
}

header("Location: auth/login.php");
exit;
?>