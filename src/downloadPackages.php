<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentType;
use PhpCfdi\SatWsDescargaMasiva\Shared\Uuid;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\Exceptions\OpenZipFileException;

// Creación de la FIEL, puede leer archivos DER (como los envía el SAT) o PEM (convertidos con openssl)
$fiel = Fiel::create(
    file_get_contents(__DIR__ . '/../certs/cer.cer'),
    file_get_contents(__DIR__ . '/../certs/key.key'),
    'Artisalie15!'
);

// verificar que la FIEL sea válida (no sea CSD y sea vigente acorde a la fecha del sistema)
if (! $fiel->isValid()) {
    return;
}

// creación del web client basado en Guzzle que implementa WebClientInterface
// para usarlo necesitas instalar guzzlehttp/guzzle, pues no es una dependencia directa
$webClient = new GuzzleWebClient();

// creación del objeto encargado de crear las solicitudes firmadas usando una FIEL
$requestBuilder = new FielRequestBuilder($fiel);

// Creación del servicio
$service = new Service($requestBuilder, $webClient);

$requestId = '70345B67-B8C3-43FF-A78A-89F3D138ADF9';

// consultar el servicio de verificación
$verify = $service->verify($requestId);

$packagesIds = $verify->getPackagesIds();

// consultar el servicio de verificación
foreach($packagesIds as $packageId) {
    $download = $service->download($packageId);
    if (! $download->getStatus()->isAccepted()) {
        echo "El paquete {$packageId} no se ha podido descargar: {$download->getStatus()->getMessage()}", PHP_EOL;
        continue;
    }
    $zipfile = "$packageId.zip";
    file_put_contents('/Users/gogl92/PhpstormProjects/algorithms/cfdis/'.$zipfile, $download->getPackageContent());
    echo "El paquete {$packageId} se ha almacenado", PHP_EOL;
}

/**
 * @var string $zipfile Contiene la ruta al archivo de paquete de archivos ZIP
 */
try {
    $cfdiReader = CfdiPackageReader::createFromFile('/Users/gogl92/PhpstormProjects/algorithms/cfdis/'.$zipfile);
} catch (OpenZipFileException $exception) {
    echo $exception->getMessage(), PHP_EOL;
    return;
}

// leer todos los CFDI dentro del archivo ZIP con el UUID como llave
foreach ($cfdiReader->cfdis() as $uuid => $content) {
    file_put_contents("/Users/gogl92/PhpstormProjects/algorithms/cfdis/$uuid.xml", $content);
}
