<?php

namespace App\Services;

use App\Models\Facturacion;
use App\Models\SedeCarrera;
use App\Models\Corte;
use App\Models\Sede;
use App\Models\Carrera;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Dompdf\Dompdf;
use Dompdf\Options;
use setasign\Fpdi\Fpdi;

class FacturacionPrintPackageService
{
    protected FacturacionAuditService $auditService;

    public function __construct(FacturacionAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Generate a unified PDF containing a cover page, a control checklist, and all merged invoice PDFs.
     *
     * @param array $filters Filters: corte_id, sede_id, carrera_id, estado_subida (optional)
     * @return string Binary PDF stream
     */
    public function generatePackage(array $filters): string
    {
        $corte = Corte::findOrFail($filters['corte_id']);
        $sede = Sede::findOrFail($filters['sede_id']);

        $carreraId = $filters['carrera_id'] ?? null;

        if ($carreraId) {
            $carreras = Carrera::where('id', $carreraId)->get();
        } else {
            // Fetch all carreras associated with this Sede in SedeCarrera
            $sedeCarreraIds = SedeCarrera::where('sede_id', $filters['sede_id'])->pluck('carrera_id');
            $carreras = Carrera::whereIn('id', $sedeCarreraIds)->get();
        }

        // Initialize FPDI and merge documents
        $pdf = new Fpdi();
        $pdf->SetTitle($this->encodeText("REPORTE CONSOLIDADO DE FACTURAS - SIRRED"));

        $hasData = false;
        $processedMetrics = [];

        foreach ($carreras as $carrera) {
            $sedeCarrera = SedeCarrera::where('sede_id', $filters['sede_id'])
                ->where('carrera_id', $carrera->id)
                ->first();

            if (!$sedeCarrera) {
                continue;
            }

            // Build base query
            $query = Facturacion::with(['docente'])
                ->where('corte_id', $filters['corte_id'])
                ->where('sede_carrera_id', $sedeCarrera->id);

            if (!empty($filters['estado_subida'])) {
                $query->where('estado_subida', $filters['estado_subida']);
            }

            $facturaciones = $query->get()->sortBy(function($fact) {
                return $fact->docente->apellidos . ' ' . $fact->docente->nombre;
            });

            // Skip this carrera if it has absolutely no records for this cut
            if ($facturaciones->isEmpty()) {
                continue;
            }

            $hasData = true;

            // Compute metrics
            $totalCount = $facturaciones->count();
            $withInvoiceCount = $facturaciones->whereNotNull('factura_path')->count();
            $withoutInvoiceCount = $totalCount - $withInvoiceCount;
            $totalAmount = $facturaciones->sum('monto');

            $user = Auth::user();

            $processedMetrics[] = [
                'carrera' => $carrera->nombre,
                'total_registros' => $totalCount,
                'total_facturas_unidas' => $withInvoiceCount
            ];

            // 1. Generate Cover & Checklist using Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);
            $dompdf = new Dompdf($options);

            $html = View::make('pdf.portada_checklist', [
                'corte'               => $corte,
                'sede'                => $sede,
                'carrera'             => $carrera,
                'facturaciones'       => $facturaciones,
                'totalCount'          => $totalCount,
                'withInvoiceCount'    => $withInvoiceCount,
                'withoutInvoiceCount' => $withoutInvoiceCount,
                'totalAmount'         => $totalAmount,
                'user'                => $user,
                'date'                => now()->format('d/m/Y H:i'),
            ])->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdfOutput = $dompdf->output();

            // Save cover page temporarily to disk
            $tempDompdfPath = tempnam(sys_get_temp_dir(), 'dompdf_');
            file_put_contents($tempDompdfPath, $dompdfOutput);

            // Import Cover & Checklist pages
            $pageCount = $pdf->setSourceFile($tempDompdfPath);
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplIdx = $pdf->importPage($i);
                $size = $pdf->getTemplatesize($tplIdx);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplIdx);
            }
            unlink($tempDompdfPath);

            // Import each teacher invoice sequentially
            foreach ($facturaciones as $facturacion) {
                // Only merge if type is FACTURACION (others don't have invoices usually)
                if ($facturacion->tipo_contrato !== 'FACTURACION') {
                    $this->addPlaceholderPage($pdf, $facturacion, "CONTRATO TIPO " . $facturacion->tipo_contrato . " - NO CORRESPONDE FACTURA");
                    continue;
                }

                if ($facturacion->factura_path) {
                    try {
                        $realPath = Storage::disk('public')->path($facturacion->factura_path);

                        if (file_exists($realPath)) {
                            $invoicePageCount = $pdf->setSourceFile($realPath);
                            for ($i = 1; $i <= $invoicePageCount; $i++) {
                                $tplIdx = $pdf->importPage($i);
                                $size = $pdf->getTemplatesize($tplIdx);
                                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                                $pdf->useTemplate($tplIdx);
                            }
                        } else {
                            $this->addPlaceholderPage($pdf, $facturacion, "ARCHIVO FISICO NO ENCONTRADO EN SERVIDOR");
                        }
                    } catch (\Exception $e) {
                        $this->addPlaceholderPage($pdf, $facturacion, "ERROR AL LEER ARCHIVO PDF: " . $e->getMessage());
                    }
                } else {
                    $this->addPlaceholderPage($pdf, $facturacion, "SIN FACTURA CARGADA");
                }
            }
        }

        if (!$hasData) {
            throw new \Exception("No se encontraron registros de facturacion en ninguna carrera de la Sede " . $sede->nombre . " para este corte.");
        }

        // Output binary string
        $binaryOutput = $pdf->Output('S');

        // Audit log
        $this->auditService->logAction(
            'GENERATE_PRINT_PACKAGE',
            null,
            $filters,
            [
                'corte' => $corte->nombre,
                'sede' => $sede->nombre,
                'carrera' => $carreraId ? $carreras->first()->nombre : 'Todas las Carreras',
                'carreras_procesadas' => $processedMetrics,
            ]
        );

        return $binaryOutput;
    }

    /**
     * Helper to render an elegant fallback placeholder page inside Fpdi.
     */
    protected function addPlaceholderPage(Fpdi $pdf, Facturacion $facturacion, string $message): void
    {
        $pdf->AddPage('P', 'A4');

        // Light background fill
        $pdf->SetFillColor(250, 250, 250);
        $pdf->Rect(0, 0, 210, 297, 'F');

        // Soft double border
        $pdf->SetDrawColor(210, 210, 210);
        $pdf->SetLineWidth(0.8);
        $pdf->Rect(10, 10, 190, 277, 'D');
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(12, 12, 186, 273, 'D');

        // Corporate System Header
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(15, 20);
        $pdf->Cell(180, 5, $this->encodeText("SISTEMA DE REGISTRO DE RESPALDOS DOCENTES (SIRRED) - UNITEPC"), 0, 0, 'C');

        // Main Title Box
        $pdf->SetXY(20, 60);
        $pdf->SetFont('Arial', 'B', 15);
        $pdf->SetTextColor(180, 60, 60);
        $pdf->Cell(170, 10, $this->encodeText("HOJA DE CONTROL - RESPALDO AUSENTE"), 0, 1, 'C');
        $pdf->SetDrawColor(180, 60, 60);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(40, 72, 170, 72);

        // Teacher Information Details
        $pdf->Ln(20);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(60, 60, 60);

        $details = [
            'Docente' => $facturacion->docente->nombre . ' ' . $facturacion->docente->apellidos,
            'Cedula de Identidad' => $facturacion->docente->ci . ($facturacion->docente->complemento ? ' - ' . $facturacion->docente->complemento : ''),
            'Corte Asociado' => $facturacion->corte->nombre,
            'Tipo de Contrato' => $facturacion->tipo_contrato,
            'Monto Asignado' => 'Bs. ' . number_format($facturacion->monto, 2),
            'Carga Horaria' => $facturacion->carga_horaria . ' Horas',
        ];

        foreach ($details as $label => $val) {
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(60, 10, $this->encodeText($label . ':'), 0, 0, 'R');
            $pdf->SetFont('Arial', '', 11);
            $pdf->Cell(110, 10, $this->encodeText(' ' . $val), 0, 1, 'L');
        }

        $pdf->Ln(20);

        // Warning Label Box
        $pdf->SetFillColor(254, 242, 242); // very light warm red
        $pdf->SetDrawColor(248, 113, 113); // red border
        $pdf->SetTextColor(153, 27, 27); // dark red
        $pdf->SetLineWidth(0.5);
        
        $pdf->SetX(30);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(150, 20, $this->encodeText($message), 1, 1, 'C', true);

        // Footer Metadata
        $pdf->SetXY(20, 260);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(170, 5, $this->encodeText("Este documento es una hoja de control autogenerada. No modifique los respaldos originales."), 0, 0, 'C');
    }

    /**
     * Encode UTF-8 string to ISO-8859-1 for FPDF without deprecation issues in PHP 8.2+
     */
    protected function encodeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }
}
