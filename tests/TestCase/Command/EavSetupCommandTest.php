<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * @uses \Eav\Command\EavSetupCommand
 */
class EavSetupCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function testDryRunOutputsMigration(): void
    {
        $this->exec('eav setup --dry-run --pk-type uuid --uuid-type uuid --json-storage json --connection test');
        $this->assertExitSuccess();
        $this->assertOutputContains('Dry run - migration not written.');
        $this->assertOutputContains('class EavSetup');
        $this->assertOutputContains('attributes');
        $this->assertOutputContains('attribute_sets');
    }
}
