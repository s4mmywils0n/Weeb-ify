<?php

declare(strict_types=1);

namespace App\Models;
use PDO;
use PDOException;
use App\Services\PDOService;

class AccountModel
{

private ?int $id;
private string $firstName;
private string $username;
private string $email;
private string $phoneNumber;
private string $password;
private string $createdAt;
protected PDO $pdo;



public function __construct(
    ?int $id = null,
    string $firstName = '',
    string $username = '',
    string $email = '',
    string $phoneNumber = '',
    string $password = '',
    string $createdAt = '',
    PDOService $db_Service
) {
    $this->id = $id;
    $this->firstName = $firstName;
    $this->username = $username;
    $this->email = $email;
    $this->phoneNumber = $phoneNumber;
    $this->password = $password;
    $this->createdAt = $createdAt;
    $this->pdo = $db_Service->getPDO();
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

public function Validate(string $firstName,string $username,string $email,string $phoneNumber,string $password): array
{
    $errors = [];

    if (preg_match('/\A[A-Za-z]{8,}\z/', $firstName) !== 1) {
        $errors[] = 'First name must be at least 8 characters long and contain only letters.';
    }
    else if ($firstName === '') {
        $errors[] = 'First name cannot be empty.';
    }

    if (preg_match('/\A(?=.*[A-Za-z]).{4,}\z/', $username) !== 1) {
        $errors[] = 'Username must be at least 4 characters long and contain at least one letter.';
    }else if ($username === '') {
        $errors[] = 'Username cannot be empty.';
    }

    if (preg_match('/\A[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\z/i', $email) !== 1) {
        $errors[] = 'Please provide a valid email address.';
    }else if ($email === '') {
        $errors[] = 'Email cannot be empty.';
    }

    if (preg_match('/\A(?=.*[A-Za-z])(?=.*\d).{8,}\z/', $password) !== 1) {
        $errors[] = 'Password must be at least 8 characters long and contain a letter and a number.';
    }else if ($password === '') {
        $errors[] = 'Password cannot be empty.';
    }

    return $errors;
}

public function createAccount(string $firstName, string $username, string $email, string $phoneNumber, string $password): void
{
    $this->firstName = $firstName;
    $this->username = $username;
    $this->email = $email;
    $this->phoneNumber = $phoneNumber;
    $this->password = password_hash($password, PASSWORD_DEFAULT);
    $this->createdAt = date('Y-m-d H:i:s');



    $pdoService = new PDOService($this->pdo);
    $pdo = $pdoService->getPDO();

    //gotta make it check to see if the database exists and probably not take multiple email accounts and usernames
    $sql = "INSERT INTO accounts (firstName, username, email, phoneNumber, password, createdAt)
            VALUES (:firstName, :username, :email, :phoneNumber, :password, :createdAt)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':firstName', $this->firstName);
    $stmt->bindParam(':username', $this->username);
    $stmt->bindParam(':email', $this->email);
    $stmt->bindParam(':phoneNumber', $this->phoneNumber);
    $stmt->bindParam(':password', $this->password);
    $stmt->bindParam(':createdAt', $this->createdAt);
    $stmt->execute();
}

public function fromArray(array $data): void
{
    $this->id = $data['id'] ?? null;
    $this->firstName = $data['firstName'] ?? '';
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
