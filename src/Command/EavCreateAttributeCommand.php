<?php
declare(strict_types=1);

namespace Eav\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

class EavCreateAttributeCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io)
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

        $Attributes = $this->getTableLocator()->get('Eav.Attributes');
        $entity = $Attributes->newEntity(['name' => $name, 'data_type' => $type, 'label' => $label]);
        if ($Attributes->save($entity)) {
            $io->out('Created attribute ' . $name . ' (' . $type . ')');
            return Command::CODE_SUCCESS;
        }
        $io->err('Failed to create attribute');
        return Command::CODE_ERROR;
    }

    public static function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser->addArgument('name', ['help' => 'Attribute name or name:type']);
        $parser->addArgument('type', ['help' => 'Attribute type', 'required' => false]);
        $parser->addOption('label', ['short' => 'l', 'help' => 'Human label']);
        return $parser;
    }
}
