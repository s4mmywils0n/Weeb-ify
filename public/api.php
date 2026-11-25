<?php
// For the PHP built-in server (php -S ... -t public): public/ is the web root there,
// so /api.php has to exist inside it. The real API is api.php in the project root.
require dirname(__DIR__) . '/api.php';
