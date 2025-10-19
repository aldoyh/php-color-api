<?php
declare(strict_types=1);

namespace Frontify\ColorApi;

use PDO;
use PDOException;

/**
 * Simple PDO-backed Database helper.
 * - Supports SQLite by default
 * - Supports MySQL/MariaDB when DB_DRIVER (mysql/mariadb) is set
 * - Provides migration + seeding helper
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver = strtolower((string) getenv('DB_DRIVER') ?: 'sqlite');

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $host = getenv('DB_HOST') ?: '127.0.0.1';
                $port = getenv('DB_PORT') ?: '3306';
                $dbname = getenv('DB_DATABASE') ?: 'colors';
                $user = getenv('DB_USERNAME') ?: 'root';
                $pass = getenv('DB_PASSWORD') ?: '';

                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
                $opts = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                self::$pdo = new PDO($dsn, $user, $pass, $opts);
            } else {
                // default: sqlite
                $dbPath = getenv('DB_PATH') ?: __DIR__ . '/../data/colors.db';
                $dataDir = dirname($dbPath);
                if (!is_dir($dataDir)) {
                    mkdir($dataDir, 0755, true);
                }
                $dsn = 'sqlite:' . $dbPath;
                $opts = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                self::$pdo = new PDO($dsn, null, null, $opts);
            }
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw $e;
        }

        return self::$pdo;
    }

    /**
     * Run lightweight migrations and seed default data if needed.
     */
    public static function runMigrations(): void
    {
        $pdo = self::getConnection();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS colors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                hex TEXT NOT NULL UNIQUE,
                rgb TEXT,
                hsl TEXT,
                created DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            // MySQL compatible DDL
            $sql = "CREATE TABLE IF NOT EXISTS colors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                hex VARCHAR(7) NOT NULL UNIQUE,
                rgb VARCHAR(255),
                hsl VARCHAR(255),
                created DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        }

        $pdo->exec($sql);

        // seed default colors if table empty
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM colors')->fetchColumn();
            if ($count === 0) {
                self::seedDefaultColors($pdo);
            }
        } catch (PDOException $e) {
            // If table doesn't exist or other error, log and continue
            error_log('Migration/seed check failed: ' . $e->getMessage());
        }
    }

    private static function seedDefaultColors(PDO $pdo): void
    {
        // Prefer seeding from colors.json if present
        $colorsFile = __DIR__ . '/../colors.json';
        $colors = null;
        if (is_file($colorsFile)) {
            $json = @json_decode((string) file_get_contents($colorsFile), true);
            if (isset($json['colors']) && is_array($json['colors'])) {
                $colors = array_map(function ($c) {
                    return [
                        'name' => $c['name'] ?? null,
                        'hex' => $c['hex'] ?? null,
                    ];
                }, $json['colors']);
            }
        }

        if (empty($colors)) {
            $colors = [
                ['name' => 'Red', 'hex' => '#FF0000'],
                ['name' => 'Green', 'hex' => '#00FF00'],
                ['name' => 'Blue', 'hex' => '#0000FF'],
                ['name' => 'Yellow', 'hex' => '#FFFF00'],
                ['name' => 'Cyan', 'hex' => '#00FFFF'],
                ['name' => 'Magenta', 'hex' => '#FF00FF'],
                ['name' => 'White', 'hex' => '#FFFFFF'],
                ['name' => 'Black', 'hex' => '#000000'],
            ];
        }

        // Use ColorUtils to compute rgb/hsl strings when possible
        $utils = new ColorUtils();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $insertSql = 'INSERT OR IGNORE INTO colors (name, hex, rgb, hsl) VALUES (:name, :hex, :rgb, :hsl)';
        } else {
            // MySQL: use INSERT IGNORE to avoid duplicate hex errors during seeding
            $insertSql = 'INSERT IGNORE INTO colors (name, hex, rgb, hsl) VALUES (:name, :hex, :rgb, :hsl)';
        }
        $stmt = $pdo->prepare($insertSql);

        foreach ($colors as $c) {
            if (empty($c['hex']) || empty($c['name'])) {
                continue;
            }

            try {
                $hex = $utils->normalizeHex($c['hex']);
                if (!$utils->isValidHex($hex)) {
                    continue;
                }

                $rgbArr = $utils->hexToRgb($hex);
                $hslArr = $utils->rgbToHsl($rgbArr['r'], $rgbArr['g'], $rgbArr['b']);

                $rgbStr = sprintf('%d, %d, %d', $rgbArr['r'], $rgbArr['g'], $rgbArr['b']);
                $hslStr = sprintf('%.0f, %.1f%%, %.1f%%', $hslArr['h'], $hslArr['s'] * 100, $hslArr['l'] * 100);

                $stmt->execute([
                    ':name' => $c['name'],
                    ':hex' => $hex,
                    ':rgb' => $rgbStr,
                    ':hsl' => $hslStr,
                ]);
            } catch (PDOException $e) {
                error_log('Seed failed for ' . ($c['name'] ?? '') . ': ' . $e->getMessage());
            } catch (\Throwable $e) {
                error_log('Seed unexpected error: ' . $e->getMessage());
            }
        }
    }
}
