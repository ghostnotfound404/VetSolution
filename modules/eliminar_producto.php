<?php
include('../includes/config.php');

// Configurar cabecera para respuestas JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar que se ha recibido el ID del producto
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID del producto no proporcionado']);
    exit();
}

$id_producto = intval($_POST['id']);

try {
    // Primero verificamos si el producto existe
    $check_query = "SELECT id_producto FROM productos WHERE id_producto = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $id_producto);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit();
    }
    
    $check_stmt->close();
    
    // Procedemos a eliminar el producto
    $delete_query = "DELETE FROM productos WHERE id_producto = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("i", $id_producto);
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo eliminar el producto']);
        }
    } else {
        throw new Exception($delete_stmt->error);
    }
    
    $delete_stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
