<?php

namespace App\Services;

use App\Models\Pedido;
use DateTime;
use DateTimeZone;
use Dompdf\Dompdf;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

// QR (chillerlan)
use chillerlan\QRCode\QRCode as ChilliQRCode;
use chillerlan\QRCode\QROptions;

class ComprobanteService
{
    private function buildSee(): See
    {
        $see = new See();

        $pemPath = base_path(env('SUNAT_CERT_PEM', 'storage/certs/sunat.pem'));
        $see->setCertificate(file_get_contents($pemPath));

        $see->setService(env('SUNAT_BETA', true)
            ? SunatEndpoints::FE_BETA
            : SunatEndpoints::FE_PRODUCCION);

        $see->setClaveSOL(
            (string)env('SUNAT_RUC'),
            (string)env('SUNAT_SOL_USER'),
            (string)env('SUNAT_SOL_PASS'),
        );

        return $see;
    }

    private function buildCompany(): Company
    {
        $addr = (new Address())
            ->setUbigueo((string)env('EMP_UBIGEO', '150101'))
            ->setDepartamento((string)env('EMP_DEPA', 'LIMA'))
            ->setProvincia((string)env('EMP_PROV', 'LIMA'))
            ->setDistrito((string)env('EMP_DIST', 'LIMA'))
            ->setUrbanizacion('-')
            ->setDireccion((string)env('EMP_DIRECCION', 'AV. SIN NOMBRE 123'))
            ->setCodLocal('0000');

        return (new Company())
            ->setRuc((string)env('SUNAT_RUC'))
            ->setRazonSocial((string)env('EMP_RAZON', 'MI EMPRESA SAC'))
            ->setNombreComercial((string)env('EMP_COMERCIAL', 'MI EMPRESA'))
            ->setAddress($addr);
    }

    public function emitir(Pedido $pedido, array $payload): array
    {
        $igv     = (float)env('SUNAT_IGV', 0.18);
        $serie   = $pedido->comprobante_serie;     // F001/B001
        $numero  = (int)$pedido->comprobante_numero;
        $tipo    = $pedido->comprobante_tipo;      // 'FA' | 'BO'
        $tipoDocCpe = $tipo === 'FA' ? '01' : '03';

        // -------- Cliente (de los datos que vienen del front) --------
        if ($tipo === 'FA') {
            $cliTipoDoc  = '6';
            $cliNumDoc   = $payload['factura']['ruc'] ?? '00000000000';
            $cliNombre   = $payload['factura']['razonSocial'] ?? 'CLIENTE FACTURA';
            $cliDir      = $payload['factura']['direccion'] ?? '-';
        } else {
            $cliTipoDoc  = '1';
            $cliNumDoc   = $payload['boleta']['dni'] ?? '00000000';
            $cliNombre   = $payload['boleta']['nombres'] ?? 'CLIENTE BOLETA';
            $cliDir      = $payload['boleta']['direccion'] ?? '-';
        }

        $client = (new Client())
            ->setTipoDoc($cliTipoDoc)
            ->setNumDoc($cliNumDoc)
            ->setRznSocial($cliNombre);

        // -------- Empresa (emisor) --------
        $company = $this->buildCompany();

        // -------- Detalles y totales --------
        $mtoGravada = 0.0;
        $mtoIGV     = 0.0;
        $mtoTotal   = 0.0;
        $detalles   = [];

        foreach ($pedido->detalles()->with('producto')->get() as $d) {
            $desc = $d->producto?->nombre ?? ('Producto #'.$d->id_producto);
            $cant = (float)$d->cantidad;
            $pUnitConIGV = (float)$d->precio_unitario;        // precios incluyen IGV
            $pUnitSinIGV = round($pUnitConIGV / (1 + $igv), 6);
            $base        = round($pUnitSinIGV * $cant, 2);
            $igvDet      = round($base * $igv, 2);
            $totalDet    = round($pUnitConIGV * $cant, 2);

            $item = (new SaleDetail())
                ->setCodProducto((string)$d->id_producto)
                ->setUnidad('NIU')
                ->setCantidad($cant)
                ->setMtoValorUnitario($pUnitSinIGV)
                ->setDescripcion($desc)
                ->setMtoBaseIgv($base)
                ->setPorcentajeIgv($igv * 100)
                ->setIgv($igvDet)
                ->setTipAfeIgv('10')
                ->setTotalImpuestos($igvDet)
                ->setMtoValorVenta($base)
                ->setMtoPrecioUnitario($pUnitConIGV);

            $detalles[]  = $item;
            $mtoGravada += $base;
            $mtoIGV     += $igvDet;
            $mtoTotal   += $totalDet;
        }

        // -------- Comprobante --------
        $tz = new DateTimeZone('America/Lima');

        $invoice = (new Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101')
            ->setTipoDoc($tipoDocCpe)
            ->setSerie($serie)
            ->setCorrelativo((string)$numero)
            ->setFechaEmision(new DateTime('now', $tz))
            ->setFormaPago(new FormaPagoContado())
            ->setTipoMoneda('PEN')
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas(round($mtoGravada, 2))
            ->setMtoIGV(round($mtoIGV, 2))
            ->setTotalImpuestos(round($mtoIGV, 2))
            ->setValorVenta(round($mtoGravada, 2))
            ->setSubTotal(round($mtoTotal, 2))
            ->setMtoImpVenta(round($mtoTotal, 2))
            ->setDetails($detalles);

        $legend = (new Legend())
            ->setCode('1000')
            ->setValue('SON ' . $this->montoEnLetras($mtoTotal) . ' SOLES');
        $invoice->setLegends([$legend]);

        $invoiceName = $invoice->getName();

        // -------- Envío a SUNAT --------
        $see    = $this->buildSee();
        $result = $see->send($invoice);

        if (!$result->isSuccess()) {
            $err = $result->getError();
            Log::error('[SUNAT ERROR]', [
                'code' => $err?->getCode(),
                'message' => $err?->getMessage() ?: 'Sin detalle',
            ]);
        }

        // -------- Guardado de archivos --------
        $tipoCarpeta = $tipo; // 'FA' o 'BO'
        $num8     = str_pad($numero, 8, '0', STR_PAD_LEFT);
        $friendly = "{$serie}-{$num8}";

        // XML firmado
        $lastXml = '';
        if (method_exists($see, 'getFactory') && $see->getFactory()) {
            $lastXml = $see->getFactory()->getLastXml();
        } elseif (method_exists($see, 'getLastXml')) {
            $lastXml = $see->getLastXml();
        }

        $xmlRel = "comprobantes/xml/{$tipoCarpeta}/{$serie}/{$friendly}.xml";
        Storage::disk('public')->put($xmlRel, $lastXml ?: '<xml/>');

        // CDR
        $cdrRel = null;
        if ($result->getCdrZip()) {
            $cdrRel = "comprobantes/cdr/{$tipoCarpeta}/R-{$friendly}.zip";
            Storage::disk('public')->put($cdrRel, $result->getCdrZip());
        }

        // -------- HASH (DigestValue) del XML --------
        $hash = '-';
        if ($lastXml && preg_match('/<ds:DigestValue>([^<]+)<\/ds:DigestValue>/i', $lastXml, $m)) {
            $hash = $m[1];
        }

        // -------- QR (en base64, sin imagick) --------
        $qrText = implode('|', [
            env('SUNAT_RUC'),
            $tipoDocCpe,           // 01/03
            $serie,
            $num8,
            number_format($mtoIGV, 2, '.', ''),
            number_format($mtoTotal, 2, '.', ''),
            date('Y-m-d'),
            $cliTipoDoc,
            $cliNumDoc,
        ]);

        $qrOptions = new QROptions([
            'outputType' => ChilliQRCode::OUTPUT_IMAGE_PNG,
            'scale'      => 3, // tamaño
            'eccLevel'   => ChilliQRCode::ECC_M,
        ]);
        $qrPng   = (new ChilliQRCode($qrOptions))->render($qrText);
        $qrB64   = 'data:image/png;base64,'.base64_encode($qrPng);

        // -------- Logo en base64 (para Dompdf) --------
        // usa tu logo local: public/Brand/logo_edorado.jpeg
        $logoPath = public_path('Brand/logo_edorado.jpeg');
        $logoB64  = file_exists($logoPath)
            ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath))
            : null;

        // -------- PDF desde Blade --------
        $pdfRel = "comprobantes/pdf/{$tipoCarpeta}/{$serie}/{$friendly}.pdf";
        $html = View::make('fe.comprobante', [
            'tipo'         => $tipo,                   // 'FA'/'BO'
            'serie'        => $serie,
            'numero'       => $num8,
            'emisor'       => [
                'ruc'         => env('SUNAT_RUC'),
                'ruc_visual'  => env('EMP_RUC_VISUAL', env('SUNAT_RUC')),
                'razon'       => env('EMP_RAZON', 'MI EMPRESA SAC'),
                'comercial'   => env('EMP_COMERCIAL', 'MI EMPRESA'),
                'direccion'   => trim((string)env('EMP_DIRECCION','-').' '.(string)env('EMP_DIST','').', '.(string)env('EMP_PROV','').', '.(string)env('EMP_DEPA','')),
            ],
            'cliente'      => [
                'doc_label'  => $tipo === 'FA' ? 'RUC' : 'DNI',
                'doc'        => $cliNumDoc,
                'nombre'     => $cliNombre,
                'direccion'  => $cliDir ?: '-',
            ],
            'moneda'       => 'PEN (PEN)',
            'mto_gravada'  => number_format($mtoGravada, 2, '.', ''),
            'mto_igv'      => number_format($mtoIGV, 2, '.', ''),
            'mto_total'    => number_format($mtoTotal, 2, '.', ''),
            'legend'       => 'SON: '.$this->montoEnLetras($mtoTotal).' SOLES',
            'items'        => $pedido->detalles()->with('producto')->get(),
            'hash'         => $hash,
            'qrB64'        => $qrB64,
            'logoB64'      => $logoB64,
            'emitido'      => date('Y-m-d H:i:s'),
        ])->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        Storage::disk('public')->put($pdfRel, $dompdf->output());

        return [
            'invoiceName' => $invoiceName,
            'xml'    => $xmlRel,
            'cdr'    => $cdrRel,
            'pdf'    => $pdfRel,
            'serie'  => $serie,
            'numero' => $numero,
        ];
    }

    private function montoEnLetras(float $monto): string
    {
        $enteros = (int)$monto;
        $dec = (int)round(($monto - $enteros) * 100);

        $basicos = [
            0=>'CERO','UNO','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE','DIEZ',
            11=>'ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE','DIECIOCHO','DIECINUEVE',
            20=>'VEINTE',30=>'TREINTA',40=>'CUARENTA',50=>'CINCUENTA',
            60=>'SESENTA',70=>'SETENTA',80=>'OCHENTA',90=>'NOVENTA',
            100=>'CIEN',200=>'DOSCIENTOS',300=>'TRESCIENTOS',400=>'CUATROCIENTOS',
            500=>'QUINIENTOS',600=>'SEISCIENTOS',700=>'SETECIENTOS',800=>'OCHOCIENTOS',900=>'NOVECIENTOS'
        ];
        $toText = function($n) use (&$toText,$basicos): string {
            if ($n < 21) return $basicos[$n] ?? (string)$n;
            if ($n < 30) return 'VEINTI'.str_replace('VEINTE','', $toText($n-20));
            if ($n < 100) return ($basicos[intval($n/10)*10] ?? '').($n%10? ' Y '.($basicos[$n%10] ?? $n%10):'');
            if ($n == 100) return 'CIEN';
            if ($n < 1000) return ($basicos[intval($n/100)*100] ?? '').($n%100? ' '.$toText($n%100):'');
            if ($n < 1000000) {
                $m = intval($n/1000); $r = $n%1000;
                $pref = ($m==1)?'MIL':$toText($m).' MIL';
                return $pref.($r? ' '.$toText($r):'');
            }
            $m = intval($n/1000000); $r = $n%1000000;
            $pref = ($m==1)?'UN MILLÓN':$toText($m).' MILLONES';
            return $pref.($r? ' '.$toText($r):'');
        };
        return $toText($enteros).' CON '.str_pad((string)$dec,2,'0',STR_PAD_LEFT).'/100';
    }
}
