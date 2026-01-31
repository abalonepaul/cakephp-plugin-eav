<?php
declare(strict_types=1);

namespace Eav;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\JsonConfig;
use Cake\Core\Plugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Eav\Command\EavCreateAttributeCommand;
use Eav\Command\EavMigrateJsonbToEavCommand;
use Eav\Command\EavSetupCommand;
use Eav\Command\EavSetupInteractiveCommand;
use Throwable;

/**
 * Eav plugin bootstrap.
 *
 * Registers CLI commands and plugin routes.
 */
class EavPlugin extends BasePlugin
{
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        // Load plugins/Eav/config/eav.json into Configure under the 'Eav' namespace
        try {
            $configDir = Plugin::path('Eav') . 'config' . DIRECTORY_SEPARATOR;
            $configFile = $configDir . 'eav.json';
            if (is_file($configFile)) {
                $raw = file_get_contents($configFile);
                $eav_config = $raw !== false ? json_decode($raw, true) : null;
                if (is_array($eav_config)) {
                    // Write full config namespace for easy access
                    Configure::write('Eav', $eav_config);
                }
            }
        } catch (Throwable) {
            // Fail quietly; components/controllers fall back to hardcoded safe defaults
        }
    }

    /**
     * Register console commands.
     *
     * @param CommandCollection $commands Command collection.
     * @return CommandCollection
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
     * @param RouteBuilder $routes Route builder.
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
