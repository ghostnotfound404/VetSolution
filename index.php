<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet App</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/clientes.css" rel="stylesheet">
    <link href="assets/css/productos.css" rel="stylesheet">
    <link href="assets/css/mascotas.css" rel="stylesheet">
    <link href="assets/css/servicios.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row flex-nowrap">
            <!-- Barra Lateral - Ocultable en móviles -->
            <div class="col-12 col-md-3 col-lg-2 px-0 bg-dark d-flex flex-column min-vh-100">
                <div class="d-flex align-items-center justify-content-between p-3">
                    <div class="w-100 text-center">
                        <span class="fs-4 text-white d-none d-md-inline">Vet App</span>
                    </div>
                    <button class="btn btn-dark d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                
                <div class="collapse d-md-block" id="sidebarMenu">
                    <nav class="nav flex-column nav-pills" id="menu">
                        <a class="nav-link active" id="dashboard" href="#">
                            <i class="fas fa-home me-2"></i>
                            <span class="d-none d-md-inline">Inicio</span>
                        </a>
                        <a class="nav-link" id="ventas" href="#">
                            <i class="fas fa-shopping-cart me-2"></i>
                            <span class="d-none d-md-inline">Ventas</span>
                        </a>
                        <a class="nav-link" id="hospitalizaciones" href="#">
                            <i class="fas fa-hospital me-2"></i>
                            <span class="d-none d-md-inline">Hospitalizaciones</span>
                        </a>
                        <a class="nav-link" id="clientes" href="#">
                            <i class="fas fa-users me-2"></i>
                            <span class="d-none d-md-inline">Clientes</span>
                        </a>
                        <a class="nav-link" id="mascotas" href="#">
                            <i class="fas fa-paw me-2"></i>
                            <span class="d-none d-md-inline">Mascotas</span>
                        </a>
                        <a class="nav-link" id="productos" href="#">
                            <i class="fas fa-box-open me-2"></i>
                            <span class="d-none d-md-inline">Productos</span>
                        </a>
                        <a class="nav-link" id="servicios" href="#">
                            <i class="fas fa-tools me-2"></i>
                            <span class="d-none d-md-inline">Servicios</span>
                        </a>
                        <a class="nav-link" id="caja" href="#">
                            <i class="fas fa-cash-register me-2"></i>
                            <span class="d-none d-md-inline">Caja</span>
                        </a>
                        <a class="nav-link" id="egresos" href="#">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            <span class="d-none d-md-inline">Egresos</span>
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Área de Contenido Principal -->
            <div class="col-md-9 col-lg-10 px-md-4">
                <div id="contenido" class="main-content">
                    <!-- El contenido se carga dinámicamente aquí -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/mascotas.js"></script>
    <script src="assets/js/clientes.js"></script>

    <script>
        $(document).ready(function() {
            // Función para cargar el contenido dinámicamente
            function cargarContenido(page) {
                $(".nav-link").removeClass("active");
                $("#" + page).addClass("active");
                
                // Actualizar URL
                history.pushState({ page: page }, "", "#/" + page);
                
                // Cerrar menú en móviles después de seleccionar
                if($(window).width() < 768) {
                    $('#sidebarMenu').collapse('hide');
                }
                
                // Cargar el contenido correspondiente
                var contenidoDiv = $("#contenido");
                contenidoDiv.html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Cargando...</p></div>');
                
                switch(page) {
                    case 'clientes':
                        $.ajax({
                            url: 'modules/clientes.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'mascotas':
                        $.ajax({
                            url: 'modules/mascotas.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'ventas':
                        $.ajax({
                            url: 'modules/ventas.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'hospitalizaciones':
                        $.ajax({
                            url: 'modules/hospitalizaciones.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'productos':
                        $.ajax({
                            url: 'modules/productos.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'servicios':
                        $.ajax({
                            url: 'modules/servicios.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'historia_clinica':
                        $.ajax({
                            url: 'modules/historia_clinica.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'caja':
                        $.ajax({
                            url: 'modules/caja.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'egresos':
                        $.ajax({
                            url: 'modules/egresos.php',
                            method: 'GET',
                            success: function(response) {
                                contenidoDiv.html(response);
                            },
                            error: function() {
                                contenidoDiv.html('<div class="alert alert-danger">Error al cargar el contenido</div>');
                            }
                        });
                        break;
                    case 'dashboard':
                    default:
                        contenidoDiv.html(`
                            <div class="container mt-4">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="jumbotron bg-primary text-white p-5 rounded">
                                            <h1 class="display-4">¡Bienvenido a Vet App!</h1>
                                            <p class="lead">Sistema integral de gestión veterinaria</p>
                                            <hr class="my-4">
                                            <p>Utiliza el menú lateral para navegar entre las diferentes secciones del sistema.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                                <h5 class="card-title">Clientes</h5>
                                                <p class="card-text">Gestión de clientes y propietarios</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-paw fa-3x text-success mb-3"></i>
                                                <h5 class="card-title">Mascotas</h5>
                                                <p class="card-text">Registro y seguimiento de mascotas</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-hospital fa-3x text-info mb-3"></i>
                                                <h5 class="card-title">Hospitalizaciones</h5>
                                                <p class="card-text">Control de pacientes hospitalizados</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-center">
                                            <div class="card-body">
                                                <i class="fas fa-shopping-cart fa-3x text-warning mb-3"></i>
                                                <h5 class="card-title">Ventas</h5>
                                                <p class="card-text">Gestión de ventas y productos</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `);
                        break;
                }
            }

            // Evento de clic en los enlaces del menú
            $(".nav-link").click(function(e) {
                e.preventDefault();
                var page = $(this).attr("id");
                cargarContenido(page);
            });

            // Manejo de la URL al cargar la página
            var path = window.location.hash.replace("#/", "");
            if (path) {
                cargarContenido(path);
            } else {
                cargarContenido("dashboard");
            }

            // Manejo del botón de retroceso
            $(window).on("popstate", function() {
                var path = window.location.hash.replace("#/", "");
                if (path) {
                    cargarContenido(path);
                }
            });
        });
    </script>
</body>
</html>