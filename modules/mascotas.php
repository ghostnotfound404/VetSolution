<?php
include('../includes/config.php');

// Obtener todas las mascotas con información del cliente
$sql = "SELECT m.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
        FROM mascotas m 
        INNER JOIN clientes c ON m.id_cliente = c.id_cliente 
        ORDER BY m.nombre";
$result = $conn->query($sql);

// Obtener todos los clientes para el select de propietarios
$clientes_sql = "SELECT * FROM clientes ORDER BY nombre, apellido";
$clientes_result = $conn->query($clientes_sql);
?>

<div class="container mascotas">
    <h2>Gestionar Mascotas</h2>

    <!-- Sección para Buscar Mascota y Crear Nueva -->
    <div class="mb-4 search-section">
        <div class="row align-items-end">
            <div class="col-md-8">
                <h4>Buscar Mascota</h4>
                <form method="GET" class="d-flex gap-2" id="formBuscarMascota">
                    <div class="flex-grow-1">
                        <input type="text" class="form-control" id="buscar_mascota" name="buscar_mascota" 
                               placeholder="Buscar por nombre de mascota o propietario..." 
                               value="<?php echo isset($_GET['buscar_mascota']) ? htmlspecialchars($_GET['buscar_mascota']) : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-secondary">Buscar</button>
                    <?php if (isset($_GET['buscar_mascota'])): ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="limpiarBusquedaMascota()">Limpiar</button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevaMascotaModal">
                    <i class="fas fa-plus"></i> Crear Nueva Mascota
                </button>
            </div>
        </div>
        
        <?php
        if (isset($_GET['buscar_mascota']) && !empty(trim($_GET['buscar_mascota']))) {
            $buscar_mascota = trim($_GET['buscar_mascota']);
            
            // Usar prepared statement para seguridad
            $search_sql = "SELECT m.*, c.nombre as nombre_cliente, c.apellido as apellido_cliente 
                          FROM mascotas m 
                          INNER JOIN clientes c ON m.id_cliente = c.id_cliente 
                          WHERE m.nombre LIKE ? 
                             OR c.nombre LIKE ? 
                             OR c.apellido LIKE ?
                             OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
                          ORDER BY m.nombre";
            
            $stmt = $conn->prepare($search_sql);
            $searchTerm = "%$buscar_mascota%";
            $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $search_result = $stmt->get_result();
            
            if ($search_result->num_rows > 0) {
                echo "<h5>Resultados de la búsqueda para: <strong>".htmlspecialchars($buscar_mascota)."</strong></h5>";
                echo "<div class='table-responsive'>";
                echo "<table class='table table-striped'>";
                echo "<thead><tr><th>#HC</th><th>Nombre</th><th>Especie</th><th>Raza</th><th>Género</th><th>Fecha de Nacimiento</th><th>Cliente</th><th>Estado</th><th>Opciones</th></tr></thead>";
                echo "<tbody>";
                while ($row = $search_result->fetch_assoc()) {
                    $fechaNacimiento = date('d/m/Y', strtotime($row['fecha_nacimiento']));
                    $nombreCliente = htmlspecialchars($row['nombre_cliente'] . ' ' . $row['apellido_cliente']);
                    $nombreMascota = htmlspecialchars($row['nombre']);
                    $especie = htmlspecialchars($row['especie']);
                    $raza = htmlspecialchars($row['raza']);
                    $genero = htmlspecialchars($row['genero']);
                    $estado = htmlspecialchars($row['estado']);
                    
                    echo "<tr>
                        <td>HC-{$row['id_mascota']}</td>
                        <td><strong>$nombreMascota</strong></td>
                        <td>$especie</td>
                        <td>$raza</td>
                        <td>$genero</td>
                        <td>$fechaNacimiento</td>
                        <td>$nombreCliente</td>
                        <td><span class='badge bg-success'>$estado</span></td>
                        <td>
                            <button class='btn btn-warning btn-sm' onclick='editarMascota({$row['id_mascota']})'>
                                <i class='fas fa-edit'></i> Editar
                            </button>
                            <button class='btn btn-info btn-sm' onclick='verHistoria({$row['id_mascota']})'>
                                <i class='fas fa-file-medical'></i> Historia
                            </button>
                        </td>
                    </tr>";
                }
                echo "</tbody></table>";
                echo "</div>";
            } else {
                echo "<div class='alert alert-info'>No se encontraron resultados para '<strong>".htmlspecialchars($buscar_mascota)."</strong>'.</div>";
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
                    <option value="mascotas_general">Mascotas - Reporte General</option>
                    <option value="mascotas_por_especie">Mascotas por Especie</option>
                    <option value="mascotas_nuevas">Mascotas Nuevas (Último mes)</option>
                    <option value="historia_clinica">Historias Clínicas</option>
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

    <!-- Lista de todas las mascotas -->
    <?php if (!isset($_GET['buscar_mascota'])): ?>
    <div class="mb-4">
        <h4>Todas las Mascotas</h4>
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#HC</th>
                            <th>Nombre</th>
                            <th>Especie</th>
                            <th>Raza</th>
                            <th>Género</th>
                            <th>Fecha de Nacimiento</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>HC-<?php echo $row['id_mascota']; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['especie']); ?></td>
                            <td><?php echo htmlspecialchars($row['raza']); ?></td>
                            <td><?php echo htmlspecialchars($row['genero']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['fecha_nacimiento'])); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_cliente'] . ' ' . $row['apellido_cliente']); ?></td>
                            <td><span class="badge bg-success"><?php echo htmlspecialchars($row['estado']); ?></span></td>
                            <td>
                                <button class="btn btn-warning btn-sm" onclick="editarMascota(<?php echo $row['id_mascota']; ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <button class="btn btn-info btn-sm" onclick="verHistoria(<?php echo $row['id_mascota']; ?>)">
                                    <i class="fas fa-file-medical"></i> Historia
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
        <?php else: ?>
            <div class="alert alert-info">No hay mascotas registradas.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Modal para Crear Nueva Mascota -->
    <div class="modal fade" id="nuevaMascotaModal" tabindex="-1" aria-labelledby="nuevaMascotaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevaMascotaModalLabel">Crear Nueva Mascota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="modules/mascotas.php" method="POST" id="formNuevaMascota">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="buscar_propietario" class="form-label">Buscar y seleccionar propietario <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="buscar_propietario" placeholder="Buscar por nombre del propietario...">
                                </div>
                                <input type="hidden" id="id_cliente" name="id_cliente" required>
                                <div id="resultados_propietario" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre_mascota" class="form-label">Nombre de la mascota <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-paw"></i></span>
                                    <input type="text" class="form-control" id="nombre_mascota" name="nombre" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="especie" class="form-label">Especie <span class="text-danger">*</span></label>
                                <select class="form-select" id="especie" name="especie" required>
                                    <option value="">Buscar especie...</option>
                                    <option value="Canino">Canino</option>
                                    <option value="Felino">Felino</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="raza" class="form-label">Raza</label>
                                <select class="form-select" id="raza" name="raza">
                                    <option value="">Buscar raza...</option>
                                    <!-- Las razas se cargarán dinámicamente según la especie -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="genero" class="form-label">Sexo <span class="text-danger">*</span></label>
                                <select class="form-select" id="genero" name="genero" required>
                                    <option value="">Selecciona una opción</option>
                                    <option value="Macho">Macho</option>
                                    <option value="Hembra">Hembra</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="esterilizado" class="form-label">¿Ha sido esterilizado?</label>
                                <select class="form-select" id="esterilizado" name="esterilizado">
                                    <option value="No">No</option>
                                    <option value="Si">Sí</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevaMascota" class="btn btn-success">Guardar Mascota</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Crear una nueva mascota
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $id_cliente = trim($_POST['id_cliente']);
        $nombre = trim($_POST['nombre']);
        $fecha_nacimiento = trim($_POST['fecha_nacimiento']);
        $especie = trim($_POST['especie']);
        $raza = trim($_POST['raza']);
        $genero = trim($_POST['genero']);
        $esterilizado = isset($_POST['esterilizado']) ? trim($_POST['esterilizado']) : 'No';

        // Validaciones
        $errores = [];
        
        if (empty($id_cliente)) {
            $errores[] = "Debe seleccionar un propietario";
        }
        
        if (empty($nombre)) {
            $errores[] = "El nombre de la mascota es obligatorio";
        }
        
        if (empty($fecha_nacimiento)) {
            $errores[] = "La fecha de nacimiento es obligatoria";
        }
        
        if (empty($especie)) {
            $errores[] = "La especie es obligatoria";
        }
        
        if (empty($genero)) {
            $errores[] = "El sexo es obligatorio";
        }

        if (empty($errores)) {
            $insert_sql = "INSERT INTO mascotas (id_cliente, nombre, fecha_nacimiento, especie, raza, genero, estado) 
                           VALUES (?, ?, ?, ?, ?, ?, 'Activo')";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("isssss", $id_cliente, $nombre, $fecha_nacimiento, $especie, $raza, $genero);
            
            if ($stmt->execute()) {
                echo "<script>
                    if (window.parent && window.parent.location.hash) {
                        window.parent.location.reload();
                    } else {
                        window.location.href = '../index.php#/mascotas';
                    }
                </script>";
            } else {
                echo "<script>alert('Error al crear mascota: " . $conn->error . "');</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('Errores encontrados:\\n" . implode("\\n", $errores) . "');</script>";
        }
    }
    ?>
</div>

<script>
function editarMascota(id) {
    // Función para editar mascota
    alert('Función de editar mascota ID: ' + id + ' (por implementar)');
}

function verHistoria(id) {
    // Función para ver historia clínica
    alert('Función de historia clínica ID: ' + id + ' (por implementar)');
}

// Función para realizar búsqueda de mascotas
function realizarBusquedaMascota() {
    const formData = new FormData(document.getElementById('formBuscarMascota'));
    const searchParams = new URLSearchParams(formData);
    
    $.ajax({
        url: 'modules/mascotas.php?' + searchParams.toString(),
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al realizar la búsqueda');
        }
    });
}

// Función para limpiar búsqueda de mascotas
function limpiarBusquedaMascota() {
    $.ajax({
        url: 'modules/mascotas.php',
        method: 'GET',
        success: function(response) {
            $('#contenido').html(response);
        },
        error: function() {
            console.error('Error al limpiar la búsqueda');
        }
    });
}

// Buscar propietario en tiempo real
$(document).ready(function() {
    // Event listeners para el formulario de búsqueda de mascotas
    $('#formBuscarMascota').on('submit', function(e) {
        e.preventDefault();
        realizarBusquedaMascota();
    });
    
    // Mejorar la experiencia de búsqueda principal de mascotas
    $('#buscar_mascota').on('keypress', function(e) {
        if (e.which == 13) { // Enter key
            e.preventDefault();
            realizarBusquedaMascota();
        }
    });
    
    // Validación de búsqueda mínima
    function validarBusqueda() {
        var searchValue = $('#buscar_mascota').val().trim();
        if (searchValue.length > 0 && searchValue.length < 2) {
            alert('Por favor ingrese al menos 2 caracteres para buscar.');
            return false;
        }
        return true;
    }
    
    // Buscar propietario en tiempo real en el modal
    $('#buscar_propietario').on('input', function() {
        var busqueda = $(this).val();
        if (busqueda.length >= 2) {
            $.ajax({
                url: 'modules/buscar_propietario.php',
                method: 'GET',
                data: { q: busqueda },
                success: function(response) {
                    $('#resultados_propietario').html(response);
                }
            });
        } else {
            $('#resultados_propietario').html('');
        }
    });
    
    // Cargar razas según especie
    $('#especie').on('change', function() {
        var especie = $(this).val();
        cargarRazas(especie);
    });
});

function seleccionarPropietario(id, nombre) {
    $('#id_cliente').val(id);
    $('#buscar_propietario').val(nombre);
    $('#resultados_propietario').html('<div class="alert alert-success">Propietario seleccionado: <strong>' + nombre + '</strong></div>');
}

function cargarRazas(especie) {
    var razas = {
        'Canino': ['Labrador', 'Golden Retriever', 'Pastor Alemán', 'Bulldog', 'Beagle', 'Poodle', 'Rottweiler', 'Yorkshire', 'Chihuahua', 'Mestizo'],
        'Felino': ['Persa', 'Siamés', 'Maine Coon', 'Británico', 'Ragdoll', 'Bengalí', 'Abisinio', 'Mestizo']

    };
    
    var select = $('#raza');
    select.html('<option value="">Buscar raza...</option>');
    
    if (razas[especie]) {
        razas[especie].forEach(function(raza) {
            select.append('<option value="' + raza + '">' + raza + '</option>');
        });
    }
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
