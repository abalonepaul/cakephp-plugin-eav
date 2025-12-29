<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * @uses \Eav\Command\EavMigrateJsonbToEavCommand
 */
class EavMigrateJsonbToEavCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = [
        'plugin.Eav.EavAttributes',
        'plugin.Eav.EavString',
        'plugin.Eav.JsonEntities',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = ConnectionManager::get('test');
        $existing = $connection->getSchemaCollection()->listTables();
        $definitions = [];

        if (!in_array('json_entities', $existing, true)) {
            $jsonEntities = new TableSchema('json_entities');
            $jsonEntities
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                // jsonb so jsonb_exists(...) works without extra casts in tests
                ->addColumn('data', ['type' => 'json', 'null' => true])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            $definitions[] = $jsonEntities;
        }

        if (!in_array('eav_attributes', $existing, true)) {
            $schema = new TableSchema('eav_attributes');
            $schema
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                ->addColumn('name', ['type' => 'string', 'length' => 191, 'null' => false])
                ->addColumn('label', ['type' => 'string', 'length' => 255, 'null' => true])
                ->addColumn('data_type', ['type' => 'string', 'length' => 50, 'null' => false])
                ->addColumn('options', ['type' => 'json', 'null' => false])
                ->addColumn('created', ['type' => 'datetime', 'null' => false])
                ->addColumn('modified', ['type' => 'datetime', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            $definitions[] = $schema;
        }

        if (!in_array('eav_string', $existing, true)) {
            $schema = new TableSchema('eav_string');
            $schema
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                ->addColumn('entity_table', ['type' => 'string', 'length' => 191, 'null' => false])
                ->addColumn('entity_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('attribute_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('value', ['type' => 'string', 'length' => 1024, 'null' => true])
                ->addColumn('created', ['type' => 'datetime', 'null' => false])
                ->addColumn('modified', ['type' => 'datetime', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            $definitions[] = $schema;
        }

        // json_entities table is provided by the JsonEntitiesFixture; no need to create it here.

        if ($definitions !== []) {
            $connection->disableConstraints(function ($connection) use ($definitions): void {
                foreach ($definitions as $schema) {
                    foreach ($schema->createSql($connection) as $sql) {
                        $connection->execute($sql);
                    }
                }
            });
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $connection = ConnectionManager::get('test');
        // Truncate target and source tables; attributes can be empty (command creates attribute if needed)
        $connection->execute('TRUNCATE eav_attributes, eav_string, json_entities CASCADE');
    }

    public function testDryRun(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'INSERT INTO json_entities (id, data) VALUES (:id, :data)',
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'data' => json_encode(['color' => 'red'], JSON_THROW_ON_ERROR),
            ]
        );

        $this->exec('eav migrate_jsonb_to_eav json_entities data --attribute color --type string --dry-run --connection test');
        $this->assertExitSuccess();
        $this->assertOutputContains('Dry run: 1 values would be migrated.');

        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $this->assertSame(0, $EavString->find()->count());
    }

    public function testMigratesValues(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'INSERT INTO json_entities (id, data) VALUES (:id, :data)',
            [
                'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'data' => json_encode(['color' => 'blue'], JSON_THROW_ON_ERROR),
            ]
        );

        $this->exec('eav migrate_jsonb_to_eav json_entities data --attribute color --type string --connection test');
        $this->assertExitSuccess();
        $this->assertOutputContains('Migrated 1 values');

        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $row = $EavString->find()->where(['entity_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'])->first();
        $this->assertNotEmpty($row);
        $this->assertSame('blue', $row->value);
    }
}
