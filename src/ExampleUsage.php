<?php

/**
 * Example Usage of CfdiService
 * 
 * This file demonstrates how to use the CfdiService class for various CFDI operations.
 * Make sure you have the required dependencies installed and configured.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Inquid\CfdiSat\CfdiService;

// Example 1: Basic usage - Create a consulta and process it
function exampleBasicWorkflow()
{
    try {
        $cfdiService = new CfdiService();
        
        echo "=== Basic CFDI Workflow Example ===\n";
        
        // Create a consulta for a date range
        $requestId = $cfdiService->createConsulta(
            '2025-01-01 00:00:00',
            '2025-01-31 23:59:59'
        );
        
        echo "Consulta created with ID: {$requestId}\n";
        
        // Check status until ready
        do {
            $status = $cfdiService->checkConsultaStatus($requestId);
            echo "Status: {$status['status_message']} (Code: {$status['status_code']})\n";
            
            if ($status['is_ready']) {
                echo "Consulta is ready! Found {$status['number_cfdis']} CFDIs in {$status['has_packages']} packages\n";
                break;
            }
            
            echo "Waiting 30 seconds before checking again...\n";
            sleep(30);
        } while (true);
        
        // Download and process packages
        $result = $cfdiService->processCompleteWorkflow(
            '2025-01-01 00:00:00',
            '2025-01-31 23:59:59'
        );
        
        if ($result['success']) {
            echo "Workflow completed successfully!\n";
            echo "- Packages downloaded: {$result['packages_downloaded']}\n";
            echo "- XML files extracted: {$result['xml_files_extracted']}\n";
            echo "- PDF files converted: {$result['pdf_files_converted']}\n";
        } else {
            echo "Workflow failed: {$result['message']}\n";
        }
        
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    }
}

// Example 2: Step-by-step processing
function exampleStepByStep()
{
    try {
        $cfdiService = new CfdiService();
        
        echo "\n=== Step-by-Step Processing Example ===\n";
        
        // Step 1: Create consulta
        $requestId = $cfdiService->createConsulta(
            '2025-01-01 00:00:00',
            '2025-01-31 23:59:59',
            'received',  // download_type
            'active'     // document_status
        );
        echo "Step 1: Consulta created - {$requestId}\n";
        
        // Step 2: Check status
        $status = $cfdiService->checkConsultaStatus($requestId);
        echo "Step 2: Status checked - {$status['status_message']}\n";
        
        if ($status['is_ready'] && $status['has_packages']) {
            // Step 3: Download packages
            $downloadResult = $cfdiService->downloadPackages($requestId);
            echo "Step 3: Packages downloaded - " . count($downloadResult['downloaded']) . " files\n";
            
            if (!empty($downloadResult['downloaded'])) {
                // Step 4: Extract XML files
                $extractResult = $cfdiService->extractXmlFromPackages($downloadResult['downloaded']);
                echo "Step 4: XML files extracted - " . count($extractResult['extracted']) . " files\n";
                
                if (!empty($extractResult['extracted'])) {
                    // Step 5: Convert to PDF
                    $xmlFolder = dirname($extractResult['extracted'][0]);
                    $convertResult = $cfdiService->convertXmlFolderToPdf($xmlFolder);
                    echo "Step 5: PDF files created - " . count($convertResult['converted']) . " files\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    }
}

// Example 3: Convert existing XML files to PDF
function exampleConvertExistingXml()
{
    try {
        $cfdiService = new CfdiService();
        
        echo "\n=== Convert Existing XML Files Example ===\n";
        
        // Specify the folder containing XML files
        $xmlFolder = '/path/to/your/xml/files';
        
        if (is_dir($xmlFolder)) {
            $result = $cfdiService->convertXmlFolderToPdf($xmlFolder);
            
            if (!empty($result['converted'])) {
                echo "Successfully converted " . count($result['converted']) . " XML files to PDF:\n";
                foreach ($result['converted'] as $pdfFile) {
                    echo "- {$pdfFile}\n";
                }
            }
            
            if (!empty($result['errors'])) {
                echo "Errors encountered:\n";
                foreach ($result['errors'] as $error) {
                    echo "- {$error}\n";
                }
            }
        } else {
            echo "XML folder not found: {$xmlFolder}\n";
        }
        
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    }
}

// Example 4: Custom configuration
function exampleWithCustomConfig()
{
    try {
        // Note: In a Laravel application, you would configure this in config/cfdi.php
        // For standalone usage, you might need to set environment variables or modify the service
        
        $cfdiService = new CfdiService();
        
        echo "\n=== Custom Configuration Example ===\n";
        echo "This example shows how to use the service with custom configuration.\n";
        echo "In a Laravel app, configure paths in config/cfdi.php:\n";
        echo "- cert.disk: Storage disk for certificates\n";
        echo "- cert.path: Path to .cer file\n";
        echo "- key.disk: Storage disk for private keys\n";
        echo "- key.path: Path to .key file\n";
        echo "- download_folder: Folder for downloaded packages\n";
        echo "- xml_folder: Folder for extracted XML files\n";
        echo "- pdf_folder: Folder for generated PDF files\n";
        
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    }
}

// Run examples
if (php_sapi_name() === 'cli') {
    echo "CFDI Service Examples\n";
    echo "===================\n\n";
    
    exampleBasicWorkflow();
    exampleStepByStep();
    exampleConvertExistingXml();
    exampleWithCustomConfig();
    
    echo "\nExamples completed!\n";
} else {
    echo "This file is designed to be run from the command line.\n";
    echo "Run: php src/ExampleUsage.php\n";
} 