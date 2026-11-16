<?php
// Only reachable through public/index.php (the router)
if (!defined('BASE_URL')) { http_response_code(404); exit; }

// Set by AuthController::register() when the form comes back with problems
$errors ??= [];   // e.g. ['username' => 'Username is already being used']
$old    ??= [];   // what the user typed, so they don't have to retype it

$value = fn(string $field): string => htmlspecialchars($old[$field] ?? '', ENT_QUOTES, 'UTF-8');
$invalid = fn(string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="' . $field . '-error"'
    : '';
$error = function (string $field) use ($errors): string {
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="form-error" id="' . $field . '-error">'
        . htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') . '</p>';
};

$title = 'Sign up — Weebi:fy';
$active = 'register';
require __DIR__ . '/partials/header.php';
?>

    <main class="auth-page">
        <section class="auth-card" aria-labelledby="register-title">
            <span class="eyebrow">Start your collection</span>
            <h1 id="register-title">Sign <span>up.</span></h1>

            <?php if (isset($errors['form'])): ?>
                <p class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form class="auth-form" action="<?= BASE_URL ?>/register" method="post" novalidate>
                <div class="form-field">
                    <label for="firstName">First name</label>
                    <input id="firstName" name="first_name" type="text" autocomplete="given-name" value="<?= $value('firstName') ?>" required<?= $invalid('firstName') ?>>
                    <?= $error('firstName') ?>
                </div>

                <div class="form-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" autocomplete="email" value="<?= $value('email') ?>" required<?= $invalid('email') ?>>
                    <?= $error('email') ?>
                </div>

                <div class="form-field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" autocomplete="username" value="<?= $value('username') ?>" required<?= $invalid('username') ?>>
                    <?= $error('username') ?>
                </div>

                <div class="form-field">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= $value('phoneNumber') ?>">
                </div>

                <div class="form-field">
                    <label for="password">Password</label>
                    <div class="password-row">
                        <input id="password" name="password" type="password" autocomplete="new-password" required<?= $invalid('password') ?>>
                        <label class="show-password">
                            <input type="checkbox" data-toggle-password="password">
                            Show
                        </label>
                    </div>
                    <?= $error('password') ?>
                </div>

                <button class="auth-primary" type="submit">Sign up</button>
            </form>

            <div class="auth-switch">
                <p>Already have an account?</p>
                <a class="auth-secondary" href="<?= BASE_URL ?>/login">Log in</a>
            </div>
        </section>
    </main>

    <script src="<?= BASE_URL ?>/assets/js/password-toggle.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
