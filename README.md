# CFDI SAT Laravel Package

A Laravel package to interact with Mexico's SAT CFDI web service. It provides
helpers to request, download and convert CFDI files to PDF using familiar
Laravel patterns.

## Installation

Require the package via Composer:

```bash
composer require inquid/cfdi-sat
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cfdi-config
```

### Certificates and Private Keys

The package reads your FIEL certificate and key using Laravel's storage
disks, so the files can live on a secure, non-public disk. Configure the
disk and path for each file in your `.env`:

```
CFDI_CERT_DISK=local
CFDI_CERT_PATH=certs/cer.cer
CFDI_KEY_DISK=local
CFDI_KEY_PATH=certs/key.key
CFDI_PASSWORD=your-password
```

Define any custom disks in `config/filesystems.php` to keep these
high-risk files isolated from the public web root.

## Usage

### Basic Example

Use the provided facade to work with the service:

```php
use Inquid\CfdiSat\Facades\Cfdi;

$requestId = Cfdi::createConsulta('2025-01-01 00:00:00', '2025-01-31 23:59:59');
```

### Complete Workflow

```php
use Inquid\CfdiSat\Facades\Cfdi;

// Process complete workflow: create consulta, download, extract, and convert to PDF
$result = Cfdi::processCompleteWorkflow('2025-01-01 00:00:00', '2025-01-31 23:59:59');

if ($result['success']) {
    echo "Workflow completed successfully!\n";
    echo "- Packages downloaded: {$result['packages_downloaded']}\n";
    echo "- XML files extracted: {$result['xml_files_extracted']}\n";
    echo "- PDF files converted: {$result['pdf_files_converted']}\n";
}
```

### Standalone Usage

For non-Laravel applications, you can use the service directly:

```php
require_once 'vendor/autoload.php';

use Inquid\CfdiSat\CfdiService;

$cfdiService = new CfdiService();
$requestId = $cfdiService->createConsulta('2025-01-01 00:00:00', '2025-01-31 23:59:59');
```

### Available Methods

- `createConsulta()` - Create a new CFDI consultation
- `checkConsultaStatus()` - Check the status of a consultation
- `downloadPackages()` - Download CFDI packages
- `extractXmlFromPackages()` - Extract XML files from packages
- `convertXmlToPdf()` - Convert a single XML file to PDF
- `convertXmlFolderToPdf()` - Convert all XML files in a folder to PDF
- `processCompleteWorkflow()` - Complete end-to-end workflow

See `src/ExampleUsage.php` for comprehensive examples and the `config/cfdi.php` file for available configuration options.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
