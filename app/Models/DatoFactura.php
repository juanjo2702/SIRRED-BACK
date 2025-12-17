<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatoFactura extends Model
{
    protected $table = 'datos_factura';

    protected $fillable = [
        'facturacion_id',
        'nit_emisor',
        'razon_social_emisor',
        'nit_cliente',
        'razon_social_cliente',
        'numero_factura',
        'codigo_autorizacion',
        'fecha_factura',
        'monto_total',
        'texto_completo',
        'qr_url',
    ];

    protected $casts = [
        'fecha_factura' => 'datetime',
        'monto_total' => 'decimal:2',
    ];

    public function facturacion()
    {
        return $this->belongsTo(Facturacion::class);
    }
}
