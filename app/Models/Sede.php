<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $fillable = ['nombre', 'estado', 'abreviacion'];

    public function sedeCarreras()
    {
        return $this->hasMany(SedeCarrera::class);
    }
}
