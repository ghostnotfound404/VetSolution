<?php
// Iniciar sesión para auditoría
session_start();

// Configurar cabeceras para respuesta JSON
header('Content-Type: application/json');

// Incluir configuración de base de datos
require_once('../includes/config.php');

// Verificar que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Se requiere POST'
    ]);
    exit();
}

// Verificar permisos del usuario (descomentar si es necesario)
// Aquí puedes activar la validación de roles de usuario como administrador
/*
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para realizar esta acción'
    ]);
    exit();
}
*/

// Verificar que se recibió el ID del cliente
if (!isset($_POST['id']) || empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de cliente no proporcionado'
    ]);
    exit();
}

// Sanitizar y validar el ID
$id_cliente = filter_var($_POST['id'], FILTER_VALIDATE_INT);
if ($id_cliente === false || $id_cliente <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de cliente no válido'
    ]);
    exit();
}

try {
    // Verificar conexión a la base de datos
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }

    // Iniciar transacción
    $conn->begin_transaction();

    // 1. Verificar si el cliente existe
    $sql_check = "SELECT id_cliente, nombre, apellido FROM clientes WHERE id_cliente = ? LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    
    if (!$stmt_check) {
        throw new Exception('Error al preparar la consulta de verificación: ' . $conn->error);
    }
    
    $stmt_check->bind_param("i", $id_cliente);
    if (!$stmt_check->execute()) {
        throw new Exception('Error al ejecutar la consulta de verificación: ' . $stmt_check->error);
    }
    
    $result = $stmt_check->get_result();
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'El cliente no existe o ya fue eliminado'
        ]);
        exit();
    }
    
    $cliente = $result->fetch_assoc();
    $stmt_check->close();

    // 2. Verificar dependencias (mascotas y citas)
    $sql_deps = "SELECT 
                (SELECT COUNT(*) FROM mascotas WHERE id_propietario = ?) as mascotas,
                (SELECT COUNT(*) FROM citas WHERE id_cliente = ? AND fecha >= CURDATE()) as citas";
    
    $stmt_deps = $conn->prepare($sql_deps);
    if (!$stmt_deps) {
        throw new Exception('Error al preparar consulta de dependencias: ' . $conn->error);
    }
    
    $stmt_deps->bind_param("ii", $id_cliente, $id_cliente);
    if (!$stmt_deps->execute()) {
        throw new Exception('Error al ejecutar consulta de dependencias: ' . $stmt_deps->error);
    }
    
    $deps = $stmt_deps->get_result()->fetch_assoc();
    $stmt_deps->close();
    
    // Verificar mascotas
    if ($deps['mascotas'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El cliente tiene mascotas registradas. Elimínelas primero.',
            'has_pets' => true
        ]);
        exit();
    }
    
    // Verificar citas
    if ($deps['citas'] > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El cliente tiene citas programadas. Cancélelas primero.',
            'has_appointments' => true
        ]);
        exit();
    }

    // 3. Eliminar el cliente
    $sql_delete = "DELETE FROM clientes WHERE id_cliente = ? LIMIT 1";
    $stmt_delete = $conn->prepare($sql_delete);
    
    if (!$stmt_delete) {
        throw new Exception('Error al preparar consulta de eliminación: ' . $conn->error);
    }
    
    $stmt_delete->bind_param("i", $id_cliente);
    if (!$stmt_delete->execute()) {
        throw new Exception('Error al ejecutar eliminación: ' . $stmt_delete->error);
    }
    
    if ($stmt_delete->affected_rows === 0) {
        throw new Exception('No se eliminó ningún registro');
    }
    
    $stmt_delete->close();

    // Confirmar transacción
    $conn->commit();

    // Registrar acción en log si es necesario
    if (isset($_SESSION['user_id'])) {
        $accion = "Eliminación de cliente ID: $id_cliente - " . $cliente['nombre'] . ' ' . $cliente['apellido'];
        // Ejemplo: guardar en tabla de logs
        // $conn->query("INSERT INTO logs (usuario_id, accion, fecha) VALUES ({$_SESSION['user_id']}, '$accion', NOW())");
    }

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Cliente eliminado correctamente',
        'deleted_id' => $id_cliente,
        'client_name' => $cliente['nombre'] . ' ' . $cliente['apellido']
    ]);

} catch (Exception $e) {
    // Revertir en caso de error
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor',
        'error' => $e->getMessage()
    ]);
    
} finally {
    // Cerrar conexión
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->close();
    }
}
