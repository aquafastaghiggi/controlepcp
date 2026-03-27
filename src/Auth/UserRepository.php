<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use PDO;

final class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
        $this->ensureTable();
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS usr_users ('
            . ' usr_id INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . ' usr_name VARCHAR(120) NOT NULL,'
            . ' usr_username VARCHAR(80) NOT NULL,'
            . ' usr_password_hash VARCHAR(255) NOT NULL,'
            . ' usr_active TINYINT(1) NOT NULL DEFAULT 1,'
            . ' usr_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . ' usr_last_login_at DATETIME NULL,'
            . ' PRIMARY KEY (usr_id),'
            . ' UNIQUE KEY uniq_usr_username (usr_username)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function countUsers(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM usr_users')->fetchColumn();
    }

    public function listUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT usr_id, usr_name, usr_username, usr_active, usr_created_at, usr_last_login_at'
            . ' FROM usr_users'
            . ' ORDER BY usr_active DESC, usr_name ASC, usr_id ASC'
        );

        return $stmt->fetchAll();
    }

    public function findActiveByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT usr_id, usr_name, usr_username, usr_password_hash, usr_active'
            . ' FROM usr_users'
            . ' WHERE usr_username = :username'
            . ' LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        if (!$row || (int) ($row['usr_active'] ?? 0) !== 1) {
            return null;
        }

        return $row;
    }

    public function createUser(string $name, string $username, string $password): int
    {
        $name = trim($name);
        $username = trim($username);

        if ($name === '' || $username === '' || $password === '') {
            throw new \InvalidArgumentException('Nome, usuário e senha são obrigatórios.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('Falha ao gerar hash da senha.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO usr_users (usr_name, usr_username, usr_password_hash, usr_active) VALUES (:name, :username, :hash, 1)'
        );
        $stmt->execute([
            'name' => $name,
            'username' => $username,
            'hash' => $hash,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->pdo->prepare('UPDATE usr_users SET usr_active = :active WHERE usr_id = :id');
        $stmt->execute([
            'active' => $active ? 1 : 0,
            'id' => $id,
        ]);
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE usr_users SET usr_last_login_at = NOW() WHERE usr_id = :id');
        $stmt->execute(['id' => $id]);
    }
}

