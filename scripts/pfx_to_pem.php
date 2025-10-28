<?php
// Uso: php scripts/pfx_to_pem.php storage/certs/mi-certifcado.pfx "CONTRASENA_PFX"

if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/pfx_to_pem.php <ruta.pfx> <password>\n");
    exit(1);
}

$pfxPath  = $argv[1];
$password = $argv[2];

if (!file_exists($pfxPath)) {
    fwrite(STDERR, "No existe el archivo: $pfxPath\n");
    exit(1);
}

$pfx = file_get_contents($pfxPath);
if ($pfx === false) {
    fwrite(STDERR, "No se pudo leer el archivo PFX.\n");
    exit(1);
}

$certs = [];
if (!openssl_pkcs12_read($pfx, $certs, $password)) {
    fwrite(STDERR, "No se pudo abrir el PFX. ¿Password correcto? ¿extensión openssl habilitada?\n");
    exit(1);
}

// $certs['cert'] = certificado público PEM
// $certs['pkey'] = llave privada PEM (normalmente sin pass)
$pub  = $certs['cert'] ?? null;
$pkey = $certs['pkey'] ?? null;

if (!$pub || !$pkey) {
    fwrite(STDERR, "No se pudo extraer cert o key del PFX.\n");
    exit(1);
}

$targetDir = __DIR__ . '/../storage/certs';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

file_put_contents($targetDir . '/public_cert.pem', $pub);
file_put_contents($targetDir . '/private_key.pem', $pkey);
file_put_contents($targetDir . '/cert.pem', $pub . $pkey);

echo "OK. Generados:\n";
echo " - storage/certs/public_cert.pem\n";
	echo " - storage/certs/private_key.pem\n";
echo " - storage/certs/cert.pem (cert + key)\n";
