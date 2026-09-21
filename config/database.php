<?php

$servidor = "localhost";
$usuario = "root";
$password = "";
$base_datos = "redsalud_integral";

$conn = new mysqli($servidor, $usuario, $password, $base_datos);

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>