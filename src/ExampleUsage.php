<?php

namespace App\Examples;

use App\Services\CfdiService;
use Illuminate\Support\Facades\Log;

class ExampleUsage
{
    private CfdiService $cfdiService;

    public function __construct(CfdiService $cfdiService)
    {
        $this->cfdiService = $cfdiService;
    }

    /**
     * Example 1: Create a consulta with basic parameters
     */
    public function createBasicConsulta(): string
    {
        try {
            $requestId = $this->cfdiService->createConsulta(
                '2025-01-01 00:00:00',
                '2025-01-31 23:59:59'
            );

            Log::info("Consulta created successfully", ['request_id' => $requestId]);
            return $requestId;

        } catch (\Exception $e) {
            Log::error("Failed to create consulta", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 2: Create a consulta with all parameters
     */
    public function createAdvancedConsulta(): string
    {
        try {
            $requestId = $this->cfdiService->createConsulta(
                '2025-01-01 00:00:00',
                '2025-01-31 23:59:59',
                'received',           // downloadType
                'active',             // documentStatus
                'AAA010101AAA',       // rfcOnBehalf
                'BBB020202BBB',       // rfcMatch
                '12345678-1234-1234-1234-123456789012' // uuid
            );

            Log::info("Advanced consulta created", ['request_id' => $requestId]);
            return $requestId;

        } catch (\Exception $e) {
            Log::error("Failed to create advanced consulta", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 3: Check consulta status
     */
    public function checkStatus(string $requestId): array
    {
        try {
            $status = $this->cfdiService->checkConsultaStatus($requestId);
            
            Log::info("Consulta status checked", [
                'request_id' => $requestId,
                'status' => $status
            ]);

            return $status;

        } catch (\Exception $e) {
            Log::error("Failed to check status", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 4: Download packages
     */
    public function downloadPackages(string $requestId): array
    {
        try {
            $result = $this->cfdiService->downloadPackages($requestId);
            
            Log::info("Packages downloaded", [
                'request_id' => $requestId,
                'downloaded_count' => count($result['downloaded']),
                'errors_count' => count($result['errors'])
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to download packages", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 5: Extract XML from packages
     */
    public function extractXml(array $packagePaths): array
    {
        try {
            $result = $this->cfdiService->extractXmlFromPackages($packagePaths);
            
            Log::info("XML files extracted", [
                'extracted_count' => count($result['extracted']),
                'errors_count' => count($result['errors'])
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to extract XML", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 6: Convert single XML to PDF
     */
    public function convertSingleXml(string $xmlPath): string
    {
        try {
            $pdfPath = $this->cfdiService->convertXmlToPdf($xmlPath);
            
            Log::info("XML converted to PDF", [
                'xml_path' => $xmlPath,
                'pdf_path' => $pdfPath
            ]);

            return $pdfPath;

        } catch (\Exception $e) {
            Log::error("Failed to convert XML to PDF", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 7: Convert all XML files in a folder
     */
    public function convertXmlFolder(string $xmlFolder): array
    {
        try {
            $result = $this->cfdiService->convertXmlFolderToPdf($xmlFolder);
            
            Log::info("XML folder converted to PDF", [
                'folder' => $xmlFolder,
                'converted_count' => count($result['converted']),
                'errors_count' => count($result['errors'])
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to convert XML folder", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 8: Complete workflow
     */
    public function completeWorkflow(): array
    {
        try {
            $result = $this->cfdiService->processCompleteWorkflow(
                '2025-01-01 00:00:00',
                '2025-01-31 23:59:59',
                [
                    'received',    // downloadType
                    'active',      // documentStatus
                    null,          // rfcOnBehalf
                    null,          // rfcMatch
                    null           // uuid
                ]
            );

            Log::info("Complete workflow executed", $result);
            return $result;

        } catch (\Exception $e) {
            Log::error("Complete workflow failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Example 9: Step-by-step workflow with status checking
     */
    public function stepByStepWorkflow(): array
    {
        try {
            // Step 1: Create consulta
            $requestId = $this->createBasicConsulta();
            
            // Step 2: Wait and check status (in real app, you might use a job queue)
            $maxAttempts = 10;
            $attempt = 0;
            
            do {
                $status = $this->checkStatus($requestId);
                $attempt++;
                
                if ($status['is_ready']) {
                    break;
                }
                
                if ($attempt >= $maxAttempts) {
                    throw new \Exception("Consulta not ready after {$maxAttempts} attempts");
                }
                
                // Wait 30 seconds before next check
                sleep(30);
                
            } while (true);

            // Step 3: Download packages
            $downloadResult = $this->downloadPackages($requestId);
            
            if (empty($downloadResult['downloaded'])) {
                throw new \Exception("No packages downloaded");
            }

            // Step 4: Extract XML
            $extractResult = $this->extractXml($downloadResult['downloaded']);
            
            if (empty($extractResult['extracted'])) {
                throw new \Exception("No XML files extracted");
            }

            // Step 5: Convert to PDF
            $xmlFolder = dirname($extractResult['extracted'][0]);
            $convertResult = $this->convertXmlFolder($xmlFolder);

            return [
                'success' => true,
                'request_id' => $requestId,
                'packages_downloaded' => count($downloadResult['downloaded']),
                'xml_files_extracted' => count($extractResult['extracted']),
                'pdf_files_converted' => count($convertResult['converted']),
                'errors' => array_merge(
                    $downloadResult['errors'],
                    $extractResult['errors'],
                    $convertResult['errors']
                )
            ];

        } catch (\Exception $e) {
            Log::error("Step-by-step workflow failed", ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
} 