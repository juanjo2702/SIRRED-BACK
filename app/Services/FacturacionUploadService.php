<?php

namespace App\Services;

use App\Models\Facturacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FacturacionUploadService
{
    protected FacturacionAuditService $auditService;

    public function __construct(FacturacionAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle upload/replacement of invoices, bypassing closed cuts for admin users.
     *
     * @param Facturacion $facturacion
     * @param UploadedFile $file
     * @param bool $isAdmin Whether the action is performed by an authenticated admin
     * @param string|null $comment Optional reason for administrative upload
     * @param bool $force Force replace if the invoice is already approved
     * @throws ValidationException
     */
    public function upload(
        Facturacion $facturacion,
        UploadedFile $file,
        bool $isAdmin = false,
        ?string $comment = null,
        bool $force = false
    ): Facturacion {
        $facturacion->load(['docente', 'sedeCarrera.carrera', 'sedeCarrera.sede', 'corte']);

        // Rule 1: Must be FACTURACION contract type
        if ($facturacion->tipo_contrato !== 'FACTURACION') {
            throw new \InvalidArgumentException('Solo el tipo de contrato FACTURACION puede subir facturas.');
        }

        // Rule 2: Prevent modifying approved invoices unless force is enabled for admins
        if ($facturacion->estado_subida === 'APROBADO') {
            if (!($isAdmin && $force)) {
                throw new \InvalidArgumentException('No se puede modificar una factura aprobada.');
            }
        }

        // Rule 3: Check if cut is closed
        if ($facturacion->corte->estado == 0) {
            if (!$isAdmin) {
                throw new \InvalidArgumentException('El corte administrativo está cerrado. No se admiten cargas.');
            }
        }

        // Prepare old values for audit log
        $oldValues = $facturacion->only(['factura_path', 'fecha_subida', 'estado_subida']);

        // Delete old file if it exists
        if ($facturacion->factura_path && Storage::disk('public')->exists($facturacion->factura_path)) {
            Storage::disk('public')->delete($facturacion->factura_path);
        }

        $facturacion->loadMissing(['docente', 'sedeCarrera.carrera', 'sedeCarrera.sede', 'corte.gestion']);

        $ci = $facturacion->docente->ci ?? 'SIN_CI';
        $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre ?? 'CARRERA');
        $sedeIdentifier = $facturacion->sedeCarrera->sede->abreviacion ?? $facturacion->sedeCarrera->sede->id ?? 'SEDE';
        $corteNombre = str_replace(' ', '_', $facturacion->corte->nombre ?? 'CORTE');

        $gestionPrefix = '';
        if ($facturacion->corte && $facturacion->corte->gestion && !empty($facturacion->corte->gestion->nombre)) {
            $gestionClean = str_replace(['/', ' '], ['-', '_'], trim($facturacion->corte->gestion->nombre));
            $gestionPrefix = $gestionClean . '_';
        }

        $filename = $gestionPrefix . $ci . '_' . $sedeIdentifier . '_' . $carreraNombre . '_' . $corteNombre . '.pdf';
        
        // Clean filename
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $filename);
        
        // Store the file on public disk
        $path = $file->storeAs('facturas', $filename, 'public');

        // Update billing record
        $facturacion->update([
            'factura_path' => $path,
            'fecha_subida' => now(),
            'estado_subida' => 'SUBIDA'
        ]);

        // Audit Logging
        $action = 'DOCENTE_UPLOAD_FACTURA';
        if ($isAdmin) {
            if ($facturacion->corte->estado == 0) {
                $action = 'ADMIN_UPLOAD_FACTURA_CLOSED_CORTE';
            } elseif ($oldValues['factura_path']) {
                $action = 'ADMIN_REPLACE_FACTURA';
            } else {
                $action = 'ADMIN_UPLOAD_FACTURA';
            }
        }

        $newValues = $facturacion->only(['factura_path', 'fecha_subida', 'estado_subida']);

        $this->auditService->logAction(
            $action,
            $facturacion->id,
            $oldValues,
            $newValues,
            $comment
        );

        return $facturacion;
    }
}
