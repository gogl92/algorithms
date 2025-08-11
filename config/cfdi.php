<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CFDI Certificate Configuration
    |--------------------------------------------------------------------------
    |
    | Paths to your FIEL certificate files and password
    |
    */
    'cert' => [
        'disk' => env('CFDI_CERT_DISK', 'local'),
        'path' => env('CFDI_CERT_PATH', 'certs/cer.cer'),
    ],
    'key' => [
        'disk' => env('CFDI_KEY_DISK', 'local'),
        'path' => env('CFDI_KEY_PATH', 'certs/key.key'),
    ],
    'password' => env('CFDI_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | CFDI Storage Paths
    |--------------------------------------------------------------------------
    |
    | Folders where CFDI files will be stored
    |
    */
    'download_folder' => env('CFDI_DOWNLOAD_FOLDER', storage_path('cfdi/downloads')),
    'xml_folder' => env('CFDI_XML_FOLDER', storage_path('cfdi/xml')),
    'pdf_folder' => env('CFDI_PDF_FOLDER', storage_path('cfdi/pdf')),

    /*
    |--------------------------------------------------------------------------
    | CFDI Processing Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for CFDI processing
    |
    */
    'default_download_type' => env('CFDI_DEFAULT_DOWNLOAD_TYPE', 'received'),
    'default_document_status' => env('CFDI_DEFAULT_DOCUMENT_STATUS', 'active'),
    'timezone' => env('CFDI_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | CFDI Logging
    |--------------------------------------------------------------------------
    |
    | Logging configuration for CFDI operations
    |
    */
    'log_channel' => env('CFDI_LOG_CHANNEL', 'daily'),
    'log_level' => env('CFDI_LOG_LEVEL', 'info'),
]; 
