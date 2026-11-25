<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Services\PDOService;

/*
 * Supabase table: public."user"
 *   id (int8, identity) | first_name | username | email | "phoneNumber" | password | "createdAt"
 *
 * "user" is a reserved word in Postgres and phoneNumber/createdAt are camelCase,
 * so those names must be written in double quotes in SQL.
 */
class AccountModel
{
    private ?int $id;
    private string $firstName;
    private string $username;
    private string $email;
    private string $phoneNumber;
    private string $password;
    private string $createdAt;
    private PDOService $dbService;

    public function __construct(
        PDOService $dbService,
        ?int $id = null,
        string $firstName = '',
        string $username = '',
        string $email = '',
        string $phoneNumber = '',
        string $password = '',
        string $createdAt = ''
    ) {
        $this->dbService = $dbService;
        $this->id = $id;
        $this->firstName = $firstName;
        $this->username = $username;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->password = $password;
        $this->createdAt = $createdAt;
    }

    // Connects only when a query actually runs (validation alone never touches the database).
    private function pdo(): PDO
    {
        return $this->dbService->getPDO();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * Returns errors keyed by field ('firstName', 'username', 'email', 'password').
     * An empty array means everything is valid.
     */
    public function Validate(string $firstName, string $username, string $email, string $phoneNumber, string $password): array
    {
        $errors = [];

        // Letters only (\p{L} also allows accented letters such as é or ñ)
        if ($firstName === '') {
            $errors['firstName'] = 'First name cannot be empty.';
        } elseif (preg_match('/\A\p{L}+\z/u', $firstName) !== 1) {
            $errors['firstName'] = 'First name can only contain letters.';
        }

        if (preg_match('/\A(?=.*[A-Za-z]).{4,}\z/', $username) !== 1) {
            $errors['username'] = 'Username must be at least 4 characters long and contain at least one letter.';
        }

        if (preg_match('/\A[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\z/i', $email) !== 1) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (preg_match('/\A(?=.*[A-Za-z])(?=.*\d).{8,}\z/', $password) !== 1) {
            $errors['password'] = 'Password must be at least 8 characters long and contain a letter and a number.';
        }

        return $errors;
    }

    // Case-insensitive, so "Cyril" and "cyril" count as the same username.
    public function isUsernameTaken(string $username): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1 FROM public."user" WHERE LOWER(username) = LOWER(:username) LIMIT 1'
        );
        $stmt->execute([':username' => $username]);
        return $stmt->fetchColumn() !== false;
    }

    public function isEmailTaken(string $email): bool
    {
        $stmt = $this->pdo()->prepare(
            'SELECT 1 FROM public."user" WHERE LOWER(email) = LOWER(:email) LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Find a user by email OR username (case-insensitive).
     * Returns the row (id, username, email, password hash) or null if nobody matches.
     */
    public function findByLogin(string $login): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, username, email, password
             FROM public."user"
             WHERE LOWER(email) = LOWER(:email) OR LOWER(username) = LOWER(:username)
             LIMIT 1'
        );
        $stmt->execute([':email' => $login, ':username' => $login]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Is this user an admin? Always read from the database (not the session), so taking
     * admin away from someone takes effect on their very next request.
     * Unknown user ids are simply not admins.
     */
    public function isAdmin(int $userId): bool
    {
        $stmt = $this->pdo()->prepare('SELECT is_admin FROM public."user" WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        return filter_var($stmt->fetchColumn(), FILTER_VALIDATE_BOOLEAN);
    }

    public function createAccount(string $firstName, string $username, string $email, string $phoneNumber, string $password): void
    {
        $this->firstName = $firstName;
        $this->username = $username;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        $this->createdAt = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO public."user" (first_name, username, email, "phoneNumber", password, "createdAt")
                VALUES (:firstName, :username, :email, :phoneNumber, :password, :createdAt)
                RETURNING id';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':firstName'   => $this->firstName,
            ':username'    => $this->username,
            ':email'       => $this->email,
            ':phoneNumber' => $this->phoneNumber !== '' ? $this->phoneNumber : null,
            ':password'    => $this->password,
            ':createdAt'   => $this->createdAt,
        ]);

        $this->id = (int) $stmt->fetchColumn();
    }

    public function fromArray(array $data): void
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->firstName = $data['first_name'] ?? '';
        $this->username = $data['username'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->phoneNumber = $data['phoneNumber'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->createdAt = $data['createdAt'] ?? '';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'username' => $this->username,
            'email' => $this->email,
            'phoneNumber' => $this->phoneNumber,
            'password' => $this->password,
            'createdAt' => $this->createdAt,
        ];
    }
}
