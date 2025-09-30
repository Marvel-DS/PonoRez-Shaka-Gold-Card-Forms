<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use SoapClient;
use PonoRez\SGCForms\UtilityService;

final class SoapClientBuilder implements SoapClientFactory
{
    /**
     * Build a SOAP client for the configured WSDL.
     */
    public function build(): SoapClient
    {
        $wsdl = UtilityService::getSoapWsdl();

        return new SoapClient($wsdl, [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_MEMORY,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]),
            'user_agent' => 'PonoRezSGCForms/1.0',
        ]);
    }
}
