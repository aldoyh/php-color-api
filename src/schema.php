<?php

declare(strict_types=1);

namespace Frontify\ColorApi;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

// Use PDO-based Database helper (migrations + seeding handled there)
require_once __DIR__ . '/Database.php';

// Run migrations and seed default colors (no-op if DB already initialized)
\Frontify\ColorApi\Database::runMigrations();

require_once __DIR__ . '/query.php';
require_once __DIR__ . '/mutation.php';

/**
 * Compatibility wrapper used by existing code. Returns a PDO instance.
 */
function getDatabase() {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = \Frontify\ColorApi\Database::getConnection();
    }

    return $pdo;
}

function sql($query, $params = []) {
    try {
        $pdo = getDatabase();

        if (empty($params)) {
            $stmt = $pdo->query($query);
            if ($stmt === false) {
                return ['success' => false, 'error' => 'Query failed'];
            }

            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $data];
        }

        $stmt = $pdo->prepare($query);
        if ($stmt === false) {
            return ['success' => false, 'error' => 'Failed to prepare statement'];
        }

        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }

        $executed = $stmt->execute();
        if (!$executed) {
            return ['success' => false, 'error' => implode(' ', $stmt->errorInfo())];
        }

        $resultData = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($resultData) {
            return ['success' => true, 'data' => $resultData];
        }

        return ['success' => true, 'id' => $pdo->lastInsertId(), 'changes' => $stmt->rowCount()];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

$schema = new Schema([
    'query' => $queryType,
    'mutation' => $mutationType,
    'debug' => true,
]);

return $schema;
