<?php

declare(strict_types=1);

namespace Stratum\Core;

use PDO;
use PDOStatement;

final class Database
{
    private PDO $pdo;
    private string $prefix;

    public function __construct(Config $config)
    {
        $this->prefix = $config->dbPrefix();
        $this->pdo = new PDO(
            $config->dbDsn(),
            $config->get('DB_USERNAME'),
            $config->get('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    /** Resolves a bare table name (no prefix) to its prefixed form, e.g. 'users' -> 'strat_users'. */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /** @param array<string, mixed> $params */
    public function prepare(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->prepare($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->prepare($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->prepare($sql, $params)->rowCount();
    }

    /**
     * @param array<string, mixed> $columns
     */
    public function insert(string $table, array $columns): string
    {
        $fields = array_keys($columns);
        $quotedFields = array_map(static fn (string $field): string => '`' . $field . '`', $fields);
        $placeholders = array_map(static fn (string $field): string => ':' . $field, $fields);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table($table),
            implode(', ', $quotedFields),
            implode(', ', $placeholders)
        );

        $this->prepare($sql, $columns);

        return $this->pdo->lastInsertId();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
