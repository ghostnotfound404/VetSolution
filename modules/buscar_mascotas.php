<?php
include('../includes/config.php');

header('Content-Type: application/json');

// Inicializar respuesta de error por defecto
$response = ['error' => false, 'message' => '', 'data' => []];

try {
    if (isset($_POST['query']) && !empty(trim($_POST['query']))) {
        $termino = trim($_POST['query']);
        
        $sql = "SELECT m.id_mascota, m.nombre as nombre_mascota, m.especie,
                       CONCAT(c.nombre, ' ', c.apellido) as propietario
                FROM mascotas m 
                JOIN clientes c ON m.id_cliente = c.id_cliente 
                WHERE m.nombre LIKE ? 
                   OR c.nombre LIKE ? 
                   OR c.apellido LIKE ?
                   OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
                ORDER BY c.nombre, c.apellido, m.nombre
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando consulta: " . $conn->error);
        }
        
        $searchTerm = "%$termino%";
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        
        if (!$stmt->execute()) {
            throw new Exception("Error ejecutando consulta: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $mascotas = [];
        
        while ($row = $result->fetch_assoc()) {
            $mascotas[] = $row;
        }
        
        $response['data'] = $mascotas;
        $stmt->close();
    } else {
        $response['data'] = [];
    }
} catch (Exception $e) {
    $response['error'] = true;
    $response['message'] = $e->getMessage();
}

echo json_encode($response['error'] ? $response : $response['data']);
$conn->close();
?>