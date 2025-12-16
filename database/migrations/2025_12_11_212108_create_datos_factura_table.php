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
        Schema::create('datos_factura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facturacion_id')->constrained('facturacions')->onDelete('cascade');
            $table->string('nit_emisor', 20)->nullable();
            $table->string('razon_social_emisor')->nullable();
            $table->string('numero_factura', 50)->nullable();
            $table->string('codigo_autorizacion')->nullable();
            $table->date('fecha_factura')->nullable();
            $table->decimal('monto_total', 12, 2)->nullable();
            $table->text('texto_completo')->nullable();
            $table->timestamps();

            $table->index('facturacion_id');
            $table->index('nit_emisor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_factura');
    }
};
