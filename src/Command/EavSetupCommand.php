<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Database\Driver\Postgres;
use Cake\Database\TypeFactory;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use InvalidArgumentException;

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
    ];

    /** @var array<string> */
    protected array $customTypes = [
        'fk_uuid',
        'fk_int',
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
        'fk_uuid' => ['type' => 'uuid', 'options' => []],
        'fk_int' => ['type' => 'biginteger', 'options' => []],
    ];

    /**
     * Generate a migration for EAV schema based on configuration.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $pkType = strtolower((string)$args->getOption('pk-type') ?: 'uuid');
        $uuidType = strtolower((string)$args->getOption('uuid-type') ?: 'uuid');
        $jsonStorage = strtolower((string)$args->getOption('json-storage') ?: 'json');
        $connectionName = (string)($args->getOption('connection') ?: 'default');
        $dryRun = (bool)$args->getOption('dry-run');
        $migrationName = (string)($args->getOption('name') ?: 'EavSetup');

        if (!in_array($pkType, ['uuid', 'int'], true)) {
            $io->err('pk-type must be uuid or int.');
            return Command::CODE_ERROR;
        }
        if (!in_array($uuidType, ['uuid', 'binaryuuid', 'nativeuuid'], true)) {
            $io->err('uuid-type must be uuid, binaryuuid, or nativeuuid.');
            return Command::CODE_ERROR;
        }
        if (!in_array($jsonStorage, ['json', 'jsonb'], true)) {
            $io->err('json-storage must be json or jsonb.');
            return Command::CODE_ERROR;
        }

        $connection = ConnectionManager::get($connectionName);
        $driver = $connection->getDriver();
        if ($jsonStorage === 'jsonb' && !$driver instanceof Postgres) {
            $io->out('JSONB storage requested, but adapter is not Postgres. Falling back to json.');
            $jsonStorage = 'json';
        }

        $types = $this->resolveTypes();
        $payload = $this->buildMigration(
            $migrationName,
            $pkType,
            $uuidType,
            $jsonStorage,
            $types,
        );

        $path = $this->migrationPath();
        $fileName = $this->nextMigrationFilename($path, $migrationName);

        if ($dryRun) {
            $io->out('Dry run - migration not written.');
            $io->out('Target: ' . $fileName);
            $io->out($payload);
            return Command::CODE_SUCCESS;
        }

        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            $io->err('Unable to create migrations directory: ' . $path);
            return Command::CODE_ERROR;
        }

        if (file_put_contents($fileName, $payload) === false) {
            $io->err('Unable to write migration file: ' . $fileName);
            return Command::CODE_ERROR;
        }

        $io->out('Migration written: ' . $fileName);
        $io->out('Run: bin/cake migrations migrate -p Eav');

        return Command::CODE_SUCCESS;
    }

    /**
     * Resolve supported types from TypeFactory plus custom types.
     *
     * @return array<string>
     */
    protected function resolveTypes(): array
    {
        $types = array_keys(TypeFactory::getMap() ?? []);
        $normalized = [];
        foreach ($types as $type) {
            $alias = $this->typeAliases[$type] ?? $type;
            $normalized[$alias] = true;
        }
        foreach ($this->customTypes as $type) {
            $normalized[$type] = true;
        }

        return array_keys($normalized);
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
    protected function buildMigration(
        string $name,
        string $pkType,
        string $uuidType,
        string $jsonStorage,
        array $types
    ): string {
        $pkSuffix = $pkType === 'int' ? 'int' : 'uuid';
        $entityField = $pkType === 'int' ? 'entity_int_id' : 'entity_id';
        $entityFieldType = $pkType === 'int' ? 'biginteger' : $uuidType;
        $jsonTableName = $jsonStorage === 'jsonb' ? 'jsonb' : 'json';

        $tableSpecs = [];
        foreach ($types as $type) {
            if (!isset($this->typeMap[$type])) {
                continue;
            }
            $valSpec = $this->typeMap[$type];
            $tableType = $type;
            $valType = $valSpec['type'];
            $valOptions = $valSpec['options'];
            if ($type === 'json') {
                $tableType = $jsonTableName;
                $valType = $jsonStorage;
            }
            $tableSpecs[] = [
                'table' => "av_{$tableType}_{$pkSuffix}",
                'valType' => $valType,
                'valOptions' => $valOptions,
            ];
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
                ->addColumn('created', 'datetime', ['null' => false])
                ->addColumn('modified', 'datetime', ['null' => false])
                ->addIndex(['name'], ['unique' => true, 'name' => 'idx_attributes_name'])
                ->create();
        }

        if (!\$this->hasTable('attribute_sets')) {
            \$this->table('attribute_sets', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', '{$uuidType}', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 191, 'null' => false])
                ->addColumn('created', 'datetime', ['null' => false])
                ->addColumn('modified', 'datetime', ['null' => false])
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
                ->addColumn('val', \$spec['valType'], \$spec['valOptions'])
                ->addColumn('created', 'datetime', ['null' => false])
                ->addColumn('modified', 'datetime', ['null' => false])
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
    protected function migrationPath(): string
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
    protected function nextMigrationFilename(string $path, string $name): string
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

    public function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser->addOption('pk-type', ['help' => 'Primary key type: uuid|int', 'default' => 'uuid']);
        $parser->addOption('uuid-type', ['help' => 'UUID storage type: uuid|binaryuuid|nativeuuid', 'default' => 'uuid']);
        $parser->addOption('json-storage', ['help' => 'JSON storage: json|jsonb', 'default' => 'json']);
        $parser->addOption('connection', ['help' => 'Connection name', 'default' => 'default']);
        $parser->addOption('name', ['help' => 'Migration class name', 'default' => 'EavSetup']);
        $parser->addOption('dry-run', ['help' => 'Output migration without writing', 'boolean' => true]);
        return $parser;
    }
}
