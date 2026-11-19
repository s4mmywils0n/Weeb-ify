<?php

declare(strict_types=1);

// One place to get the database connection, so every model doesn't repeat the .env code.
namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

class PDOService
{
    private ?PDO $pdo;

    // Pass a PDO in if you already have one; otherwise it connects on first use.
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Connect using the project-root .env (kept out of git for security).
     *
     * @throws RuntimeException when the .env file is missing
     * @throws PDOException     when the database refuses the connection
     */
    public function connect(): void
    {
        // app/Services -> project root
        $envPath = dirname(__DIR__, 2) . '/.env';

        if (!is_file($envPath)) {
            throw new RuntimeException('.env file not found in the project root.');
        }

        $env = parse_ini_file($envPath, false, INI_SCANNER_RAW) ?: [];

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $env['host'] ?? '',
            $env['port'] ?? '5432',
            $env['database'] ?? ''
        );

        $this->pdo = new PDO($dsn, $env['user'] ?? '', $env['password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // true: works with Supabase's connection pooler (see the note in api.php)
            PDO::ATTR_EMULATE_PREPARES   => true,
        ]);
    }

    // Reuses the same connection instead of reconnecting on every call.
    public function getPDO(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }
}
