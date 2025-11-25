<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SedeCarrera extends Model
{
    protected $fillable = ['sede_id', 'carrera_id', 'estado'];

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class);
    }

    public function facturacions()
    {
        return $this->hasMany(Facturacion::class);
    }
}
