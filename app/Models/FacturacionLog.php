<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacturacionLog extends Model
{
    // Table name is facturacion_logs
    protected $table = 'facturacion_logs';

    // Disable standard timestamps as we only have created_at
    public $timestamps = false;

    protected $fillable = [
        'facturacion_id', 'user_id', 'action', 'old_values', 'new_values',
        'comment', 'ip_address', 'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the facturacion record associated with this log.
     */
    public function facturacion()
    {
        return $this->belongsTo(Facturacion::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
