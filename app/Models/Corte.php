<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Corte extends Model
{
    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'fecha_inicio_facturacion',
        'fecha_fin_facturacion'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_inicio_facturacion' => 'date',
        'fecha_fin_facturacion' => 'date',
    ];

    public function facturacions()
    {
        return $this->hasMany(Facturacion::class);
    }

    /**
     * Verifica si el periodo de facturación está abierto
     * Usa zona horaria de Bolivia (America/La_Paz)
     */
    public function isPeriodoFacturacionAbierto(): bool
    {
        $ahora = Carbon::now('America/La_Paz')->startOfDay();

        // Si no hay fechas configuradas, el periodo está abierto (comportamiento legacy)
        if (!$this->fecha_inicio_facturacion || !$this->fecha_fin_facturacion) {
            return true;
        }

        $inicio = Carbon::parse($this->fecha_inicio_facturacion)->startOfDay();
        $fin = Carbon::parse($this->fecha_fin_facturacion)->endOfDay();

        return $ahora->between($inicio, $fin);
    }

    /**
     * Retorna el estado del periodo de facturación
     * @return string 'pendiente' | 'abierto' | 'cerrado'
     */
    public function getPeriodoStatus(): string
    {
        $ahora = Carbon::now('America/La_Paz')->startOfDay();

        if (!$this->fecha_inicio_facturacion || !$this->fecha_fin_facturacion) {
            return 'abierto'; // Comportamiento legacy
        }

        $inicio = Carbon::parse($this->fecha_inicio_facturacion)->startOfDay();
        $fin = Carbon::parse($this->fecha_fin_facturacion)->endOfDay();

        if ($ahora->lt($inicio)) {
            return 'pendiente';
        }

        if ($ahora->gt($fin)) {
            return 'cerrado';
        }

        return 'abierto';
    }

    /**
     * Calcula los días restantes para el cierre del periodo
     * @return int|null Días restantes, null si no hay fecha configurada
     */
    public function getDiasRestantes(): ?int
    {
        if (!$this->fecha_fin_facturacion) {
            return null;
        }

        $ahora = Carbon::now('America/La_Paz')->startOfDay();
        $fin = Carbon::parse($this->fecha_fin_facturacion)->startOfDay();

        if ($ahora->gt($fin)) {
            return 0;
        }

        return $ahora->diffInDays($fin);
    }
}

