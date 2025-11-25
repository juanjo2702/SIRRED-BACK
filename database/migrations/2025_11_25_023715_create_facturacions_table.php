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
        Schema::create('facturacions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docentes');
            $table->foreignId('sede_carrera_id')->constrained('sede_carreras');
            $table->foreignId('corte_id')->constrained('cortes');
            $table->enum('tipo_contrato', ['FACTURACION', 'RETENCION', 'AFILIACION']);
            $table->decimal('monto', 10, 2);
            $table->integer('carga_horaria');
            $table->timestamp('fecha_subida')->nullable();
            $table->string('factura_path')->nullable();
            $table->boolean('estado_subida')->nullable(); // 0=denied, 1=uploaded, null=pending
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturacions');
    }
};
