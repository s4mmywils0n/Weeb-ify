<?php

//realized i need to get the damn connection for the db call each time so im just using this to make my life easier atp
namespace App\Services;
use PDO;
use PDOException;

 class PDOService
{
    private ?PDO  $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    //same shit as the db file idk man 
    public function connect(): void 
    {

    try{
        $envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    $host = $env['host'];
    $port = $env['port'];
    $database = $env['database'];
    $user = $env['user'];
    $password = $env['password'];
} else {
    die("Error: .env file not found.");
}
$dsn = "pgsql:host=$host;port=$port;dbname=$database";

$this->pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}
    }

    //THEORITACALLY THIS SHIT SHOULD WORK BUT I AM GONNA CRY IF IT FKN DOESNT
    public function getPDO(): PDO 
    {
        $this->connect();
        return $this->pdo;
    }
}