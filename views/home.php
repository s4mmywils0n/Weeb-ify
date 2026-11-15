<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171421">
    <title>Weebi:fy — Find your favorites</title>
    
    <link rel="stylesheet" href="site-chrome.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="home.php" aria-label="Weebi:fy home">Weebi<span>:fy</span></a>

        <nav class="primary-nav" aria-label="Main navigation">
            <a href="anime.php">Anime</a>
            <a href="manga.php">Manga</a>
            <a href="products.php">Products</a>
             <a href="favorites.php">Favorites</a>
        </nav>

        <form class="header-search" role="search" action="" method="get">
            <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none">
                <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input type="search" name="q" placeholder="Search your collection" aria-label="Search your collection">
        </form>

        <a class="login-link" href="../app/src/Login">Log in</a>
    </header>

    <main>
        <section class="welcome" aria-labelledby="welcome-title">
            <span class="eyebrow">Your collection, your way</span>
            <h1 id="welcome-title">Find your <span>favorites.</span></h1>
            <p>Keep track of the anime, manga, and collectibles that make your shelves feel like yours.</p>
            <a class="browse-link" href="anime.php">Explore anime <span aria-hidden="true">→</span></a>
        </section>
    </main>
    <footer class="site-footer">
        <p>Weebi:fy — Your collection, your way.</p>
        <nav class="footer-nav" aria-label="Footer navigation">
            <a href="anime.php">Anime</a>
            <a href="manga.php">Manga</a>
            <a href="products.php">Products</a>
             <a href="favorites.php">Favorites</a>
        </nav>
    </footer>
</body>
</html>
