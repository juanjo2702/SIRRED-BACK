<?php

namespace App\Services;

use App\Models\FacturacionLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class FacturacionAuditService
{
    /**
     * Log an action to the database.
     *
     * @param string $action
     * @param int|null $facturacionId
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param string|null $comment
     * @return FacturacionLog
     */
    public function logAction(
        string $action,
        ?int $facturacionId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $comment = null
    ): FacturacionLog {
        return FacturacionLog::create([
            'facturacion_id' => $facturacionId,
            'user_id'        => Auth::id(),
            'action'         => $action,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'comment'        => $comment,
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
        ]);
    }
}
