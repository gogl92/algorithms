<?php

namespace App\Http\Controllers;

use App\Services\CfdiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CfdiController extends Controller
{
    private CfdiService $cfdiService;

    public function __construct(CfdiService $cfdiService)
    {
        $this->cfdiService = $cfdiService;
    }

    /**
     * Create a new CFDI consulta
     */
    public function createConsulta(Request $request): JsonResponse
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

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'message' => 'Consulta created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create consulta', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create consulta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check consulta status
     */
    public function checkStatus(string $requestId): JsonResponse
    {
        try {
            $status = $this->cfdiService->checkConsultaStatus($requestId);

            return response()->json([
                'success' => true,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check status', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download packages for a consulta
     */
    public function downloadPackages(string $requestId): JsonResponse
    {
        try {
            $result = $this->cfdiService->downloadPackages($requestId);

            return response()->json([
                'success' => true,
                'downloaded_count' => count($result['downloaded']),
                'errors_count' => count($result['errors']),
                'errors' => $result['errors']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to download packages', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download packages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract XML from packages
     */
    public function extractXml(Request $request): JsonResponse
    {
        $request->validate([
            'package_paths' => 'required|array',
            'package_paths.*' => 'string|file_exists'
        ]);

        try {
            $result = $this->cfdiService->extractXmlFromPackages($request->package_paths);

            return response()->json([
                'success' => true,
                'extracted_count' => count($result['extracted']),
                'errors_count' => count($result['errors']),
                'errors' => $result['errors']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extract XML', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to extract XML: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert XML to PDF
     */
    public function convertToPdf(Request $request): JsonResponse
    {
        $request->validate([
            'xml_path' => 'required|string|file_exists',
            'output_path' => 'nullable|string'
        ]);

        try {
            $pdfPath = $this->cfdiService->convertXmlToPdf(
                $request->xml_path,
                $request->output_path
            );

            return response()->json([
                'success' => true,
                'pdf_path' => $pdfPath,
                'message' => 'XML converted to PDF successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to convert XML to PDF', ['xml_path' => $request->xml_path, 'error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert XML to PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert all XML files in a folder to PDF
     */
    public function convertFolderToPdf(Request $request): JsonResponse
    {
        $request->validate([
            'xml_folder' => 'required|string|directory'
        ]);

        try {
            $result = $this->cfdiService->convertXmlFolderToPdf($request->xml_folder);

            return response()->json([
                'success' => true,
                'converted_count' => count($result['converted']),
                'errors_count' => count($result['errors']),
                'errors' => $result['errors']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to convert folder to PDF', ['folder' => $request->xml_folder, 'error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert folder to PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete workflow - create consulta, download, extract, and convert
     */
    public function completeWorkflow(Request $request): JsonResponse
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
            $result = $this->cfdiService->processCompleteWorkflow(
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
                [
                    $request->download_type,
                    $request->document_status,
                    $request->rfc_on_behalf,
                    $request->rfc_match,
                    $request->uuid
                ]
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Complete workflow failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Complete workflow failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get configuration information
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config' => [
                'download_folder' => config('cfdi.download_folder'),
                'xml_folder' => config('cfdi.xml_folder'),
                'pdf_folder' => config('cfdi.pdf_folder'),
                'cert_path' => config('cfdi.cert_path'),
                'key_path' => config('cfdi.key_path'),
                'cert_exists' => file_exists(config('cfdi.cert_path')),
                'key_exists' => file_exists(config('cfdi.key_path'))
            ]
        ]);
    }
} 