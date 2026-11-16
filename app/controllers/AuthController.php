<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AccountModel;
use App\Services\PDOService;
use Throwable;

class AuthController
{
    // POST /login
    public function login(): void
    {
        $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $error = null;
        $userId = null;

        if ($login === '' || $password === '') {
            $error = 'Please enter your email or username and your password.';
        } else {
            try {
                $user = (new AccountModel(new PDOService()))->findByLogin($login);

                // Same message whether the user doesn't exist or the password is wrong,
                // so nobody can use this form to find out which accounts exist.
                if ($user === null || !password_verify($password, (string) $user['password'])) {
                    $error = 'Incorrect email/username or password.';
                } else {
                    $userId = (int) $user['id'];
                }
            } catch (Throwable $e) {
                error_log('Login failed: ' . $e->getMessage());
                $error = 'Something went wrong. Please try again.';
            }
        }

        if ($userId !== null) {
            // New session id on login (stops session-fixation attacks)
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;

            // Back to the page that asked them to log in, otherwise the main page
            $intended = $_SESSION['intended'] ?? '/';
            unset($_SESSION['intended']);
            redirect(isSafeLocalPath($intended) ? $intended : '/');
        }

        http_response_code(422);
        view('login', ['error' => $error, 'old' => ['login' => $login]]);
    }

    // POST /logout
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $cookie['path'],
                'domain'   => $cookie['domain'],
                'secure'   => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?: 'Lax',
            ]);
        }
        session_destroy();

        redirect('/');
    }

    // POST /register
    public function register(): void
    {
        $text = fn(string $key): string => is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';

        // Values typed into the form (the password is never sent back to the page)
        $old = [
            'firstName'   => $text('first_name'),
            'username'    => $text('username'),
            'email'       => $text('email'),
            'phoneNumber' => $text('phone'),
        ];
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        $account = new AccountModel(new PDOService());
        $errors = $account->Validate(
            $old['firstName'],
            $old['username'],
            $old['email'],
            $old['phoneNumber'],
            $password
        );

        $created = false;

        try {
            // Only look up names that passed validation
            if (!isset($errors['username']) && $account->isUsernameTaken($old['username'])) {
                $errors['username'] = 'Username is already being used';
            }
            if (!isset($errors['email']) && $account->isEmailTaken($old['email'])) {
                $errors['email'] = 'Email account is already being used';
            }

            if ($errors === []) {
                $account->createAccount(
                    $old['firstName'],
                    $old['username'],
                    $old['email'],
                    $old['phoneNumber'],
                    $password
                );
                $created = true;
            }
        } catch (Throwable $e) {
            // Log the real problem for developers, show a safe message to the user
            error_log('Register failed: ' . $e->getMessage());
            $errors['form'] = 'Something went wrong. Please try again.';
        }

        if ($created) {
            redirect('/login?registered=1');
        }

        http_response_code(422);
        view('register', ['errors' => $errors, 'old' => $old]);
    }
}
