<?php
$viewApiBaseUrl = isset($viewApiBaseUrl) ? $viewApiBaseUrl : '/api.php';
$viewRouteBase = isset($viewRouteBase) ? $viewRouteBase : '';
$recordId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$recordId = $recordId === false || $recordId === null ? '' : (string) $recordId;
$pageNumber = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 1000000]]
);
$pageNumber = $pageNumber === false || $pageNumber === null ? 1 : $pageNumber;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title>Anime details — Weebi:fy</title>
</head>
<body>
    <div
        id="react-root"
        data-view="anime-id"
        data-api-base="<?= htmlspecialchars($viewApiBaseUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-route-base="<?= htmlspecialchars($viewRouteBase, ENT_QUOTES, 'UTF-8') ?>"
        data-record-id="<?= htmlspecialchars($recordId, ENT_QUOTES, 'UTF-8') ?>"
        data-page="<?= (int) $pageNumber ?>"
    ></div>
    <?php require __DIR__ . '/partials/react-assets.php'; ?>
</body>
</html>
