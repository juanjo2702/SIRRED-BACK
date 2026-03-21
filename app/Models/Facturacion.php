<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facturacion extends Model
{
    protected $fillable = [
        'docente_id', 'sede_carrera_id', 'corte_id', 'tipo_contrato',
        'monto', 'carga_horaria', 'fecha_subida', 'factura_path', 'estado_subida',
        'es_practica', 'fecha_inicio_practica', 'fecha_fin_practica', 'materia_practica', 'hospital_practica'
    ];

    public function docente()
    {
        return $this->belongsTo(Docente::class);
    }

    public function sedeCarrera()
    {
        return $this->belongsTo(SedeCarrera::class);
    }

    public function corte()
    {
        return $this->belongsTo(Corte::class);
    }
}
