<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckSunatCert extends Command
{
    protected $signature = 'sunat:check-cert';
    protected $description = 'Muestra info básica del certificado PEM configurado';

    public function handle()
    {
        $path = base_path(env('SUNAT_CERT_PEM_PATH', 'storage/certs/cert.pem'));
        if (!file_exists($path)) {
            $this->error("No existe: $path");
            return Command::FAILURE;
        }

        $pem = file_get_contents($path);
        if (!$pem) {
            $this->error("No se pudo leer el PEM");
            return Command::FAILURE;
        }

        // Extraer sólo el bloque de CERT para parsearlo
        if (preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $m)) {
            $certBlock = "-----BEGIN CERTIFICATE-----\n" . trim($m[1]) . "\n-----END CERTIFICATE-----\n";
            $data = openssl_x509_parse($certBlock);
            if ($data === false) {
                $this->error("No se pudo parsear el certificado X.509");
                return Command::FAILURE;
            }

            $this->info("Sujeto (CN): " . ($data['subject']['CN'] ?? 'N/D'));
            $this->info("Emisor (O): " . ($data['issuer']['O'] ?? 'N/D'));
            $this->info("Válido desde: " . date('Y-m-d H:i:s', $data['validFrom_time_t']));
            $this->info("Válido hasta: " . date('Y-m-d H:i:s', $data['validTo_time_t']));
            return Command::SUCCESS;
        }

        $this->error("No se encontró bloque de certificado en el PEM");
        return Command::FAILURE;
    }
}
