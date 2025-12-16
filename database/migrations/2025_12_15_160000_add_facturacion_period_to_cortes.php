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
        Schema::table('cortes', function (Blueprint $table) {
            $table->date('fecha_inicio_facturacion')->nullable()->after('fecha_fin');
            $table->date('fecha_fin_facturacion')->nullable()->after('fecha_inicio_facturacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cortes', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio_facturacion', 'fecha_fin_facturacion']);
        });
    }
};
