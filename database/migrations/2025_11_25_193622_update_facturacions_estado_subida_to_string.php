<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero, migrar los datos existentes
        DB::table('facturacions')
            ->where('estado_subida', 0)
            ->update(['estado_subida' => -1]); // Temporal para DENEGADO

        DB::table('facturacions')
            ->where('estado_subida', 1)
            ->update(['estado_subida' => -2]); // Temporal para SUBIDA

        // Cambiar el tipo de columna a string
        Schema::table('facturacions', function (Blueprint $table) {
            $table->string('estado_subida', 20)->nullable()->change();
        });

        // Actualizar con los nuevos valores
        DB::table('facturacions')
            ->where('estado_subida', '-1')
            ->update(['estado_subida' => 'DENEGADO']);

        DB::table('facturacions')
            ->where('estado_subida', '-2')
            ->update(['estado_subida' => 'SUBIDA']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a los valores numéricos
        DB::table('facturacions')
            ->where('estado_subida', 'DENEGADO')
            ->update(['estado_subida' => '0']);

        DB::table('facturacions')
            ->where('estado_subida', 'SUBIDA')
            ->update(['estado_subida' => '1']);

        DB::table('facturacions')
            ->where('estado_subida', 'APROBADO')
            ->update(['estado_subida' => '1']);

        // Cambiar de vuelta a boolean
        Schema::table('facturacions', function (Blueprint $table) {
            $table->boolean('estado_subida')->nullable()->change();
        });
    }
};
