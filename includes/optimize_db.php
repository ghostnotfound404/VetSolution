<?php
include('config.php');
include('pagination.php');

// Optimizar la base de datos
optimizeDatabase($conn);

echo "Base de datos optimizada correctamente con índices para mejorar el rendimiento.";
?>
