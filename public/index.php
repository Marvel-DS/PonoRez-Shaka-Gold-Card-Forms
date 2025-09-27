<?php

declare(strict_types=1);

require dirname(__DIR__) . '/controller/Setup.php';

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SGC Forms Bootstrap Check</title>
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center">
  <main class="p-6 rounded-xl shadow-lg bg-white text-center space-y-4">
    <h1 class="text-2xl font-semibold text-slate-800">SGC Forms Application</h1>
    <p class="text-slate-600">Bootstrap successful. Assets compiled and ready.</p>
    <button class="px-4 py-2 rounded-lg bg-blue-600 text-white font-medium">Book Now</button>
  </main>
  <script type="module" src="/assets/js/main.js"></script>
</body>
</html>
