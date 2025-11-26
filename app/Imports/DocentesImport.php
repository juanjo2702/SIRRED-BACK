<?php

namespace App\Imports;

use App\Models\Docente;
use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DocentesImport implements ToModel, WithHeadingRow
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

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Validate that CI exists and is not empty
        $ci = $row['ci'] ?? null;
        if (empty($ci)) {
            // Skip rows without CI
            return null;
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

        // Create facturacion record
        Facturacion::create([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $this->sedeCarreraId,
            'corte_id' => $this->corteId,
            'tipo_contrato' => $this->tipoContrato,
            'monto' => $row['liquido_pagable'] ?? 0,
            'carga_horaria' => $row['carga_pagada'] ?? 0,
            'fecha_subida' => null,
            'factura_path' => null,
            'estado_subida' => null
        ]);

        return null; // We handle creation manually
    }
}
