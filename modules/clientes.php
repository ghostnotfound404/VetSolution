<?php
include('../includes/config.php');

// Obtener todos los clientes
$sql = "SELECT * FROM clientes";
$result = $conn->query($sql);
?>

<div class="container clientes">
    <h2>Gestionar Clientes</h2>

    <!-- Sección para Buscar Cliente y Crear Nuevo -->
    <div class="mb-4 search-section">
        <div class="row align-items-end">
            <div class="col-md-8">
                <h4>Buscar Cliente</h4>
                <form method="GET" class="d-flex gap-2" id="formBuscarCliente">
                    <div class="flex-grow-1">
                        <input type="text" class="form-control" id="buscar_nombre" name="buscar_nombre" 
                               placeholder="Buscar por nombre o apellido..." 
                               value="<?php echo isset($_GET['buscar_nombre']) ? htmlspecialchars($_GET['buscar_nombre']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary">Buscar</button>
                    <?php if (isset($_GET['buscar_nombre'])): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusqueda()">Limpiar</button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoClienteModal">
                    <i class="fas fa-plus"></i> Crear Nuevo Cliente
                </button>
            </div>
        </div>
        
        <?php
        if (isset($_GET['buscar_nombre']) && !empty(trim($_GET['buscar_nombre']))) {
            $buscar_nombre = trim($_GET['buscar_nombre']);
            
            // Usar prepared statement para seguridad
            $search_sql = "SELECT * FROM clientes 
                          WHERE nombre LIKE ? 
                             OR apellido LIKE ? 
                             OR dni LIKE ?
                             OR CONCAT(nombre, ' ', apellido) LIKE ?
                          ORDER BY nombre, apellido";
            
            $stmt = $conn->prepare($search_sql);
            $searchTerm = "%$buscar_nombre%";
            $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $search_result = $stmt->get_result();
            
            if ($search_result->num_rows > 0) {
                echo "<h5>Resultados de la búsqueda para: <strong>".htmlspecialchars($buscar_nombre)."</strong></h5>";
                echo "<div class='table-responsive'>";
                echo "<table class='table table-striped'>";
                echo "<thead><tr><th>Nombre</th><th>Apellido</th><th>Celular</th><th>DNI</th><th>Dirección</th><th>Acciones</th></tr></thead>";
                echo "<tbody>";
                while ($row = $search_result->fetch_assoc()) {
                    $nombre = htmlspecialchars($row['nombre']);
                    $apellido = htmlspecialchars($row['apellido']);
                    $celular = htmlspecialchars($row['celular']);
                    $dni = htmlspecialchars($row['dni']);
                    $direccion = htmlspecialchars($row['direccion']);
                    
                    echo "<tr>
                        <td>$nombre</td>
                        <td>$apellido</td>
                        <td>$celular</td>
                        <td>$dni</td>
                        <td>$direccion</td>
                        <td>
                            <button class='btn btn-warning btn-sm' onclick='editarCliente({$row['id_cliente']})'>
                                <i class='fas fa-edit'></i> Editar
                            </button>
                            <button class='btn btn-info btn-sm' onclick='verMascotas({$row['id_cliente']})'>
                                <i class='fas fa-paw'></i> Ver Mascotas
                            </button>
                        </td>
                    </tr>";
                }
                echo "</tbody></table>";
                echo "</div>";
            } else {
                echo "<div class='alert alert-info'>No se encontraron resultados para '<strong>".htmlspecialchars($buscar_nombre)."</strong>'.</div>";
            }
            
            $stmt->close();
        }
        ?>
    </div>

    <!-- Sección de Auditoría -->
    <div class="mb-4">
        <h4>Auditoría</h4>
        <div class="row">
            <div class="col-md-6">
                <select class="form-select" id="auditoria_select">
                    <option value="">Seleccionar tipo de auditoría...</option>
                    <option value="clientes_general">Clientes - Reporte General</option>
                    <option value="clientes_activos">Clientes Activos</option>
                    <option value="clientes_nuevos">Clientes Nuevos (Último mes)</option>
                </select>
            </div>
            <div class="col-md-6">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-success" onclick="descargarExcel()">
                        <i class="fas fa-file-excel"></i> Descargar Excel
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="descargarPDF()">
                        <i class="fas fa-file-pdf"></i> Descargar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de todos los clientes -->
    <?php if (!isset($_GET['buscar_nombre'])): ?>
    <div class="mb-4">
        <h4>Todos los Clientes</h4>
        <?php if ($result->num_rows > 0): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Celular</th>
                        <th>DNI</th>
                        <th>Dirección</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['celular']); ?></td>
                        <td><?php echo htmlspecialchars($row['dni']); ?></td>
                        <td><?php echo htmlspecialchars($row['direccion']); ?></td>
                        <td>
                            <a href='editar_cliente.php?id=<?php echo $row['id_cliente']; ?>' class='btn btn-warning btn-sm'>Editar</a>
                            <a href='ver_mascotas.php?id=<?php echo $row['id_cliente']; ?>' class='btn btn-info btn-sm'>Ver Mascotas</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No hay clientes registrados.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Modal para Crear Nuevo Cliente -->
    <div class="modal fade" id="nuevoClienteModal" tabindex="-1" aria-labelledby="nuevoClienteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoClienteModalLabel">Crear Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="modules/clientes.php" method="POST" id="formNuevoCliente">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellido" name="apellido" required>
                        </div>
                        <div class="mb-3">
                            <label for="celular" class="form-label">Celular <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="celular" name="celular" 
                                   pattern="[0-9]{9}" maxlength="9" 
                                   placeholder="9 dígitos (ej: 987654321)" required>
                            <div class="form-text">Ingresa exactamente 9 números</div>
                        </div>
                        <div class="mb-3">
                            <label for="dni" class="form-label">DNI</label>
                            <input type="text" class="form-control" id="dni" name="dni" 
                                   pattern="[0-9]{8}" maxlength="8" 
                                   placeholder="8 dígitos (ej: 12345678) - Opcional">
                            <div class="form-text">Opcional - Si ingresas, debe tener exactamente 8 números</div>
                        </div>
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion" placeholder="Opcional">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevoCliente" class="btn btn-success">Guardar Cliente</button>
                </div>
            </div>
        </div>
    </div>

        <?php
        // Crear un nuevo cliente
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $nombre = trim($_POST['nombre']);
            $apellido = trim($_POST['apellido']);
            $celular = trim($_POST['celular']);
            $dni = trim($_POST['dni']);
            $direccion = trim($_POST['direccion']);

            // Validaciones
            $errores = [];
            
            if (empty($nombre)) {
                $errores[] = "El nombre es obligatorio";
            }
            
            if (empty($apellido)) {
                $errores[] = "El apellido es obligatorio";
            }
            
            if (empty($celular)) {
                $errores[] = "El celular es obligatorio";
            } elseif (!preg_match('/^[0-9]{9}$/', $celular)) {
                $errores[] = "El celular debe tener exactamente 9 números";
            }
            
            // DNI es opcional, pero si se proporciona debe ser válido
            if (!empty($dni) && !preg_match('/^[0-9]{8}$/', $dni)) {
                $errores[] = "El DNI debe tener exactamente 8 números";
            }

            if (empty($errores)) {
                // Verificar si el DNI ya existe (solo si se proporciona)
                if (!empty($dni)) {
                    $check_dni = "SELECT id_cliente FROM clientes WHERE dni = '$dni'";
                    $result_check = $conn->query($check_dni);
                    
                    if ($result_check->num_rows > 0) {
                        echo "<script>alert('Error: Ya existe un cliente con ese DNI.');</script>";
                        return;
                    }
                }
                
                $insert_sql = "INSERT INTO clientes (nombre, apellido, celular, dni, direccion) VALUES ('$nombre', '$apellido', '$celular', '$dni', '$direccion')";
                if ($conn->query($insert_sql) === TRUE) {
                    echo "<script>
                        if (window.parent && window.parent.location.hash) {
                            window.parent.location.reload();
                        } else {
                            window.location.href = '../index.php#/clientes';
                        }
                    </script>";
                } else {
                    echo "<script>alert('Error al crear cliente: " . $conn->error . "');</script>";
                }
            } else {
                echo "<script>alert('Errores encontrados:\\n" . implode("\\n", $errores) . "');</script>";
            }
        }
        ?>
    </div>

    <script>
    // Funciones para los botones de acción
    function editarCliente(id) {
        // TODO: Implementar edición de cliente
        alert('Función de editar cliente ID: ' + id + ' (por implementar)');
    }

    function verMascotas(id) {
        // TODO: Implementar vista de mascotas del cliente
        alert('Función de ver mascotas del cliente ID: ' + id + ' (por implementar)');
    }
    
    // Mejorar experiencia de búsqueda
    $(document).ready(function() {
        // Búsqueda con Enter
        $('#buscar_nombre').on('keypress', function(e) {
            if (e.which == 13) { // Enter key
                e.preventDefault();
                realizarBusqueda();
            }
        });
        
        // Manejar envío del formulario
        $('#formBuscarCliente').on('submit', function(e) {
            e.preventDefault();
            realizarBusqueda();
        });
    });
    
    // Función para realizar búsqueda sin recargar la página
    function realizarBusqueda() {
        var searchValue = $('#buscar_nombre').val().trim();
        if (searchValue.length > 0 && searchValue.length < 2) {
            alert('Por favor ingrese al menos 2 caracteres para buscar.');
            return false;
        }
        
        // Construir URL con parámetros de búsqueda
        var searchUrl = 'modules/clientes.php';
        if (searchValue.length >= 2) {
            searchUrl += '?buscar_nombre=' + encodeURIComponent(searchValue);
        }
        
        // Recargar el contenido con AJAX
        $.ajax({
            url: searchUrl,
            method: 'GET',
            success: function(response) {
                $('#contenido').html(response);
            },
            error: function() {
                alert('Error al realizar la búsqueda');
            }
        });
    }
    
    // Función para limpiar búsqueda
    function limpiarBusqueda() {
        $.ajax({
            url: 'modules/clientes.php',
            method: 'GET',
            success: function(response) {
                $('#contenido').html(response);
            },
            error: function() {
                alert('Error al limpiar la búsqueda');
            }
        });
    }
    
    // Funciones de auditoría
    function descargarExcel() {
        const tipoAuditoria = document.getElementById('auditoria_select').value;
        if (!tipoAuditoria) {
            alert('Por favor selecciona un tipo de auditoría');
            return;
        }
        // Aquí implementarías la lógica para generar y descargar el Excel
        window.open('exportar_excel.php?tipo=' + tipoAuditoria, '_blank');
    }

    function descargarPDF() {
        const tipoAuditoria = document.getElementById('auditoria_select').value;
        if (!tipoAuditoria) {
            alert('Por favor selecciona un tipo de auditoría');
            return;
        }
        // Aquí implementarías la lógica para generar y descargar el PDF
        window.open('exportar_pdf.php?tipo=' + tipoAuditoria, '_blank');
    }
    </script>
</div>
