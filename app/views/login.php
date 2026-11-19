<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

// Set by AuthController::login() when the login fails
$error ??= null;
$old   ??= [];

$title = 'Log in — Weebi:fy';
$active = 'login';
require __DIR__ . '/partials/header.php';
?>

    <main class="auth-page">
        <section class="auth-card" aria-labelledby="login-title">
            <span class="eyebrow">Welcome back</span>
            <h1 id="login-title">Log <span>in.</span></h1>

            <?php if (($_GET['registered'] ?? '') === '1'): ?>
                <p class="auth-alert auth-alert-success" role="status">Your account was created. You can log in now.</p>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <p class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form class="auth-form" action="<?= BASE_URL ?>/login" method="post">
                <div class="form-field">
                    <label for="login">Email or username</label>
                    <input id="login" name="login" type="text" autocomplete="username" value="<?= htmlspecialchars($old['login'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-field">
                    <label for="password">Password</label>
                    <div class="password-row">
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                        <label class="show-password">
                            <input type="checkbox" data-toggle-password="password">
                            Show
                        </label>
                    </div>
                </div>

                <button class="auth-primary" type="submit">Log in</button>
            </form>

            <div class="auth-switch">
                <p>Don't have an account?</p>
                <a class="auth-secondary" href="<?= BASE_URL ?>/register">Sign up</a>
            </div>
        </section>
    </main>

    <script src="<?= BASE_URL ?>/assets/js/password-toggle.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
