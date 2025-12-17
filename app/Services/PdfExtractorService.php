<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Zxing\QrReader;
use Exception;

class PdfExtractorService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extrae datos de una factura boliviana en PDF
     *
     * @param string $filePath Ruta completa al archivo PDF
     * @return array Datos extraídos de la factura
     */
    public function extractFromInvoice(string $filePath): array
    {
        try {
            $pdf = $this->parser->parseFile($filePath);
            $text = $pdf->getText();

            // Intentar extraer QR URL
            $qrUrl = $this->extractQrUrl($pdf);

            return [
                'success' => true,
                'data' => [
                    'nit_emisor' => $this->extractNitEmisor($text),
                    'razon_social_emisor' => $this->extractRazonSocial($text),
                    'nit_cliente' => $this->extractNitCliente($text),
                    'razon_social_cliente' => $this->extractRazonSocialCliente($text),
                    'numero_factura' => $this->extractNumeroFactura($text),
                    'codigo_autorizacion' => $this->extractCodigoAutorizacion($text),
                    'fecha_factura' => $this->extractFecha($text),
                    'monto_total' => $this->extractMontoTotal($text),
                    'texto_completo' => $text,
                    'qr_url' => $qrUrl,
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Extrae la URL del código QR del PDF
     * Intenta encontrar imágenes embebidas y decodificar el QR
     */
    protected function extractQrUrl($pdf): ?string
    {
        try {
            // Obtener todas las páginas del PDF
            $pages = $pdf->getPages();

            foreach ($pages as $page) {
                // Obtener objetos XObject (imágenes) de la página
                $xObjects = $page->getXObjects();

                foreach ($xObjects as $xObject) {
                    // Verificar si es una imagen
                    if (method_exists($xObject, 'getContent')) {
                        $imageContent = $xObject->getContent();

                        if (!empty($imageContent)) {
                            // Crear archivo temporal para la imagen
                            $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';

                            // Intentar detectar el tipo de imagen y convertir
                            $image = @imagecreatefromstring($imageContent);

                            if ($image !== false) {
                                // Guardar como PNG
                                imagepng($image, $tempFile);
                                imagedestroy($image);

                                // Intentar leer el QR
                                try {
                                    $qrReader = new QrReader($tempFile);
                                    $qrText = $qrReader->text();

                                    // Limpiar archivo temporal
                                    @unlink($tempFile);

                                    // Si encontramos texto que parece una URL, retornar
                                    if ($qrText && (
                                        strpos($qrText, 'http') === 0 ||
                                        strpos($qrText, 'www.') === 0 ||
                                        strpos($qrText, 'impuestos') !== false
                                    )) {
                                        return $qrText;
                                    }
                                } catch (Exception $e) {
                                    // Continuar con la siguiente imagen
                                    @unlink($tempFile);
                                }
                            } else {
                                @unlink($tempFile);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Si falla la extracción del QR, no es crítico
            // Solo registramos y continuamos
        }

        return null;
    }

    /**
     * Extrae el NIT del emisor
     * Formato: NIT\t[números]
     */
    protected function extractNitEmisor(string $text): ?string
    {
        // Buscar patrón NIT seguido de tabulador o espacios y números
        if (preg_match('/NIT[\t\s]+(\d+)/i', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extrae la razón social del emisor (primera línea del texto)
     */
    protected function extractRazonSocial(string $text): ?string
    {
        $lines = explode("\n", trim($text));
        if (!empty($lines[0])) {
            // La primera línea suele ser el nombre/razón social del emisor
            return trim($lines[0]);
        }
        return null;
    }

    /**
     * Extrae el número de factura
     * Formato: FACTURA N°\t[número] o variantes
     */
    protected function extractNumeroFactura(string $text): ?string
    {
        // Patrón flexible: FACTURA seguido de N y cualquier caracter especial, luego espacios y número
        if (preg_match('/FACTURA\s+N[^\d]*(\d+)/iu', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extrae el código de autorización del SIN
     * El código viene en múltiples líneas después de "CÓD. AUTORIZACIÓN"
     */
    protected function extractCodigoAutorizacion(string $text): ?string
    {
        // Buscar líneas que contienen el código de autorización
        $lines = explode("\n", $text);
        $codigo = '';
        $foundAuth = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Detectar inicio del código de autorización
            if (preg_match('/C[ÓO]D\.?\s*AUTORIZACI[ÓO]N\s*([A-F0-9]*)/iu', $trimmedLine, $matches)) {
                $foundAuth = true;
                if (!empty($matches[1])) {
                    $codigo = $matches[1];
                }
                continue;
            }

            // Si ya encontramos el inicio, capturar líneas alfanuméricas
            if ($foundAuth) {
                // Si la línea es solo alfanumérica (parte del código)
                if (preg_match('/^[A-F0-9]+$/i', $trimmedLine)) {
                    $codigo .= $trimmedLine;
                }
                // Si encontramos "FACTURA" ya terminamos
                elseif (stripos($trimmedLine, 'FACTURA') !== false) {
                    break;
                }
            }
        }

        return !empty($codigo) ? $codigo : null;
    }

    /**
     * Extrae la fecha de la factura
     * Formato: Fecha:\t[fecha] [hora]
     */
    protected function extractFecha(string $text): ?string
    {
        // Buscar formato de fecha DD/MM/YYYY
        if (preg_match('/Fecha:[\t\s]+(\d{2}\/\d{2}\/\d{4})/i', $text, $matches)) {
            // Convertir de DD/MM/YYYY a YYYY-MM-DD
            $parts = explode('/', $matches[1]);
            if (count($parts) === 3) {
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }
        return null;
    }

    /**
     * Extrae el monto total
     * Formato: TOTAL Bs [monto]
     */
    protected function extractMontoTotal(string $text): ?float
    {
        // Buscar "TOTAL Bs" seguido del monto
        // El monto puede tener formato 1,234.56 o 1234.56
        if (preg_match('/TOTAL\s+Bs[\t\s]+([\d,]+\.?\d*)/i', $text, $matches)) {
            // Remover comas de miles
            $monto = str_replace(',', '', $matches[1]);
            return (float) $monto;
        }
        return null;
    }

    /**
     * Extrae el NIT del cliente (a quien se emite la factura)
     * Formato: NIT/CI/CEX:\t[números] o similar
     * Este aparece en la sección del cliente, no del emisor
     */
    protected function extractNitCliente(string $text): ?string
    {
        // Buscar NIT/CI/CEX seguido de números (formato del cliente)
        if (preg_match('/NIT\/CI\/CEX[:\s\t]+(\d+)/i', $text, $matches)) {
            return $matches[1];
        }
        // También puede aparecer como "Cod. Cliente"
        if (preg_match('/Cod\.?\s*Cliente[:\s\t]+(\d+)/i', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extrae la razón social del cliente (a quien se emite la factura)
     * Formato: Nombre/Razón Social:\t[texto] o similar
     */
    protected function extractRazonSocialCliente(string $text): ?string
    {
        // Buscar "Nombre/Razón Social:" seguido del nombre
        if (preg_match('/Nombre\/Raz[oó]n\s*Social[:\s\t]+([A-ZÁÉÍÓÚÑ\s]+)/iu', $text, $matches)) {
            return trim($matches[1]);
        }
        // Alternativa: buscar después de "FACTURA" donde aparece el nombre del cliente
        // El formato es: Nombre/Razón Social:  UNITEPC
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            if (preg_match('/Nombre.*Raz[oó]n.*Social[:\s\t]+(.+)/iu', $line, $matches)) {
                return trim($matches[1]);
            }
        }
        return null;
    }
}
