<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Sqlserver;
use Cake\Database\Driver\Sqlite;

class EavSetupInteractiveCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        return $this->runInteractive($io);
    }

    /**
     * Run the setup wizard and generate output.
     *
     * - Persists plugins/Eav/config/eav.json
     * - Delegates migration building/writing to EavSetupCommand helpers
     *
     * @return int CommandInterface::CODE_*
     */
    public function runInteractive(ConsoleIo $io): int
    {
        // 1) Choose connection
        $configured = ConnectionManager::configured();
        $defaultIdx = array_search('default', $configured, true);
        $default = $defaultIdx !== false ? 'default' : (string)($configured[0] ?? 'default');
        $connName = $io->askChoice('Select connection', $configured ?: [$default], $default);

        $conn = ConnectionManager::get($connName);
        $driver = $conn->getDriver();

        // 2) Output mode (Migrations default; Raw SQL support coming up)
        $outputMode = $io->askChoice('Output mode', ['migrations', 'raw_sql'], 'migrations');
        if (!($driver instanceof Postgres) && !($driver instanceof Mysql)) {
            if ($outputMode === 'raw_sql') {
                $io->warning('Raw SQL is currently supported for Postgres/MySQL only. Falling back to Migrations.');
                $outputMode = 'migrations';
            }
        }

        // 3) PK family and UUID subtype
        $pkType = $io->askChoice('Primary key family for entity_id', ['uuid', 'int'], 'uuid');
        $uuidType = 'uuid';
        if ($pkType === 'uuid') {
            $recommended = 'uuid';
            if ($driver instanceof Postgres || $driver instanceof Sqlserver) {
                $recommended = 'nativeuuid';
            } elseif ($driver instanceof Mysql) {
                $recommended = 'binaryuuid';
            } elseif ($driver instanceof Sqlite) {
                $recommended = 'uuid';
            }
            $uuidType = $io->askChoice('UUID subtype (recommended shown first)', ['nativeuuid', 'binaryuuid', 'uuid'], $recommended);
        }

        // 4) JSON Attribute column storage for eav_json.value
        $jsonStorage = $io->askChoice('JSON attribute storage type for eav_json.value', ['json', 'jsonb'], 'json');
        if ($jsonStorage === 'jsonb' && !($driver instanceof Postgres)) {
            $io->warning('JSONB requires Postgres driver. Falling back to json.');
            $jsonStorage = 'json';
        }

        // 5) Default behavior storage mode
        $storageDefault = $io->askChoice('Default behavior storage', ['tables', 'json_column'], 'tables');

        // 5a) Optional per-table JSON Storage mapping when json_column is selected
        $jsonColumns = [];
        if ($storageDefault === 'json_column') {
            $io->out('Configure JSON Storage Mode (entity-level JSON column) per table.');
            $io->out('Note: This is separate from JSON Attribute (eav_json).');

            $schema = $conn->getSchemaCollection();
            $allTables = $schema->listTables();
            // Exclude plugin tables and typical junctions
            $appTables = array_values(array_filter($allTables, function (string $t) {
                return !preg_match('/^(attributes|attribute_sets|attribute_set_attributes|eav_)/i', $t);
            }));

            if ($appTables === []) {
                $io->warning('No application tables detected to configure JSON Storage Mode.');
            } else {
                // Present numbered list for multi-select
                $io->out('Select one or more tables by number (CSV), or press Enter to skip:');
                foreach ($appTables as $idx => $t) {
                    $io->out(sprintf('  %d) %s', $idx + 1, $t));
                }
                $selection = trim((string)$io->ask('Tables (e.g., "1,3,5" or empty to skip)', ''));
                $indexes = [];
                if ($selection !== '') {
                    foreach (explode(',', $selection) as $token) {
                        $n = (int)trim($token);
                        if ($n >= 1 && $n <= count($appTables)) {
                            $indexes[] = $n - 1;
                        }
                    }
                    $indexes = array_values(array_unique($indexes));
                }

                foreach ($indexes as $i) {
                    $tableName = $appTables[$i];
                    // Inspect columns; suggest existing json/jsonb columns if any
                    $desc = $schema->describe($tableName);
                    $cols = $desc->columns();
                    $jsonish = [];
                    foreach ($cols as $c) {
                        $ctype = strtolower((string)$desc->getColumnType($c));
                        if ($ctype === 'json' || $ctype === 'jsonb') {
                            $jsonish[] = $c;
                        }
                    }

                    $io->out(sprintf('Table "%s":', $tableName));
                    if ($jsonish) {
                        $io->out('Existing JSON columns:');
                        foreach ($jsonish as $j) {
                            $io->out('  - ' . $j);
                        }
                    } else {
                        $io->out('No existing JSON/JSONB columns detected.');
                    }

                    // Accept either an existing column name OR a brand-new column name directly.
                    // If user enters "[add_new_column]" (or leaves blank), we will prompt for the name.
                    $defaultChoice = $jsonish ? $jsonish[0] : '[add_new_column]';
                    $input = trim((string)$io->ask(
                        sprintf('Use an existing JSON column or type a new column name (%s/[add_new_column])', $jsonish ? implode('|', $jsonish) : ''),
                        $defaultChoice
                    ));

                    if ($input === '') {
                        $input = $defaultChoice;
                    }

                    $isAddNew = ($input === '[add_new_column]') || !in_array($input, $jsonish, true);
                    if ($isAddNew) {
                        // If the user typed a custom name (not in existing list), accept it directly.
                        if ($input === '[add_new_column]') {
                            $proposed = in_array('attrs', $cols, true) ? 'spec' : 'attrs';
                            $input = (string)$io->ask('Enter JSON column name to add', $proposed);
                            $input = trim($input);
                            if ($input === '') {
                                $input = $proposed;
                            }
                        }

                        // Validate/normalize identifier to a safe SQL column name (letters, digits, underscores; cannot start with digit).
                        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $input));
                        if ($normalized === '' || ctype_digit(substr($normalized, 0, 1))) {
                            $normalized = 'attrs';
                        }

                        $jsonColumns[$tableName] = $normalized;

                        // Driver-aware note for the user (migration to actually add the column is generated in the next step).
                        if ($driver instanceof Postgres) {
                            $io->out(sprintf('Will add jsonb NULL column "%s" on "%s" (optional GIN/functional indexes available).', $normalized, $tableName));
                        } else {
                            $io->out(sprintf('Will add json NULL column "%s" on "%s". Functional index generation is skipped on MySQL.', $normalized, $tableName));
                        }
                    } else {
                        // Existing column chosen
                        $jsonColumns[$tableName] = $input;
                    }
                }
            }
        }

        // 6) Types selection
        $mode = $io->askChoice('Types to scaffold', ['defaults', 'all', 'custom'], 'defaults');
        $typesCsv = 'defaults';
        if ($mode === 'custom') {
            $typesCsv = (string)$io->ask('Enter CSV list of types (aliases ok), e.g. "string,int,json,fk"');
            if (trim($typesCsv) === '') {
                $typesCsv = 'defaults';
            }
        } else {
            $typesCsv = $mode;
        }

        // Resolve types via the existing non-interactive command helper (now public)
        $setup = new EavSetupCommand();
        $types = $setup->resolveSelectedTypes($typesCsv);

        // 7) Migration name
        $migrationName = (string)$io->ask('Migration class name', 'EavSetup');

        // 8) Persist eav.json
        $jsonColumnsForJson = $jsonColumns !== [] ? $jsonColumns : new \stdClass(); // {} when empty
        $config = [
            'connection' => $connName,
            'driver' => get_class($driver),
            'outputMode' => $outputMode,
            'pkType' => $pkType,
            'uuidType' => $uuidType,
            'jsonAttributeStorage' => $jsonStorage,
            'jsonEncodeOnWrite' => false,
            'storageDefault' => $storageDefault,
            'jsonColumns' => $jsonColumnsForJson,
            'types' => $types,
            'migrationName' => $migrationName,
            'generatedAt' => gmdate('c'),
        ];
        $configDir = dirname(__DIR__, 2) . '/config';
        $configPath = $configDir . '/eav.json';
        if (!is_dir($configDir) && !mkdir($configDir, 0775, true) && !is_dir($configDir)) {
            $io->warning('Unable to create config directory: ' . $configDir);
        } else {
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (@file_put_contents($configPath, (string)$json) === false) {
                $io->warning('Unable to write ' . $configPath . '. Printing contents:');
                $io->out((string)$json);
            } else {
                $io->success('Saved ' . $configPath);
            }
        }

        // 8) Generation (raw_sql path will fall back for now)
        if ($outputMode === 'raw_sql') {
            if (!($driver instanceof Postgres) && !($driver instanceof Mysql)) {
                $io->warning('Raw SQL is supported only for Postgres/MySQL at this time. Falling back to Migrations.');
            } else {
                $io->warning('Raw SQL DDL emission will be provided in the next step. Falling back to Migrations for now.');
            }
        }

        // Delegate to the migration builder in EavSetupCommand
        $payload = $setup->buildMigration($migrationName, $pkType, $uuidType, $jsonStorage, $types);

        $path = $setup->migrationPath();
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            $io->err('Unable to create migrations directory: ' . $path);
            return CommandInterface::CODE_ERROR;
        }

        $fileName = $setup->nextMigrationFilename($path, $migrationName);
        if (file_put_contents($fileName, $payload) === false) {
            $io->err('Unable to write migration file: ' . $fileName);
            return CommandInterface::CODE_ERROR;
        }

        $io->success('Migration written: ' . $fileName);
        $io->out('Run: bin/cake migrations migrate -p Eav -c ' . $connName);

        // Optional: Generate a migration to add JSON Storage columns when selected
        if (!empty($jsonColumns) && $storageDefault === 'json_column') {
            $wantJsonMig = $io->askChoice('Generate a migration to add JSON columns now?', ['yes', 'no'], 'yes') === 'yes';
            if ($wantJsonMig) {
                // For Postgres, collect index choices per table
                $pgIndexSpec = [];
                if ($driver instanceof Postgres) {
                    foreach ($jsonColumns as $t => $col) {
                        $io->out(sprintf('Index options for %s.%s (Postgres):', $t, $col));
                        $gin = $io->askChoice(' - Add GIN index on the JSONB column?', ['yes', 'no'], 'no') === 'yes';
                        $keysCsv = (string)$io->ask(' - Functional indexes (CSV of keys to index, blank to skip)', '');
                        $keys = array_values(array_filter(array_map('trim', explode(',', $keysCsv))));
                        $pgIndexSpec[$t] = ['gin' => $gin, 'keys' => $keys];
                    }
                }

                // Build migration payload
                $jsonMigClass = 'AddJsonColumns';
                $migBody = [];
                $migBody[] = "<?php";
                $migBody[] = "declare(strict_types=1);";
                $migBody[] = "";
                $migBody[] = "use Migrations\\AbstractMigration;";
                $migBody[] = "";
                $migBody[] = "class {$jsonMigClass} extends AbstractMigration";
                $migBody[] = "{";
                $migBody[] = "    public function change(): void";
                $migBody[] = "    {";
                foreach ($jsonColumns as $tableName => $columnName) {
                    $colType = ($driver instanceof Postgres) ? 'jsonb' : 'json';
                    $migBody[] = "        // {$tableName}.{$columnName}";
                    $migBody[] = "        \$t = \$this->table('{$tableName}');";
                    $migBody[] = "        if (!\$t->hasColumn('{$columnName}')) {";
                    $migBody[] = "            \$t->addColumn('{$columnName}', '{$colType}', ['null' => true])->update();";
                    $migBody[] = "        }";
                    if ($driver instanceof Postgres) {
                        $spec = $pgIndexSpec[$tableName] ?? ['gin' => false, 'keys' => []];
                        if (!empty($spec['gin'])) {
                            $ginIdx = "idx_{$tableName}_{$columnName}_gin";
                            $migBody[] = "        // GIN index on {$tableName}.{$columnName}";
                            $migBody[] = "        \$this->execute(\"CREATE INDEX IF NOT EXISTS {$ginIdx} ON {$tableName} USING GIN ({$columnName})\");";
                        }
                        if (!empty($spec['keys'])) {
                            foreach ($spec['keys'] as $key) {
                                $safeKey = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($key));
                                $fnIdx = "idx_{$tableName}_{$columnName}_key_{$safeKey}";
                                // Default to string; callers can recreate with typed casts later if needed
                                $migBody[] = "        // Functional index on JSON key '{$key}'";
                                $migBody[] = "        \$this->execute(\"CREATE INDEX IF NOT EXISTS {$fnIdx} ON {$tableName} ((({$columnName} ->> '{$key}')))\");";
                            }
                        }
                    } else {
                        // MySQL note (no functional indexes emitted here)
                        $migBody[] = "        // MySQL: functional indexes on JSON are limited; skipping index creation.";
                    }
                    $migBody[] = "";
                }
                $migBody[] = "    }";
                $migBody[] = "}";
                $jsonMigPayload = implode(PHP_EOL, $migBody);

                // Write migration file for JSON columns
                $jsonMigFile = $setup->nextMigrationFilename($path, $jsonMigClass);
                if (file_put_contents($jsonMigFile, $jsonMigPayload) === false) {
                    $io->err('Unable to write JSON columns migration: ' . $jsonMigFile);
                } else {
                    $io->success('JSON columns migration written: ' . $jsonMigFile);
                }
            }
        }

        return CommandInterface::CODE_SUCCESS;
    }
}
