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

            // Registrar en caja el total general
            $descripcion_caja = "Venta - Mascota ID: $id_mascota";
            $insert_caja_sql = "INSERT INTO caja (tipo, monto, descripcion, medio_pago, fecha) VALUES ('ingreso', ?, ?, ?, ?)";
            $stmt_caja = $conn->prepare($insert_caja_sql);
            $stmt_caja->bind_param("dsss", $total_general, $descripcion_caja, $medio_pago, $fecha_venta);
            $stmt_caja->execute();
            $stmt_caja->close();

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
                    <button type="button" class="btn btn-outline-secondary me-2" id="btnCancelar">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnRegistrarVenta" disabled>
                        <i class="fas fa-shopping-cart me-1"></i> Registrar Venta
                    </button>
                </div>
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
                                                     <small class="text-muted">Precio: $${parseFloat(item.precio).toFixed(2)}</small>
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
            } else {
                let html = '';
                carritoItems.forEach((item, index) => {
                    const subtotal = item.cantidad * item.precio;
                    html += `
                        <tr>
                            <td>${item.nombre}</td>
                            <td><span class="badge bg-${item.tipo === 'producto' ? 'primary' : 'success'}">${item.tipo}</span></td>
                            <td>${item.cantidad}</td>
                            <td>$${item.precio.toFixed(2)}</td>
                            <td>$${subtotal.toFixed(2)}</td>
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
            }
        }

        // Validar formulario
        function validarFormulario() {
            const mascotaId = $('#id_mascota').val();
            const medioPago = $('#medio_pago').val();
            const tieneItems = carritoItems.length > 0;
            
            const esValido = mascotaId && medioPago && tieneItems;
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
                        location.reload();
                    }
                });
            } else {
                location.reload();
            }
        });
    });
</script>
    </div>
</div>
