<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Corte extends Model
{
    protected $fillable = ['gestion_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'tipo_corte'];

    public function gestion()
    {
        return $this->belongsTo(Gestion::class);
    }

    public function facturacions()
    {
        return $this->hasMany(Facturacion::class);
    }
}
