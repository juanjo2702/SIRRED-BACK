<?php

namespace App\Services;

use App\Models\Facturacion;
use App\Models\DatoFactura;
use App\Models\Corte;
use Carbon\Carbon;

class FacturaValidatorService
{
    // NIT de UNITEPC
    const NIT_UNITEPC = '1009199021';

    // Razones sociales válidas
    const RAZONES_SOCIALES_VALIDAS = [
        'UNITEPC',
        'UNIVERSIDAD TECNICA PRIVADA COSMOS',
        'UNIVERSIDAD TÉCNICA PRIVADA COSMOS'
    ];



    /**
     * Valida los datos extraídos de la factura
     *
     * IMPORTANTE: Se validan los datos del CLIENTE (a quien se factura = UNITEPC)
     * NO los datos del emisor (quien genera la factura = docente)
     *
     * @param Facturacion $facturacion
     * @param DatoFactura $datoFactura
     * @return array
     */
    public function validar(Facturacion $facturacion, DatoFactura $datoFactura): array
    {
        $errores = [];
        $detalles = [];

        // 1. Validar NIT del CLIENTE
        $resNit = $this->validarNitCliente($datoFactura->nit_cliente);
        $detalles[] = $resNit;
        if ($resNit['estado'] === 'error') {
            $errores[] = $resNit['mensaje'];
        }

        // 2. Validar Razón Social del CLIENTE
        $resRazon = $this->validarRazonSocialCliente($datoFactura->razon_social_cliente);
        $detalles[] = $resRazon;
        if ($resRazon['estado'] === 'error') {
            $errores[] = $resRazon['mensaje'];
        }

        // 3. Validar Fecha de Factura
        $resFecha = $this->validarFechaEnPeriodo($datoFactura->fecha_factura, $facturacion->corte);
        $detalles[] = $resFecha;
        if ($resFecha['estado'] === 'error') {
            $errores[] = $resFecha['mensaje'];
        }

        // 4. Validar Monto
        $resMonto = $this->validarMonto($datoFactura->monto_total, $facturacion->monto);
        $detalles[] = $resMonto;
        if ($resMonto['estado'] === 'error') {
            $errores[] = $resMonto['mensaje'];
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
            'detalles' => $detalles
        ];
    }

    /**
     * Valida que el NIT del CLIENTE sea el de UNITEPC
     */
    protected function validarNitCliente(?string $nit): array
    {
        $titulo = "NIT del Cliente (UNITEPC)";

        if (!$nit) {
            return [
                'titulo' => $titulo,
                'mensaje' => "No se pudo extraer el NIT. Verifique que la factura sea legible.",
                'estado' => 'error'
            ];
        }

        // Limpiar el NIT
        $nitLimpio = preg_replace('/[^0-9]/', '', $nit);

        if ($nitLimpio !== self::NIT_UNITEPC) {
            return [
                'titulo' => $titulo,
                'mensaje' => "El NIT ({$nit}) no coincide con UNITEPC (" . self::NIT_UNITEPC . ").",
                'estado' => 'error'
            ];
        }

        return [
            'titulo' => $titulo,
            'mensaje' => "NIT correcto: {$nit}",
            'estado' => 'ok'
        ];
    }

    /**
     * Valida que la razón social del CLIENTE sea UNITEPC o variantes
     */
    protected function validarRazonSocialCliente(?string $razonSocial): array
    {
        $titulo = "Razón Social del Cliente";

        if (!$razonSocial) {
            return [
                'titulo' => $titulo,
                'mensaje' => "No se pudo extraer la Razón Social.",
                'estado' => 'error'
            ];
        }

        $razonSocialNormalizada = mb_strtoupper(trim($razonSocial));

        foreach (self::RAZONES_SOCIALES_VALIDAS as $razonValida) {
            if (strpos($razonSocialNormalizada, $razonValida) !== false) {
                return [
                    'titulo' => $titulo,
                    'mensaje' => "Razón Social correcta: {$razonValida}",
                    'estado' => 'ok'
                ];
            }
        }

        return [
            'titulo' => $titulo,
            'mensaje' => "La Razón Social ({$razonSocial}) no es válida. Debe ser UNITEPC.",
            'estado' => 'error'
        ];
    }

    /**
     * Valida que la fecha de factura esté dentro del periodo de facturación
     */
    protected function validarFechaEnPeriodo($fechaFactura, ?Corte $corte): array
    {
        $titulo = "Fecha de Emisión";

        if (!$fechaFactura) {
            return [
                'titulo' => $titulo,
                'mensaje' => "No se pudo extraer la fecha de la factura.",
                'estado' => 'error'
            ];
        }

        if (!$corte || !$corte->fecha_inicio_facturacion || !$corte->fecha_fin_facturacion) {
            return [
                'titulo' => $titulo,
                'mensaje' => "No se pudo verificar el periodo de facturación.",
                'estado' => 'error' // O warning si prefieres no bloquear
            ];
        }

        $fecha = Carbon::parse($fechaFactura)->startOfDay();
        $fechaInicio = Carbon::parse($corte->fecha_inicio_facturacion)->startOfDay();
        $fechaFin = Carbon::parse($corte->fecha_fin_facturacion)->endOfDay();

        if ($fecha->lt($fechaInicio) || $fecha->gt($fechaFin)) {
            return [
                'titulo' => $titulo,
                'mensaje' => "La fecha ({$fecha->format('d/m/Y')}) está fuera del periodo ({$fechaInicio->format('d/m/Y')} - {$fechaFin->format('d/m/Y')}).",
                'estado' => 'error'
            ];
        }

        return [
            'titulo' => $titulo,
            'mensaje' => "Fecha válida: {$fecha->format('d/m/Y')}",
            'estado' => 'ok'
        ];
    }

    /**
     * Valida que el monto de la factura coincida con el monto esperado
     */
    protected function validarMonto($montoFactura, $montoEsperado): array
    {
        $titulo = "Monto Total";

        if (!$montoFactura) {
            return [
                'titulo' => $titulo,
                'mensaje' => "No se pudo extraer el monto.",
                'estado' => 'error'
            ];
        }

        if (!$montoEsperado) {
            return [
                'titulo' => $titulo,
                'mensaje' => "No hay monto esperado definido.",
                'estado' => 'ok' // Asumimos ok si no hay contra qué validar
            ];
        }

        $montoFacturaFloat = floatval($montoFactura);
        $montoEsperadoFloat = floatval($montoEsperado);

        // Comparar con tolerancia de 0.01 (por redondeos)
        if (abs($montoFacturaFloat - $montoEsperadoFloat) > 0.01) {
            return [
                'titulo' => $titulo,
                'mensaje' => "El monto (Bs " . number_format($montoFacturaFloat, 2) . ") no coincide con el esperado (Bs " . number_format($montoEsperadoFloat, 2) . ").",
                'estado' => 'error'
            ];
        }

        return [
            'titulo' => $titulo,
            'mensaje' => "Monto correcto: Bs " . number_format($montoFacturaFloat, 2),
            'estado' => 'ok'
        ];
    }

    /**
     * Determina el estado final basado en la validación
     */
    public function determinarEstado(bool $valido): string
    {
        return $valido ? 'APROBADO' : 'RECHAZADO';
    }
}
