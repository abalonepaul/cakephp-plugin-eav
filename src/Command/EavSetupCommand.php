<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Database\Driver\Postgres;
use Cake\Database\TypeFactory;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Cake\Console\ConsoleOptionParser;

class EavSetupCommand extends Command
{
    /** @var array<string,string> */
    protected array $typeAliases = [
        'bool' => 'boolean',
        'int' => 'integer',
        'smallint' => 'smallinteger',
        'bigint' => 'biginteger',
        'tinyint' => 'tinyinteger',
        'double' => 'float',
        'timestamp' => 'datetime',
        'varchar' => 'string',
        'jsonb' => 'json',
        // Back-compat aliases for unified FK type
        'fk_uuid' => 'fk',
        'fk_int' => 'fk',
    ];

    /** @var array<string> */
    protected array $customTypes = [
        // Unified FK custom type, single table eav_fk
        'fk',
    ];

    /** @var array<string,array{type:string,options:array<string,mixed>}> */
    protected array $typeMap = [
        'string' => ['type' => 'string', 'options' => ['limit' => 1024]],
        'char' => ['type' => 'char', 'options' => ['limit' => 255]],
        'text' => ['type' => 'text', 'options' => []],
        'uuid' => ['type' => 'uuid', 'options' => []],
        'binaryuuid' => ['type' => 'binaryuuid', 'options' => []],
        'nativeuuid' => ['type' => 'nativeuuid', 'options' => []],
        'integer' => ['type' => 'integer', 'options' => []],
        'smallinteger' => ['type' => 'smallinteger', 'options' => []],
        'tinyinteger' => ['type' => 'tinyinteger', 'options' => []],
        'biginteger' => ['type' => 'biginteger', 'options' => []],
        'float' => ['type' => 'float', 'options' => []],
        'decimal' => ['type' => 'decimal', 'options' => ['precision' => 18, 'scale' => 6]],
        'boolean' => ['type' => 'boolean', 'options' => []],
        'binary' => ['type' => 'binary', 'options' => []],
        'date' => ['type' => 'date', 'options' => []],
        'datetime' => ['type' => 'datetime', 'options' => []],
        'datetimefractional' => ['type' => 'datetime', 'options' => []],
        'timestamp' => ['type' => 'timestamp', 'options' => []],
        'timestampfractional' => ['type' => 'timestamp', 'options' => []],
        'timestamptimezone' => ['type' => 'timestamp', 'options' => []],
        'time' => ['type' => 'time', 'options' => []],
        'json' => ['type' => 'json', 'options' => []],
        'enum' => ['type' => 'string', 'options' => ['limit' => 255]],
        'geometry' => ['type' => 'geometry', 'options' => []],
        'point' => ['type' => 'point', 'options' => []],
        'linestring' => ['type' => 'linestring', 'options' => []],
        'polygon' => ['type' => 'polygon', 'options' => []],
        // Note: 'fk' is handled specially in buildMigration based on pk family
    ];

    /**
     * Generate a migration for EAV schema based on configuration.
     *
     * @param Arguments $args Arguments.
     * @param ConsoleIo $io Console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        // Interactive auto-launch behavior (Approach B)
        $explicitInteractive = (bool)$args->getOption('interactive');
        $explicitNoInteractive = (bool)$args->getOption('no-interactive');
        $configPathOpt = (string)($args->getOption('config') ?? '');

        if ($explicitInteractive && !$explicitNoInteractive) {
            // Delegate to the wizard
            $io->out('Launching interactive setup wizard ...');
            $wizard = new EavSetupInteractiveCommand();
            return $wizard->runInteractive($io);
        }

        // "Magic" launch when invoked with no options and no --no-interactive
        if (!$explicitNoInteractive && !$explicitInteractive && $configPathOpt === '') {
            $argv = $_SERVER['argv'] ?? [];
            $passedOptions = 0;
            foreach ($argv as $a) {
                if (is_string($a) && str_starts_with($a, '--')) {
                    // ignore explicit interactive toggles in the count
                    if ($a === '--interactive' || $a === '--no-interactive') {
                        continue;
                    }
                    $passedOptions++;
                }
            }
            if ($passedOptions === 0) {
                $io->out('Launching interactive setup wizard (use --no-interactive to run non-interactively).');
                $wizard = new EavSetupInteractiveCommand();
                return $wizard->runInteractive($io);
            }
        }

        // Defaults from CLI
        $pkType = strtolower((string)$args->getOption('pk-type') ?: 'uuid');
        $uuidType = strtolower((string)$args->getOption('uuid-type') ?: 'uuid');
        $jsonStorage = strtolower((string)$args->getOption('json-storage') ?: 'json');
        $connectionName = (string)($args->getOption('connection') ?: 'default');
        $dryRun = (bool)$args->getOption('dry-run');
        $migrationName = (string)($args->getOption('name') ?: 'EavSetup');

        // Optional: load config JSON (--config)
        if ($configPathOpt !== '') {
            $path = $configPathOpt;
            if (!is_file($path)) {
                $io->err('Config file not found: ' . $path);
                return CommandInterface::CODE_ERROR;
            }
            $json = file_get_contents($path);
            if ($json === false) {
                $io->err('Unable to read config file: ' . $path);
                return CommandInterface::CODE_ERROR;
            }
            $cfg = json_decode($json, true);
            if (!is_array($cfg)) {
                $io->err('Invalid JSON in config file: ' . $path);
                return CommandInterface::CODE_ERROR;
            }

            // Use config as the source of truth for non-interactive run
            $connectionName = (string)($cfg['connection'] ?? $connectionName);
            $pkType = strtolower((string)($cfg['pkType'] ?? $pkType));
            $uuidType = strtolower((string)($cfg['uuidType'] ?? $uuidType));
            $jsonStorage = strtolower((string)($cfg['jsonAttributeStorage'] ?? $jsonStorage));
            $migrationName = (string)($cfg['migrationName'] ?? $migrationName);
        }

        // Validate core choices
        if (!in_array($pkType, ['uuid', 'int'], true)) {
            $io->err('pk-type must be uuid or int.');
            return CommandInterface::CODE_ERROR;
        }
        if (!in_array($uuidType, ['uuid', 'binaryuuid', 'nativeuuid'], true)) {
            $io->err('uuid-type must be uuid, binaryuuid, or nativeuuid.');
            return CommandInterface::CODE_ERROR;
        }
        if (!in_array($jsonStorage, ['json', 'jsonb'], true)) {
            $io->err('json-storage must be json or jsonb.');
            return CommandInterface::CODE_ERROR;
        }

        // Resolve connection/driver and guard jsonb when not Postgres
        $connection = ConnectionManager::get($connectionName);
        $driver = $connection->getDriver();
        if ($jsonStorage === 'jsonb' && !$driver instanceof Postgres) {
            $io->out('JSONB storage requested, but adapter is not Postgres. Falling back to json.');
            $jsonStorage = 'json';
        }

        // Resolve selected types (CLI or config)
        $types = [];
        if ($configPathOpt !== '') {
            $json = file_get_contents($configPathOpt);
            $cfg = $json !== false ? json_decode((string)$json, true) : null;
            if (is_array($cfg) && isset($cfg['types']) && is_array($cfg['types'])) {
                // Use types from config as-is (assumed normalized by the wizard)
                $types = array_values(array_unique(array_map(fn($t) => strtolower((string)$t), $cfg['types'])));
            }
        }
        if ($types === []) {
            $typesArg = (string)($args->getOption('types') ?? 'defaults');
            $types = $this->resolveSelectedTypes($typesArg);
        }

        // Build migration payload
        $payload = $this->buildMigration(
            $migrationName,
            $pkType,
            $uuidType,
            $jsonStorage,
            $types,
        );

        // Stamp a header summarizing the selections
        $header = "/**\n"
            . " * EAV Setup Migration\n"
            . " * connection: {$connectionName}\n"
            . " * driver: " . get_class($driver) . "\n"
            . " * pkType: {$pkType}\n"
            . " * uuidType: {$uuidType}\n"
            . " * jsonAttributeStorage: {$jsonStorage}\n"
            . " * types: " . implode(',', $types) . "\n"
            . " * generatedAt: " . gmdate('c') . "\n"
            . " */\n\n";

        // Insert header after declare(strict_types=1);
        $payload = str_replace("declare(strict_types=1);\n\n", "declare(strict_types=1);\n\n" . $header, $payload);

        $path = $this->migrationPath();
        $fileName = $this->nextMigrationFilename($path, $migrationName);

        if ($dryRun) {
            $io->out('Dry run - migration not written.');
            $io->out('Target: ' . $fileName);
            $io->out($payload);
            return CommandInterface::CODE_SUCCESS;
        }

        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            $io->err('Unable to create migrations directory: ' . $path);
            return CommandInterface::CODE_ERROR;
        }

        if (file_put_contents($fileName, $payload) === false) {
            $io->err('Unable to write migration file: ' . $fileName);
            return CommandInterface::CODE_ERROR;
        }

        $io->out('Migration written: ' . $fileName);
        $io->out('Run: bin/cake migrations migrate -p Eav -c ' . $connectionName);

        return CommandInterface::CODE_SUCCESS;
    }

    /**
     * Resolve supported types from TypeFactory plus custom types based on selection.
     *
     * @param string $typesArg defaults|all|csv
     * @return array<string>
     */
    public function resolveSelectedTypes(string $typesArg): array
    {
        // Defaults (pre-selected)
        $defaults = [
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

        // All = union of known map types + custom 'fk'
        $all = array_values(array_unique(array_merge(array_keys($this->typeMap), ['fk'])));

        $arg = strtolower(trim((string)$typesArg));
        if ($arg === '' || $arg === 'defaults') {
            return $defaults;
        }
        if ($arg === 'all') {
            return $all;
        }

        // CSV selection with alias normalization
        $parts = array_filter(array_map('trim', explode(',', $typesArg)));
        $normalized = [];
        foreach ($parts as $t) {
            $tRaw = strtolower($t);
            $alias = $this->typeAliases[$tRaw] ?? $tRaw;
            $normalized[] = $alias;
        }
        $normalized = array_values(array_unique($normalized));

        // Validate: accept custom 'fk', typeMap keys, or anything resolvable by TypeFactory
        $valid = [];
        foreach ($normalized as $t) {
            if ($t === 'fk') {
                $valid[] = $t;
                continue;
            }
            if (isset($this->typeMap[$t])) {
                $valid[] = $t;
                continue;
            }
            if (TypeFactory::getMap($t) !== null) {
                $valid[] = $t;
            }
        }

        return $valid ?: $defaults;
    }

    /**
     * Build migration file contents.
     *
     * @param string $name Migration class name suffix.
     * @param string $pkType Primary key type.
     * @param string $uuidType UUID column type.
     * @param string $jsonStorage JSON storage type.
     * @param array<string> $types Types to create.
     * @return string
     */
    public function buildMigration(
        string $name,
        string $pkType,
        string $uuidType,
        string $jsonStorage,
        array $types
    ): string {
        // Canonical eav_* naming, unified entity_id, and value column
        $entityField = 'entity_id';
        $entityFieldType = $pkType === 'int' ? 'biginteger' : $uuidType;

        // Build table specs for selected types.
        $tableSpecs = [];
        foreach ($types as $type) {
            // Special handling for fk: single table eav_fk; value type depends on PK family
            if ($type === 'fk') {
                $tableSpecs[] = [
                    'table' => 'eav_fk',
                    'valType' => $entityFieldType,
                    'valOptions' => [],
                ];
                continue;
            }

            // Known mapped type
            if (isset($this->typeMap[$type])) {
                $valSpec = $this->typeMap[$type];
                $tableType = $type;
                $valType = $valSpec['type'];
                $valOptions = $valSpec['options'];

                if ($type === 'json') {
                    $tableType = 'json';     // always eav_json
                    $valType = $jsonStorage; // column type json or jsonb
                }

                $tableSpecs[] = [
                    'table' => "eav_{$tableType}",
                    'valType' => $valType,
                    'valOptions' => $valOptions,
                ];
                continue;
            }

            // Fallback for TypeFactory-known types not in $typeMap
            if (TypeFactory::getMap($type) !== null) {
                $tableSpecs[] = [
                    'table' => "eav_{$type}",
                    'valType' => $type,
                    'valOptions' => [],
                ];
            }
        }

        $specLines = [];
        foreach ($tableSpecs as $spec) {
            $options = var_export($spec['valOptions'], true);
            $specLines[] = "            ['table' => '{$spec['table']}', 'valType' => '{$spec['valType']}', 'valOptions' => {$options}],";
        }
        $specBlock = implode("\n", $specLines);
        $className = Inflector::camelize($name);

        return <<<PHP
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class {$className} extends AbstractMigration
{
    public function change(): void
    {
        if (!\$this->hasTable('attributes')) {
            \$this->table('attributes', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', '{$uuidType}', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 191, 'null' => false])
                ->addColumn('label', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('data_type', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('options', 'json', ['null' => false])
                ->addTimestamps('created', 'modified')
                ->addIndex(['name'], ['unique' => true, 'name' => 'idx_attributes_name'])
                ->create();
        }

        if (!\$this->hasTable('attribute_sets')) {
            \$this->table('attribute_sets', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', '{$uuidType}', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 191, 'null' => false])
                ->addTimestamps('created', 'modified')
                ->addIndex(['name'], ['unique' => true, 'name' => 'idx_attribute_sets_name'])
                ->create();
        }

        if (!\$this->hasTable('attribute_set_attributes')) {
            \$this->table('attribute_set_attributes', ['id' => false, 'primary_key' => ['attribute_set_id', 'attribute_id']])
                ->addColumn('attribute_set_id', '{$uuidType}', ['null' => false])
                ->addColumn('attribute_id', '{$uuidType}', ['null' => false])
                ->addColumn('position', 'integer', ['null' => true, 'default' => 0])
                ->addForeignKey('attribute_set_id', 'attribute_sets', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('attribute_id', 'attributes', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        \$tables = [
{$specBlock}
        ];

        foreach (\$tables as \$spec) {
            if (\$this->hasTable(\$spec['table'])) {
                continue;
            }
            \$table = \$this->table(\$spec['table'], ['id' => false, 'primary_key' => ['id']]);
            \$table
                ->addColumn('id', '{$uuidType}', ['null' => false])
                ->addColumn('entity_table', 'string', ['limit' => 191, 'null' => false])
                ->addColumn('{$entityField}', '{$entityFieldType}', ['null' => false])
                ->addColumn('attribute_id', '{$uuidType}', ['null' => false])
                ->addColumn('value', \$spec['valType'], array_merge(\$spec['valOptions'], ['null' => true]))
                ->addTimestamps('created', 'modified')
                ->addIndex(['entity_table', '{$entityField}', 'attribute_id'], ['unique' => true, 'name' => 'idx_' . \$spec['table'] . '_lookup'])
                ->addForeignKey('attribute_id', 'attributes', 'id', ['delete' => 'CASCADE'])
                ->create();
        }
    }
}
PHP;
    }

    /**
     * Find the migrations directory.
     *
     * @return string
     */
    public function migrationPath(): string
    {
        return dirname(__DIR__, 2) . '/config/Migrations';
    }

    /**
     * Get a new migration file name.
     *
     * @param string $path Directory.
     * @param string $name Base name.
     * @return string
     */
    public function nextMigrationFilename(string $path, string $name): string
    {
        $timestamp = date('YmdHis');
        $base = $path . '/' . $timestamp . '_' . Inflector::underscore($name) . '.php';
        $counter = 0;
        while (file_exists($base)) {
            $counter++;
            $base = $path . '/' . $timestamp . '_' . Inflector::underscore($name) . "_{$counter}.php";
        }

        return $base;
    }

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addOption('pk-type', ['help' => 'Primary key type: uuid|int', 'default' => 'uuid']);
        $parser->addOption('uuid-type', ['help' => 'UUID storage type: uuid|binaryuuid|nativeuuid', 'default' => 'uuid']);
        $parser->addOption('json-storage', ['help' => 'JSON storage: json|jsonb', 'default' => 'json']);
        $parser->addOption('connection', ['help' => 'Connection name', 'default' => 'default']);
        $parser->addOption('name', ['help' => 'Migration class name', 'default' => 'EavSetup']);
        $parser->addOption('dry-run', ['help' => 'Output migration without writing', 'boolean' => true]);
        // Feature 2: allow selecting types to scaffold
        $parser->addOption('types', ['help' => 'Types to scaffold: defaults|all|csv (e.g. "string,int,json,fk")', 'default' => 'defaults']);
        // Feature 4: interactive and config options
        $parser->addOption('interactive', ['help' => 'Launch interactive setup wizard', 'boolean' => true, 'default' => false]);
        $parser->addOption('no-interactive', ['help' => 'Force non-interactive behavior (no wizard)', 'boolean' => true, 'default' => false]);
        $parser->addOption('config', ['help' => 'Path to eav.json to load options from (non-interactive)', 'default' => null]);

        return $parser;
    }
}
