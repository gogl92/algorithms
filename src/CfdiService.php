<?php

declare(strict_types=1);

namespace Gogl92\CfdiSat;

use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\Fiel;
use PhpCfdi\SatWsDescargaMasiva\RequestBuilder\FielRequestBuilder\FielRequestBuilder;
use PhpCfdi\SatWsDescargaMasiva\Service;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;
use PhpCfdi\SatWsDescargaMasiva\Services\Query\QueryParameters;
use PhpCfdi\SatWsDescargaMasiva\Shared\DateTimePeriod;
use PhpCfdi\SatWsDescargaMasiva\Shared\DownloadType;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentStatus;
use PhpCfdi\SatWsDescargaMasiva\Shared\DocumentType;
use PhpCfdi\SatWsDescargaMasiva\Shared\Uuid;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\Exceptions\OpenZipFileException;
use PhpCfdi\CfdiCleaner\Cleaner;
use CfdiUtils\Nodes\XmlNodeUtils;
use PhpCfdi\CfdiToPdf\CfdiDataBuilder;
use PhpCfdi\CfdiToPdf\Converter;
use PhpCfdi\CfdiToPdf\Builders\Html2PdfBuilder;
use Exception;
use Illuminate\Support\Facades\Log;

class CfdiService
{
    private Service $service;
    private Fiel $fiel;

    public function __construct()
    {
        $this->initializeFiel();
        $this->initializeService();
    }

    private function initializeFiel(): void
    {
        $certPath = config('cfdi.cert_path', storage_path('certs/cer.cer'));
        $keyPath = config('cfdi.key_path', storage_path('certs/key.key'));
        $password = config('cfdi.password', '');

        if (!file_exists($certPath) || !file_exists($keyPath)) {
            throw new Exception('Certificate or key files not found');
        }

        $this->fiel = Fiel::create(
            file_get_contents($certPath),
            file_get_contents($keyPath),
            $password
        );

        if (!$this->fiel->isValid()) {
            throw new Exception('Invalid FIEL certificate');
        }
    }

    private function initializeService(): void
    {
        $webClient = new GuzzleWebClient();
        $requestBuilder = new FielRequestBuilder($this->fiel);
        $this->service = new Service($requestBuilder, $webClient);
    }

    public function createConsulta(
        string $startDate,
        string $endDate,
        ?string $downloadType = null,
        ?string $documentStatus = null,
        ?string $rfcOnBehalf = null,
        ?string $rfcMatch = null,
        ?string $uuid = null
    ): string {
        $request = QueryParameters::create()
            ->withPeriod(DateTimePeriod::createFromValues($startDate, $endDate));

        if ($downloadType) {
            $request->withDownloadType(DownloadType::{$downloadType}());
        }

        if ($documentStatus) {
            $request->withDocumentStatus(DocumentStatus::{$documentStatus}());
        }

        if ($rfcOnBehalf) {
            $request->withRfcOnBehalf($rfcOnBehalf);
        }

        if ($rfcMatch) {
            $request->withRfcMatch($rfcMatch);
        }

        if ($uuid) {
            $request->withUuid(Uuid::create($uuid));
        }

        $query = $this->service->query($request);

        if (!$query->getStatus()->isAccepted()) {
            throw new Exception("Failed to create consulta: {$query->getStatus()->getMessage()}");
        }

        return $query->getRequestId();
    }

    public function checkConsultaStatus(string $requestId): array
    {
        $verify = $this->service->verify($requestId);
        $statusCode = $verify->getCodeRequest()->getValue();

        $result = [
            'status_code' => $statusCode,
            'status_message' => $verify->getStatus()->getMessage(),
            'number_cfdis' => $verify->getNumberCfdis(),
            'packages' => $verify->getPackagesIds(),
            'is_ready' => $statusCode === 2,
            'has_packages' => count($verify->getPackagesIds()) > 0
        ];

        return $result;
    }

    public function downloadPackages(string $requestId): array
    {
        $status = $this->checkConsultaStatus($requestId);
        
        if (!$status['is_ready']) {
            throw new Exception("Consulta is not ready. Status: {$status['status_message']}");
        }

        if (!$status['has_packages']) {
            return ['downloaded' => [], 'errors' => []];
        }

        $downloadFolder = config('cfdi.download_folder', storage_path('cfdi/downloads'));
        if (!is_dir($downloadFolder)) {
            mkdir($downloadFolder, 0755, true);
        }

        $downloaded = [];
        $errors = [];

        foreach ($status['packages'] as $packageId) {
            try {
                $download = $this->service->download($packageId);
                
                if (!$download->getStatus()->isAccepted()) {
                    $errors[] = "Failed to download package {$packageId}: {$download->getStatus()->getMessage()}";
                    continue;
                }

                $zipFileName = "package_{$packageId}.zip";
                $zipPath = $downloadFolder . '/' . $zipFileName;
                
                file_put_contents($zipPath, $download->getPackageContent());
                $downloaded[] = $zipPath;

                Log::info("Package downloaded successfully", ['package_id' => $packageId, 'path' => $zipPath]);
            } catch (Exception $e) {
                $errors[] = "Error downloading package {$packageId}: {$e->getMessage()}";
                Log::error("Package download failed", ['package_id' => $packageId, 'error' => $e->getMessage()]);
            }
        }

        return ['downloaded' => $downloaded, 'errors' => $errors];
    }

    public function extractXmlFromPackages(array $packagePaths): array
    {
        $xmlFolder = config('cfdi.xml_folder', storage_path('cfdi/xml'));
        if (!is_dir($xmlFolder)) {
            mkdir($xmlFolder, 0755, true);
        }

        $extracted = [];
        $errors = [];

        foreach ($packagePaths as $packagePath) {
            if (!file_exists($packagePath)) {
                $errors[] = "Package file not found: {$packagePath}";
                continue;
            }

            try {
                $cfdiReader = CfdiPackageReader::createFromFile($packagePath);
                
                foreach ($cfdiReader->cfdis() as $uuid => $content) {
                    $xmlPath = $xmlFolder . '/' . $uuid . '.xml';
                    file_put_contents($xmlPath, $content);
                    $extracted[] = $xmlPath;
                }

                Log::info("XML files extracted from package", ['package' => $packagePath, 'count' => count($cfdiReader->cfdis())]);
            } catch (OpenZipFileException $e) {
                $errors[] = "Failed to open package {$packagePath}: {$e->getMessage()}";
                Log::error("Package extraction failed", ['package' => $packagePath, 'error' => $e->getMessage()]);
            }
        }

        return ['extracted' => $extracted, 'errors' => $errors];
    }

    public function convertXmlToPdf(string $xmlPath, ?string $outputPath = null): string
    {
        if (!file_exists($xmlPath)) {
            throw new Exception("XML file not found: {$xmlPath}");
        }

        $xml = file_get_contents($xmlPath);
        $xml = Cleaner::staticClean($xml);

        $comprobante = XmlNodeUtils::nodeFromXmlString($xml);
        $cfdiData = (new CfdiDataBuilder())->build($comprobante);

        $converter = new Converter(new Html2PdfBuilder());

        if (!$outputPath) {
            $pdfFolder = config('cfdi.pdf_folder', storage_path('cfdi/pdf'));
            if (!is_dir($pdfFolder)) {
                mkdir($pdfFolder, 0755, true);
            }
            
            $filename = pathinfo($xmlPath, PATHINFO_FILENAME);
            $outputPath = "{$pdfFolder}/{$filename}.pdf";
        }

        $converter->createPdfAs($cfdiData, $outputPath);

        Log::info("XML converted to PDF", ['xml' => $xmlPath, 'pdf' => $outputPath]);

        return $outputPath;
    }

    public function convertXmlFolderToPdf(string $xmlFolder): array
    {
        if (!is_dir($xmlFolder)) {
            throw new Exception("XML folder not found: {$xmlFolder}");
        }

        $xmlFiles = glob($xmlFolder . '/*.xml');
        $converted = [];
        $errors = [];

        foreach ($xmlFiles as $xmlFile) {
            try {
                $pdfPath = $this->convertXmlToPdf($xmlFile);
                $converted[] = $pdfPath;
            } catch (Exception $e) {
                $errors[] = "Failed to convert {$xmlFile}: {$e->getMessage()}";
                Log::error("XML to PDF conversion failed", ['xml' => $xmlFile, 'error' => $e->getMessage()]);
            }
        }

        return ['converted' => $converted, 'errors' => $errors];
    }

    public function processCompleteWorkflow(
        string $startDate,
        string $endDate,
        array $options = []
    ): array {
        try {
            $requestId = $this->createConsulta($startDate, $endDate, ...$options);
            Log::info("Consulta created", ['request_id' => $requestId]);

            $status = $this->checkConsultaStatus($requestId);
            if (!$status['is_ready'] || !$status['has_packages']) {
                return [
                    'success' => false,
                    'message' => 'Consulta not ready or no packages available',
                    'status' => $status
                ];
            }

            $downloadResult = $this->downloadPackages($requestId);
            if (empty($downloadResult['downloaded'])) {
                return [
                    'success' => false,
                    'message' => 'No packages downloaded',
                    'errors' => $downloadResult['errors']
                ];
            }

            $extractResult = $this->extractXmlFromPackages($downloadResult['downloaded']);
            if (empty($extractResult['extracted'])) {
                return [
                    'success' => false,
                    'message' => 'No XML files extracted',
                    'errors' => $extractResult['errors']
                ];
            }

            $convertResult = $this->convertXmlFolderToPdf(dirname($extractResult['extracted'][0]));

            return [
                'success' => true,
                'request_id' => $requestId,
                'packages_downloaded' => count($downloadResult['downloaded']),
                'xml_files_extracted' => count($extractResult['extracted']),
                'pdf_files_converted' => count($convertResult['converted']),
                'errors' => array_merge($downloadResult['errors'], $extractResult['errors'], $convertResult['errors'])
            ];

        } catch (Exception $e) {
            Log::error("Workflow failed", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
} 
