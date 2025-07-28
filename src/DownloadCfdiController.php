<?php

namespace App\Http\Controllers;

use App\Services\CfdiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class DownloadCfdiController extends Controller
{
    private CfdiService $cfdiService;

    public function __construct(CfdiService $cfdiService)
    {
        $this->cfdiService = $cfdiService;
    }

    /**
     * Create a new CFDI consulta
     * POST /cfdi/downloads
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'download_type' => 'nullable|in:received,issued',
            'document_status' => 'nullable|in:active,cancelled',
            'rfc_on_behalf' => 'nullable|string|size:13',
            'rfc_match' => 'nullable|string|size:13',
            'uuid' => 'nullable|string|regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'
        ]);

        try {
            $requestId = $this->cfdiService->createConsulta(
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
                $request->download_type,
                $request->document_status,
                $request->rfc_on_behalf,
                $request->rfc_match,
                $request->uuid
            );

            Log::info('CFDI consulta created successfully', [
                'request_id' => $requestId,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'message' => 'Consulta created successfully'
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to create consulta', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create consulta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check consulta status and process if ready
     * GET /cfdi/downloads/{requestId}
     */
    public function show(string $requestId): JsonResponse
    {
        try {
            // Check consulta status
            $status = $this->cfdiService->checkConsultaStatus($requestId);
            
            Log::info('CFDI consulta status checked', [
                'request_id' => $requestId,
                'status' => $status
            ]);

            // If not ready, return status only
            if (!$status['is_ready']) {
                return response()->json([
                    'success' => true,
                    'status' => $status,
                    'message' => 'Consulta is not ready yet'
                ]);
            }

            // If no packages available
            if (!$status['has_packages']) {
                return response()->json([
                    'success' => true,
                    'status' => $status,
                    'message' => 'Consulta is ready but no packages available'
                ]);
            }

            // Process the consulta: download, extract, and convert
            $downloadResult = $this->cfdiService->downloadPackages($requestId);
            
            if (empty($downloadResult['downloaded'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No packages downloaded',
                    'errors' => $downloadResult['errors']
                ], 500);
            }

            $extractResult = $this->cfdiService->extractXmlFromPackages($downloadResult['downloaded']);
            
            if (empty($extractResult['extracted'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No XML files extracted',
                    'errors' => $extractResult['errors']
                ], 500);
            }

            $xmlFolder = dirname($extractResult['extracted'][0]);
            $convertResult = $this->cfdiService->convertXmlFolderToPdf($xmlFolder);

            Log::info('CFDI processing completed', [
                'request_id' => $requestId,
                'packages_downloaded' => count($downloadResult['downloaded']),
                'xml_files_extracted' => count($extractResult['extracted']),
                'pdf_files_converted' => count($convertResult['converted'])
            ]);

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'status' => $status,
                'packages_downloaded' => count($downloadResult['downloaded']),
                'xml_files_extracted' => count($extractResult['extracted']),
                'pdf_files_converted' => count($convertResult['converted']),
                'pdf_files' => $convertResult['converted'],
                'errors' => array_merge(
                    $downloadResult['errors'],
                    $extractResult['errors'],
                    $convertResult['errors']
                )
            ]);

        } catch (Exception $e) {
            Log::error('Failed to process consulta', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process consulta: ' . $e->getMessage()
            ], 500);
        }
    }
} 