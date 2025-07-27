<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\RequestType;

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

// Crear la consulta
$request = QueryParameters::create()
->withPeriod(DateTimePeriod::createFromValues('2025-06-01 00:00:00', '2025-07-26 23:59:59'))
->withDownloadType(DownloadType::received());

// presentar la consulta
$query = $service->query($request);

// verificar que el proceso de consulta fue correcto
if (! $query->getStatus()->isAccepted()) {
    echo "Fallo al presentar la consulta: {$query->getStatus()->getMessage()}";
    return;
}

// el identificador de la consulta está en $query->getRequestId()
echo "Se generó la solicitud " . strtoupper($query->getRequestId()), PHP_EOL;
