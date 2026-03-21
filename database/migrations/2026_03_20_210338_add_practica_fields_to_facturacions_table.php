<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facturacions', function (Blueprint $table) {
            $table->boolean('es_practica')->default(false)->after('estado_subida');
            $table->date('fecha_inicio_practica')->nullable()->after('es_practica');
            $table->date('fecha_fin_practica')->nullable()->after('fecha_inicio_practica');
            $table->string('materia_practica')->nullable()->after('fecha_fin_practica');
            $table->string('hospital_practica')->nullable()->after('materia_practica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturacions', function (Blueprint $table) {
            $table->dropColumn([
                'es_practica',
                'fecha_inicio_practica',
                'fecha_fin_practica',
                'materia_practica',
                'hospital_practica'
            ]);
        });
    }
};
