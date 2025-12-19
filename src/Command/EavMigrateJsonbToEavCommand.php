<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Database\TypeFactory;
use Cake\Datasource\ConnectionManager;

class EavMigrateJsonbToEavCommand extends Command
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
    ];

    /** @var array<string> */
    protected array $customTypes = [
        'fk_uuid',
        'fk_int',
    ];

    /**
     * Migrate JSONB key/value pairs to EAV attributes.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $table = (string)$args->getArgument('table');
        $jsonbField = (string)$args->getArgument('jsonbField');
        $attribute = (string)$args->getOption('attribute');
        $type = (string)$args->getOption('type') ?: 'string';
        $entityTable = (string)$args->getOption('entity-table') ?: $table;
        $pkType = (string)$args->getOption('pk') ?: 'uuid';
        $dryRun = (bool)$args->getOption('dry-run');
        $batchSize = (int)($args->getOption('batch-size') ?: 1000);
        if ($batchSize < 1) {
            $batchSize = 1000;
        }

        if (!$table || !$jsonbField || !$attribute) {
            $io->err('Usage: bin/cake eav migrate_jsonb_to_eav table jsonbField --attribute key --type decimal --entity-table items --pk uuid');
            return Command::CODE_ERROR;
        }

        /** @var Connection $conn */
        $conn = ConnectionManager::get('default');
        $driver = $conn->getDriver();
        if (!$driver instanceof Postgres) {
            $io->err('This command requires Postgres JSONB support.');
            return Command::CODE_ERROR;
        }

        $normalizedType = $this->normalizeType($type, $io);
        if ($normalizedType === null) {
            return Command::CODE_ERROR;
        }

        $Attributes = $this->getTableLocator()->get('Eav.Attributes');
        $attr = $Attributes->find()->where(['name'=>$attribute])->first();
        if (!$attr) {
            $attr = $Attributes->newEntity([
                'name' => $attribute,
                'data_type' => $normalizedType,
                'options' => [],
            ]);
            $Attributes->saveOrFail($attr);
        }

        $t = $driver->quoteIdentifier($table);
        $f = $driver->quoteIdentifier($jsonbField);

        $Table = $this->getTableLocator()->get($table);
        $Table->addBehavior('Eav.Eav', [
            'entityTable' => $entityTable,
            'pkType' => $pkType,
        ]);

        $count = 0;
        $offset = 0;
        $preview = [];
        while (true) {
            $sql = "SELECT id, {$f} ->> :key AS val FROM {$t} WHERE {$f} ? :key";
            $sql .= " ORDER BY id LIMIT {$batchSize} OFFSET {$offset}";
            $rows = $conn->execute($sql, ['key' => $attribute])->fetchAll('assoc');
            if ($rows === []) {
                break;
            }

            if ($dryRun) {
                foreach ($rows as $r) {
                    if ($r['val'] === null || $r['val'] === '') {
                        continue;
                    }
                    $count++;
                    if (count($preview) < 5) {
                        $preview[] = $r;
                    }
                }
            } else {
                $conn->transactional(function () use ($rows, $Table, $attribute, $type, &$count): void {
                    foreach ($rows as $r) {
                        if ($r['val'] === null || $r['val'] === '') {
                            continue;
                        }
                        $Table->saveEavValue($r['id'], $attribute, $type, $r['val']);
                        $count++;
                    }
                });
            }

            $io->out("Processed {$count} rows...");
            $offset += $batchSize;
        }

        if ($dryRun) {
            $io->out("Dry run: {$count} values would be migrated.");
            if ($preview !== []) {
                $io->out('Preview:');
                foreach ($preview as $row) {
                    $io->out(sprintf('%s => %s', $row['id'], (string)$row['val']));
                }
            }
        } else {
            $io->out("Migrated {$count} values from {$table}.{$jsonbField} -> {$attribute} ({$normalizedType}).");
        }
        return Command::CODE_SUCCESS;
    }

    /**
     * Normalize type and validate it against TypeFactory/custom types.
     *
     * @param string $type Raw type name.
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return string|null
     */
    protected function normalizeType(string $type, ConsoleIo $io): ?string
    {
        $raw = strtolower(trim($type));
        if ($raw === 'jsonb') {
            $raw = 'json';
        }
        $normalized = $this->typeAliases[$raw] ?? $raw;
        if (in_array($normalized, $this->customTypes, true)) {
            return $normalized;
        }
        if (TypeFactory::getMap($normalized) === null) {
            $io->err('Unsupported EAV type: ' . $type);
            return null;
        }

        return $normalized;
    }

    public function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser->addArgument('table');
        $parser->addArgument('jsonbField');
        $parser->addOption('attribute', ['short'=>'a', 'help'=>'JSON key to migrate', 'required'=>true]);
        $parser->addOption('type', ['short'=>'t', 'help'=>'EAV type (string,int,decimal,bool,date,datetime,json,uuid,fk_uuid)']);
        $parser->addOption('entity-table', ['help'=>'Entity table name in AV rows']);
        $parser->addOption('pk', ['help'=>'Primary key type: uuid|int', 'default'=>'uuid']);
        $parser->addOption('dry-run', ['help' => 'Preview without writing', 'boolean' => true]);
        $parser->addOption('batch-size', ['help' => 'Batch size', 'default' => 1000]);
        return $parser;
    }
}
