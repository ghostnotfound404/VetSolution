<?php
define('DB_HOST', 'localhost');      // Dirección del servidor MySQL (usualmente 'localhost')
define('DB_USER', 'root');           // Usuario de la base de datos
define('DB_PASS', '');              // Contraseña del usuario de la base de datos
define('DB_PORT', 3306);              // Contraseña del usuario de la base de datos
define('DB_NAME', 'vetapp_db');    // Nombre de la base de datos

// Crear la conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Comprobar la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>
