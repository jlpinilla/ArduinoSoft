<?php
// config.php
// Configuración de la base de datos MySQL

// Parámetros de conexión
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Server431_');
define('DB_NAME', 'suite_ambiental');

// Crear conexión
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión
if ($mysqli->connect_error) {
    die("Error de conexión a la base de datos: " . $mysqli->connect_error);
}

// Opcional: fijar conjunto de caracteres
$mysqli->set_charset('utf8mb4');
