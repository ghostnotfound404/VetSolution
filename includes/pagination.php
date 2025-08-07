<?php
/**
 * Clase para manejar paginación y optimización de consultas
 */
class PaginationHelper {
    private $conn;
    private $itemsPerPage;
    
    public function __construct($connection, $itemsPerPage = 10) {
        $this->conn = $connection;
        $this->itemsPerPage = $itemsPerPage;
    }
    
    /**
     * Obtener datos paginados
     */
    public function getPaginatedData($table, $select = '*', $joins = '', $where = '', $orderBy = '', $params = []) {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $this->itemsPerPage;
        
        // Consulta para contar total de registros
        $countSql = "SELECT COUNT(*) as total FROM {$table} {$joins}";
        if (!empty($where)) {
            $countSql .= " WHERE {$where}";
        }
        
        $countStmt = $this->conn->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $countStmt->execute();
        $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
        
        // Consulta para obtener datos paginados
        $sql = "SELECT {$select} FROM {$table} {$joins}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }
        $sql .= " LIMIT {$this->itemsPerPage} OFFSET {$offset}";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        
        return [
            'data' => $data,
            'pagination' => $this->generatePaginationInfo($page, $totalRecords)
        ];
    }
    
    /**
     * Generar información de paginación
     */
    private function generatePaginationInfo($currentPage, $totalRecords) {
        $totalPages = ceil($totalRecords / $this->itemsPerPage);
        
        return [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'items_per_page' => $this->itemsPerPage,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => $currentPage - 1,
            'next_page' => $currentPage + 1,
            'start_record' => (($currentPage - 1) * $this->itemsPerPage) + 1,
            'end_record' => min($currentPage * $this->itemsPerPage, $totalRecords)
        ];
    }
    
    /**
     * Generar HTML de paginación
     */
    public function generatePaginationHTML($pagination, $baseUrl = '') {
        if ($pagination['total_pages'] <= 1) {
            return '';
        }
        
        $html = '<nav aria-label="Paginación">';
        $html .= '<ul class="pagination justify-content-center">';
        
        // Botón anterior
        if ($pagination['has_previous']) {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $pagination['previous_page'] . '" aria-label="Anterior">';
            $html .= '<span aria-hidden="true">&laquo;</span></a></li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link" aria-label="Anterior"><span aria-hidden="true">&laquo;</span></span></li>';
        }
        
        // Números de página
        $startPage = max(1, $pagination['current_page'] - 2);
        $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
        
        if ($startPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
            if ($startPage > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $pagination['current_page']) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
            }
        }
        
        if ($endPage < $pagination['total_pages']) {
            if ($endPage < $pagination['total_pages'] - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $pagination['total_pages'] . '">' . $pagination['total_pages'] . '</a></li>';
        }
        
        // Botón siguiente
        if ($pagination['has_next']) {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $pagination['next_page'] . '" aria-label="Siguiente">';
            $html .= '<span aria-hidden="true">&raquo;</span></a></li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link" aria-label="Siguiente"><span aria-hidden="true">&raquo;</span></span></li>';
        }
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        // Info de registros
        $html .= '<div class="pagination-info text-center text-muted mt-2">';
        $html .= 'Mostrando ' . $pagination['start_record'] . ' - ' . $pagination['end_record'];
        $html .= ' de ' . $pagination['total_records'] . ' registros';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Búsqueda optimizada con paginación
     */
    public function searchWithPagination($table, $searchFields, $searchTerm, $select = '*', $joins = '', $orderBy = '') {
        if (empty($searchTerm)) {
            return $this->getPaginatedData($table, $select, $joins, '', $orderBy);
        }
        
        $whereConditions = [];
        $params = [];
        
        foreach ($searchFields as $field) {
            $whereConditions[] = "{$field} LIKE ?";
            $params[] = "%{$searchTerm}%";
        }
        
        $where = implode(' OR ', $whereConditions);
        
        return $this->getPaginatedData($table, $select, $joins, $where, $orderBy, $params);
    }
}

/**
 * Función para optimizar consultas con índices
 */
function optimizeDatabase($conn) {
    $optimizations = [
        // Índices para clientes
        "CREATE INDEX IF NOT EXISTS idx_clientes_nombre ON clientes(nombre, apellido)",
        "CREATE INDEX IF NOT EXISTS idx_clientes_dni ON clientes(dni)",
        "CREATE INDEX IF NOT EXISTS idx_clientes_celular ON clientes(celular)",
        
        // Índices para mascotas
        "CREATE INDEX IF NOT EXISTS idx_mascotas_cliente ON mascotas(id_cliente)",
        "CREATE INDEX IF NOT EXISTS idx_mascotas_nombre ON mascotas(nombre)",
        "CREATE INDEX IF NOT EXISTS idx_mascotas_especie ON mascotas(especie)",
        "CREATE INDEX IF NOT EXISTS idx_mascotas_fecha ON mascotas(fecha_nacimiento)",
        
        // Índices para productos
        "CREATE INDEX IF NOT EXISTS idx_productos_nombre ON productos(nombre)",
        "CREATE INDEX IF NOT EXISTS idx_productos_stock ON productos(stock)",
        "CREATE INDEX IF NOT EXISTS idx_productos_precio ON productos(precio)",
        
        // Índices para servicios
        "CREATE INDEX IF NOT EXISTS idx_servicios_nombre ON servicios(nombre)",
        "CREATE INDEX IF NOT EXISTS idx_servicios_precio ON servicios(precio)",
        
        // Índices para ventas
        "CREATE INDEX IF NOT EXISTS idx_ventas_mascota ON ventas(id_mascota)",
        "CREATE INDEX IF NOT EXISTS idx_ventas_fecha ON ventas(fecha_venta)",
        "CREATE INDEX IF NOT EXISTS idx_ventas_medio_pago ON ventas(medio_pago)",
        "CREATE INDEX IF NOT EXISTS idx_ventas_tipo_item ON ventas(tipo_item)",
        "CREATE INDEX IF NOT EXISTS idx_ventas_id_item ON ventas(id_item)",
        
        // Índices para caja
        "CREATE INDEX IF NOT EXISTS idx_caja_fecha ON caja(fecha)",
        "CREATE INDEX IF NOT EXISTS idx_caja_tipo ON caja(tipo)",
        
        // Índices para historia clínica
        "CREATE INDEX IF NOT EXISTS idx_historia_mascota ON historia_clinica(id_mascota)",
        "CREATE INDEX IF NOT EXISTS idx_historia_fecha ON historia_clinica(fecha)",
        
        // Índices para hospitalizaciones
        "CREATE INDEX IF NOT EXISTS idx_hospitalizaciones_mascota ON hospitalizaciones(id_mascota)",
        "CREATE INDEX IF NOT EXISTS idx_hospitalizaciones_fecha ON hospitalizaciones(fecha_ingreso)"
    ];
    
    foreach ($optimizations as $sql) {
        try {
            $conn->query($sql);
        } catch (Exception $e) {
            // Log error but continue
            error_log("Error creating index: " . $e->getMessage());
        }
    }
}
?>
