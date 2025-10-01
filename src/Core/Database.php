<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use Exception;

class Database
{
    private PDO $connection;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->connect();
    }

    private function connect(): void
    {
        $databaseUrl = $this->config->get('DATABASE_URL');
        
        if (empty($databaseUrl)) {
            throw new Exception('DATABASE_URL environment variable is required');
        }

        $parsedUrl = parse_url($databaseUrl);
        
        if ($parsedUrl === false) {
            throw new Exception('Invalid DATABASE_URL format');
        }

        $host = $parsedUrl['host'] ?? '';
        $port = $parsedUrl['port'] ?? 5432;
        $database = ltrim($parsedUrl['path'] ?? '', '/');
        $username = $parsedUrl['user'] ?? '';
        $password = $parsedUrl['pass'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode=require";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result === false ? null : $result;
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    public function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('Execute failed: ' . $e->getMessage());
        }
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            $result = $stmt->fetch();
            return (int)$result['id'];
        } catch (PDOException $e) {
            throw new Exception('Insert failed: ' . $e->getMessage());
        }
    }

    public function update(string $table, array $data, array $where): int
    {
        $setParts = array_map(fn($col) => $col . ' = :' . $col, array_keys($data));
        $whereParts = array_map(fn($col) => $col . ' = :where_' . $col, array_keys($where));
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        // Prefix where parameters to avoid conflicts
        $whereParams = [];
        foreach ($where as $key => $value) {
            $whereParams['where_' . $key] = $value;
        }

        $params = array_merge($data, $whereParams);

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('Update failed: ' . $e->getMessage());
        }
    }

    public function delete(string $table, array $where): int
    {
        $whereParts = array_map(fn($col) => $col . ' = :' . $col, array_keys($where));
        
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereParts)
        );

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($where);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception('Delete failed: ' . $e->getMessage());
        }
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    public function ping(): bool
    {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}