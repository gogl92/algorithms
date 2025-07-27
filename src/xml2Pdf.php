<?php

declare(strict_types=1);

include_once __DIR__ . '/../vendor/autoload.php';

$cfdifile = '/Users/gogl92/PhpstormProjects/algorithms/cfdis/kavak.xml';
$xml = file_get_contents($cfdifile);

// clean cfdi
$xml = \PhpCfdi\CfdiCleaner\Cleaner::staticClean($xml);

// create the main node structure
$comprobante = \CfdiUtils\Nodes\XmlNodeUtils::nodeFromXmlString($xml);

// create the CfdiData object, it contains all the required information
$cfdiData = (new \PhpCfdi\CfdiToPdf\CfdiDataBuilder())
    ->build($comprobante);

// create the converter
$converter = new \PhpCfdi\CfdiToPdf\Converter(
    new \PhpCfdi\CfdiToPdf\Builders\Html2PdfBuilder()
);

// create the invoice as output.pdf
$converter->createPdfAs($cfdiData, '/Users/gogl92/PhpstormProjects/algorithms/cfdis/kavak_generated.pdf');
