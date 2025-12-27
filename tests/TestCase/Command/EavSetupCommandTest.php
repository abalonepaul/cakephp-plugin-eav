<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Command;

use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * @uses \Eav\Command\EavSetupCommand
 */
class EavSetupCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function testMigrationDryRunOutputsMigration(): void
    {
        // Non-interactive (flags provided); ensure migration is emitted to stdout on dry-run
        $this->exec('eav setup --dry-run --connection test --pk-type uuid --uuid-type uuid --json-storage json --types defaults');

        $this->assertExitSuccess();
        $this->assertOutputContains('Dry run - migration not written.');
        $this->assertOutputContains('EAV Setup Migration');
        $this->assertOutputContains('class EavSetup extends AbstractMigration');
        $this->assertOutputContains("->table('eav_attributes'");
        $this->assertOutputContains("->table('eav_attribute_sets'");
        $this->assertOutputContains("->addTimestamps('created', 'modified')");
    }

    public function testRawSqlDryRunOnSupportedDrivers(): void
    {
        $conn = ConnectionManager::get('test');
        $driver = $conn->getDriver();

        $isPg = $driver instanceof \Cake\Database\Driver\Postgres;
        $isMy = $driver instanceof \Cake\Database\Driver\Mysql;

        if (!$isPg && !$isMy) {
            $this->markTestSkipped('Raw SQL path is only supported for Postgres/MySQL drivers.');
        }

        $this->exec('eav setup --dry-run --connection test --output raw_sql --pk-type uuid --uuid-type uuid --json-storage json --types defaults');

        $this->assertExitSuccess();
        $this->assertOutputContains('Dry run - SQL not written.');
        $this->assertOutputContains('EAV Setup SQL');
        $this->assertOutputContains('CREATE TABLE IF NOT EXISTS eav_attributes');
        $this->assertOutputContains('CREATE TABLE IF NOT EXISTS eav_attribute_sets');
        $this->assertOutputContains('CREATE TABLE IF NOT EXISTS eav_attribute_sets_eav_attributes');
        $this->assertOutputContains('CREATE UNIQUE INDEX IF NOT EXISTS idx_eav_string_lookup');
    }

    public function testConfigFileRespectedForOutputModeAndTypes(): void
    {
        $conn = ConnectionManager::get('test');
        $driver = $conn->getDriver();

        $isPg = $driver instanceof \Cake\Database\Driver\Postgres;
        $isMy = $driver instanceof \Cake\Database\Driver\Mysql;

        // Build a temporary eav.json
        $tmp = tempnam(sys_get_temp_dir(), 'eav_cfg_');
        $this->assertNotFalse($tmp, 'Failed to allocate temp file');

        $cfg = [
            'connection' => 'test',
            'driver' => get_class($driver),
            'outputMode' => ($isPg || $isMy) ? 'raw_sql' : 'migrations',
            'pkType' => 'uuid',
            'uuidType' => 'uuid',
            'jsonAttributeStorage' => $isPg ? 'jsonb' : 'json',
            'jsonEncodeOnWrite' => false,
            'storageDefault' => 'tables',
            'jsonColumns' => (object)[],
            'types' => ['string', 'json', 'integer', 'fk'],
            'migrationName' => 'EavSetup',
            'generatedAt' => gmdate('c'),
        ];
        file_put_contents($tmp, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Use --config; also pass --dry-run so no files are written
        $this->exec(sprintf('eav setup --config %s --connection test --dry-run', escapeshellarg($tmp)));

        $this->assertExitSuccess();

        if ($isPg || $isMy) {
            $this->assertOutputContains('EAV Setup SQL', 'Expected raw SQL output when driver supports it');
            $this->assertOutputContains('CREATE TABLE IF NOT EXISTS eav_attributes');
        } else {
            $this->assertOutputContains('EAV Setup Migration', 'Expected fallback to migrations on unsupported driver');
            $this->assertOutputContains('class EavSetup extends AbstractMigration');
        }

        @unlink($tmp);
    }
}
