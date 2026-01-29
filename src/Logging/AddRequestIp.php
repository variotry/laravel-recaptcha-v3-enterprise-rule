<?php

namespace Variotry\Recaptcha\V3\Enterprise\Logging;

use Illuminate\Log\Logger;

class AddRequestIp
{
    public function __invoke( Logger $logger ): void
    {
        $logger->pushProcessor( function ( $record ) {
            $record[ 'extra' ][ 'ip' ] = \Request::ip();
            return $record;
        } );
    }
}
