<?php

declare(strict_types=1);

$errorMessage = $errorMessage ?? 'Unable to load the requested booking page.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SGC Forms &mdash; Not Found</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center">
    <main class="max-w-xl mx-auto p-8 bg-white rounded-xl shadow-lg text-center space-y-4">
        <h1 class="text-2xl font-semibold text-slate-800">Configuration not found</h1>
        <p class="text-slate-600"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="inline-flex items-center justify-center px-4 py-2 font-medium text-white bg-blue-600 rounded-lg" href="/">
            Back to home
        </a>
    </main>
</body>
</html>
