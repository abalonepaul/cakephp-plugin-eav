<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Database\TypeFactory;
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
            $io->err('Usage: bin/cake eav create_attribute name:type [--label "Label"]');
            return Command::CODE_ERROR;
        }
        if (strpos($name, ':') !== false && !$args->getArgument('type')) {
            [$name, $type] = explode(':', $name, 2);
        }
        $label = (string)($args->getOption('label') ?? '');
        $normalizedType = $this->normalizeType($type, $io);
        if ($normalizedType === null) {
            return Command::CODE_ERROR;
        }
        if ($label === '') {
            $label = Inflector::humanize($name);
        }

        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
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
        return $parser;
    }
}
