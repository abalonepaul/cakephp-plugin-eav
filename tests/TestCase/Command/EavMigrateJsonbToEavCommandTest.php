<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Command;

use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class EavMigrateJsonbToEavCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = [
        'plugin.Eav.Attributes',
        'plugin.Eav.AvString',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = ConnectionManager::get('test');
        $existing = $connection->getSchemaCollection()->listTables();
        $definitions = [];

        if (!in_array('attributes', $existing, true)) {
            $schema = new TableSchema('attributes');
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

        if (!in_array('av_string_uuid', $existing, true)) {
            $schema = new TableSchema('av_string_uuid');
            $schema
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                ->addColumn('entity_table', ['type' => 'string', 'length' => 191, 'null' => false])
                ->addColumn('entity_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('attribute_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('val', ['type' => 'string', 'length' => 1024, 'null' => false])
                ->addColumn('created', ['type' => 'datetime', 'null' => false])
                ->addColumn('modified', ['type' => 'datetime', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            $definitions[] = $schema;
        }

        if (!in_array('test_json_entities', $existing, true)) {
            $connection->execute(
                'CREATE TABLE test_json_entities (
                    id UUID PRIMARY KEY,
                    data JSONB NOT NULL
                )'
            );
        }

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
        $connection->execute('TRUNCATE attributes, av_string_uuid, test_json_entities');
    }

    public function testDryRun(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'INSERT INTO test_json_entities (id, data) VALUES (:id, :data)',
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'data' => json_encode(['color' => 'red'], JSON_THROW_ON_ERROR),
            ]
        );

        $this->exec('eav migrate_jsonb_to_eav test_json_entities data --attribute color --type string --dry-run');
        $this->assertExitSuccess();
        $this->assertOutputContains('Dry run: 1 values would be migrated.');

        $AvString = TableRegistry::getTableLocator()->get('Eav.AvStringUuid');
        $this->assertSame(0, $AvString->find()->count());
    }

    public function testMigratesValues(): void
    {
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'INSERT INTO test_json_entities (id, data) VALUES (:id, :data)',
            [
                'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'data' => json_encode(['color' => 'blue'], JSON_THROW_ON_ERROR),
            ]
        );

        $this->exec('eav migrate_jsonb_to_eav test_json_entities data --attribute color --type string');
        $this->assertExitSuccess();
        $this->assertOutputContains('Migrated 1 values');

        $AvString = TableRegistry::getTableLocator()->get('Eav.AvStringUuid');
        $row = $AvString->find()->where(['entity_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'])->first();
        $this->assertNotEmpty($row);
        $this->assertSame('blue', $row->val);
    }
}
