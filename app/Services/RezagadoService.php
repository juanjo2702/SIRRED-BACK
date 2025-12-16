<?php

namespace App\Services;

use App\Models\Corte;
use App\Models\Facturacion;
use Carbon\Carbon;

class RezagadoService
{
    /**
     * Marca automáticamente como REZAGADO las facturaciones
     * de cortes cuyo periodo de facturación ha cerrado
     *
     * @return int Número de registros marcados
     */
    public static function marcarRezagadosAutomaticamente(): int
    {
        $ahora = Carbon::now('America/La_Paz')->startOfDay();

        // Buscar cortes con periodo de facturación cerrado
        $cortes = Corte::whereNotNull('fecha_fin_facturacion')
            ->whereDate('fecha_fin_facturacion', '<', $ahora->toDateString())
            ->get();

        $totalMarcados = 0;

        foreach ($cortes as $corte) {
            // Marcar como REZAGADO las facturaciones sin subir (estado_subida = null)
            $marcados = Facturacion::where('corte_id', $corte->id)
                ->where('tipo_contrato', 'FACTURACION')
                ->whereNull('estado_subida')
                ->update(['estado_subida' => 'REZAGADO']);

            $totalMarcados += $marcados;
        }

        return $totalMarcados;
    }
}
