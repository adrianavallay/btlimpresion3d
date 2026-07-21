<?php
/**
 * Helpers de contenido para la web pública.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Escapa HTML. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Escapa y convierte *texto* en <em>texto</em> (acento itálico del diseño). */
function fmt_em(?string $s): string
{
    return preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e($s));
}

/** Escapa y convierte saltos de línea en <br>. */
function fmt_nl(?string $s): string
{
    return nl2br(e($s), false);
}

/** Todas las settings como mapa clave => valor. */
function settings(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
            // Los seeds usan \n literal para saltos de línea
            $map[$row['skey']] = str_replace('\\n', "\n", $row['svalue']);
        }
    }
    return $map;
}

/** Una setting concreta. */
function setting(string $key, string $default = ''): string
{
    return settings()[$key] ?? $default;
}

function fetch_services(): array
{
    return db()->query('SELECT * FROM services ORDER BY position, id')->fetchAll();
}

function fetch_projects(bool $onlyVisible = true): array
{
    $sql = 'SELECT * FROM projects' . ($onlyVisible ? ' WHERE visible = 1' : '') . ' ORDER BY position, id';
    return db()->query($sql)->fetchAll();
}

function fetch_reviews(bool $onlyVisible = true): array
{
    $sql = 'SELECT * FROM reviews' . ($onlyVisible ? ' WHERE visible = 1' : '') . ' ORDER BY position, id';
    return db()->query($sql)->fetchAll();
}
