# CFDI SAT Laravel Package

A Laravel package to interact with Mexico's SAT CFDI web service. It provides
helpers to request, download and convert CFDI files to PDF using familiar
Laravel patterns.

## Installation

Require the package via Composer:

```bash
composer require gogl92/cfdi-sat
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cfdi-config
```

## Usage

Use the provided facade to work with the service:

```php
use Gogl92\CfdiSat\Facades\Cfdi;

$requestId = Cfdi::createConsulta('2025-01-01 00:00:00', '2025-01-31 23:59:59');
```

See the `config/cfdi.php` file for available configuration options.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
