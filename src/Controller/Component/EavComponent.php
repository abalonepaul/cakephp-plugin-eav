<?php
declare(strict_types=1);

namespace Eav\Controller\Component;

use Cake\Cache\Cache;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Core\Plugin;

class EavComponent extends Component
{
    /**
     * In-memory cache for the parsed eav.json for the current request.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $configData = null;

    /**
     * Load plugin config (plugins/Eav/config/eav.json) with app cache and per-request memoization.
     *
     * @return array<string,mixed>
     */
    protected function loadConfig(): array
    {
        if ($this->configData !== null) {
            return $this->configData;
        }

        $this->configData = Cache::remember('Eav.eav_config', function () {
            $path = Plugin::path('Eav') . 'config' . DIRECTORY_SEPARATOR . 'eav.json';
            if (!is_file($path)) {
                return [];
            }
            $json = @file_get_contents($path);
            if ($json === false) {
                return [];
            }
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        });

        return $this->configData;
    }

    /**
     * Return configured data type options for Attribute forms.
     * Fallback order: eav.json "types" -> Configure('Eav.defaultTypes') -> repo defaults.
     *
     * @return array<string,string>
     */
    public function getDataTypeOptions(): array
    {
        $cfg = $this->loadConfig();

        // Prefer eav.json "types"
        $types = [];
        if (isset($cfg['types']) && is_array($cfg['types'])) {
            $types = $cfg['types'];
        }

        // Allow overriding defaults via Configure in plugin bootstrap if desired
        if ($types === []) {
            $types = (array)(Configure::read('Eav.defaultTypes') ?: []);
        }

        // Hard fallback to the repository defaults (from eav.json in the repo)
        if ($types === []) {
            $types = [
                'string',
                'text',
                'integer',
                'smallinteger',
                'tinyinteger',
                'biginteger',
                'decimal',
                'float',
                'boolean',
                'date',
                'datetime',
                'time',
                'json',
                'uuid',
                'binaryuuid',
                'nativeuuid',
                'fk',
            ];
        }

        // Normalize to options-style (value => label)
        $types = array_values(array_unique(array_map('strval', $types)));
        return array_combine($types, $types);
    }

    /**
     * Optional admin route prefix for link URL arrays.
     *
     * @return string|null
     */
    public function getAdminPrefix(): ?string
    {
        $cfg = $this->loadConfig();
        $prefix = $cfg['adminPrefix'] ?? null;

        return (is_string($prefix) && $prefix !== '') ? $prefix : null;
    }
}
