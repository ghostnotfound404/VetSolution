<?php
include('../includes/config.php');
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Crear una nueva hoja de cálculo
$spreadsheet = new Spreadsheet();

// Estilo para encabezados
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2980B9'],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
];

// Estilo para las filas de datos
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
];

// Estilo para las filas de totales
$totalStyle = [
    'font' => [
        'bold' => true,
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'ECF0F1'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
        ],
    ],
];

// Obtener fecha y hora actual
$fecha_actual = date('Y-m-d H:i:s');

// ====== HOJA PRINCIPAL: RESUMEN DE VENTAS ======
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen de Ventas');

// Configurar encabezado
$sheet->setCellValue('A1', 'REPORTE DE CUADRE DE CAJA');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', 'Fecha de generación: ' . date('d/m/Y H:i:s', strtotime($fecha_actual)));
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Encabezados de la tabla
$sheet->setCellValue('A4', 'Fecha');
$sheet->setCellValue('B4', 'Cliente / Mascota');
$sheet->setCellValue('C4', 'Tipo');
$sheet->setCellValue('D4', 'Item');
$sheet->setCellValue('E4', 'Cantidad');
$sheet->setCellValue('F4', 'Subtotal');
$sheet->setCellValue('G4', 'Medio de Pago');
$sheet->getStyle('A4:G4')->applyFromArray($headerStyle);

// Verificar si la columna tipo_negocio existe
$result = $conn->query("SHOW COLUMNS FROM ventas LIKE 'tipo_negocio'");
$column_exists = ($result->num_rows > 0);

// Obtener datos de ventas
$query = "SELECT 
            v.fecha_venta, 
            CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
            m.nombre as nombre_mascota,";
            
// Usar la columna tipo_negocio si existe, o un valor por defecto si no
if ($column_exists) {
    $query .= "IFNULL(v.tipo_negocio, 'clinica') as tipo_negocio,";
} else {
    $query .= "'clinica' as tipo_negocio,";
}

$query .= "CASE 
                WHEN v.tipo_item = 'producto' THEN p.nombre 
                WHEN v.tipo_item = 'servicio' THEN s.nombre 
                ELSE 'Desconocido'
            END as item_nombre,
            v.tipo_item,
            v.cantidad,
            v.subtotal,
            v.medio_pago
          FROM ventas v
          LEFT JOIN mascotas m ON v.id_mascota = m.id_mascota 
          LEFT JOIN clientes c ON m.id_cliente = c.id_cliente
          LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
          LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio
          ORDER BY v.fecha_venta DESC";

$result = $conn->query($query);
$row_count = 5;

while ($row = $result->fetch_assoc()) {
    // Formatear datos para el cliente/mascota
    $cliente_mascota = $row['nombre_cliente'];
    if (!empty($row['nombre_mascota'])) {
        $cliente_mascota .= ' / ' . $row['nombre_mascota'];
    }
    
    // Llenar datos
    $sheet->setCellValue('A' . $row_count, date('d/m/Y H:i', strtotime($row['fecha_venta'])));
    $sheet->setCellValue('B' . $row_count, $cliente_mascota);
    $sheet->setCellValue('C' . $row_count, ucfirst($row['tipo_negocio']));
    $sheet->setCellValue('D' . $row_count, $row['item_nombre'] . ' (' . ucfirst($row['tipo_item']) . ')');
    $sheet->setCellValue('E' . $row_count, $row['cantidad']);
    $sheet->setCellValue('F' . $row_count, $row['subtotal']);
    $sheet->setCellValue('G' . $row_count, $row['medio_pago']);
    
    $row_count++;
}

// Aplicar estilo a todas las filas de datos
$sheet->getStyle('A5:G' . ($row_count - 1))->applyFromArray($dataStyle);

// Formato para moneda
$sheet->getStyle('F5:F' . ($row_count - 1))
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');

// Autoajustar anchos de columna
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ====== SECCIÓN DE TOTALES ======
$row_count += 2;
$totales_start = $row_count;

// Títulos
$sheet->setCellValue('A' . $row_count, 'RESUMEN DE INGRESOS POR MEDIO DE PAGO');
$sheet->mergeCells('A' . $row_count . ':G' . $row_count);
$sheet->getStyle('A' . $row_count)->getFont()->setBold(true);
$sheet->getStyle('A' . $row_count)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$row_count++;

// Obtener totales por medio de pago
$medios = ['Efectivo', 'Yape', 'Transferencia', 'Tarjeta'];
$total_medios = 0;

foreach ($medios as $medio) {
    $query = "SELECT COALESCE(SUM(subtotal), 0) as total FROM ventas WHERE medio_pago = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $medio);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'];
    $total_medios += $total;
    
    $sheet->setCellValue('A' . $row_count, 'Total ' . $medio);
    $sheet->setCellValue('B' . $row_count, $total);
    $sheet->getStyle('B' . $row_count)
        ->getNumberFormat()
        ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');
    
    $row_count++;
}

// Obtener total de egresos
$total_egresos = $conn->query("SELECT COALESCE(SUM(monto), 0) as total FROM egresos")->fetch_assoc()['total'];

// Calcular total en caja (ingresos en efectivo - egresos)
$ingresos_efectivo = $conn->query("SELECT COALESCE(SUM(subtotal), 0) as total FROM ventas WHERE medio_pago = 'Efectivo'")->fetch_assoc()['total'];
$total_caja = $ingresos_efectivo - $total_egresos;

$sheet->setCellValue('A' . $row_count, 'Total Ingresos');
$sheet->setCellValue('B' . $row_count, $total_medios);
$sheet->getStyle('B' . $row_count)
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');
$sheet->getStyle('A' . $row_count . ':B' . $row_count)->applyFromArray($totalStyle);
$row_count++;

$sheet->setCellValue('A' . $row_count, 'Total Egresos');
$sheet->setCellValue('B' . $row_count, $total_egresos);
$sheet->getStyle('B' . $row_count)
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');
$sheet->getStyle('A' . $row_count . ':B' . $row_count)->applyFromArray($totalStyle);
$row_count++;

$sheet->setCellValue('A' . $row_count, 'TOTAL EN CAJA');
$sheet->setCellValue('B' . $row_count, $total_caja);
$sheet->getStyle('A' . $row_count . ':B' . $row_count)->getFont()->setBold(true);
$sheet->getStyle('A' . $row_count . ':B' . $row_count)->applyFromArray($totalStyle);
$sheet->getStyle('B' . $row_count)
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');
$row_count++;

// Estilos para la sección de totales
$sheet->getStyle('A' . $totales_start . ':B' . ($row_count - 1))->applyFromArray($dataStyle);


// ====== HOJA DE CLÍNICA ======
$spreadsheet->createSheet();
$sheet = $spreadsheet->getSheet(1);
$sheet->setTitle('Clínica');

// Encabezado
$sheet->setCellValue('A1', 'RESUMEN DE VENTAS - CLÍNICA');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Encabezados de la tabla
$sheet->setCellValue('A3', 'Fecha');
$sheet->setCellValue('B3', 'Cliente / Mascota');
$sheet->setCellValue('C3', 'Item');
$sheet->setCellValue('D3', 'Cantidad');
$sheet->setCellValue('E3', 'Subtotal');
$sheet->setCellValue('F3', 'Medio de Pago');
$sheet->getStyle('A3:F3')->applyFromArray($headerStyle);

// Obtener datos de ventas de tipo clínica
$query = "SELECT 
            v.fecha_venta, 
            CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
            m.nombre as nombre_mascota,
            CASE 
                WHEN v.tipo_item = 'producto' THEN p.nombre 
                WHEN v.tipo_item = 'servicio' THEN s.nombre 
                ELSE 'Desconocido'
            END as item_nombre,
            v.tipo_item,
            v.cantidad,
            v.subtotal,
            v.medio_pago
          FROM ventas v
          LEFT JOIN mascotas m ON v.id_mascota = m.id_mascota 
          LEFT JOIN clientes c ON m.id_cliente = c.id_cliente
          LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
          LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio";
          
// Modificamos la condición WHERE según si existe la columna
if ($column_exists) {
    $query .= " WHERE v.tipo_negocio = 'clinica' OR v.tipo_negocio IS NULL";
}

$query .= " ORDER BY v.fecha_venta DESC";

$result = $conn->query($query);
$row_count = 4;
$total_clinica = 0;

while ($row = $result->fetch_assoc()) {
    // Formatear datos para el cliente/mascota
    $cliente_mascota = $row['nombre_cliente'];
    if (!empty($row['nombre_mascota'])) {
        $cliente_mascota .= ' / ' . $row['nombre_mascota'];
    }
    
    // Llenar datos
    $sheet->setCellValue('A' . $row_count, date('d/m/Y H:i', strtotime($row['fecha_venta'])));
    $sheet->setCellValue('B' . $row_count, $cliente_mascota);
    $sheet->setCellValue('C' . $row_count, $row['item_nombre'] . ' (' . ucfirst($row['tipo_item']) . ')');
    $sheet->setCellValue('D' . $row_count, $row['cantidad']);
    $sheet->setCellValue('E' . $row_count, $row['subtotal']);
    $sheet->setCellValue('F' . $row_count, $row['medio_pago']);
    
    $total_clinica += $row['subtotal'];
    $row_count++;
}

// Aplicar estilo a todas las filas de datos
$sheet->getStyle('A4:F' . ($row_count - 1))->applyFromArray($dataStyle);

// Formato para moneda
$sheet->getStyle('E4:E' . ($row_count - 1))
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');

// Agregar total
$sheet->setCellValue('D' . $row_count, 'TOTAL CLÍNICA:');
$sheet->setCellValue('E' . $row_count, $total_clinica);
$sheet->getStyle('D' . $row_count . ':E' . $row_count)->applyFromArray($totalStyle);
$sheet->getStyle('E' . $row_count)
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');

// Autoajustar anchos de columna
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}


// ====== HOJA DE FARMACIA ======
$spreadsheet->createSheet();
$sheet = $spreadsheet->getSheet(2);
$sheet->setTitle('Farmacia');

// Encabezado
$sheet->setCellValue('A1', 'RESUMEN DE VENTAS - FARMACIA');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Encabezados de la tabla
$sheet->setCellValue('A3', 'Fecha');
$sheet->setCellValue('B3', 'Cliente / Mascota');
$sheet->setCellValue('C3', 'Item');
$sheet->setCellValue('D3', 'Cantidad');
$sheet->setCellValue('E3', 'Subtotal');
$sheet->setCellValue('F3', 'Medio de Pago');
$sheet->getStyle('A3:F3')->applyFromArray($headerStyle);

// Obtener datos de ventas de tipo farmacia
$query = "SELECT 
            v.fecha_venta, 
            CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
            m.nombre as nombre_mascota,
            CASE 
                WHEN v.tipo_item = 'producto' THEN p.nombre 
                WHEN v.tipo_item = 'servicio' THEN s.nombre 
                ELSE 'Desconocido'
            END as item_nombre,
            v.tipo_item,
            v.cantidad,
            v.subtotal,
            v.medio_pago
          FROM ventas v
          LEFT JOIN mascotas m ON v.id_mascota = m.id_mascota 
          LEFT JOIN clientes c ON m.id_cliente = c.id_cliente
          LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
          LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio";

// Añadimos la condición WHERE solo si la columna existe
if ($column_exists) {
    $query .= " WHERE v.tipo_negocio = 'farmacia'";
} else {
    // Si la columna no existe, no mostramos nada en esta pestaña
    $query .= " WHERE 1=0"; // Condición para no devolver resultados
}

$query .= " ORDER BY v.fecha_venta DESC";

$result = $conn->query($query);
$row_count = 4;
$total_farmacia = 0;

while ($row = $result->fetch_assoc()) {
    // Formatear datos para el cliente/mascota
    $cliente_mascota = $row['nombre_cliente'];
    if (!empty($row['nombre_mascota'])) {
        $cliente_mascota .= ' / ' . $row['nombre_mascota'];
    }
    
    // Llenar datos
    $sheet->setCellValue('A' . $row_count, date('d/m/Y H:i', strtotime($row['fecha_venta'])));
    $sheet->setCellValue('B' . $row_count, $cliente_mascota);
    $sheet->setCellValue('C' . $row_count, $row['item_nombre'] . ' (' . ucfirst($row['tipo_item']) . ')');
    $sheet->setCellValue('D' . $row_count, $row['cantidad']);
    $sheet->setCellValue('E' . $row_count, $row['subtotal']);
    $sheet->setCellValue('F' . $row_count, $row['medio_pago']);
    
    $total_farmacia += $row['subtotal'];
    $row_count++;
}

// Aplicar estilo a todas las filas de datos
$sheet->getStyle('A4:F' . ($row_count - 1))->applyFromArray($dataStyle);

// Formato para moneda
$sheet->getStyle('E4:E' . ($row_count - 1))
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');

// Agregar total
$sheet->setCellValue('D' . $row_count, 'TOTAL FARMACIA:');
$sheet->setCellValue('E' . $row_count, $total_farmacia);
$sheet->getStyle('D' . $row_count . ':E' . $row_count)->applyFromArray($totalStyle);
$sheet->getStyle('E' . $row_count)
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');

// Autoajustar anchos de columna
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}


// ====== HOJA DE PET SHOP ======
$spreadsheet->createSheet();
$sheet = $spreadsheet->getSheet(3);
$sheet->setTitle('Pet Shop');

// Encabezado
$sheet->setCellValue('A1', 'RESUMEN DE VENTAS - PET SHOP');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Encabezados de la tabla
$sheet->setCellValue('A3', 'Fecha');
$sheet->setCellValue('B3', 'Cliente / Mascota');
$sheet->setCellValue('C3', 'Item');
$sheet->setCellValue('D3', 'Cantidad');
$sheet->setCellValue('E3', 'Subtotal');
$sheet->setCellValue('F3', 'Medio de Pago');
$sheet->getStyle('A3:F3')->applyFromArray($headerStyle);

// Obtener datos de ventas de tipo pet shop
$query = "SELECT 
            v.fecha_venta, 
            CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
            m.nombre as nombre_mascota,
            CASE 
                WHEN v.tipo_item = 'producto' THEN p.nombre 
                WHEN v.tipo_item = 'servicio' THEN s.nombre 
                ELSE 'Desconocido'
            END as item_nombre,
            v.tipo_item,
            v.cantidad,
            v.subtotal,
            v.medio_pago
          FROM ventas v
          LEFT JOIN mascotas m ON v.id_mascota = m.id_mascota 
          LEFT JOIN clientes c ON m.id_cliente = c.id_cliente
          LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
          LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio";

// Añadimos la condición WHERE solo si la columna existe
if ($column_exists) {
    $query .= " WHERE v.tipo_negocio = 'petshop'";
} else {
    // Si la columna no existe, no mostramos nada en esta pestaña
    $query .= " WHERE 1=0"; // Condición para no devolver resultados
}

$query .= " ORDER BY v.fecha_venta DESC";

$result = $conn->query($query);
$row_count = 4;
$total_petshop = 0;

while ($row = $result->fetch_assoc()) {
    // Formatear datos para el cliente/mascota
    $cliente_mascota = $row['nombre_cliente'];
    if (!empty($row['nombre_mascota'])) {
        $cliente_mascota .= ' / ' . $row['nombre_mascota'];
    }
    
    // Llenar datos
    $sheet->setCellValue('A' . $row_count, date('d/m/Y H:i', strtotime($row['fecha_venta'])));
    $sheet->setCellValue('B' . $row_count, $cliente_mascota);
    $sheet->setCellValue('C' . $row_count, $row['item_nombre'] . ' (' . ucfirst($row['tipo_item']) . ')');
    $sheet->setCellValue('D' . $row_count, $row['cantidad']);
    $sheet->setCellValue('E' . $row_count, $row['subtotal']);
    $sheet->setCellValue('F' . $row_count, $row['medio_pago']);
    
    $total_petshop += $row['subtotal'];
    $row_count++;
}

// Aplicar estilo a todas las filas de datos
$sheet->getStyle('A4:F' . ($row_count - 1))->applyFromArray($dataStyle);

// Formato para moneda
$sheet->getStyle('E4:E' . ($row_count - 1))
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');

// Agregar total
$sheet->setCellValue('D' . $row_count, 'TOTAL PET SHOP:');
$sheet->setCellValue('E' . $row_count, $total_petshop);
$sheet->getStyle('D' . $row_count . ':E' . $row_count)->applyFromArray($totalStyle);
$sheet->getStyle('E' . $row_count)
    ->getNumberFormat()
    ->setFormatCode('_("S/ "* #,##0.00_);_("S/ "* \(#,##0.00\);_("S/ "* "-"??_);_(@_)');

// Autoajustar anchos de columna
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Establecer la primera hoja como activa
$spreadsheet->setActiveSheetIndex(0);

// Determinar formato de exportación
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'excel';
$filename = 'cuadre_caja_' . date('Y-m-d_H-i-s');

// Obtener parámetros
$incluir_detalles = isset($_GET['detalles']) ? $_GET['detalles'] == '1' : true;
$incluir_resumen = isset($_GET['resumen']) ? $_GET['resumen'] == '1' : true;
$incluir_medios_pago = isset($_GET['medios_pago']) ? $_GET['medios_pago'] == '1' : true;

// Si se solicita formato PDF
if ($formato === 'pdf') {
    require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    // Crear nuevo PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    
    // Configurar PDF
    $pdf->SetCreator('VetSolution');
    $pdf->SetAuthor('VetSolution');
    $pdf->SetTitle('Cuadre de Caja');
    $pdf->SetSubject('Cuadre de Caja');
    $pdf->SetKeywords('Caja, Ventas, Reporte');
    
    // Eliminar encabezado y pie de página
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurar márgenes
    $pdf->SetMargins(15, 15, 15);
    
    // Agregar página
    $pdf->AddPage();
    
    // Título
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'REPORTE DE CUADRE DE CAJA', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Fecha de generación: ' . date('d/m/Y H:i:s', strtotime($fecha_actual)), 0, 1, 'C');
    
    // Separador
    $pdf->Ln(5);
    
    // Total en caja
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'TOTAL EN CAJA: S/ ' . number_format($total_caja, 2), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Mostrar resumen por medios de pago si se solicitó
    if ($incluir_medios_pago) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'RESUMEN POR MEDIOS DE PAGO', 0, 1, 'L');
        
        // Crear tabla
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(120, 7, 'Medio de Pago', 1, 0, 'C');
        $pdf->Cell(50, 7, 'Importe', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        foreach ($medios as $medio) {
            $query = "SELECT COALESCE(SUM(subtotal), 0) as total FROM ventas WHERE medio_pago = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $medio);
            $stmt->execute();
            $result = $stmt->get_result();
            $total = $result->fetch_assoc()['total'];
            
            $pdf->Cell(120, 7, $medio, 1, 0, 'L');
            $pdf->Cell(50, 7, 'S/ ' . number_format($total, 2), 1, 1, 'R');
        }
        
        // Totales
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(120, 7, 'Total Ingresos', 1, 0, 'L');
        $pdf->Cell(50, 7, 'S/ ' . number_format($total_medios, 2), 1, 1, 'R');
        
        $pdf->Cell(120, 7, 'Total Egresos', 1, 0, 'L');
        $pdf->Cell(50, 7, 'S/ ' . number_format($total_egresos, 2), 1, 1, 'R');
        
        $pdf->Cell(120, 7, 'TOTAL EN CAJA', 1, 0, 'L');
        $pdf->Cell(50, 7, 'S/ ' . number_format($total_caja, 2), 1, 1, 'R');
        
        $pdf->Ln(10);
    }
    
    // Mostrar resumen por tipo de negocio si se solicitó
    if ($incluir_resumen && $column_exists) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'RESUMEN POR TIPO DE NEGOCIO', 0, 1, 'L');
        
        // Crear tabla
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(120, 7, 'Tipo de Negocio', 1, 0, 'C');
        $pdf->Cell(50, 7, 'Importe', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(120, 7, 'Clínica', 1, 0, 'L');
        $pdf->Cell(50, 7, 'S/ ' . number_format($total_clinica, 2), 1, 1, 'R');
        
        $pdf->Cell(120, 7, 'Farmacia', 1, 0, 'L');
        $pdf->Cell(50, 7, 'S/ ' . number_format($total_farmacia, 2), 1, 1, 'R');
        
        $pdf->Cell(120, 7, 'Pet Shop', 1, 0, 'L');
        $pdf->Cell(50, 7, 'S/ ' . number_format($total_petshop, 2), 1, 1, 'R');
        
        // Total
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(120, 7, 'TOTAL', 1, 0, 'L');
        $pdf->Cell(50, 7, 'S/ ' . number_format($total_clinica + $total_farmacia + $total_petshop, 2), 1, 1, 'R');
        
        $pdf->Ln(10);
    }
    
    // Mostrar detalle de ventas si se solicitó
    if ($incluir_detalles) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'DETALLE DE VENTAS', 0, 1, 'L');
        
        // Crear tabla
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(30, 7, 'Fecha', 1, 0, 'C');
        $pdf->Cell(50, 7, 'Cliente / Mascota', 1, 0, 'C');
        $pdf->Cell(25, 7, 'Tipo', 1, 0, 'C');
        $pdf->Cell(40, 7, 'Item', 1, 0, 'C');
        $pdf->Cell(25, 7, 'Subtotal', 1, 1, 'C');
        
        // Obtener datos de ventas
        $query = "SELECT 
                    v.fecha_venta, 
                    CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
                    m.nombre as nombre_mascota,
                    IFNULL(v.tipo_negocio, 'clinica') as tipo_negocio,
                    CASE 
                        WHEN v.tipo_item = 'producto' THEN p.nombre 
                        WHEN v.tipo_item = 'servicio' THEN s.nombre 
                        ELSE 'Desconocido'
                    END as item_nombre,
                    v.subtotal,
                    v.medio_pago
                  FROM ventas v
                  LEFT JOIN mascotas m ON v.id_mascota = m.id_mascota 
                  LEFT JOIN clientes c ON m.id_cliente = c.id_cliente
                  LEFT JOIN productos p ON v.tipo_item = 'producto' AND v.id_item = p.id_producto
                  LEFT JOIN servicios s ON v.tipo_item = 'servicio' AND v.id_item = s.id_servicio
                  ORDER BY v.fecha_venta DESC";
        $result = $conn->query($query);
        
        $pdf->SetFont('helvetica', '', 8);
        
        while ($row = $result->fetch_assoc()) {
            // Formatear datos para el cliente/mascota
            $cliente_mascota = $row['nombre_cliente'];
            if (!empty($row['nombre_mascota'])) {
                $cliente_mascota .= ' / ' . $row['nombre_mascota'];
            }
            
            $pdf->Cell(30, 6, date('d/m/Y H:i', strtotime($row['fecha_venta'])), 1, 0, 'L');
            $pdf->Cell(50, 6, $cliente_mascota, 1, 0, 'L');
            $pdf->Cell(25, 6, ucfirst($row['tipo_negocio']), 1, 0, 'L');
            $pdf->Cell(40, 6, $row['item_nombre'], 1, 0, 'L');
            $pdf->Cell(25, 6, 'S/ ' . number_format($row['subtotal'], 2), 1, 1, 'R');
        }
    }
    
    // Generar el PDF
    $filename = $filename . '.pdf';
    $pdf->Output($filename, 'I');
    exit();
} else {
    // Excel (por defecto)
    // Guardar el archivo
    $writer = new Xlsx($spreadsheet);
    $filename = $filename . '.xlsx';
    
    // Mostrar en el navegador
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: inline;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit();
}
