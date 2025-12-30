<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Database\TypeFactory;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;

class EavCreateAttributeCommand extends Command
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
        'fk',
    ];

    /**
     * Create an attribute.
     *
     * @param \Cake\Console\Arguments $args Arguments.
     * @param \Cake\Console\ConsoleIo $io Console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $name = (string)($args->getArgument('name') ?? '');
        $type = (string)($args->getArgument('type') ?? 'string');
        if (!$name) {
            $io->err('Usage: bin/cake eav create_attribute name:type [--label "Label"] [--connection <name>]');
            return Command::CODE_ERROR;
        }
        if (strpos($name, ':') !== false && !$args->getArgument('type')) {
            [$name, $type] = explode(':', $name, 2);
        }

        // Resolve and validate connection
        $connectionName = (string)($args->getOption('connection') ?? 'default');
        try {
            ConnectionManager::get($connectionName);
        } catch (\Throwable $e) {
            $io->err('Unknown connection: ' . $connectionName);
            return Command::CODE_ERROR;
        }
        $io->out('Using connection: ' . $connectionName);

        $label = (string)($args->getOption('label') ?? '');
        $normalizedType = $this->normalizeType($type, $io);
        if ($normalizedType === null) {
            return Command::CODE_ERROR;
        }
        if ($label === '') {
            $label = Inflector::humanize($name);
        }

        // Use the selected connection for the attributes registry; avoid reconfiguring an existing registry instance.
        $locator = $this->getTableLocator();
        if ($locator->exists('Eav.EavAttributes')) {
            $Attributes = $locator->get('Eav.EavAttributes');
            // Honor --connection: if the loaded table uses a different connection, recreate it on the requested one.
            $actual = method_exists($Attributes->getConnection(), 'configName') ? $Attributes->getConnection()->configName() : null;
            if ($actual !== $connectionName && method_exists($locator, 'remove')) {
                $locator->remove('Eav.EavAttributes');
                $Attributes = $locator->get('Eav.EavAttributes', ['connectionName' => $connectionName]);
            }
        } else {
            $Attributes = $locator->get('Eav.EavAttributes', ['connectionName' => $connectionName]);
        }

        $existing = $Attributes->find()->select(['id'])->where(['name' => $name])->first();
        if ($existing) {
            $io->out('Attribute already exists: ' . $name);
            return Command::CODE_SUCCESS;
        }

        $entity = $Attributes->newEntity([
            'name' => $name,
            'data_type' => $normalizedType,
            'label' => $label,
            'options' => [],
        ]);
        if ($Attributes->save($entity)) {
            $io->out('Created attribute ' . $name . ' (' . $normalizedType . ')');
            return Command::CODE_SUCCESS;
        }
        $io->err('Failed to create attribute');
        return Command::CODE_ERROR;
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
        if ($raw === 'fk_uuid' || $raw === 'fk_int') {
            $raw = 'fk';
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
        $parser->addArgument('name', ['help' => 'Attribute name or name:type']);
        $parser->addArgument('type', ['help' => 'Attribute type', 'required' => false]);
        $parser->addOption('label', ['short' => 'l', 'help' => 'Human label']);
        // Step 1: Add connection flag to align with other commands
        $parser->addOption('connection', ['help' => 'Datasource connection name', 'default' => 'default']);
        return $parser;
    }
}
