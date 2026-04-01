<?php

namespace App\Imports;

use App\Models\Docente;
use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;

class DocentesPracticaImport implements OnEachRow, WithStartRow, WithChunkReading
{
    protected $sedeCarreraId;
    protected $corteId;

    public function __construct($sedeCarreraId, $corteId)
    {
        $this->sedeCarreraId = $sedeCarreraId;
        $this->corteId = $corteId;
    }

    public function startRow(): int
    {
        return 2;
    }

    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row      = $row->toArray();

        // 0 => (vacio), 1 => No., 2 => Apellidos, 3 => Nombres, 4 => CI, 5 => Fecha Inicio, 6 => Fecha Fin, 7 => Materia, 8 => Hospital, 9 => Importe
        $ci = trim($row[4] ?? '');
        if (empty($ci) || strtolower($ci) === 'c.i.') {
            return;
        }

        $apellidos = trim($row[2] ?? '');
        $nombre = trim($row[3] ?? '');

        $fechaInicio = $this->transformDate($row[5] ?? null);
        $fechaFin = $this->transformDate($row[6] ?? null);

        $materia = trim($row[7] ?? '');
        $hospital = trim($row[8] ?? '');
        $monto = floatval($row[9] ?? 0);

        // Find or create docente by CI
        $docente = Docente::updateOrCreate(
            ['ci' => $ci],
            [
                'nombre' => $nombre,
                'apellidos' => $apellidos,
                'estado' => 1
            ]
        );

        // Find or create facturacion record
        // Include materia, hospital, and dates in the key so each unique
        // practice assignment (different hospital, subject, or dates) gets
        // its own record instead of overwriting the previous one.
        $facturacion = Facturacion::firstOrNew([
            'docente_id' => $docente->id,
            'sede_carrera_id' => $this->sedeCarreraId,
            'corte_id' => $this->corteId,
            'es_practica' => true,
            'materia_practica' => $materia,
            'hospital_practica' => $hospital,
            'fecha_inicio_practica' => $fechaInicio,
            'fecha_fin_practica' => $fechaFin,
        ]);

        if ($facturacion->exists && $facturacion->monto != $monto) {
            if ($facturacion->estado_subida === 'APROBADO') {
                $facturacion->estado_subida = 'SUBIDA';
            }
        }

        $facturacion->monto = $monto;
        $facturacion->carga_horaria = 0; // Practice doesn't have carga horaria
        $facturacion->tipo_contrato = 'FACTURACION'; // Default for practice
        $facturacion->fecha_inicio_practica = $fechaInicio;
        $facturacion->fecha_fin_practica = $fechaFin;
        $facturacion->materia_practica = $materia;
        $facturacion->hospital_practica = $hospital;
        $facturacion->save();
    }

    public function chunkSize(): int
    {
        return 100;
    }

    private function transformDate($value, $format = 'Y-m-d')
    {
        try {
            if (empty($value)) return null;
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format($format);
            }
            // If it's a string like dd/mm/yyyy
            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
            return $date ? $date->format($format) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
