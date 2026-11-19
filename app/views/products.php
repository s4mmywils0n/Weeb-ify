<?php
if (!defined('BASE_URL')) { http_response_code(404); exit; }

$title = 'Products — Weebi:fy';
$active = 'products';

require __DIR__ . '/partials/header.php';
?>

<main>
  <section class="coming-soon" aria-labelledby="products-coming-soon">
    <div class="coming-soon-sign">
      <div class="coming-soon-icon" aria-hidden="true">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none">
          <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
          <path d="m4 7.5 8 4.5 8-4.5M12 12v9" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>
      </div>
      <span class="eyebrow">Weebi:fy Shop</span>
      <h1 id="products-coming-soon">Coming <span>Soon</span></h1>
      <p>We are putting together a collection of anime and manga merch. Check back soon for new releases.</p>
    </div>
  </section>
</main>

<?php require __DIR__.'/partials/footer.php'; ?>