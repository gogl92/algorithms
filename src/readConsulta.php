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
use PhpCfdi\SatWsDescargaMasiva\PackageReader\Exceptions\OpenZipFileException;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\MetadataPackageReader;

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

$requestId = '0d64e5c6-aba1-4b24-aef8-17d408d4a128'; // Consulta de 6 meses
// $requestId = '1be9114d-7717-4d7b-bb38-a7650fc32f3a'; // Consulta de Julio 2025
// $requestId = '89C53159-90E6-46F5-A6E2-570CC63A7D7F'; // Consulta Manual Junio-Julio 2025

// Configurar zona horaria de México
date_default_timezone_set('America/Mexico_City');

echo "=== VERIFICACIÓN DE SOLICITUD ===", PHP_EOL;
echo "ID de solicitud: {$requestId}", PHP_EOL;
echo "Fecha/Hora: " . date('Y-m-d H:i:s') . " (México)", PHP_EOL;
echo "", PHP_EOL;

try {
// Verificar el estado de la solicitud
$verify = $service->verify($requestId);

echo "Estado: {$verify->getStatus()->getMessage()}", PHP_EOL;
echo "Código de estado: {$verify->getCodeRequest()->getValue()}", PHP_EOL;
echo "Número de CFDIs encontrados: {$verify->getNumberCfdis()}", PHP_EOL;

$statusCode = $verify->getCodeRequest()->getValue();

// Interpretar el código de estado
switch ($statusCode) {
    case 1:
        echo "📋 Estado: EN PROCESO - La solicitud está siendo procesada", PHP_EOL;
        echo "⏰ Acción: Verificar nuevamente en unos minutos", PHP_EOL;
        break;

    case 2:
        echo "✅ Estado: TERMINADA - La solicitud está lista", PHP_EOL;

        // Verificar si hay paquetes para descargar
        $packages = $verify->getPackagesIds();
        if (count($packages) > 0) {
            echo "📦 Paquetes disponibles: " . count($packages), PHP_EOL;
            echo "🔽 Iniciando descarga automática...", PHP_EOL;
            echo "", PHP_EOL;

            // Descargar cada paquete
            foreach ($packages as $packageId) {
                echo "Descargando paquete: {$packageId}...", PHP_EOL;

                $download = $service->download($packageId);

                if (! $download->getStatus()->isAccepted()) {
                    echo "❌ Error al descargar: {$download->getStatus()->getMessage()}", PHP_EOL;
                    continue;
                }

                // Guardar el archivo
                $zipFileName = "descarga_{$packageId}.zip";
                file_put_contents('/Users/gogl92/PhpstormProjects/algorithms/cfdis/'.$zipFileName, $download->getPackageContent());

                $fileSize = number_format(strlen($download->getPackageContent()));
                echo "✅ Guardado como: {$zipFileName} ({$fileSize} bytes)", PHP_EOL;
            }

            echo "", PHP_EOL;
            echo "🎉 ¡Descarga completada!", PHP_EOL;
        } else {
            echo "📭 No hay paquetes disponibles para descargar", PHP_EOL;
            if ($verify->getNumberCfdis() == 0) {
                echo "💡 Posible causa: No hay CFDIs en el rango de fechas consultado", PHP_EOL;
            }
        }
        break;

    case 3:
        echo "❌ Estado: ERROR - La solicitud falló", PHP_EOL;
        echo "🔍 Detalle: {$verify->getStatus()->getMessage()}", PHP_EOL;
        break;

    case 5000:
        echo "⏳ Estado: SOLICITUD ACEPTADA - Procesando en segundo plano", PHP_EOL;
        echo "⏰ Acción: Verificar nuevamente en 5-10 minutos", PHP_EOL;
        break;

    default:
        echo "❓ Estado desconocido: {$statusCode}", PHP_EOL;
        echo "📝 Mensaje: {$verify->getStatus()->getMessage()}", PHP_EOL;
        break;
}
} catch (Exception $e) {
    echo "❌ Error al verificar la solicitud: " . $e->getMessage(), PHP_EOL;
    exit(1);
    }

echo "", PHP_EOL;
echo "=== FIN DE VERIFICACIÓN ===", PHP_EOL;

