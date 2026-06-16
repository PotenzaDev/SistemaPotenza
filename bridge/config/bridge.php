<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bridge API Token
    |--------------------------------------------------------------------------
    |
    | Token estático exigido no header "X-Bridge-Token" para autenticar
    | chamadas server-to-server vindas do backend principal.
    |
    */

    'token' => env('BRIDGE_API_TOKEN'),

];
