<?php
// archivo de configuración
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Server431_');
define('DB_NAME', 'suite_ambiental');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Eliminamos la instrucción die para permitir que index.php maneje el error
// if ($mysqli->connect_error) {
//     die("Error de conexión a la base de datos: " . $mysqli->connect_error);
// }

$mysqli->set_charset('utf8mb4');
