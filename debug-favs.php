<?php
$env = parse_ini_file(__DIR__ . '/.env');
$pdo = new PDO(
    "pgsql:host={$env['host']};port={$env['port']};dbname={$env['database']}",
    $env['user'], $env['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "All favorites rows:\n";
$stmt = $pdo->query("SELECT * FROM public.favorites");
print_r($stmt->fetchAll());

echo "\nJoined query (user 4):\n";
$stmt = $pdo->prepare(
    "SELECT f.anime_id, a.id, a.title, a.image, a.episode_count, a.status, a.show_type
       FROM public.favorites f
       JOIN public.anime a ON a.anime_id = f.anime_id
      WHERE f.user_id = :u
      ORDER BY f.created_at DESC"
);
$stmt->execute([':u' => 4]);
print_r($stmt->fetchAll());