<?php

namespace App\Services\Fe;

use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Greenter\Model\Client\Client;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\Report\ReportFactory;
use Greenter\Report\HtmlReport;

class SunatService
{
    private See $see;
    private HtmlReport $htmlReport;

    public function __construct()
    {
        $this->see = new See();
        $this->configureSee();
        $this->htmlReport = (new ReportFactory())->create();
    }

    private function configureSee(): void
    {
        // Endpoint
        $beta = filter_var(env('SUNAT_BETA', true), FILTER_VALIDATE_BOOL);
        $endpoint = $beta
            ? SunatEndpoints::FE_BETA
            : SunatEndpoints::FE_PRODUCCION;
        $this->see->setService($endpoint);

        // Certificado
        $certPem = base_path(env('SUNAT_CERT_PEM', 'storage/certs/cert.pem'));
        $keyPem  = base_path(env('SUNAT_KEY_PEM',  'storage/certs/key.pem'));
        $pass    = env('SUNAT_CERT_PASS', null);

        $this->see->setCertificate(file_get_contents($certPem));
        $this->see->setPrivateKey(file_get_contents($keyPem), $pass);

        // Credenciales SOL
        $ruc  = env('SUNAT_RUC');
        $usr  = env('SUNAT_SOL_USER');
        $pwd  = env('SUNAT_SOL_PASS');

        $this->see->setCredentials($ruc.$usr, $pwd);
    }

    /** Dirección de la empresa emisora (ajusta a tu razón social real) */
    public function getCompany(): Company
    {
        $address = (new Address())
            ->setUbigueo('190101')     // Lima-Cercado (ejemplo)
            ->setDepartamento('PASCO')
            ->setProvincia('PASCO')
            ->setDistrito('CHAUPIMARCA')
            ->setUrbanizacion('-')
            ->setDireccion('Prolongación Yauli Bloque 3');

        return (new Company())
            ->setRuc(env('20614857430'))
            ->setRazonSocial('ESTILO DORADO S.A.C.')
            ->setNombreComercial('Estilo Dorado')
            ->setAddress($address);
    }

    /** Genera correlativo simple (archivo en storage/fe/counters) */
    public function nextCorrelative(string $serie): int
    {
        $dir = storage_path('app/fe/counters');
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $file = $dir."/{$serie}.txt";
        if (!file_exists($file)) file_put_contents($file, '0');
        $n = (int)file_get_contents($file);
        $n++;
        file_put_contents($file, (string)$n);
        return $n;
    }

    /** Crea un Invoice (01 Factura o 03 Boleta) a partir del payload del front */
    public function buildInvoice(array $payload): Invoice
    {
        // $payload esperado:
        // [
        //   'tipo' => 'boleta'|'factura',
        //   'cliente' => ['tipoDoc'=>'1|6', 'numDoc'=>'...', 'nombre'=>'...', 'direccion'=>'...'],
        //   'items' => [['codigo','descripcion','cantidad','precioUnit']], // precios con IGV
        //   'moneda' => 'PEN'
        // ]

        $tipo = strtolower($payload['tipo'] ?? 'boleta');
        $isFactura = $tipo === 'factura';
        $tipoDoc = $isFactura ? '01' : '03'; // 01 Factura, 03 Boleta
        $serie   = $isFactura ? 'F001' : 'B001';
        $correl  = $this->nextCorrelative($serie);
        $igv     = (float)env('SUNAT_IGV', 0.18);

        // CLIENTE
        $c = $payload['cliente'] ?? [];
        $client = (new Client())
            ->setTipoDoc($c['tipoDoc'] ?? '1')      // 1: DNI, 6: RUC
            ->setNumDoc($c['numDoc'] ?? '00000000')
            ->setRznSocial($c['nombre'] ?? 'Cliente Varios')
            ->setAddress(
                (new Address())->setDireccion($c['direccion'] ?? '-')
            );

        // ITEMS (precios incluyen IGV)
        $items = [];
        $totalVenta = 0;
        foreach (($payload['items'] ?? []) as $it) {
            $cant = (float)$it['cantidad'];
            $punitConIgv = (float)$it['precioUnit'];
            $punitSinIgv = round($punitConIgv / (1+$igv), 2);
            $valorVenta  = round($punitSinIgv * $cant, 2);
            $igvItem     = round($valorVenta * $igv, 2);
            $totalLinea  = round($valorVenta + $igvItem, 2);

            $detail = (new SaleDetail())
                ->setCodProducto((string)($it['codigo'] ?? 'P001'))
                ->setDescripcion($it['descripcion'] ?? 'Producto')
                ->setCantidad($cant)
                ->setMtoValorUnitario($punitSinIgv)     // sin IGV
                ->setMtoValorVenta($valorVenta)         // sin IGV
                ->setMtoBaseIgv($valorVenta)
                ->setPorcentajeIgv($igv*100)
                ->setIgv($igvItem)
                ->setTipAfeIgv('10')                    // Gravado - Onerosa
                ->setTotalImpuestos($igvItem)
                ->setMtoPrecioUnitario($punitConIgv);   // con IGV

            $items[] = $detail;
            $totalVenta += $totalLinea;
        }

        $mtoIGV = round($totalVenta - ($totalVenta/(1+$igv)), 2);
        $mtoGravada = round($totalVenta - $mtoIGV, 2);

        $invoice = (new Invoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101') // Venta interna
            ->setTipoDoc($tipoDoc)     // 01 o 03
            ->setSerie($serie)
            ->setCorrelativo($correl)
            ->setFechaEmision(new \DateTime())
            ->setTipoMoneda($payload['moneda'] ?? 'PEN')
            ->setCompany($this->getCompany())
            ->setClient($client)
            ->setMtoOperGravadas($mtoGravada)
            ->setMtoIGV($mtoIGV)
            ->setTotalImpuestos($mtoIGV)
            ->setValorVenta($mtoGravada)
            ->setMtoImpVenta($totalVenta)
            ->setDetails($items)
            ->setLegends([
                (new Legend())->setCode('1000')->setValue($this->numToText($totalVenta).' SOLES')
            ]);

        return $invoice;
    }

    /** Enviar a SUNAT y guardar XML/CDR/PDF */
    public function sendAndStore(Invoice $invoice): array
    {
        $res = $this->see->send($invoice);
        $name = $invoice->getName(); // RUC-TIPO-SERIE-CORRELATIVO

        $dirXml = storage_path('app/fe/xml');
        $dirCdr = storage_path('app/fe/cdr');
        $dirPdf = storage_path('app/fe/pdf');

        foreach ([$dirXml,$dirCdr,$dirPdf] as $d) {
            if (!is_dir($d)) mkdir($d, 0777, true);
        }

        // XML firmado
        file_put_contents("$dirXml/{$name}.xml", $this->see->getFactory()->getLastXml());

        // CDR si hubo aceptación o rechazo
        if ($res->isSuccess()) {
            file_put_contents("$dirCdr/R-{$name}.zip", $res->getCdrZip());
        }

        // HTML de Greenter -> PDF con Dompdf
        $html = $this->htmlReport->render($invoice);
        $pdfPath = "$dirPdf/{$name}.pdf";
        $this->htmlToPdf($html, $pdfPath);

        return [
            'success' => $res->isSuccess(),
            'message' => $res->isSuccess() ? 'Aceptado' : $res->getError()->getMessage(),
            'name'    => $name,
            'xml'     => route('fe.xml', ['name' => $name]),
            'cdr'     => $res->isSuccess() ? route('fe.cdr', ['name' => "R-{$name}"]) : null,
            'pdf'     => route('fe.pdf', ['name' => $name]),
        ];
    }

    private function htmlToPdf(string $html, string $dest): void
    {
        // usa Dompdf
        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,   // para imágenes remotas si hubiera
            'defaultFont'     => 'DejaVu Sans'
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        file_put_contents($dest, $dompdf->output());
    }

    /** Conversor simple NÚMERO -> TEXTO (muy básico) */
    private function numToText($amount): string
    {
        // Para demo; usa un helper serio si quieres completo
        $int = floor($amount);
        $dec = round(($amount - $int) * 100);
        return $int.' CON '.str_pad((string)$dec, 2, '0', STR_PAD_LEFT).'/100';
    }
}
