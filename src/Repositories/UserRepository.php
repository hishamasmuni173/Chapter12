<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :email'
        );
        $stmt->execute([':email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, role, created_at FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $name, string $email, string $hash, string $role = 'member'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (:n,:e,:h,:r)'
        );
        $stmt->execute([
            ':n' => trim($name),
            ':e' => mb_strtolower(trim($email)),
            ':h' => $hash,
            ':r' => $role,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :e');
        $stmt->execute([':e' => mb_strtolower(trim($email))]);
        return (bool)$stmt->fetchColumn();
    }
}
