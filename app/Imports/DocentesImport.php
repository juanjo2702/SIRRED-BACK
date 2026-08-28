<?php

namespace App\Imports;

use App\Models\Docente;
use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

class DocentesImport implements OnEachRow, WithHeadingRow, WithChunkReading
{
    protected $sedeCarreraId;
    protected $corteId;
    protected $tipoContrato;

    public function __construct($sedeCarreraId, $corteId, $tipoContrato)
    {
        $this->sedeCarreraId = $sedeCarreraId;
        $this->corteId = $corteId;
        $this->tipoContrato = $tipoContrato;
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row      = $row->toArray();

        // Validate that CI exists and is not empty
        $ci = $row['ci'] ?? null;
        if (empty($ci)) {
            // Skip rows without CI
            return;
        }

        // Map apellidos - try different possible column names
        $primerApellido = $row['1_apellido'] ?? $row['1o_apellido'] ?? $row['primer_apellido'] ?? '';
        $segundoApellido = $row['2_apellido'] ?? $row['2o_apellido'] ?? $row['segundo_apellido'] ?? '';
        $apellidos = trim($primerApellido . ' ' . $segundoApellido);

        // Get nombre
        $nombre = $row['nombres'] ?? $row['nombre'] ?? $row['nombres'] ?? '';

        // Find or create docente by CI, updating if exists
        $docente = Docente::updateOrCreate(
            ['ci' => $ci],
            [
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'estado' => 1
            ]
        );

        // Find or create facturacion record
        $facturacion = Facturacion::firstOrNew([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $this->sedeCarreraId,
            'corte_id' => $this->corteId,
            'tipo_contrato' => $this->tipoContrato,
        ]);

        $nuevoMonto = $this->parseNumber($row['liquido_pagable'] ?? 0);
        $nuevaCarga = $this->parseNumber($row['carga_pagada'] ?? 0);

        // Check if amount changed and reset status if it was approved
        if ($facturacion->exists && $facturacion->monto != $nuevoMonto) {
            if ($facturacion->estado_subida === 'APROBADO') {
                $facturacion->estado_subida = 'SUBIDA';
            }
        }

        $facturacion->monto = $nuevoMonto;
        $facturacion->carga_horaria = $nuevaCarga;
        $facturacion->save();
    }

    private function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $clean = trim($value);
            // Handle cases with thousands separators and decimal points
            if (str_contains($clean, ',') && str_contains($clean, '.')) {
                if (strrpos($clean, ',') > strrpos($clean, '.')) {
                    // European / Latin format: 1.234,50
                    $clean = str_replace('.', '', $clean);
                    $clean = str_replace(',', '.', $clean);
                } else {
                    // US format: 1,234.50
                    $clean = str_replace(',', '', $clean);
                }
            } elseif (str_contains($clean, ',')) {
                // Comma as decimal separator: 12,50
                $clean = str_replace(',', '.', $clean);
            }

            $clean = preg_replace('/[^\d.-]/', '', $clean);
            return is_numeric($clean) ? (float) $clean : 0.0;
        }

        return 0.0;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
