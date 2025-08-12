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

    // Iniciamos una única transacción
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

    // Verificar si el cliente tiene mascotas y bloquear eliminación
    if (!empty($nombres_mascotas)) {
        http_response_code(400);
        
        // Crear un mensaje informativo con los nombres de las mascotas
        $mascotasLista = implode(", ", $nombres_mascotas);
        
        echo json_encode([
            'success' => false,
            'message' => 'Este cliente tiene mascotas registradas (' . $mascotasLista . '). Debe eliminar primero las mascotas antes de eliminar al cliente.',
            'has_pets' => true,
            'pet_names' => $nombres_mascotas
        ]);
        exit();
    }

    error_log("Procediendo con eliminación real");
    
    // Verificar posibles tablas con relaciones que no hemos considerado
    $posibles_tablas_relacionadas = ["ventas", "historia_clinica", "hospitalizaciones", "citas"];
    $tablas_con_registros = [];
    
    foreach ($posibles_tablas_relacionadas as $tabla) {
        try {
            // Verificar si la tabla existe
            $tabla_existe = $conn->query("SHOW TABLES LIKE '$tabla'")->num_rows > 0;
            
            if ($tabla_existe) {
                // Verificar si hay registros relacionados con este cliente
                $check_sql = "SELECT COUNT(*) as total FROM $tabla WHERE id_cliente = ?";
                $check_stmt = $conn->prepare($check_sql);
                if ($check_stmt) {
                    $check_stmt->bind_param("i", $id_cliente);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    if ($row['total'] > 0) {
                        $tablas_con_registros[] = "$tabla ({$row['total']} registros)";
                    }
                    
                    $check_stmt->close();
                }
            }
        } catch (Exception $ex) {
            error_log("Error al verificar relaciones en tabla $tabla: " . $ex->getMessage());
        }
    }
    
    // Si hay tablas relacionadas, informar y detener la eliminación
    if (!empty($tablas_con_registros)) {
        $mensaje = "No se puede eliminar el cliente porque tiene registros en: " . implode(", ", $tablas_con_registros);
        error_log($mensaje);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $mensaje,
            'has_dependencies' => true,
            'dependencies' => $tablas_con_registros
        ]);
        exit();
    }

    // Verificar si la tabla citas existe antes de consultar
    $tableExists = false;
    $checkTable = $conn->query("SHOW TABLES LIKE 'citas'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $tableExists = true;
    }
    
    // Solo verificar dependencias si la tabla existe
    $count_citas = 0;
    if ($tableExists) {
        // Verificar dependencias (citas)
        try {
            $sql_deps = "SELECT COUNT(*) FROM citas WHERE id_cliente = ? AND fecha >= CURDATE()";
            $stmt_deps = $conn->prepare($sql_deps);
            if (!$stmt_deps) {
                error_log("Error al preparar consulta de dependencias: " . $conn->error);
            } else {
                $stmt_deps->bind_param("i", $id_cliente);
                if (!$stmt_deps->execute()) {
                    error_log("Error al ejecutar consulta de dependencias: " . $stmt_deps->error);
                } else {
                    $stmt_deps->bind_result($count_citas);
                    $stmt_deps->fetch();
                    $stmt_deps->close();
                }
            }
        } catch (Exception $ex) {
            error_log("Error al verificar citas: " . $ex->getMessage());
            // Continuamos con la eliminación si hay error en esta parte
        }
    }
    
    if ($count_citas > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'El cliente tiene citas programadas. Cancélelas primero.',
            'has_appointments' => true
        ]);
        exit();
    }

    // Ya validamos que no tenga mascotas, así que podemos proceder directamente con la eliminación del cliente
    
    // Eliminar cliente
    try {
        $sql_delete = "DELETE FROM clientes WHERE id_cliente = ? LIMIT 1";
        $stmt_delete = $conn->prepare($sql_delete);
        if (!$stmt_delete) {
            throw new Exception('Error al preparar consulta de eliminación: ' . $conn->error);
        }
        $stmt_delete->bind_param("i", $id_cliente);
        
        // Añadir mensajes de depuración
        error_log("Ejecutando DELETE para cliente ID: " . $id_cliente);
        
        if (!$stmt_delete->execute()) {
            $error = $stmt_delete->error;
            
            // Verificar si es un error de clave externa
            if (stripos($error, "foreign key constraint") !== false) {
                throw new Exception('No se puede eliminar el cliente porque tiene registros relacionados. Error: ' . $error);
            } else {
                throw new Exception('Error al ejecutar eliminación: ' . $error);
            }
        }

        $affected = $stmt_delete->affected_rows;
        error_log("Filas afectadas por DELETE: " . $affected);
        
        if ($affected === 0) {
            throw new Exception('No se eliminó ningún registro. El cliente posiblemente ya fue eliminado.');
        }
        $stmt_delete->close();
    } catch (Exception $deleteEx) {
        error_log("Error en la eliminación: " . $deleteEx->getMessage());
        throw $deleteEx; // Re-lanzamos la excepción para que se maneje en el bloque catch principal
    }

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
    
    // Registrar el error en el log para diagnóstico
    error_log("Error en eliminar_cliente.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->close();
    }
}