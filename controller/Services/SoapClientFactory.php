<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use SoapClient;

interface SoapClientFactory
{
    public function build(): SoapClient;
}
