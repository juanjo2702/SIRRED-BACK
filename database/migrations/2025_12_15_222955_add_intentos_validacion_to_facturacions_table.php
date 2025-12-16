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
            $table->integer('intentos_validacion')->default(0)->after('estado_subida');
            $table->text('errores_validacion')->nullable()->after('intentos_validacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturacions', function (Blueprint $table) {
            $table->dropColumn(['intentos_validacion', 'errores_validacion']);
        });
    }
};
