<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Corte;
use App\Models\Facturacion;
use Carbon\Carbon;

class MarcarRezagados extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facturacion:marcar-rezagados';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marca como REZAGADO las facturaciones sin subir después del cierre del periodo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ahora = Carbon::now('America/La_Paz');

        // Buscar cortes activos con periodo de facturación cerrado
        $cortes = Corte::where('estado', 1)
            ->whereNotNull('fecha_fin_facturacion')
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

            if ($marcados > 0) {
                $this->info("Corte '{$corte->nombre}': {$marcados} facturaciones marcadas como REZAGADO");
            }
        }

        if ($totalMarcados === 0) {
            $this->info('No hay facturaciones pendientes para marcar como REZAGADO');
        } else {
            $this->info("Total: {$totalMarcados} facturaciones marcadas como REZAGADO");
        }

        return Command::SUCCESS;
    }
}
