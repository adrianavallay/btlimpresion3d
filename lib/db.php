<?php
/**
 * Conexión PDO a MySQL. Las credenciales viven en el archivo .env
 * de la raíz del proyecto (ver .env.example). Lo genera install.php.
 */

declare(strict_types=1);

/** Carga y cachea las variables del .env como mapa CLAVE => valor. */
function env_all(): array
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = __DIR__ . '/../.env';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Falta el archivo .env. Ejecuta install.php o copia .env.example como .env.');
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            // Quitar comillas envolventes opcionales
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[0] === substr($v, -1)) {
                $v = substr($v, 1, -1);
            }
            $vars[trim($k)] = $v;
        }
    }
    return $vars;
}

function env(string $key, string $default = ''): string
{
    return env_all()[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME')
        );
        $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
