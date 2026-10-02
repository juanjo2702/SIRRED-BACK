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
        $facturacion->loadMissing(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte.gestion']);

        $oldPath = $facturacion->factura_path;

        // Verify the file actually exists
        if (!Storage::disk('public')->exists($oldPath)) {
            return false;
        }

        $ci = $facturacion->docente->ci ?? 'SIN_CI';
        $sedeIdentifier = $facturacion->sedeCarrera->sede->abreviacion ?? $facturacion->sedeCarrera->sede->id ?? 'SEDE';
        $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre ?? 'CARRERA');
        $corteNombre = str_replace(' ', '_', $facturacion->corte->nombre ?? 'CORTE');

        $gestionPrefix = '';
        if ($facturacion->corte && $facturacion->corte->gestion && !empty($facturacion->corte->gestion->nombre)) {
            $gestionClean = str_replace(['/', ' '], ['-', '_'], trim($facturacion->corte->gestion->nombre));
            $gestionPrefix = $gestionClean . '_';
        }

        $filename = $gestionPrefix . $ci . '_' . $sedeIdentifier . '_' . $carreraNombre . '_' . $corteNombre . '.pdf';
        
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
