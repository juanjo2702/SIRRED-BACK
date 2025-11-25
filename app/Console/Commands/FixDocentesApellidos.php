<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Docente;

class FixDocentesApellidos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:docentes-apellidos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix docentes apellidos by splitting full name';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing docentes apellidos...');

        $docentes = Docente::whereNull('apellidos')
            ->orWhere('apellidos', '')
            ->get();

        $this->info("Found {$docentes->count()} docentes with empty apellidos");

        $fixed = 0;
        foreach ($docentes as $docente) {
            // Split the nombre field
            // Assuming format: "APELLIDO1 APELLIDO2 NOMBRE1 NOMBRE2..."
            // We'll take the first 2 words as apellidos and the rest as nombre
            $parts = explode(' ', trim($docente->nombre));

            if (count($parts) >= 3) {
                // Take first 2 as apellidos, rest as nombre
                $apellidos = $parts[0] . ' ' . $parts[1];
                $nombre = implode(' ', array_slice($parts, 2));
            } elseif (count($parts) == 2) {
                // Take first as apellido, second as nombre
                $apellidos = $parts[0];
                $nombre = $parts[1];
            } else {
                // Only one word, keep as nombre
                continue;
            }

            $docente->update([
                'apellidos' => $apellidos,
                'nombre' => $nombre
            ]);

            $fixed++;
            $this->line("Fixed: {$docente->ci} - {$apellidos}, {$nombre}");
        }

        $this->info("Fixed {$fixed} docentes");

        return 0;
    }
}
