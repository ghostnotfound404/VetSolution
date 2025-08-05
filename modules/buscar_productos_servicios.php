<?php
include('../includes/config.php');

header('Content-Type: application/json');

// Inicializar respuesta de error por defecto
$response = ['error' => false, 'message' => '', 'data' => []];

try {
    if (isset($_POST['query']) && !empty($_POST['query'])) {
        $termino = trim($_POST['query']);
        
        if (strlen($termino) >= 2) {
            $resultados = [];
            $searchTerm = "%$termino%";
            
            // Buscar productos
            $sql_productos = "SELECT id_producto as id, nombre, precio, stock, 'producto' as tipo
                             FROM productos 
                             WHERE stock > 0 AND (nombre LIKE ? OR descripcion LIKE ?)
                             ORDER BY nombre 
                             LIMIT 10";
            
            $stmt_productos = $conn->prepare($sql_productos);
            if (!$stmt_productos) {
                throw new Exception("Error preparando consulta de productos: " . $conn->error);
            }
            
            $stmt_productos->bind_param("ss", $searchTerm, $searchTerm);
            if (!$stmt_productos->execute()) {
                throw new Exception("Error ejecutando consulta de productos: " . $stmt_productos->error);
            }
            
            $productos = $stmt_productos->get_result();
            while ($producto = $productos->fetch_assoc()) {
                $resultados[] = $producto;
            }
            $stmt_productos->close();
            
            // Buscar servicios
            $sql_servicios = "SELECT id_servicio as id, nombre, precio, NULL as stock, 'servicio' as tipo
                             FROM servicios 
                             WHERE nombre LIKE ? OR descripcion LIKE ?
                             ORDER BY nombre 
                             LIMIT 10";
            
            $stmt_servicios = $conn->prepare($sql_servicios);
            if (!$stmt_servicios) {
                throw new Exception("Error preparando consulta de servicios: " . $conn->error);
            }
            
            $stmt_servicios->bind_param("ss", $searchTerm, $searchTerm);
            if (!$stmt_servicios->execute()) {
                throw new Exception("Error ejecutando consulta de servicios: " . $stmt_servicios->error);
            }
            
            $servicios = $stmt_servicios->get_result();
            while ($servicio = $servicios->fetch_assoc()) {
                $resultados[] = $servicio;
            }
            $stmt_servicios->close();
            
            // Ordenar por nombre
            usort($resultados, function($a, $b) {
                return strcmp($a['nombre'], $b['nombre']);
            });
            
            $response['data'] = array_slice($resultados, 0, 10);
        } else {
            $response['data'] = [];
        }
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

$conn->close();
?>
