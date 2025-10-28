<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    *{ font-family: DejaVu Sans, sans-serif; }
    body{ font-size:12px; color:#111; }
    .row{ width:100%; }
    .mt-8{ margin-top:8px; } .mt-12{ margin-top:12px; }
    .b{ font-weight:bold; }
    .box{ border:1px solid #999; border-radius:4px; padding:10px; }
    table{ border-collapse:collapse; width:100%; }
    .w-20{ width:20%; } .w-60{ width:60%; } .w-20r{ width:20%; text-align:right; }
    .header td{ vertical-align:top; }
    .rbox{ border:2px solid #000; border-radius:4px; padding:10px; text-align:center; }
    .h-title{ font-size:14px; font-weight:700; letter-spacing:.2px; }
    .muted{ color:#666; font-size:11px; }
    .blk{ background:#111; color:#fff; }
    .th{ padding:7px; font-weight:700; }
    .td{ padding:7px; border-bottom:1px solid #eee; }
    .totals td{ padding:5px 7px; }
    .water{ position:absolute; top:45%; left:8%; font-size:42px; color:rgba(255,0,0,.22); transform:rotate(-15deg); }
    .qr-row{ display:table; width:100%; }
    .qr-left{ display:table-cell; width:120px; vertical-align:top; }
    .qr-right{ display:table-cell; padding-left:10px; vertical-align:top; }
    .hash b{ font-weight:800; }
</style>
</head>
<body>

{{-- WATERMARK --}}
<div class="water">PRUEBA SIN VALOR</div>

{{-- HEADER: 3 columnas en una sola fila --}}
<table class="header">
    <tr>
        <td class="w-20">
            @if($logoB64)
                <img src="{{ $logoB64 }}" style="width:110px;height:110px;border-radius:6px;">
            @endif
        </td>
        <td class="w-60">
            <div class="h-title">{{ strtoupper($emisor['comercial']) }}</div>
            <div class="b" style="margin-top:2px">{{ strtoupper($emisor['razon']) }}</div>
            <div class="muted" style="margin-top:6px">{{ $emisor['direccion'] }}</div>
        </td>
        <td class="w-20r">
            <div class="rbox">
                <div class="b">R.U.C. N° {{ $emisor['ruc_visual'] }}</div>
                <div style="margin-top:4px">{{ $tipo === 'FA' ? 'FACTURA ELECTRÓNICA' : 'BOLETA ELECTRÓNICA' }}</div>
                <div class="b" style="margin-top:4px">{{ $serie }}-{{ $numero }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- DATOS CLIENTE y EMISIÓN (dos columnas), NO tabla de cuadrícula gruesa --}}
<table class="box mt-12">
    <tr>
        <td style="width:55%;vertical-align:top">
            <div><span class="b">NOMBRE:</span> {{ $cliente['nombre'] }}</div>
            <div><span class="b">{{ $cliente['doc_label'] }}:</span> {{ $cliente['doc'] }}</div>
            <div><span class="b">DIRECCIÓN:</span> {{ $cliente['direccion'] }}</div>
        </td>
        <td style="width:45%;vertical-align:top">
            <div><span class="b">EMISIÓN:</span> {{ $emitido }}</div>
            <div><span class="b">MONEDA:</span> {{ $moneda }}</div>
            <div><span class="b">FORMA DE PAGO:</span> CONTADO</div>
            <div><span class="b">TIPO DE OPERACIÓN:</span> VENTA INTERNA</div>
        </td>
    </tr>
</table>

{{-- ITEMS --}}
<table class="mt-12">
    <thead class="blk">
        <tr>
            <th class="th" style="width:12%">CANTIDAD</th>
            <th class="th" style="width:54%">CÓDIGO Y DESCRIPCIÓN</th>
            <th class="th" style="width:17%">PRECIO UNITARIO</th>
            <th class="th" style="width:17%">PRECIO TOTAL</th>
        </tr>
    </thead>
    <tbody>
    @foreach($items as $it)
        @php
            $desc = $it->producto?->nombre ?? ('#'.$it->id_producto);
            $cant = (float)$it->cantidad;
            $puni = number_format($it->precio_unitario, 2, '.', '');
            $st   = number_format($it->cantidad * $it->precio_unitario, 2, '.', '');
        @endphp
        <tr>
            <td class="td" style="text-align:center">{{ (int)$cant }}</td>
            <td class="td">{{ $desc }}</td>
            <td class="td" style="text-align:right">{{ $puni }}</td>
            <td class="td" style="text-align:right">{{ $st }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- TOTALES --}}
<table class="totals mt-8">
    <tr>
        <td style="width:60%"></td>
        <td style="width:22%; text-align:right; color:#666;">OP. GRAVADA</td>
        <td style="width:18%; text-align:right;">{{ $mto_gravada }}</td>
    </tr>
    <tr>
        <td></td>
        <td style="text-align:right; color:#666;">IGV</td>
        <td style="text-align:right;">{{ $mto_igv }}</td>
    </tr>
    <tr>
        <td></td>
        <td class="b" style="text-align:right;">IMPORTE TOTAL (S/)</td>
        <td class="b" style="text-align:right;">{{ $mto_total }}</td>
    </tr>
</table>

{{-- SON: (bloque único sin bordes de tabla) --}}
<div class="box mt-12" style="background:#f7f7f7">
    {{ $legend }}
</div>

{{-- QR + HASH en la misma fila --}}
<div class="qr-row mt-12">
    <div class="qr-left">
        @if($qrB64)
            <img src="{{ $qrB64 }}" style="width:115px;height:115px;">
        @endif
    </div>
    <div class="qr-right hash">
        <div><b>HASH (DigestValue):</b> {{ $hash }}</div>
        <div class="muted" style="margin-top:6px">
            Representación impresa de la {{ $tipo==='FA' ? 'FACTURA' : 'BOLETA' }} ELECTRÓNICA.
            En caso de discrepancia, prevalece el comprobante electrónico (XML) enviado a SUNAT.
        </div>
        <div class="muted">Documento generado en entorno de pruebas (SUNAT BETA).</div>
    </div>
</div>

</body>
</html>
