<?php
include('../includes/config.php');
include('../includes/pagination.php');

// Inicializar sistema de paginación
$pagination = new PaginationHelper($conn, 10);
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_fecha = isset($_GET['filtro_fecha']) ? $_GET['filtro_fecha'] : '';

// Manejar solicitudes AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'buscar_mascotas') {
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
        exit();
    }
    
    if ($_POST['action'] == 'buscar_productos') {
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
        exit();
    }
}

// Procesar formulario de nueva venta
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    $id_mascota = intval($_POST['id_mascota']);
    $items = json_decode($_POST['items'], true);
    $medio_pago = trim($_POST['medio_pago']);
    $total_general = floatval($_POST['total_general']);
    $fecha_venta = date('Y-m-d H:i:s');

    // Validaciones
    $errores = [];
    
    if ($id_mascota <= 0) {
        $errores[] = "Debe seleccionar una mascota válida";
    }
    
    if (empty($items) || !is_array($items)) {
        $errores[] = "Debe agregar al menos un producto o servicio";
    }
    
    if (empty($medio_pago)) {
        $errores[] = "Debe seleccionar un medio de pago";
    }

    // Verificar stock de productos
    foreach ($items as $item) {
        if ($item['tipo'] == 'producto') {
            $stock_sql = "SELECT stock, nombre FROM productos WHERE id_producto = ?";
            $stmt_stock = $conn->prepare($stock_sql);
            $stmt_stock->bind_param("i", $item['id']);
            $stmt_stock->execute();
            $producto = $stmt_stock->get_result()->fetch_assoc();
            $stmt_stock->close();
            
            if (!$producto) {
                $errores[] = "Producto no encontrado: ID " . $item['id'];
            } elseif ($producto['stock'] < $item['cantidad']) {
                $errores[] = "Stock insuficiente para: " . $producto['nombre'];
            }
        }
    }

    if (empty($errores)) {
        // Iniciar transacción
        $conn->begin_transaction();

        try {
            // Insertar cada item como una venta individual (según la estructura actual)
            foreach ($items as $item) {
                $subtotal = $item['cantidad'] * $item['precio'];
                
                $insert_venta_sql = "INSERT INTO ventas (id_mascota, tipo_item, id_item, cantidad, precio_unitario, subtotal, medio_pago, fecha_venta) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_venta = $conn->prepare($insert_venta_sql);
                $stmt_venta->bind_param("isiiddss", $id_mascota, $item['tipo'], $item['id'], $item['cantidad'], $item['precio'], $subtotal, $medio_pago, $fecha_venta);
                $stmt_venta->execute();
                $stmt_venta->close();

                // Actualizar stock solo para productos
                if ($item['tipo'] == 'producto') {
                    $update_stock_sql = "UPDATE productos SET stock = stock - ? WHERE id_producto = ?";
                    $stmt_stock = $conn->prepare($update_stock_sql);
                    $stmt_stock->bind_param("ii", $item['cantidad'], $item['id']);
                    $stmt_stock->execute();
                    $stmt_stock->close();
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Venta registrada correctamente']);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error al procesar la venta: ' . $e->getMessage()]);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => implode(', ', $errores)]);
        exit();
    }
}

// Obtener estadísticas para las tarjetas
$total_mascotas = $conn->query("SELECT COUNT(*) as total FROM mascotas")->fetch_assoc()['total'];
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock > 0")->fetch_assoc()['total'];
$total_servicios = $conn->query("SELECT COUNT(*) as total FROM servicios")->fetch_assoc()['total'];
$ventas_hoy = $conn->query("SELECT COUNT(*) as total FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetch_assoc()['total'];

// Obtener ventas para el listado
$where_conditions = [];
$params = [];

// Aplicar filtro de búsqueda si existe
if (!empty($buscar)) {
    $where_conditions[] = "(m.nombre LIKE ? OR CONCAT(c.nombre, ' ', c.apellido) LIKE ? OR p.nombre LIKE ? OR s.nombre LIKE ?)";
    $buscar_param = "%$buscar%";
    $params = array_fill(0, 4, $buscar_param);
}

// Aplicar filtro de fecha si existe
if (!empty($filtro_fecha)) {
    $where_conditions[] = "DATE(v.fecha_venta) = ?";
    $params[] = $filtro_fecha;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Consulta principal para obtener las ventas
$ventas_sql = "SELECT v.*, m.nombre as nombre_mascota, 
               CONCAT(c.nombre, ' ', c.apellido) as propietario,
               CASE 
                   WHEN v.tipo_item = 'producto' THEN p.nombre
                   WHEN v.tipo_item = 'servicio' THEN s.nombre
                   ELSE 'Item no encontrado'
               END as item_nombre,
               CASE 
                   WHEN v.tipo_item = 'producto' THEN 'Producto'
                   WHEN v.tipo_item = 'servicio' THEN 'Servicio'
                   ELSE v.tipo_item
               END as tipo_item_display
               FROM ventas v
               INNER JOIN mascotas m ON v.id_mascota = m.id_mascota
               INNER JOIN clientes c ON m.id_cliente = c.id_cliente
               LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
               LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio
               $where_clause
               ORDER BY v.fecha_venta DESC";

if (!empty($params)) {
    $stmt_ventas = $conn->prepare($ventas_sql);
    $types = str_repeat('s', count($params));
    $stmt_ventas->bind_param($types, ...$params);
    $stmt_ventas->execute();
    $result_ventas = $stmt_ventas->get_result();
} else {
    $result_ventas = $conn->query($ventas_sql);
}

$ventas = [];
if ($result_ventas) {
    while ($row = $result_ventas->fetch_assoc()) {
        $ventas[] = $row;
    }
}

// Obtener el total de ventas para estadísticas
$total_ventas_sql = "SELECT COUNT(*) as total, COALESCE(SUM(subtotal), 0) as total_monto FROM ventas";
$total_ventas_result = $conn->query($total_ventas_sql);
$total_ventas_data = $total_ventas_result->fetch_assoc();
$total_ventas = $total_ventas_data['total'];
$total_monto_ventas = $total_ventas_data['total_monto'];
?>

<div class="container-fluid px-4">
    <div class="ventas">
        <!-- Encabezado con estadísticas -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Gestión de Ventas</h2>
        </div>

    <!-- Tarjetas de Resumen -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Total Mascotas</h6>
                            <h4 class="mb-0"><?= $total_mascotas ?></h4>
                        </div>
                        <i class="fas fa-paw fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Productos Disponibles</h6>
                            <h4 class="mb-0"><?= $total_productos ?></h4>
                        </div>
                        <i class="fas fa-box-open fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Servicios</h6>
                            <h4 class="mb-0"><?= $total_servicios ?></h4>
                        </div>
                        <i class="fas fa-tools fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase">Ventas Hoy</h6>
                            <h4 class="mb-0"><?= $ventas_hoy ?></h4>
                        </div>
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario de Nueva Venta -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-cart-plus me-2"></i>Nueva Venta</h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Buscar Mascota <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="buscar_mascota" placeholder="Escriba el nombre de la mascota...">
                            <button class="btn btn-outline-secondary" type="button" id="btnLimpiarMascota" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="resultados_mascotas" class="list-group mt-2" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                        <input type="hidden" id="id_mascota" name="id_mascota">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Buscar Producto/Servicio <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="buscar_producto" placeholder="Escriba el nombre del producto o servicio...">
                        </div>
                        <div id="resultados_productos" class="list-group mt-2" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                        <input type="hidden" id="producto_seleccionado" name="producto_seleccionado">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cantidad <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="cantidad_item" value="1" min="1" placeholder="1">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label fw-bold">&nbsp;</label>
                        <button class="btn btn-primary form-control" type="button" id="btnAgregarItem">
                            <i class="fas fa-plus"></i> Añadir
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tipo y Medio de Pago -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Tipo<span class="text-danger">*</span></label>
                    <div class="mt-2">
                        <button type="button" class="btn btn-success btn-sm me-2" id="btnClinica">Clínica</button>
                        <button type="button" class="btn btn-success btn-sm me-2" id="btnFarmacia">Farmacia</button>
                        <button type="button" class="btn btn-success btn-sm" id="btnPetshop">Petshop</button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Medio de Pago <span class="text-danger">*</span></label>
                    <div class="mt-2">
                        <button type="button" class="btn btn-success btn-sm me-2" data-pago="Efectivo">Efectivo</button>
                        <button type="button" class="btn btn-success btn-sm me-2" data-pago="Yape">Yape</button>
                        <button type="button" class="btn btn-success btn-sm me-2" data-pago="Visa">Visa</button>
                        <button type="button" class="btn btn-success btn-sm" data-pago="Transferencia">Transferencia</button>
                    </div>
                    <input type="hidden" id="medio_pago" name="medio_pago">
                </div>
            </div>

            <!-- Información de la mascota seleccionada -->
            <div id="info_mascota" class="alert alert-info mb-4" style="display: none;">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Mascota:</strong> <span id="nombre_mascota"></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Propietario:</strong> <span id="propietario_mascota"></span>
                    </div>
                    <div class="col-md-4">
                        <strong>Especie:</strong> <span id="especie_mascota"></span>
                    </div>
                </div>
            </div>

            <!-- Tabla del carrito -->
            <div class="table-responsive mb-4">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Producto/Servicio</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Precio Unit.</th>
                            <th>Subtotal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="carrito_body">
                        <tr id="carrito_vacio">
                            <td colspan="6" class="text-center text-muted py-4">No hay productos en el carrito</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Botones de acción -->
            <div class="row">
                <div class="col-md-12 text-end">
                    <button type="button" class="btn btn-outline-secondary me-2" id="btnCancelar" style="display: none;">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnRegistrarVenta" disabled>
                        <i class="fas fa-shopping-cart me-1"></i> Registrar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Todas las Ventas -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-list me-2"></i>Todas las Ventas</h4>
            <div class="d-flex gap-2 align-items-center">
                <!-- Filtros -->
                <div class="input-group" style="width: 300px;">
                    <input type="text" class="form-control form-control-sm" id="buscar_ventas" placeholder="Buscar ventas..." value="<?= htmlspecialchars($buscar) ?>">
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="buscar_btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <input type="date" class="form-control form-control-sm" id="filtro_fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" style="width: 150px;">
                <button class="btn btn-outline-success btn-sm" id="exportar_excel">
                    <i class="fas fa-file-excel"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-12">
                    <p class="mb-0">Total de ventas: <strong><?= $total_ventas ?></strong> | Monto total: <strong>S/ <?= number_format($total_monto_ventas, 2) ?></strong></p>
                </div>
            </div>
            
            <!-- Vista móvil responsive -->
            <div class="d-md-none">
                <?php if (empty($ventas)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-shopping-cart fa-3x mb-3 d-block"></i>
                        No hay ventas registradas
                    </div>
                <?php else: ?>
                    <?php foreach ($ventas as $venta): ?>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-<?= $venta['tipo_item_display'] == 'Producto' ? 'box' : 'stethoscope' ?> text-<?= $venta['tipo_item_display'] == 'Producto' ? 'primary' : 'info' ?> me-2"></i>
                                        <strong><?= htmlspecialchars($venta['item_nombre']) ?></strong>
                                    </div>
                                    <span class="badge bg-<?= $venta['tipo_item_display'] == 'Producto' ? 'primary' : 'info' ?>">
                                        <?= $venta['tipo_item_display'] ?>
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <div class="d-flex align-items-center mb-1">
                                        <i class="fas fa-paw text-muted me-2"></i>
                                        <strong><?= htmlspecialchars($venta['nombre_mascota']) ?></strong>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user text-muted me-2"></i>
                                        <small class="text-muted"><?= htmlspecialchars($venta['propietario']) ?></small>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <small class="text-muted">Cantidad:</small><br>
                                        <span class="fw-bold"><?= $venta['cantidad'] ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Precio Unit.:</small><br>
                                        <span class="fw-bold">S/ <?= number_format($venta['precio_unitario'], 2) ?></span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">Total:</small><br>
                                        <strong class="text-success">S/ <?= number_format($venta['subtotal'], 2) ?></strong>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?= $venta['medio_pago'] == 'Efectivo' ? 'success' : ($venta['medio_pago'] == 'Tarjeta' ? 'primary' : ($venta['medio_pago'] == 'Yape' ? 'warning' : 'info')) ?> fs-6">
                                            <i class="fas fa-<?= $venta['medio_pago'] == 'Efectivo' ? 'money-bill' : ($venta['medio_pago'] == 'Tarjeta' ? 'credit-card' : ($venta['medio_pago'] == 'Yape' ? 'mobile-alt' : 'university')) ?> me-1"></i>
                                            <?= $venta['medio_pago'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></small>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-sm" onclick="verDetalleVenta(<?= $venta['id_venta'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-info btn-sm" onclick="imprimirTicket(<?= $venta['id_venta'] ?>)">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Vista desktop (tabla) -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-striped table-hover" id="tabla_ventas">
                        <tr>
                            <th><i class="fas fa-paw me-1"></i>Mascota / Propietario</th>
                            <th><i class="fas fa-box me-1"></i>Producto/Servicio</th>
                            <th><i class="fas fa-tag me-1"></i>Tipo</th>
                            <th class="text-center"><i class="fas fa-hashtag me-1"></i>Cantidad</th>
                            <th class="text-end"><i class="fas fa-money-bill me-1"></i>Precio Unit.</th>
                            <th class="text-end"><i class="fas fa-calculator me-1"></i>Subtotal</th>
                            <th><i class="fas fa-credit-card me-1"></i>Medio de Pago</th>
                            <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                            <th class="text-center"><i class="fas fa-cogs me-1"></i>Acciones</th>
                        </tr>
                    <tbody>
                        <?php if (empty($ventas)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-shopping-cart fa-3x mb-3 d-block"></i>
                                    No hay ventas registradas
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventas as $venta): ?>
                                <tr class="align-middle">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2">
                                                <i class="fas fa-paw text-primary"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($venta['nombre_mascota']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($venta['propietario']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-<?= $venta['tipo_item_display'] == 'Producto' ? 'box' : 'stethoscope' ?> text-<?= $venta['tipo_item_display'] == 'Producto' ? 'primary' : 'info' ?> me-2"></i>
                                            <strong><?= htmlspecialchars($venta['item_nombre']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $venta['tipo_item_display'] == 'Producto' ? 'primary' : 'info' ?> fs-6">
                                            <i class="fas fa-<?= $venta['tipo_item_display'] == 'Producto' ? 'box' : 'stethoscope' ?> me-1"></i>
                                            <?= $venta['tipo_item_display'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info fs-6"><?= $venta['cantidad'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-muted">S/ <?= number_format($venta['precio_unitario'], 2) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success fs-5">S/ <?= number_format($venta['subtotal'], 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $venta['medio_pago'] == 'Efectivo' ? 'success' : ($venta['medio_pago'] == 'Tarjeta' ? 'primary' : ($venta['medio_pago'] == 'Yape' ? 'warning' : 'info')) ?> fs-6">
                                            <i class="fas fa-<?= $venta['medio_pago'] == 'Efectivo' ? 'money-bill' : ($venta['medio_pago'] == 'Tarjeta' ? 'credit-card' : ($venta['medio_pago'] == 'Yape' ? 'mobile-alt' : 'university')) ?> me-1"></i>
                                            <?= $venta['medio_pago'] ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-primary"><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary btn-sm" onclick="verDetalleVenta(<?= $venta['id_venta'] ?>)" title="Ver detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-info btn-sm" onclick="imprimirTicket(<?= $venta['id_venta'] ?>)" title="Imprimir ticket">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JS para Ventas -->
<script>
    $(document).ready(function() {
        let carritoItems = [];
        let productoTemp = null;
        let tipoSeleccionado = '';
        let medioPagoSeleccionado = '';
        let timeoutMascota = null;
        let timeoutProducto = null;

        // Seleccionar tipo
        $('#btnClinica, #btnFarmacia, #btnPetshop').click(function() {
            tipoSeleccionado = $(this).text();
            $('#btnClinica, #btnFarmacia, #btnPetshop').removeClass('btn-primary').addClass('btn-success');
            $(this).removeClass('btn-success').addClass('btn-primary');
            validarFormulario();
        });

        // Seleccionar medio de pago
        $('[data-pago]').click(function() {
            medioPagoSeleccionado = $(this).data('pago');
            $('#medio_pago').val(medioPagoSeleccionado);
            $('[data-pago]').removeClass('btn-primary').addClass('btn-success');
            $(this).removeClass('btn-success').addClass('btn-primary');
            validarFormulario();
        });

        // Buscar mascotas
        $('#buscar_mascota').on('input', function() {
            const query = $(this).val().trim();
            
            // Limpiar timeout anterior
            if (timeoutMascota) {
                clearTimeout(timeoutMascota);
            }
            
            if (query.length >= 2) {
                // Mostrar indicador de carga
                $('#resultados_mascotas').html('<div class="list-group-item"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>').show();
                
                // Ejecutar búsqueda con delay
                timeoutMascota = setTimeout(function() {
                    $.ajax({
                        url: 'modules/ventas.php', // Cambia esto si es necesario
                        method: 'POST',
                        data: { 
                            action: 'buscar_mascotas',
                            query: query 
                        },
                        dataType: 'json',
                        success: function(data) {
                            let html = '';
                            // Verificar si la respuesta tiene errores
                            if (data.error) {
                                html = `<div class="list-group-item text-danger"><i class="fas fa-exclamation-triangle"></i> ${data.message}</div>`;
                            } else if (data.length > 0) {
                                data.forEach(function(mascota) {
                                    html += `<a href="#" class="list-group-item list-group-item-action seleccionar-mascota" 
                                             data-id="${mascota.id_mascota}" 
                                             data-nombre="${mascota.nombre_mascota}" 
                                             data-propietario="${mascota.propietario}" 
                                             data-especie="${mascota.especie}">
                                             <strong>${mascota.nombre_mascota}</strong> (${mascota.especie})<br>
                                             <small class="text-muted">Propietario: ${mascota.propietario}</small>
                                         </a>`;
                                });
                                $('#btnLimpiarMascota').show();
                            } else {
                                html = '<div class="list-group-item">No se encontraron mascotas con ese criterio</div>';
                            }
                            $('#resultados_mascotas').html(html).show();
                        },
                        error: function() {
                            $('#resultados_mascotas').html('<div class="list-group-item text-danger"><i class="fas fa-exclamation-triangle"></i> Error al buscar mascotas</div>').show();
                        }
                    });
                }, 300); // Delay de 300ms
            } else {
                $('#resultados_mascotas').hide();
                $('#btnLimpiarMascota').hide();
            }
        });

        // Seleccionar mascota
        $(document).on('click', '.seleccionar-mascota', function(e) {
            e.preventDefault();
            const mascota = $(this);
            
            $('#id_mascota').val(mascota.data('id'));
            $('#buscar_mascota').val(mascota.data('nombre'));
            $('#nombre_mascota').text(mascota.data('nombre'));
            $('#propietario_mascota').text(mascota.data('propietario'));
            $('#especie_mascota').text(mascota.data('especie'));
            
            $('#resultados_mascotas').hide();
            $('#info_mascota').show();
            validarFormulario();
        });

        // Limpiar búsqueda de mascota
        $('#btnLimpiarMascota').click(function() {
            $('#buscar_mascota').val('');
            $('#id_mascota').val('');
            $('#resultados_mascotas').hide();
            $('#info_mascota').hide();
            $(this).hide();
            validarFormulario();
        });

        // Buscar productos/servicios
        $('#buscar_producto').on('input', function() {
            const query = $(this).val().trim();
            
            // Limpiar timeout anterior
            if (timeoutProducto) {
                clearTimeout(timeoutProducto);
            }
            
            if (query.length >= 2) {
                // Mostrar indicador de carga
                $('#resultados_productos').html('<div class="list-group-item"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>').show();
                
                // Ejecutar búsqueda con delay
                timeoutProducto = setTimeout(function() {
                    $.ajax({
                        url: 'modules/ventas.php', // Cambia esto si es necesario
                        method: 'POST',
                        data: { 
                            action: 'buscar_productos',
                            query: query 
                        },
                        dataType: 'json',
                        success: function(data) {
                            let html = '';
                            // Verificar si la respuesta tiene errores
                            if (data.error) {
                                html = `<div class="list-group-item text-danger"><i class="fas fa-exclamation-triangle"></i> ${data.message}</div>`;
                            } else if (data.length > 0) {
                                data.forEach(function(item) {
                                    const stockInfo = item.tipo === 'producto' ? ` (Stock: ${item.stock})` : '';
                                    const stockBadge = item.tipo === 'producto' ? 
                                        (item.stock > 0 ? `<span class="badge bg-success ms-2">Stock: ${item.stock}</span>` : '<span class="badge bg-danger ms-2">Sin stock</span>') : 
                                        '<span class="badge bg-info ms-2">Servicio</span>';
                                    
                                    html += `<a href="#" class="list-group-item list-group-item-action seleccionar-producto" 
                                             data-id="${item.id}" 
                                             data-nombre="${item.nombre}" 
                                             data-precio="${item.precio}" 
                                             data-stock="${item.stock || 0}" 
                                             data-tipo="${item.tipo}">
                                             <div class="d-flex justify-content-between align-items-center">
                                                 <div>
                                                     <strong>${item.nombre}</strong><br>
                                                     <small class="text-muted">Precio: S/${parseFloat(item.precio).toFixed(2)}</small>
                                                 </div>
                                                 ${stockBadge}
                                             </div>
                                         </a>`;
                                });
                            } else {
                                html = '<div class="list-group-item">No se encontraron productos o servicios con ese criterio</div>';
                            }
                            $('#resultados_productos').html(html).show();
                        },
                        error: function() {
                            $('#resultados_productos').html('<div class="list-group-item text-danger"><i class="fas fa-exclamation-triangle"></i> Error al buscar productos</div>').show();
                        }
                    });
                }, 300); // Delay de 300ms
            } else {
                $('#resultados_productos').hide();
            }
        });

        // Seleccionar producto
        $(document).on('click', '.seleccionar-producto', function(e) {
            e.preventDefault();
            const producto = $(this);
            
            productoTemp = {
                id: producto.data('id'),
                nombre: producto.data('nombre'),
                precio: parseFloat(producto.data('precio')),
                stock: parseInt(producto.data('stock')) || 0,
                tipo: producto.data('tipo')
            };
            
            $('#buscar_producto').val(producto.data('nombre'));
            $('#resultados_productos').hide();
        });

        // Añadir item al carrito
        $('#btnAgregarItem').click(function() {
            // Validar que se haya seleccionado tipo
            if (!tipoSeleccionado) {
                Swal.fire('Error', 'Debe seleccionar un tipo (Clínica, Farmacia o Petshop) primero', 'error');
                return;
            }
            
            // Validar que se haya seleccionado medio de pago
            if (!medioPagoSeleccionado) {
                Swal.fire('Error', 'Debe seleccionar un medio de pago primero', 'error');
                return;
            }
            
            if (!productoTemp) {
                Swal.fire('Error', 'Debe seleccionar un producto o servicio primero', 'error');
                return;
            }
            
            const cantidad = parseInt($('#cantidad_item').val()) || 1;
            
            if (cantidad < 1) {
                Swal.fire('Error', 'La cantidad debe ser mayor a 0', 'error');
                return;
            }
            
            // Validar stock para productos
            if (productoTemp.tipo === 'producto' && cantidad > productoTemp.stock) {
                Swal.fire('Error', `Stock insuficiente. Stock disponible: ${productoTemp.stock}`, 'error');
                return;
            }
            
            // Verificar si el item ya existe en el carrito
            const itemExistente = carritoItems.find(item => item.id === productoTemp.id && item.tipo === productoTemp.tipo);
            
            if (itemExistente) {
                const nuevaCantidad = itemExistente.cantidad + cantidad;
                if (productoTemp.tipo === 'producto' && nuevaCantidad > productoTemp.stock) {
                    Swal.fire('Error', `Stock insuficiente. Stock disponible: ${productoTemp.stock}`, 'error');
                    return;
                }
                itemExistente.cantidad = nuevaCantidad;
                Swal.fire('Éxito', 'Cantidad actualizada en el carrito', 'success');
            } else {
                carritoItems.push({
                    id: productoTemp.id,
                    nombre: productoTemp.nombre,
                    precio: productoTemp.precio,
                    cantidad: cantidad,
                    tipo: productoTemp.tipo,
                    stock: productoTemp.stock
                });
                Swal.fire('Éxito', 'Producto agregado al carrito', 'success');
            }
            
            actualizarTablaCarrito();
            
            // Limpiar campos
            $('#buscar_producto').val('');
            $('#cantidad_item').val('1');
            $('#resultados_productos').hide();
            productoTemp = null;
            validarFormulario();
        });

        // Editar venta (cambiar cantidad)
        $(document).on('click', '.editar_venta', function() {
            const index = $(this).data('index');
            const item = carritoItems[index];
            
            Swal.fire({
                title: 'Editar Cantidad',
                input: 'number',
                inputValue: item.cantidad,
                inputAttributes: {
                    min: 1,
                    max: item.tipo === 'producto' ? item.stock : 999,
                    step: 1
                },
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save"></i> Guardar',
                confirmButtonColor: '#ffc107',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    const cantidad = parseInt(value);
                    if (!cantidad || cantidad < 1) {
                        return 'Debe ingresar una cantidad válida';
                    }
                    if (item.tipo === 'producto' && cantidad > item.stock) {
                        return `Stock insuficiente. Máximo: ${item.stock}`;
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    carritoItems[index].cantidad = parseInt(result.value);
                    actualizarTablaCarrito();
                    validarFormulario();
                }
            });
        });

        // Eliminar venta
        $(document).on('click', '.eliminar_venta', function() {
            const index = $(this).data('index');
            Swal.fire({
                title: '¿Está seguro?',
                text: 'Esta acción eliminará el item del carrito',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    carritoItems.splice(index, 1);
                    actualizarTablaCarrito();
                    validarFormulario();
                }
            });
        });

        // Imprimir venta (función placeholder)
        $(document).on('click', '.imprimir_venta', function() {
            const index = $(this).data('index');
            const item = carritoItems[index];
            Swal.fire({
                title: 'Imprimir Item',
                text: `${item.nombre} - Cantidad: ${item.cantidad}`,
                icon: 'info',
                confirmButtonText: 'Cerrar'
            });
        });

        // Actualizar tabla del carrito
        function actualizarTablaCarrito() {
            const tbody = $('#carrito_body');
            
            if (carritoItems.length === 0) {
                tbody.html('<tr id="carrito_vacio"><td colspan="6" class="text-center text-muted py-4">No hay productos en el carrito</td></tr>');
                // Ocultar botón cancelar cuando no hay items con animación
                $('#btnCancelar').fadeOut(300);
            } else {
                let html = '';
                carritoItems.forEach((item, index) => {
                    const subtotal = item.cantidad * item.precio;
                    html += `
                        <tr>
                            <td>${item.nombre}</td>
                            <td><span class="badge bg-${item.tipo === 'producto' ? 'primary' : 'success'}">${item.tipo}</span></td>
                            <td>${item.cantidad}</td>
                            <td>S/ ${item.precio.toFixed(2)}</td>
                            <td>S/ ${subtotal.toFixed(2)}</td>
                            <td>
                                <button class="btn btn-warning btn-sm me-1 editar_venta" data-index="${index}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm me-1 eliminar_venta" data-index="${index}" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-info btn-sm imprimir_venta" data-index="${index}" title="Imprimir">
                                    <i class="fas fa-print"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.html(html);
                // Mostrar botón cancelar cuando hay items con animación
                $('#btnCancelar').fadeIn(300);
            }
        }

        // Validar formulario
        function validarFormulario() {
            const mascotaId = $('#id_mascota').val();
            const medioPago = $('#medio_pago').val();
            const tieneItems = carritoItems.length > 0;
            const tipoValido = tipoSeleccionado !== '';
            
            const esValido = mascotaId && medioPago && tieneItems && tipoValido;
            $('#btnRegistrarVenta').prop('disabled', !esValido);
        }

        // Registrar venta
        $('#btnRegistrarVenta').click(function() {
            if (carritoItems.length === 0) {
                Swal.fire('Error', 'Debe agregar al menos un producto o servicio', 'error');
                return;
            }

            const formData = {
                id_mascota: $('#id_mascota').val(),
                items: JSON.stringify(carritoItems),
                medio_pago: $('#medio_pago').val(),
                total_general: carritoItems.reduce((total, item) => total + (item.cantidad * item.precio), 0)
            };

            $.ajax({
                url: 'modules/ventas.php', // Cambia esto si es necesario
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Éxito', response.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Error al procesar la venta', 'error');
                }
            });
        });

        // Cancelar venta
        $('#btnCancelar').click(function() {
            if (carritoItems.length > 0) {
                Swal.fire({
                    title: '¿Está seguro?',
                    text: 'Se perderán todos los datos ingresados',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, cancelar',
                    cancelButtonText: 'No'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Limpiar carrito y resetear formulario
                        carritoItems = [];
                        actualizarTablaCarrito();
                        validarFormulario();
                        
                        // Limpiar campos del formulario
                        $('#buscar_mascota').val('');
                        $('#buscar_producto').val('');
                        $('#cantidad_item').val('1');
                        $('#id_mascota').val('');
                        $('#info_mascota').hide();
                        $('#resultados_mascotas').hide();
                        $('#resultados_productos').hide();
                        productoTemp = null;
                        
                        // Resetear selecciones de tipo y medio de pago
                        tipoSeleccionado = '';
                        medioPagoSeleccionado = '';
                        $('#medio_pago').val('');
                        $('#btnClinica, #btnFarmacia, #btnPetshop').removeClass('btn-primary').addClass('btn-success');
                        $('[data-pago]').removeClass('btn-primary').addClass('btn-success');
                        
                        Swal.fire('Cancelado', 'Se ha limpiado el formulario', 'success');
                    }
                });
            } else {
                // Si no hay items, simplemente ocultar el botón con animación
                $('#btnCancelar').fadeOut(300);
            }
        });

        // Funciones para el listado de ventas
        $('#buscar_btn').click(function() {
            const buscar = $('#buscar_ventas').val();
            const fecha = $('#filtro_fecha').val();
            let url = 'index.php?module=ventas';
            
            if (buscar || fecha) {
                url += '&';
                if (buscar) url += 'buscar=' + encodeURIComponent(buscar);
                if (buscar && fecha) url += '&';
                if (fecha) url += 'filtro_fecha=' + fecha;
            }
            
            window.location.href = url;
        });

        $('#buscar_ventas').keypress(function(e) {
            if (e.which == 13) {
                $('#buscar_btn').click();
            }
        });

        $('#filtro_fecha').change(function() {
            $('#buscar_btn').click();
        });

        $('#exportar_excel').click(function() {
            window.location.href = 'modules/exportar_excel.php?tipo=ventas';
        });

        // Funciones para acciones de ventas
        window.verDetalleVenta = function(id) {
            Swal.fire({
                title: 'Detalle de Venta #' + id,
                html: 'Cargando información...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Aquí podrías hacer una llamada AJAX para obtener más detalles
            setTimeout(() => {
                Swal.update({
                    title: 'Detalle de Venta #' + id,
                    html: '<p>Información detallada de la venta.</p>',
                    showCloseButton: true,
                    showConfirmButton: false
                });
                Swal.hideLoading();
            }, 1000);
        };

        window.imprimirTicket = function(id) {
            Swal.fire({
                title: '¿Imprimir Ticket?',
                text: 'Se imprimirá el ticket de la venta',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-print"></i> Imprimir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aquí podrías abrir una ventana de impresión
                    window.open('modules/imprimir_ticket.php?id=' + id, '_blank', 'width=300,height=600');
                }
            });
        };
    });
</script>
    </div>
</div>
