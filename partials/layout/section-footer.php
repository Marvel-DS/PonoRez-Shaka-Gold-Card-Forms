<?php

declare(strict_types=1);

$footerContext = $footerContext ?? [];

if (!is_array($footerContext) || $footerContext === []) {
    $resolvedSupplier = null;
    $resolvedSlug = null;

    if (isset($pageContext['supplier']) && is_array($pageContext['supplier'])) {
        $resolvedSupplier = $pageContext['supplier'];
        $resolvedSlug = $pageContext['supplierSlug'] ?? ($resolvedSupplier['supplierSlug'] ?? null);
    }

    if ($resolvedSupplier === null && isset($supplier) && is_array($supplier)) {
        $resolvedSupplier = $supplier;
        $resolvedSlug = $resolvedSlug ?? ($supplierSlug ?? ($supplier['supplierSlug'] ?? null));
    }

    if ($resolvedSupplier === null && isset($supplierConfig) && is_array($supplierConfig)) {
        $resolvedSupplier = $supplierConfig;
        $resolvedSlug = $resolvedSlug ?? ($supplierConfig['supplierSlug'] ?? null);
    }

    $footerContext = [
        'supplier' => $resolvedSupplier ?? [],
        'supplierSlug' => $resolvedSlug,
    ];
}

include dirname(__DIR__) . '/shared/component-supplier-footer.php';

unset($footerContext);
