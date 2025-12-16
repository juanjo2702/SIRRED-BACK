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

    // Número máximo de intentos antes de pasar a revisión manual
    const MAX_INTENTOS = 3;

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

        // 1. Validar NIT del CLIENTE (debe ser UNITEPC)
        $errorNit = $this->validarNitCliente($datoFactura->nit_cliente);
        if ($errorNit) {
            $errores[] = $errorNit;
        }

        // 2. Validar Razón Social del CLIENTE (debe ser UNITEPC)
        $errorRazonSocial = $this->validarRazonSocialCliente($datoFactura->razon_social_cliente);
        if ($errorRazonSocial) {
            $errores[] = $errorRazonSocial;
        }

        // 3. Validar Fecha de Factura dentro del periodo
        $errorFecha = $this->validarFechaEnPeriodo($datoFactura->fecha_factura, $facturacion->corte);
        if ($errorFecha) {
            $errores[] = $errorFecha;
        }

        // 4. Validar Monto coincide
        $errorMonto = $this->validarMonto($datoFactura->monto_total, $facturacion->monto);
        if ($errorMonto) {
            $errores[] = $errorMonto;
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores,
        ];
    }

    /**
     * Valida que el NIT del CLIENTE sea el de UNITEPC
     */
    protected function validarNitCliente(?string $nit): ?string
    {
        if (!$nit) {
            return "No se pudo extraer el NIT del cliente de la factura. Verifique que la factura esté emitida a nombre de UNITEPC.";
        }

        // Limpiar el NIT (quitar espacios, guiones, etc.)
        $nitLimpio = preg_replace('/[^0-9]/', '', $nit);

        if ($nitLimpio !== self::NIT_UNITEPC) {
            return "El NIT del cliente en la factura ({$nit}) no es correcto. Debe facturar al NIT " . self::NIT_UNITEPC . " (UNITEPC).";
        }

        return null;
    }

    /**
     * Valida que la razón social del CLIENTE sea UNITEPC o variantes
     */
    protected function validarRazonSocialCliente(?string $razonSocial): ?string
    {
        if (!$razonSocial) {
            return "No se pudo extraer el Nombre/Razón Social del cliente de la factura.";
        }

        // Normalizar para comparación (mayúsculas, sin acentos extra)
        $razonSocialNormalizada = mb_strtoupper(trim($razonSocial));

        foreach (self::RAZONES_SOCIALES_VALIDAS as $razonValida) {
            if (strpos($razonSocialNormalizada, $razonValida) !== false) {
                return null; // Es válida
            }
        }

        return "El Nombre/Razón Social del cliente ({$razonSocial}) debe ser 'UNITEPC' o 'Universidad Técnica Privada Cosmos'.";
    }

    /**
     * Valida que la fecha de factura esté dentro del periodo de facturación
     */
    protected function validarFechaEnPeriodo($fechaFactura, ?Corte $corte): ?string
    {
        if (!$fechaFactura) {
            return "No se pudo extraer la fecha de la factura.";
        }

        if (!$corte) {
            return null; // Sin corte, no podemos validar
        }

        if (!$corte->fecha_inicio_facturacion || !$corte->fecha_fin_facturacion) {
            return null; // Sin periodo definido, no podemos validar
        }

        $fecha = Carbon::parse($fechaFactura)->startOfDay();
        $fechaInicio = Carbon::parse($corte->fecha_inicio_facturacion)->startOfDay();
        $fechaFin = Carbon::parse($corte->fecha_fin_facturacion)->endOfDay();

        if ($fecha->lt($fechaInicio) || $fecha->gt($fechaFin)) {
            return "La fecha de la factura (" . $fecha->format('d/m/Y') . ") debe estar entre " .
                   $fechaInicio->format('d/m/Y') . " y " . $fechaFin->format('d/m/Y') . ".";
        }

        return null;
    }

    /**
     * Valida que el monto de la factura coincida con el monto esperado
     */
    protected function validarMonto($montoFactura, $montoEsperado): ?string
    {
        if (!$montoFactura) {
            return "No se pudo extraer el monto de la factura.";
        }

        if (!$montoEsperado) {
            return null; // Sin monto esperado, no podemos validar
        }

        // Convertir a float para comparación
        $montoFacturaFloat = floatval($montoFactura);
        $montoEsperadoFloat = floatval($montoEsperado);

        // Comparar con tolerancia de 0.01 (por redondeos)
        if (abs($montoFacturaFloat - $montoEsperadoFloat) > 0.01) {
            return "El monto de la factura (Bs " . number_format($montoFacturaFloat, 2) .
                   ") no coincide con el monto esperado (Bs " . number_format($montoEsperadoFloat, 2) . ").";
        }

        return null;
    }

    /**
     * Determina el estado final basado en la validación e intentos
     */
    public function determinarEstado(bool $valido, int $intentos): string
    {
        if ($valido) {
            return 'APROBADO';
        }

        if ($intentos >= self::MAX_INTENTOS) {
            return 'SUBIDA'; // Pendiente de revisión manual
        }

        return 'RECHAZADO';
    }

    /**
     * Retorna el número máximo de intentos
     */
    public function getMaxIntentos(): int
    {
        return self::MAX_INTENTOS;
    }
}
