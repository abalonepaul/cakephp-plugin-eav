<?php
declare(strict_types=1);

namespace Eav;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Eav\Command\EavCreateAttributeCommand;
use Eav\Command\EavMigrateJsonbToEavCommand;
use Eav\Command\EavSetupCommand;
use Eav\Command\EavSetupInteractiveCommand;

class EavPlugin extends BasePlugin
{
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        $commands->add('eav create_attribute', EavCreateAttributeCommand::class);
        $commands->add('eav migrate_jsonb_to_eav', EavMigrateJsonbToEavCommand::class);
        $commands->add('eav setup', EavSetupCommand::class);
        // Explicit interactive entrypoint alongside magic launch from "eav setup"
        $commands->add('eav setup:interactive', EavSetupInteractiveCommand::class);

        return $commands;
    }
}
