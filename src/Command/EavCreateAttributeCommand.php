<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Database\TypeFactory;
use Cake\Datasource\ConnectionManager;
use Throwable;

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
     * Flags-only UX:
     *   bin/cake eav create_attribute --name <name> --type <type> [--connection <name>]
     *
     * @param Arguments $args Arguments.
     * @param ConsoleIo $io Console io.
     * @return int
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        // Enforce flags-only input
        $name = (string)($args->getOption('name') ?? '');
        $type = (string)($args->getOption('type') ?? '');

        if ($name === '') {
            $io->err('Missing required option: --name');
            $io->err('Usage: bin/cake eav create_attribute --name <name> --type <type> [--connection <name>]');
            return CommandInterface::CODE_ERROR;
        }
        if ($type === '') {
            $io->err('Missing required option: --type');
            $io->err('Usage: bin/cake eav create_attribute --name <name> --type <type> [--connection <name>]');
            return CommandInterface::CODE_ERROR;
        }

        // Resolve and validate connection
        $connectionName = (string)($args->getOption('connection') ?? 'default');
        try {
            ConnectionManager::get($connectionName);
        } catch (Throwable) {
            $io->err('Unknown connection: ' . $connectionName);
            return CommandInterface::CODE_ERROR;
        }
        $io->out('Using connection: ' . $connectionName);

        $normalizedType = $this->normalizeType($type, $io);
        if ($normalizedType === null) {
            return CommandInterface::CODE_ERROR;
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
            return CommandInterface::CODE_SUCCESS;
        }

        $entity = $Attributes->newEntity([
            'name' => $name,
            'data_type' => $normalizedType,
        ]);
        if ($Attributes->save($entity)) {
            $io->out('Created attribute ' . $name . ' (' . $normalizedType . ')');
            return CommandInterface::CODE_SUCCESS;
        }
        $io->err('Failed to create attribute');
        return CommandInterface::CODE_ERROR;
    }

    /**
     * Normalize type and validate it against TypeFactory/custom types.
     *
     * @param string $type Raw type name.
     * @param ConsoleIo $io Console io.
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

    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        // Flags-only parser (no positional arguments)
        $parser
            ->setDescription('Create an EAV attribute in the eav_attributes registry.')
            ->addOption('name', [
                'help' => 'Attribute name (e.g., color)',
                'short' => 'n',
            ])
            ->addOption('type', [
                'help' => 'Attribute type (e.g., string, integer, json, fk)',
                'short' => 't',
            ])
            ->addOption('connection', [
                'help' => 'Datasource connection name',
                'default' => 'default',
            ])
            ->setEpilog('Example: bin/cake eav create_attribute --name color --type string --connection test');

        return $parser;
    }
}
