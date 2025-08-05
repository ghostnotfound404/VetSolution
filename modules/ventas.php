<?php
include('../includes/config.php');

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
            $total_calculado = 0;
            
            // Registrar cada item de la venta
            foreach ($items as $item) {
                $subtotal = $item['cantidad'] * $item['precio'];
                $total_calculado += $subtotal;
                
                // Insertar en ventas
                $insert_sql = "INSERT INTO ventas (id_mascota, tipo_item, id_item, cantidad, precio_unitario, subtotal, medio_pago, fecha_venta) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("isiiddss", $id_mascota, $item['tipo'], $item['id'], $item['cantidad'], $item['precio'], $subtotal, $medio_pago, $fecha_venta);
                $stmt->execute();
                $stmt->close();
                
                // Si es producto, actualizar stock
                if ($item['tipo'] == 'producto') {
                    $update_stock_sql = "UPDATE productos SET stock = stock - ? WHERE id_producto = ?";
                    $stmt_update = $conn->prepare($update_stock_sql);
                    $stmt_update->bind_param("ii", $item['cantidad'], $item['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            }
            
            // Registrar en caja (ingresos)
            $concepto = "Venta - Mascota ID: $id_mascota";
            $insert_caja_sql = "INSERT INTO caja (tipo, concepto, monto, medio_pago, fecha) VALUES ('ingreso', ?, ?, ?, ?)";
            $stmt_caja = $conn->prepare($insert_caja_sql);
            $stmt_caja->bind_param("sdss", $concepto, $total_calculado, $medio_pago, $fecha_venta);
            $stmt_caja->execute();
            $stmt_caja->close();

            // Confirmar transacción
            $conn->commit();

            echo json_encode([
                'success' => true, 
                'message' => 'Venta registrada correctamente',
                'total' => $total_calculado
            ]);
            exit;
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            
            echo json_encode([
                'success' => false, 
                'message' => 'Error al registrar la venta: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => implode('\n', $errores)
        ]);
        exit;
    }
}

// Obtener estadísticas para las tarjetas
$total_mascotas = $conn->query("SELECT COUNT(*) as total FROM mascotas")->fetch_assoc()['total'];
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock > 0")->fetch_assoc()['total'];
$total_servicios = $conn->query("SELECT COUNT(*) as total FROM servicios")->fetch_assoc()['total'];
$ventas_hoy = $conn->query("SELECT COUNT(*) as total FROM ventas WHERE DATE(fecha_venta) = CURDATE()")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - VetSolution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-stat {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-stat .card-body {
            padding: 1.5rem;
        }
        .card-stat h5 {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .card-stat h3 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        .search-box {
            border-radius: 20px;
            padding: 1.5rem;
            background-color: #f8f9fa;
        }
        .table-cart {
            border-radius: 10px;
            overflow: hidden;
        }
        .table-cart thead th {
            background-color: #0d6efd;
            color: white;
        }
        .badge-product {
            background-color: #0d6efd;
        }
        .badge-service {
            background-color: #198754;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-0"><i class="bi bi-cart me-2"></i>Gestión de Ventas</h2>
            </div>
        </div>

        <!-- Tarjetas de estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card card-stat border-0">
                    <div class="card-body text-center">
                        <h5>Total Mascotas</h5>
                        <h3><?= $total_mascotas ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat border-0">
                    <div class="card-body text-center">
                        <h5>Productos Disponibles</h5>
                        <h3><?= $total_productos ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat border-0">
                    <div class="card-body text-center">
                        <h5>Servicios</h5>
                        <h3><?= $total_servicios ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat border-0">
                    <div class="card-body text-center">
                        <h5>Ventas Hoy</h5>
                        <h3><?= $ventas_hoy ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de Nueva Venta -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="mb-4"><i class="bi bi-cart-plus me-2"></i>Nueva Venta</h4>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="search-box mb-3">
                            <label class="form-label fw-bold">Buscar Mascota *</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="buscar_mascota" placeholder="Escriba el nombre de la mascota...">
                                <button class="btn btn-outline-secondary" type="button" id="btnLimpiarMascota" style="display: none;">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                            <div id="resultados_mascotas" class="list-group mt-2" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                            <input type="hidden" id="id_mascota" name="id_mascota">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="search-box">
                            <label class="form-label fw-bold">Buscar Producto/Servicio *</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="buscar_producto" placeholder="Escriba el nombre del producto o servicio...">
                                <button class="btn btn-outline-secondary" type="button" id="btnAgregarItem">
                                    <i class="bi bi-plus"></i> Añadir
                                </button>
                            </div>
                            <div id="resultados_productos" class="list-group mt-2" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                            <input type="hidden" id="cantidad_item" value="1">
                        </div>
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
                    <table class="table table-cart">
                        <thead>
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
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total General:</td>
                                <td class="fw-bold">$<span id="total_general">0.00</span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Medio de pago -->
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Medio de Pago *</label>
                        <select class="form-select" id="medio_pago" name="medio_pago" required>
                            <option value="">Seleccionar medio de pago...</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Transferencia">Transferencia</option>
                        </select>
                    </div>
                    <div class="col-md-8 text-end">
                        <button type="button" class="btn btn-outline-secondary me-2" id="btnCancelar">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnRegistrarVenta" disabled>
                            <i class="bi bi-cart-check me-1"></i> Registrar Venta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        let carritoItems = [];
        let productoTemp = null;

        // Buscar mascotas
        $('#buscar_mascota').on('input', function() {
            const query = $(this).val().trim();
            
            if (query.length >= 2) {
                $.ajax({
                    url: 'buscar_mascotas.php',
                    method: 'POST',
                    data: { query: query },
                    dataType: 'json',
                    success: function(data) {
                        let html = '';
                        if (data.length > 0) {
                            data.forEach(function(mascota) {
                                html += `<a href="#" class="list-group-item list-group-item-action seleccionar-mascota" 
                                         data-id="${mascota.id_mascota}" 
                                         data-nombre="${mascota.nombre_mascota}" 
                                         data-propietario="${mascota.propietario}" 
                                         data-especie="${mascota.especie}">
                                         ${mascota.nombre_mascota} (${mascota.especie}) - ${mascota.propietario}
                                     </a>`;
                            });
                            $('#resultados_mascotas').html(html).show();
                            $('#btnLimpiarMascota').show();
                        } else {
                            $('#resultados_mascotas').html('<div class="list-group-item">No se encontraron mascotas</div>').show();
                        }
                    }
                });
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
            
            if (query.length >= 2) {
                $.ajax({
                    url: 'buscar_productos_servicios.php',
                    method: 'POST',
                    data: { query: query },
                    dataType: 'json',
                    success: function(data) {
                        let html = '';
                        if (data.length > 0) {
                            data.forEach(function(item) {
                                const stockInfo = item.tipo === 'producto' ? ` (Stock: ${item.stock})` : '';
                                html += `<a href="#" class="list-group-item list-group-item-action seleccionar-producto" 
                                         data-id="${item.id}" 
                                         data-nombre="${item.nombre}" 
                                         data-precio="${item.precio}" 
                                         data-stock="${item.stock || 0}" 
                                         data-tipo="${item.tipo}">
                                         ${item.nombre} - S/ ${parseFloat(item.precio).toFixed(2)}${stockInfo}
                                     </a>`;
                            });
                            $('#resultados_productos').html(html).show();
                        } else {
                            $('#resultados_productos').html('<div class="list-group-item">No se encontraron productos/servicios</div>').show();
                        }
                    }
                });
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
                Swal.fire('Error', 'Debe seleccionar un producto o servicio', 'error');
                return;
            }
            
            const cantidad = parseInt($('#cantidad_item').val()) || 1;
            
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
                    Swal.fire('Error', `No se puede agregar más cantidad. Stock disponible: ${productoTemp.stock}`, 'error');
                    return;
                }
                itemExistente.cantidad = nuevaCantidad;
            } else {
                carritoItems.push({
                    id: productoTemp.id,
                    nombre: productoTemp.nombre,
                    precio: productoTemp.precio,
                    cantidad: cantidad,
                    tipo: productoTemp.tipo,
                    stock: productoTemp.stock
                });
            }
            
            actualizarTablaCarrito();
            
            // Limpiar campos
            $('#buscar_producto').val('');
            $('#cantidad_item').val(1);
            productoTemp = null;
        });

        // Actualizar tabla del carrito
        function actualizarTablaCarrito() {
            const tbody = $('#carrito_body');
            tbody.empty();
            
            if (carritoItems.length === 0) {
                tbody.append('<tr id="carrito_vacio"><td colspan="6" class="text-center text-muted py-4">No hay productos en el carrito</td></tr>');
                $('#total_general').text('0.00');
                validarFormulario();
                return;
            }
            
            let totalGeneral = 0;
            
            carritoItems.forEach(function(item, index) {
                const subtotal = item.cantidad * item.precio;
                totalGeneral += subtotal;
                
                const stockInfo = item.tipo === 'producto' ? ` (Stock: ${item.stock})` : '';
                
                tbody.append(`
                    <tr>
                        <td>${item.nombre}${stockInfo}</td>
                        <td><span class="badge ${item.tipo === 'producto' ? 'bg-primary badge-product' : 'bg-success badge-service'}">${item.tipo}</span></td>
                        <td>${item.cantidad}</td>
                        <td>$${item.precio.toFixed(2)}</td>
                        <td>$${subtotal.toFixed(2)}</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm eliminar-item" data-index="${index}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
            
            $('#total_general').text(totalGeneral.toFixed(2));
            validarFormulario();
        }

        // Eliminar item del carrito
        $(document).on('click', '.eliminar-item', function() {
            const index = $(this).data('index');
            carritoItems.splice(index, 1);
            actualizarTablaCarrito();
        });

        // Validar formulario
        function validarFormulario() {
            const mascotaSeleccionada = $('#id_mascota').val();
            const hayItems = carritoItems.length > 0;
            const medioPago = $('#medio_pago').val();
            
            if (mascotaSeleccionada && hayItems && medioPago) {
                $('#btnRegistrarVenta').prop('disabled', false);
            } else {
                $('#btnRegistrarVenta').prop('disabled', true);
            }
        }

        // Validar al cambiar medio de pago
        $('#medio_pago').change(validarFormulario);

        // Enviar formulario
        $('#btnRegistrarVenta').click(function(e) {
            e.preventDefault();
            
            if (carritoItems.length === 0) {
                Swal.fire('Error', 'Debe agregar al menos un producto o servicio', 'error');
                return;
            }
            
            const formData = {
                id_mascota: $('#id_mascota').val(),
                items: JSON.stringify(carritoItems),
                medio_pago: $('#medio_pago').val(),
                total_general: parseFloat($('#total_general').text())
            };
            
            Swal.fire({
                title: 'Procesando...',
                text: 'Registrando la venta',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => Swal.showLoading()
            });
            
            $.ajax({
                url: 'ventas.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Venta registrada!',
                            text: response.message,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire('Error', 'Error de conexión', 'error');
                }
            });
        });

        // Cancelar venta
        $('#btnCancelar').click(function() {
            Swal.fire({
                title: '¿Cancelar venta?',
                text: 'Se perderán todos los datos ingresados',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'Continuar'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        });
    });
    </script>
</body>
</html>