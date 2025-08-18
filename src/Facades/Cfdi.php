<?php

namespace Inquid\CfdiSat\Facades;

use Illuminate\Support\Facades\Facade;

class Cfdi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cfdi';
    }
}

