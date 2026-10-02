<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Consolidado de Facturas Docentes</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            line-height: 1.4;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        
        .page-cover {
            position: relative;
            height: 100%;
            padding: 40px;
            page-break-after: always;
        }

        .cover-header {
            text-align: center;
            margin-top: 40px;
        }

        .cover-logo-text {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #1e3a8a; /* deep blue */
            text-transform: uppercase;
        }

        .cover-system-title {
            font-size: 10px;
            color: #666666;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        .cover-main {
            text-align: center;
            margin-top: 100px;
            margin-bottom: 100px;
        }

        .cover-title {
            font-size: 26px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }

        .cover-divider {
            width: 80px;
            height: 4px;
            background-color: #1e3a8a;
            margin: 0 auto 30px auto;
        }

        .cover-subtitle {
            font-size: 14px;
            color: #4b5563;
            margin-bottom: 5px;
        }

        .cover-details-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 25px;
            margin: 0 auto;
            width: 80%;
            text-align: left;
        }

        .cover-details-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cover-details-table td {
            padding: 6px 4px;
            vertical-align: top;
        }

        .cover-details-table td.label {
            font-weight: bold;
            color: #4b5563;
            width: 35%;
        }

        .cover-details-table td.value {
            color: #111827;
        }

        .cover-footer {
            position: absolute;
            bottom: 40px;
            left: 40px;
            right: 40px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }

        /* Checklist / Control List styles */
        .page-checklist {
            padding: 25px;
        }

        .checklist-title-box {
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .checklist-title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
        }

        .checklist-subtitle {
            font-size: 10px;
            color: #6b7280;
            margin-top: 3px;
        }

        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .checklist-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 8px 6px;
            font-size: 10px;
            border: 1px solid #1e3a8a;
        }

        .checklist-table td {
            padding: 6px;
            border: 1px solid #e5e7eb;
            vertical-align: middle;
            font-size: 9px;
        }

        .checklist-table tr:nth-child(even) td {
            background-color: #f9fafb;
        }

        .checkbox-container {
            text-align: center;
        }

        .checkbox-box {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #4b5563;
            border-radius: 2px;
            background-color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            line-height: 13px;
            text-align: center;
            vertical-align: middle;
        }

        .checkbox-bien {
            border-color: #10b981;
            color: #10b981;
            background-color: #ecfdf5;
        }

        .checkbox-proceso {
            border-color: #3b82f6;
            color: #3b82f6;
            background-color: #eff6ff;
        }

        .checkbox-mal {
            border-color: #ef4444;
            color: #ef4444;
            background-color: #fef2f2;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 8px;
            text-align: center;
            text-transform: uppercase;
        }

        .status-subida {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-aprobado {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-denegado {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-pendiente {
            background-color: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>

    <!-- PORTADA -->
    <div class="page-cover">
        <div class="cover-header">
            <div class="cover-logo-text">UNITEPC</div>
            <div class="cover-system-title">Sistema de Registro de Respaldos Docentes (SIRRED)</div>
        </div>

        <div class="cover-main">
            <div class="cover-title">Reporte Consolidado<br>de Facturas Docentes</div>
            <div class="cover-divider"></div>
            <div class="cover-subtitle">Documento compilado oficial para auditoría e impresión manual.</div>
        </div>

        <div class="cover-details-box">
            <table class="cover-details-table">
                <tr>
                    <td class="label">Corte Administrativo:</td>
                    <td class="value">{{ $corte->nombre }}</td>
                </tr>
                <tr>
                    <td class="label">Sede Académica:</td>
                    <td class="value">{{ $sede->nombre }}</td>
                </tr>
                <tr>
                    <td class="label">Carrera / Programa:</td>
                    <td class="value">{{ $carrera->nombre }}</td>
                </tr>
                <tr>
                    <td class="label">Fecha de Generación:</td>
                    <td class="value">{{ $date }}</td>
                </tr>
                <tr>
                    <td class="label">Generado por:</td>
                    <td class="value">{{ $user ? ($user->name . ' ' . $user->apellidos) : 'Administrador del Sistema' }}</td>
                </tr>
                <tr>
                    <td class="label">Total Asignaciones:</td>
                    <td class="value">{{ $totalCount }} registros</td>
                </tr>
                <tr>
                    <td class="label">Facturas Importadas:</td>
                    <td class="value">{{ $withInvoiceCount }} unificadas</td>
                </tr>
                <tr>
                    <td class="label">Asignaciones sin Factura:</td>
                    <td class="value">{{ $withoutInvoiceCount }} pendientes / no requeridas</td>
                </tr>
                <tr>
                    <td class="label">Monto Consolidado:</td>
                    <td class="value">Bs. {{ number_format($totalAmount, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="cover-footer">
            UNITEPC &copy; {{ date('Y') }} - Departamento de Administración y Finanzas. Todos los derechos reservados.
        </div>
    </div>

    <!-- LISTA DE CONTROL -->
    <div class="page-checklist">
        <div class="checklist-title-box">
            <div class="checklist-title">Lista de Control y Cotejo</div>
            <div class="checklist-subtitle">Corte: {{ $corte->nombre }} | Sede: {{ $sede->nombre }} | Carrera: {{ $carrera->nombre }}</div>
        </div>

        <table class="checklist-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">Nro</th>
                    <th style="width: 10%;">C.I.</th>
                    <th style="width: 17%;">Apellido Paterno</th>
                    <th style="width: 17%;">Apellido Materno</th>
                    <th style="width: 18%;">Nombres</th>
                    <th style="width: 13%;">Contrato</th>
                    <th style="width: 12%;">Monto</th>
                    <th style="width: 8%; text-align: center;">Cotejo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($facturaciones as $facturacion)
                    @php
                        $apellidosTrimmed = trim($facturacion->docente->apellidos);
                        $parts = preg_split('/\s+/', $apellidosTrimmed);
                        $paterno = $parts[0] ?? '';
                        $materno = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
                    @endphp
                    <tr>
                        <td style="text-align: center;">{{ $loop->iteration }}</td>
                        <td>{{ $facturacion->docente->ci }}{{ $facturacion->docente->complemento ? ' - ' . $facturacion->docente->complemento : '' }}</td>
                        <td style="font-weight: bold;">{{ $paterno }}</td>
                        <td style="font-weight: bold;">{{ $materno }}</td>
                        <td style="font-weight: bold;">{{ $facturacion->docente->nombre }}</td>
                        <td>{{ $facturacion->tipo_contrato }}</td>
                        <td style="text-align: right;">Bs. {{ number_format($facturacion->monto, 2) }}</td>
                        <td class="checkbox-container">
                            @if($facturacion->estado_subida === 'APROBADO')
                                <div class="checkbox-box checkbox-bien">V</div>
                            @elseif($facturacion->estado_subida === 'SUBIDA')
                                <div class="checkbox-box checkbox-proceso">?</div>
                            @else
                                <div class="checkbox-box checkbox-mal">X</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>
