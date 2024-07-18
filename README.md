
# Adobe PDF Services PHP SDK

Adobe PDF Services PHP SDK is a PHP library for creating PDF files using Adobe PDF Services.

## Installation

Install via Composer:

```bash
composer require danny3b/adobe-pdf-services
```

## Requirements

- PHP >= 8.0
- rmccue/requests >= 1.8

## Usage

```php
<?php
use AdobePDFServices\CreatePDF;

$clientId = 'YOUR_CLIENT_ID';
$clientSecret = 'YOUR_CLIENT_SECRET';

$pdfCreator = new CreatePDF($clientId, $clientSecret);
$file = '/path/to/your/file';
$downloadUri = $pdfCreator->convert($file);
?>
```

## License

This project is licensed under the MIT License.