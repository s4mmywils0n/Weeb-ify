<?php
/*
 * Shared site footer + closing tags. Include it at the very end of every view:
 *
 *   require __DIR__ . '/partials/footer.php';
 *
 * Put page <script> blocks before this require.
 */
if (!defined('BASE_URL')) { http_response_code(404); exit; }
?>
    <footer class="site-footer">
        <p>Weebi:fy — Your collection, your way.</p>
        <nav class="footer-nav" aria-label="Footer navigation">
            <a href="<?= BASE_URL ?>/anime">Anime</a>
            <a href="<?= BASE_URL ?>/manga">Manga</a>
            <a href="<?= BASE_URL ?>/products">Products</a>
            <a href="<?= BASE_URL ?>/favorites">Favorites</a>
        </nav>
    </footer>
</body>
</html>
