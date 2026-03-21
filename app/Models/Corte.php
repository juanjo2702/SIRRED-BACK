<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Corte extends Model
{
    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'estado', 'tipo_corte'];

    public function facturacions()
    {
        return $this->hasMany(Facturacion::class);
    }
}
