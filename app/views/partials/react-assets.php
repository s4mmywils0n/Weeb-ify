<?php
$viewRouteBase = isset($viewRouteBase) ? $viewRouteBase : '';
$projectRoot = dirname(__DIR__, 3);
$settingsFile = $projectRoot . '/.env';
$settings = is_file($settingsFile)
    ? (parse_ini_file($settingsFile, false, INI_SCANNER_RAW) ?: [])
    : [];
$useViteDevServer = strtolower($settings['APP_ENV'] ?? 'development') !== 'production';
$viteManifestFile = $projectRoot . '/public/build/.vite/manifest.json';
$viteManifest = [];
$viteEntry = null;

if (!$useViteDevServer && is_file($viteManifestFile)) {
    $viteManifest = json_decode((string) file_get_contents($viteManifestFile), true) ?: [];
    $viteEntry = $viteManifest['app/src/main.tsx'] ?? null;
}
?>
<?php if ($useViteDevServer): ?>
    <script type="module">
        import RefreshRuntime from 'http://localhost:5173/@react-refresh'
        RefreshRuntime.injectIntoGlobalHook(window)
        window.$RefreshReg$ = () => {}
        window.$RefreshSig$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
    </script>
    <script type="module" src="http://localhost:5173/@vite/client"></script>
    <script type="module" src="http://localhost:5173/app/src/main.tsx"></script>
<?php elseif (is_array($viteEntry) && !empty($viteEntry['file'])): ?>
    <?php foreach (($viteEntry['css'] ?? []) as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($viewRouteBase . '/build/' . $stylesheet, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <script type="module" src="<?= htmlspecialchars($viewRouteBase . '/build/' . $viteEntry['file'], ENT_QUOTES, 'UTF-8') ?>"></script>
<?php else: ?>
    <p class="app-error">React assets are missing. Run the production build and reload.</p>
<?php endif; ?>
