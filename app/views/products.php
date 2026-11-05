<?php
$viewApiBaseUrl = isset($viewApiBaseUrl) ? $viewApiBaseUrl : '/api.php';
$viewRouteBase = isset($viewRouteBase) ? $viewRouteBase : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title>Products — Weebi:fy</title>
</head>
<body>
    <div
        id="react-root"
        data-view="products"
        data-api-base="<?= htmlspecialchars($viewApiBaseUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-route-base="<?= htmlspecialchars($viewRouteBase, ENT_QUOTES, 'UTF-8') ?>"
        data-page="1"
    ></div>
    <?php require __DIR__ . '/partials/react-assets.php'; ?>
</body>
</html>
