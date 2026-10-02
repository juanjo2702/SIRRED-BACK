<?php

namespace App\Services;

use App\Models\Facturacion;
use Illuminate\Support\Facades\Storage;

class FacturacionFileRenameService
{
    /**
     * Rename the PDF file associated with a billing record based on its current relationships.
     *
     * @param Facturacion $facturacion
     * @return bool Returns true if a rename occurred, false otherwise.
     */
    public function renameFile(Facturacion $facturacion): bool
    {
        if (!$facturacion->factura_path) {
            return false;
        }

        // Ensure we have the latest relationships loaded
        $facturacion->load(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte']);

        $oldPath = $facturacion->factura_path;

        // Verify the file actually exists
        if (!Storage::disk('public')->exists($oldPath)) {
            return false;
        }

        $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre);
        $sedeIdentifier = $facturacion->sedeCarrera->sede->abreviacion ?? $facturacion->sedeCarrera->sede->id;
        $filename = $facturacion->docente->ci . '_' . $sedeIdentifier . '_' . $carreraNombre . '_' . $facturacion->corte->nombre . '.pdf';
        
        // Clean filename of invalid filesystem characters but keep extension, dots and underscores
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $filename);
        $newPath = 'facturas/' . $filename;

        if ($oldPath !== $newPath) {
            // Collision resolution: delete target if it exists
            if (Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            Storage::disk('public')->move($oldPath, $newPath);
            
            $facturacion->update([
                'factura_path' => $newPath
            ]);

            return true;
        }

        return false;
    }
}
