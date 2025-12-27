<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Behavior;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * Tables Storage Mode behavior tests.
 * Verifies automatic WHERE and ORDER rewriting for attributes on eav_* tables.
 */
class EavTablesStorageModeTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.Attributes',
        'plugin.Eav.EavString',
        'plugin.Eav.TestEntities',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $existing = $connection->getSchemaCollection()->listTables();

        if (!in_array('test_entities', $existing, true)) {
            $schema = new \Cake\Database\Schema\TableSchema('test_entities');
            $schema
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);

            $connection->disableConstraints(function ($connection) use ($schema): void {
                foreach ($schema->createSql($connection) as $sql) {
                    $connection->execute($sql);
                }
            });
        }
    }

    private Table $Entities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->Entities = TableRegistry::getTableLocator()->get('TestEntities', [
            'className' => Table::class,
            'table' => 'test_entities',
        ]);
        $this->Entities->setPrimaryKey('id');

        // Attach behavior in tables storage mode; no explicit map required for rewriting
        $this->Entities->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            // Optional: overrides can be passed per-query with eavTypes; not needed here
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->Entities);
        parent::tearDown();
    }

    public function testWhereEqualityRewriting(): void
    {
        // color = 'red' should match entity_id 2222... only (joined to eav_string)
        $rows = $this->Entities->find()->where(['color' => 'red'])->all()->toList();

        $this->assertCount(1, $rows);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $rows[0]->id);
        // Projected alias should be available (cast by select type map when applicable)
        $this->assertSame('red', $rows[0]->color);
    }

    public function testIsNullSemantics(): void
    {
        // color IS NULL should match the entity without an attribute row (3333...)
        $rows = $this->Entities->find()->where(['color IS' => null])->all()->toList();

        $this->assertCount(1, $rows);
        $this->assertSame('33333333-3333-3333-3333-333333333333', $rows[0]->id);
        $this->assertNull($rows[0]->color);
    }

    public function testOrderByAttributeNullsLast(): void
    {
        // ORDER BY color ASC with NULLS LAST: 'red' first, null second
        $rows = $this->Entities->find()
            ->orderByAsc('color')
            ->all()
            ->toList();

        $this->assertCount(2, $rows);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $rows[0]->id); // red
        $this->assertSame('33333333-3333-3333-3333-333333333333', $rows[1]->id); // null
    }
}
