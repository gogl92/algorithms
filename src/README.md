# CFDI Service for Laravel

A structured service for handling CFDI (Comprobantes Fiscales Digitales por Internet) operations in Laravel applications.

## Features

- Create CFDI consultas with flexible parameters
- Check consulta status
- Download CFDI packages
- Extract XML files from packages
- Convert XML files to PDF
- Complete workflow processing
- Error handling and logging
- Laravel configuration integration

## Installation

### 1. Copy Files to Your Laravel Project

Copy the following files to your Laravel project:

- `CfdiService.php` → `app/Services/CfdiService.php`
- `CfdiServiceProvider.php` → `app/Providers/CfdiServiceProvider.php`
- `cfdi.php` → `config/cfdi.php`

### 2. Register Service Provider

Add the service provider to your `config/app.php`:

```php
'providers' => [
    // ... other providers
    App\Providers\CfdiServiceProvider::class,
],
```

### 3. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=cfdi-config
```

### 4. Environment Variables

Add these variables to your `.env` file:

```env
# CFDI Certificate Configuration
CFDI_CERT_PATH=/path/to/your/cert.cer
CFDI_KEY_PATH=/path/to/your/key.key
CFDI_PASSWORD=your_certificate_password

# CFDI Storage Paths
CFDI_DOWNLOAD_FOLDER=/path/to/downloads
CFDI_XML_FOLDER=/path/to/xml
CFDI_PDF_FOLDER=/path/to/pdf

# CFDI Processing Settings
CFDI_DEFAULT_DOWNLOAD_TYPE=received
CFDI_DEFAULT_DOCUMENT_STATUS=active
CFDI_TIMEZONE=America/Mexico_City

# CFDI Logging
CFDI_LOG_CHANNEL=daily
CFDI_LOG_LEVEL=info
```

### 5. Required Dependencies

Make sure you have these packages in your `composer.json`:

```json
{
    "require": {
        "phpcfdi/sat-ws-descarga-masiva": "^1.0",
        "phpcfdi/cfdi-cleaner": "^1.0",
        "phpcfdi/cfdi-to-pdf": "^1.0",
        "cfdiutils/cfdiutils": "^2.0",
        "guzzlehttp/guzzle": "^7.0"
    }
}
```

## Usage

### Basic Usage

```php
use App\Services\CfdiService;

class CfdiController extends Controller
{
    public function processCfdi(CfdiService $cfdiService)
    {
        try {
            // Create consulta
            $requestId = $cfdiService->createConsulta(
                '2025-01-01 00:00:00',
                '2025-01-31 23:59:59'
            );

            // Check status
            $status = $cfdiService->checkConsultaStatus($requestId);
            
            if ($status['is_ready'] && $status['has_packages']) {
                // Download packages
                $downloadResult = $cfdiService->downloadPackages($requestId);
                
                // Extract XML
                $extractResult = $cfdiService->extractXmlFromPackages($downloadResult['downloaded']);
                
                // Convert to PDF
                $xmlFolder = dirname($extractResult['extracted'][0]);
                $convertResult = $cfdiService->convertXmlFolderToPdf($xmlFolder);
                
                return response()->json([
                    'success' => true,
                    'request_id' => $requestId,
                    'pdf_files' => $convertResult['converted']
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

### Advanced Usage with All Parameters

```php
$requestId = $cfdiService->createConsulta(
    '2025-01-01 00:00:00',           // startDate (required)
    '2025-01-31 23:59:59',           // endDate (required)
    'received',                       // downloadType (optional)
    'active',                         // documentStatus (optional)
    'AAA010101AAA',                   // rfcOnBehalf (optional)
    'BBB020202BBB',                   // rfcMatch (optional)
    '12345678-1234-1234-1234-123456789012' // uuid (optional)
);
```

### Complete Workflow

```php
$result = $cfdiService->processCompleteWorkflow(
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

if ($result['success']) {
    echo "Processed {$result['pdf_files_converted']} PDF files";
} else {
    echo "Error: {$result['message']}";
}
```

### Individual Operations

```php
// Check status
$status = $cfdiService->checkConsultaStatus($requestId);

// Download packages
$downloadResult = $cfdiService->downloadPackages($requestId);

// Extract XML from specific packages
$extractResult = $cfdiService->extractXmlFromPackages($packagePaths);

// Convert single XML to PDF
$pdfPath = $cfdiService->convertXmlToPdf('/path/to/file.xml');

// Convert all XML files in a folder
$convertResult = $cfdiService->convertXmlFolderToPdf('/path/to/xml/folder');
```

## Configuration Options

### Certificate Configuration
- `cert_path`: Path to your FIEL certificate (.cer file)
- `key_path`: Path to your FIEL private key (.key file)
- `password`: Certificate password

### Storage Paths
- `download_folder`: Where to store downloaded packages
- `xml_folder`: Where to store extracted XML files
- `pdf_folder`: Where to store generated PDF files

### Processing Settings
- `default_download_type`: Default download type (received/issued)
- `default_document_status`: Default document status (active/cancelled)
- `timezone`: Timezone for date operations

## Error Handling

The service includes comprehensive error handling:

- Certificate validation
- File existence checks
- Network request failures
- Processing errors
- Detailed logging

All errors are logged and can be handled gracefully in your application.

## Logging

The service uses Laravel's logging system. You can configure logging in your `config/logging.php`:

```php
'channels' => [
    'cfdi' => [
        'driver' => 'daily',
        'path' => storage_path('logs/cfdi.log'),
        'level' => 'info',
        'days' => 14,
    ],
],
```

## Security Considerations

1. Store certificate files securely
2. Use environment variables for sensitive data
3. Set appropriate file permissions
4. Regularly rotate certificates
5. Monitor logs for suspicious activity

## Troubleshooting

### Common Issues

1. **Certificate not found**: Check file paths in configuration
2. **Invalid certificate**: Verify certificate validity and password
3. **Network errors**: Check internet connectivity and SAT service status
4. **Permission errors**: Ensure proper file permissions for storage directories

### Debug Mode

Enable debug logging by setting `CFDI_LOG_LEVEL=debug` in your `.env` file.

## License

This service is provided as-is for integration with Laravel applications. 