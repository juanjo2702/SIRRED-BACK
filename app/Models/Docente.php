<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Facturacion; // Added this use statement for Facturacion model

class Docente extends Model
{
    protected $fillable = ['nombre', 'apellidos', 'ci', 'estado', 'tipo_compra'];

    public function facturacions()
    {
        return $this->hasMany(Facturacion::class);
    }
    //
}
