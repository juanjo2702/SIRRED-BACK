<?php

namespace App\Services;

use App\Models\Facturacion;
use App\Models\Docente;
use App\Models\SedeCarrera;
use Illuminate\Support\Facades\DB;

class FacturacionUpdateService
{
    protected FacturacionAuditService $auditService;
    protected FacturacionFileRenameService $renameService;

    public function __construct(
        FacturacionAuditService $auditService,
        FacturacionFileRenameService $renameService
    ) {
        $this->auditService = $auditService;
        $this->renameService = $renameService;
    }

    /**
     * Update a billing assignment and its associated docente demographics.
     *
     * @param Facturacion $facturacion
     * @param array $data Update fields
     * @param bool $isAdmin
     * @param string|null $comment
     * @param bool $force
     * @return Facturacion
     * @throws \Exception
     */
    public function update(
        Facturacion $facturacion,
        array $data,
        bool $isAdmin = false,
        ?string $comment = null,
        bool $force = false
    ): Facturacion {
        $facturacion->load(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte']);

        // Check Approved Invoice Lock
        if ($facturacion->estado_subida === 'APROBADO' && !($isAdmin && $force)) {
            throw new \InvalidArgumentException('El registro de facturación está APROBADO y bloqueado. Use confirmación especial para forzar.');
        }

        return DB::transaction(function () use ($facturacion, $data, $isAdmin, $comment) {
            $docente = $facturacion->docente;

            // Capture old values for audit
            $oldValues = [
                'docente' => $docente->only(['nombre', 'apellidos', 'ci', 'complemento', 'correo', 'telefono', 'estado']),
                'facturacion' => $facturacion->only([
                    'sede_carrera_id', 'corte_id', 'tipo_contrato', 'monto', 'carga_horaria',
                    'estado_subida', 'es_practica', 'fecha_inicio_practica', 'fecha_fin_practica',
                    'materia_practica', 'hospital_practica', 'observaciones'
                ])
            ];

            // 1. Handle Docente updates and check for CI conflicts
            if (isset($data['ci'])) {
                $newCi = $data['ci'];

                if ($newCi !== $docente->ci) {
                    // Check if a docente with the new CI already exists
                    $existingDocente = Docente::where('ci', $newCi)->first();

                    if ($existingDocente) {
                        // Associate billing with this existing docente
                        $facturacion->docente_id = $existingDocente->id;
                        $facturacion->save();
                        $docente = $existingDocente;
                    } else {
                        // Update current docente's CI
                        $docente->ci = $newCi;
                    }
                }
            }

            // Update remaining demographic fields
            if (isset($data['nombres'])) {
                $docente->nombre = $data['nombres'];
            }
            if (isset($data['apellidos'])) {
                $docente->apellidos = $data['apellidos'];
            }
            if (array_key_exists('complemento', $data)) {
                $docente->complemento = $data['complemento'];
            }
            if (array_key_exists('correo', $data)) {
                $docente->correo = $data['correo'];
            }
            if (array_key_exists('telefono', $data)) {
                $docente->telefono = $data['telefono'];
            }
            if (isset($data['docente_estado'])) {
                $docente->estado = $data['docente_estado'];
            }
            $docente->save();

            // 2. Handle Sede and Carrera lookup
            if (isset($data['sede_id']) && isset($data['carrera_id'])) {
                $sedeCarrera = SedeCarrera::where('sede_id', $data['sede_id'])
                    ->where('carrera_id', $data['carrera_id'])
                    ->first();

                if (!$sedeCarrera) {
                    throw new \InvalidArgumentException('La carrera seleccionada no está asignada a la sede seleccionada.');
                }
                $facturacion->sede_carrera_id = $sedeCarrera->id;
            }

            // 3. Update billing fields
            if (isset($data['corte_id'])) {
                $facturacion->corte_id = $data['corte_id'];
            }
            if (isset($data['tipo_contrato'])) {
                $facturacion->tipo_contrato = $data['tipo_contrato'];
            }
            if (isset($data['monto'])) {
                $facturacion->monto = $data['monto'];
            }
            if (isset($data['carga_horaria'])) {
                $facturacion->carga_horaria = $data['carga_horaria'];
            }
            if (isset($data['estado_subida'])) {
                $facturacion->estado_subida = $data['estado_subida'];
            }
            if (isset($data['es_practica'])) {
                $facturacion->es_practica = filter_var($data['es_practica'], FILTER_VALIDATE_BOOLEAN);
            }
            if (array_key_exists('fecha_inicio_practica', $data)) {
                $facturacion->fecha_inicio_practica = $data['fecha_inicio_practica'];
            }
            if (array_key_exists('fecha_fin_practica', $data)) {
                $facturacion->fecha_fin_practica = $data['fecha_fin_practica'];
            }
            if (array_key_exists('materia_practica', $data)) {
                $facturacion->materia_practica = $data['materia_practica'];
            }
            if (array_key_exists('hospital_practica', $data)) {
                $facturacion->hospital_practica = $data['hospital_practica'];
            }
            if (array_key_exists('observaciones', $data)) {
                $facturacion->observaciones = $data['observaciones'];
            }

            $facturacion->save();

            // 4. Eagerly reload billing data and trigger filesystem renaming if path exists
            if ($facturacion->factura_path) {
                $this->renameService->renameFile($facturacion);
            }

            // Reload model for audit logging
            $facturacion->load(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte']);

            $newValues = [
                'docente' => $facturacion->docente->only(['nombre', 'apellidos', 'ci', 'complemento', 'correo', 'telefono', 'estado']),
                'facturacion' => $facturacion->only([
                    'sede_carrera_id', 'corte_id', 'tipo_contrato', 'monto', 'carga_horaria',
                    'estado_subida', 'es_practica', 'fecha_inicio_practica', 'fecha_fin_practica',
                    'materia_practica', 'hospital_practica', 'observaciones'
                ])
            ];

            // Log the actions
            $this->auditService->logAction(
                'UPDATE_FACTURACION_DATA',
                $facturacion->id,
                $oldValues,
                $newValues,
                $comment
            );

            return $facturacion;
        });
    }
}
