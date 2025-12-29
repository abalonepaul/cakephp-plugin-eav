<?php
declare(strict_types=1);

namespace Eav;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Eav\Command\EavCreateAttributeCommand;
use Eav\Command\EavMigrateJsonbToEavCommand;
use Eav\Command\EavSetupCommand;
use Eav\Command\EavSetupInteractiveCommand;

/**
 * Eav plugin bootstrap.
 *
 * Registers CLI commands and plugin routes.
 */
class EavPlugin extends BasePlugin
{
    /**
     * Register console commands.
     *
     * @param \Cake\Console\CommandCollection $commands Command collection.
     * @return \Cake\Console\CommandCollection
     */
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

    /**
     * Register plugin routes.
     *
     * CakePHP 5 requires either:
     * - a plugin route scope here, or
     * - app-level $routes->plugin('Eav', ...) in config/routes.php,
     * or loading the plugin with ['routes' => true] and defining a routes file.
     *
     * Defining the scope here ensures /eav/* resolves to plugin controllers without
     * additional app configuration.
     *
     * @param \Cake\Routing\RouteBuilder $routes Route builder.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);

        // Scope all plugin controllers under /eav with DashedRoute conventions.
        $routes->plugin('Eav', ['path' => '/eav'], function (RouteBuilder $builder): void {
            $builder->setRouteClass(DashedRoute::class);

            // Default to attributes index at /eav
            $builder->connect('/', ['controller' => 'EavAttributes', 'action' => 'index']);

            // Conventional fallbacks: /eav/<controller>/<action>/*
            $builder->fallbacks(DashedRoute::class);
        });
    }
}
