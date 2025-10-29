<?php

declare(strict_types=1);
?>
<div class="sgc-overlay fixed inset-0 z-50 hidden" data-overlay="checkout" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/70" data-overlay-backdrop></div>
    <div class="relative flex h-full w-full items-start justify-center overflow-y-auto px-4 py-8">
        <div class="relative flex h-full w-full max-w-6xl flex-1 flex-col overflow-hidden rounded-2xl shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="checkout-overlay-title">
            <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white/95 px-6 py-4 backdrop-blur">
                <div>
                    <h2 id="checkout-overlay-title" class="text-lg font-semibold text-slate-900">Shaka Gold Card Checkout</h2>
                </div>
                <button type="button"
                        class="inline-flex h-9 w-9 items-center justify-center text-slate-600 font-semibold"
                        data-overlay-close
                        aria-label="Close checkout window">
                    &times;
                </button>
            </header>

            <div class="relative flex-1">
                <iframe data-overlay-frame title="Ponorez checkout" class=" size-full border-0" loading="lazy"></iframe>

                <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/95 text-center" data-overlay-loading hidden>
                    <span class="h-6 w-6 animate-spin rounded-full border-2 border-slate-200 border-t-[var(--sgc-brand-primary)]"></span>
                    <p class="text-sm text-slate-600">Preparing checkout...</p>
                </div>

                <div class="absolute inset-x-0 bottom-0 px-6 pb-6 hidden" data-overlay-message>
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-overlay-error hidden></div>
                    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700" data-overlay-success hidden></div>
                </div>
            </div>
        </div>
    </div>
</div>
