<?php
session_start();
header('Content-Type: application/json');
require_once('../includes/config.php');

// Logging para debug
error_log("eliminar_cliente.php iniciado");
error_log("POST data: " . print_r($_POST, true));

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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar que se recibió el ID del cliente
if (!isset($_POST['id']) || empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de cliente no proporcionado'
    ]);
    exit();
}

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
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }

    error_log("Conexión a DB establecida");

    $conn->begin_transaction();

    // Verificar si el cliente existe
    $sql_check = "SELECT id_cliente, nombre, apellido FROM clientes WHERE id_cliente = ? LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception('Error al preparar la consulta de verificación: ' . $conn->error);
    }
    $stmt_check->bind_param("i", $id_cliente);
    if (!$stmt_check->execute()) {
        throw new Exception('Error al ejecutar la consulta de verificación: ' . $stmt_check->error);
    }

    $stmt_check->bind_result($db_id, $nombre, $apellido);
    if (!$stmt_check->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'El cliente no existe o ya fue eliminado'
        ]);
        exit();
    }
    $stmt_check->close();

    // Obtener información de mascotas antes de eliminar
    $sql_mascotas = "SELECT nombre FROM mascotas WHERE id_cliente = ?";
    $stmt_mascotas = $conn->prepare($sql_mascotas);
    if (!$stmt_mascotas) {
        throw new Exception('Error al preparar consulta de mascotas: ' . $conn->error);
    }
    $stmt_mascotas->bind_param("i", $id_cliente);
    if (!$stmt_mascotas->execute()) {
        throw new Exception('Error al ejecutar consulta de mascotas: ' . $stmt_mascotas->error);
    }
    
    $nombres_mascotas = [];
    $result = $stmt_mascotas->get_result();
    while ($row = $result->fetch_assoc()) {
        $nombres_mascotas[] = $row['nombre'];
    }
    $stmt_mascotas->close();

    error_log("Procediendo con eliminación real");

    // Si llegamos aquí, procedemos con la eliminación real
    $conn->begin_transaction();

    // Verificar dependencias (solo citas, ya no importan las mascotas)
    $sql_deps = "SELECT COUNT(*) FROM citas WHERE id_cliente = ? AND fecha >= CURDATE()";
    $stmt_deps = $conn->prepare($sql_deps);
    if (!$stmt_deps) {
        throw new Exception('Error al preparar consulta de dependencias: ' . $conn->error);
    }
    $stmt_deps->bind_param("i", $id_cliente);
    if (!$stmt_deps->execute()) {
        throw new Exception('Error al ejecutar consulta de dependencias: ' . $stmt_deps->error);
    }

    $stmt_deps->bind_result($count_citas);
    $stmt_deps->fetch();
    $stmt_deps->close();

    if ($count_citas > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El cliente tiene citas programadas. Cancélelas primero.',
            'has_appointments' => true
        ]);
        exit();
    }

    // Eliminar mascotas del cliente primero (si las tiene)
    $count_mascotas = count($nombres_mascotas);
    if ($count_mascotas > 0) {
        $sql_delete_mascotas = "DELETE FROM mascotas WHERE id_cliente = ?";
        $stmt_delete_mascotas = $conn->prepare($sql_delete_mascotas);
        if (!$stmt_delete_mascotas) {
            throw new Exception('Error al preparar consulta de eliminación de mascotas: ' . $conn->error);
        }
        $stmt_delete_mascotas->bind_param("i", $id_cliente);
        if (!$stmt_delete_mascotas->execute()) {
            throw new Exception('Error al ejecutar eliminación de mascotas: ' . $stmt_delete_mascotas->error);
        }
        $stmt_delete_mascotas->close();
    }

    // Eliminar cliente
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

    $conn->commit();

    // Log de auditoría (si aplica)
    if (isset($_SESSION['user_id'])) {
        $accion = "Eliminación de cliente ID: $id_cliente - $nombre $apellido";
        // $conn->query("INSERT INTO logs (usuario_id, accion, fecha) VALUES ({$_SESSION['user_id']}, '$accion', NOW())");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cliente eliminado correctamente',
        'deleted_id' => $id_cliente,
        'client_name' => "$nombre $apellido",
        'deleted_pets' => $nombres_mascotas,
        'pets_count' => count($nombres_mascotas)
    ]);

} catch (Exception $e) {
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
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->close();
    }
}
