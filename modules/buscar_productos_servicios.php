<?php
include('../includes/config.php');

header('Content-Type: application/json');

if (isset($_GET['termino']) && !empty($_GET['termino'])) {
    $termino = trim($_GET['termino']);
    
    if (strlen($termino) >= 2) {
        // Buscar productos
        $sql_productos = "SELECT id_producto as id, nombre, precio, stock, 'producto' as tipo
                         FROM productos 
                         WHERE stock > 0 AND (nombre LIKE ? OR descripcion LIKE ?)
                         ORDER BY nombre 
                         LIMIT 10";
        
        // Buscar servicios
        $sql_servicios = "SELECT id_servicio as id, nombre, precio, NULL as stock, 'servicio' as tipo
                         FROM servicios 
                         WHERE nombre LIKE ? OR descripcion LIKE ?
                         ORDER BY nombre 
                         LIMIT 10";
        
        $searchTerm = "%$termino%";
        $resultados = [];
        
        // Ejecutar búsqueda de productos
        $stmt_productos = $conn->prepare($sql_productos);
        $stmt_productos->bind_param("ss", $searchTerm, $searchTerm);
        $stmt_productos->execute();
        $productos = $stmt_productos->get_result();
        
        while ($producto = $productos->fetch_assoc()) {
            $resultados[] = $producto;
        }
        $stmt_productos->close();
        
        // Ejecutar búsqueda de servicios
        $stmt_servicios = $conn->prepare($sql_servicios);
        $stmt_servicios->bind_param("ss", $searchTerm, $searchTerm);
        $stmt_servicios->execute();
        $servicios = $stmt_servicios->get_result();
        
        while ($servicio = $servicios->fetch_assoc()) {
            $resultados[] = $servicio;
        }
        $stmt_servicios->close();
        
        // Ordenar por nombre
        usort($resultados, function($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });
        
        echo json_encode(array_slice($resultados, 0, 10));
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}

$conn->close();
?>
