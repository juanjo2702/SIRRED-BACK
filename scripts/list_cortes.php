<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cortes = \App\Models\Corte::all();

foreach ($cortes as $corte) {
    echo "ID: {$corte->id} | Nombre: {$corte->nombre} | Inicio: {$corte->fecha_inicio?->format('Y-m-d')} | Fin: {$corte->fecha_fin?->format('Y-m-d')}\n";
}
