<?php
require 'vendor/autoload.php';

$path = '../archivo practicas hospitales.xlsx';
if (!file_exists($path)) {
    echo "Archivo no encontrado en $path\n";
    exit;
}

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();
foreach($sheet->getRowIterator(1, 15) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $data = [];
    foreach($cellIterator as $cell) {
        $data[] = $cell->getFormattedValue();
    }
    echo 'Row ' . $row->getRowIndex() . ': ' . json_encode($data) . PHP_EOL;
}
