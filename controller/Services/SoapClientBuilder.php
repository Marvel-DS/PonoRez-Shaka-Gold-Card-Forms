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

        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ];

        $caBundle = UtilityService::getTrustedCaBundlePath();
        $disableVerification = UtilityService::shouldDisableSoapCertificateVerification();

        if ($disableVerification) {
            $sslOptions['verify_peer'] = false;
            $sslOptions['verify_peer_name'] = false;
            $sslOptions['allow_self_signed'] = true;
        } elseif ($caBundle !== null) {
            $sslOptions['cafile'] = $caBundle;
        }

        return new SoapClient($wsdl, [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_MEMORY,
            'stream_context' => stream_context_create([
                'ssl' => $sslOptions,
            ]),
            'user_agent' => 'PonoRezSGCForms/1.0',
        ]);
    }
}
