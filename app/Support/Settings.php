<?php
/**
 * Réglages stockés en base (table settings) : clés d'API, liens boutiques, mode maintenance...
 * Tous les réglages sont chargés en une seule requête au premier accès.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Settings
{
    /** @var array<string, string>|null */
    private ?array $values = null;

    public function __construct(private Db $db)
    {
    }

    private function load(): array
    {
        if ($this->values === null) {
            $this->values = [];
            foreach ($this->db->all('SELECT name, value FROM {settings}') as $row) {
                $this->values[$row['name']] = (string) $row['value'];
            }
        }
        return $this->values;
    }

    public function get(string $name, string $default = ''): string
    {
        return $this->load()[$name] ?? $default;
    }

    public function bool(string $name): bool
    {
        return $this->get($name) === '1';
    }

    public function json(string $name, array $default = []): array
    {
        $value = $this->get($name);
        if ($value === '') {
            return $default;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public function set(string $name, string $value): void
    {
        $this->db->upsert('settings', ['value' => $value], ['name' => $name]);
        $this->load();
        $this->values[$name] = $value;
    }

    public function remove(string $name): void
    {
        $this->db->delete('settings', ['name' => $name]);
        $this->load();
        unset($this->values[$name]);
    }

    /** Oublie le cache (après une restauration par exemple). */
    public function reset(): void
    {
        $this->values = null;
    }
}
