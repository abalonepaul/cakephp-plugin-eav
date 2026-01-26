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
        $this->assertOutputContains('class EavSetup extends BaseMigration');
        $this->assertOutputContains("->table('eav_attributes'");
        $this->assertOutputContains("->table('eav_attribute_sets'");
        $this->assertOutputContains("->table('eav_entities'");
        $this->assertOutputContains("->addTimestamps('created', 'modified')");
        $this->assertOutputContains("->addColumn('placeholder'");
        $this->assertOutputContains("->addColumn('help_text'");

        // EAV-29: value tables schema (composite PK, no unique index)
        $this->assertOutputContains("->addColumn('eav_entity_id'");
        $this->assertOutputContains("->addColumn('eav_attribute_id'");
        $this->assertOutputContains("->addForeignKey('eav_entity_id', 'eav_entities', 'id'");
        $this->assertOutputContains("->addForeignKey('eav_attribute_id', 'eav_attributes', 'id'");
        // Composite primary key present
        $this->assertOutputContains("'primary_key' => ['eav_entity_id', 'entity_id', 'eav_attribute_id']");
        // No separate unique index on lookup columns under EAV-29
        $this->assertOutputNotContains("->addIndex(['eav_entity_id', 'entity_id', 'eav_attribute_id']");
        $this->assertOutputNotContains("->addColumn('entity_table'");
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
        $this->assertOutputContains('placeholder');
        $this->assertOutputContains('help_text');

        $this->assertOutputContains('CREATE TABLE IF NOT EXISTS eav_attribute_sets');
        $this->assertOutputContains('CREATE TABLE IF NOT EXISTS eav_entities');
        $this->assertOutputContains('CREATE TABLE IF NOT EXISTS eav_attribute_sets_eav_attributes');

        // EAV-29: value tables DDL checks (composite PK; no unique index)
        $this->assertOutputContains('PRIMARY KEY (eav_entity_id, entity_id, eav_attribute_id)');
        $this->assertOutputContains('FOREIGN KEY (eav_entity_id) REFERENCES eav_entities(id)');
        $this->assertOutputContains('FOREIGN KEY (eav_attribute_id) REFERENCES eav_attributes(id)');
        $this->assertOutputContains('eav_entity_id');
        $this->assertOutputContains('eav_attribute_id');
        $this->assertOutputNotContains('CREATE UNIQUE INDEX IF NOT EXISTS idx_eav_string_lookup');
        $this->assertOutputNotContains('entity_table');
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
            $this->assertOutputContains('class EavSetup extends BaseMigration');
        }

        @unlink($tmp);
    }
}
