<?php
include('../includes/config.php');

header('Content-Type: application/json');

if (isset($_GET['termino']) && !empty(trim($_GET['termino']))) {
    $termino = trim($_GET['termino']);
    
    $sql = "SELECT m.id_mascota, m.nombre, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
            FROM mascotas m 
            JOIN clientes c ON m.id_cliente = c.id_cliente 
            WHERE m.nombre LIKE ? 
               OR c.nombre LIKE ? 
               OR c.apellido LIKE ?
               OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
            ORDER BY c.nombre, c.apellido, m.nombre
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $searchTerm = "%$termino%";
    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mascotas = [];
    while ($row = $result->fetch_assoc()) {
        $mascotas[] = $row;
    }
    
    echo json_encode($mascotas);
    $stmt->close();
} else {
    echo json_encode([]);
}

$conn->close();
?>
