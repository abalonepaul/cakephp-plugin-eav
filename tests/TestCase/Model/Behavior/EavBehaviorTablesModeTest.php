<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Behavior;

use Cake\Event\Event;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class EavBehaviorTablesModeTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.EavAttributes',
        'plugin.Eav.EavEntities',
        'plugin.Eav.EavString',
        'plugin.Eav.EavInteger',
        'plugin.Eav.TestEntities',
    ];

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->table = TableRegistry::getTableLocator()->get('TestEntities', [
            'className' => Table::class,
            'table' => 'test_entities',
        ]);
        $this->table->setPrimaryKey('id');
        // Ensure clean behavior config between tests (CakePHP 5.2 API)
        if ($this->table->hasBehavior('Eav')) {
            $this->table->removeBehavior('Eav');
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->table) && $this->table->hasBehavior('Eav')) {
            $this->table->removeBehavior('Eav');
        }
        unset($this->table);
        parent::tearDown();
    }

    public function testBeforeMarshalAndAfterSavePersistsAttribute(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'color' => ['type' => 'string'],
            ],
        ]);

        $id = '88888888-8888-8888-8888-888888888888';
        $entity = $this->table->newEntity(['id' => $id, 'color' => 'blue']);
        $this->assertNotFalse($this->table->save($entity), 'Save should succeed');

        // Verify persisted to eav_string with correct composite keys
        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $row = $EavString->find()
            ->where([
                'eav_entity_id' => '00000000-0000-0000-0000-000000000001', // from EavEntitiesFixture for test_entities
                'entity_id' => $id,
            ])
            ->first();
        $this->assertNotNull($row, 'Expected a row in eav_string');
        $this->assertSame('blue', $row->get('value'));
    }

    public function testPersistFalseSkipsWrite(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'transient_flag' => ['type' => 'string', 'persist' => false],
            ],
        ]);

        $id = '99999999-9999-9999-9999-999999999999';
        $entity = $this->table->newEntity(['id' => $id, 'transient_flag' => 'x']);
        $this->assertNotFalse($this->table->save($entity), 'Save should succeed');

        // No row should be created in any EAV table and no attribute definition should be inserted
        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $row = $EavString->find()->where(['entity_id' => $id])->first();
        $this->assertNull($row, 'persist=false must not write any EAV row');

        $EavAttributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $attr = $EavAttributes->find()->where(['name' => 'transient_flag'])->first();
        $this->assertNull($attr, 'persist=false must not create attribute definition');
    }

    public function testWhereArrayRewritesAndProjects(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        $row = $this->table->find()->where(['color' => 'red'])->firstOrFail();
        $this->assertSame('22222222-2222-2222-2222-222222222222', $row->get('id'));
        $this->assertSame('red', $row->get('color'));
    }

    public function testStringConditionIsNotNullInOptions(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Pass conditions as a string to hit the string branch in beforeFind (tables mode)
        $results = $this->table->find()
            ->applyOptions(['conditions' => 'color IS NOT NULL'])
            ->all();
        $ids = $results->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    public function testOrderByAttributeViaOptionsNullsLast(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Expect non-null color (id=2222...) first, null (id=3333...) last
        $results = $this->table->find()->applyOptions(['order' => ['color' => 'ASC']])->all();
        $results = $this->table->find('all', options: ['order' => ['color' => 'ASC']])->all();
        $ids = $results->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $ids
        );
    }

    public function testOrderByAttributeViaMethodOrderByAsc(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        $results = $this->table->find()->orderByAsc('color')->all();

        $ids = $results->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $ids
        );
    }

    public function testOrderByAttributeViaOptionsStringForm(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Use string-form order item to hit the string branch ('color DESC')
        $results = $this->table->find()
            ->applyOptions(['order' => ['color DESC']])
            ->all();

        $ids = $results->map(fn($r) => $r->get('id'))->toList();
        // Non-null first in DESC order, then null
        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $ids
        );
    }

    public function testOrderByAttributeViaOptionsAssociativeWithNativeRetention(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Attribute order should be rewritten; native 'id' should remain in options (retained as remainingOrder)
        $results = $this->table->find()
            ->applyOptions(['order' => ['color' => 'ASC', 'id' => 'DESC']])
            ->all();

        $ids = $results->map(fn($r) => (string)$r->get('id'))->toList();
        // color ASC => 2222... first (red), then 3333... (nulls last emulation)
        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $ids
        );
    }

    public function testOptionsArrayConditionsLike(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Exercise LIKE branch in options['conditions'] (tables mode)
        //$row = $this->table->find()
        //    ->applyOptions(['conditions' => ['color LIKE' => 're%']])
        //    ->firstOrFail();
        $row = $this->table->find('all', options: ['conditions' => ['color LIKE' => 're%']])
            ->firstOrFail();

        $this->assertSame('22222222-2222-2222-2222-222222222222', (string)$row->get('id'));
        $this->assertSame('red', $row->get('color'));
    }

    public function testIlikeFallbackOnNonPostgres(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        $row = $this->table->find()->where(['color ILIKE' => 'RE%'])->firstOrFail();
        $this->assertSame('22222222-2222-2222-2222-222222222222', $row->get('id'));
        $this->assertSame('red', $row->get('color'));
    }

    public function testOptionsArrayConditionsIlike(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Exercise options['conditions'] array path with ILIKE operator
        $row = $this->table->find()
            ->applyOptions(['conditions' => ['color ILIKE' => 'RE%']])
            ->firstOrFail();

        $this->assertSame('22222222-2222-2222-2222-222222222222', $row->get('id'));
        $this->assertSame('red', $row->get('color'));
    }

    public function testOptionsUnknownAttributeMakesQueryImpossible(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        $rows = $this->table->find()
            ->applyOptions(['conditions' => ['no_such_attr' => 'x']])
            ->all();

        $this->assertCount(0, $rows);
    }



    public function testInOperatorRewriting(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        $row = $this->table->find()->where(['color IN' => ['red', 'blue']])->firstOrFail();
        $this->assertSame('22222222-2222-2222-2222-222222222222', $row->get('id'));
    }

    public function testIsNullLeftJoin(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        $rows = $this->table->find()->where(['color IS' => null])->all();
        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(['33333333-3333-3333-3333-333333333333'], $ids);
    }

    public function testIsNullUnaryExpressionLeftJoin(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Use closure to build a UnaryExpression and hit rewriteTablesWhereTree's unary branch
        $rows = $this->table->find()
            ->where(function ($exp) {
                return $exp->isNull('color');
            })
            ->all();

        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(['33333333-3333-3333-3333-333333333333'], $ids);
    }

    public function testAfterFindBatchMergeWithoutExplicitSelect(): void
    {
        // afterFind requires attributes map to merge values
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'color' => ['type' => 'string'],
            ],
        ]);

        // Hint the attribute type so beforeFind pre-projects "color" and afterFind merges deterministically
        $rows = $this->table->find()
            ->applyOptions(['eavTypes' => ['color' => 'string']])
            ->select(['id'])
            ->orderByAsc('id')
            ->all();

        $list = $rows->map(fn($r) => [$r->get('id'), $r->get('color')])->toList();

        $this->assertSame('red', $list[0][1]); // 2222... has 'red'
        $this->assertNull($list[1][1]);        // 3333... has no color row
    }

    public function testManualAfterFindInvokesBatchMerge(): void
    {
        // Configure attributes for afterFind merge
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'color' => ['type' => 'string'],
            ],
        ]);

        $query = $this->table->find()->select(['id'])->orderByAsc('id');
        // Manually invoke behavior's afterFind to register formatResults callback
        $behavior = $this->table->behaviors()->get('Eav');
        $behavior->afterFind(new Event('Model.afterFind'), $query, new \ArrayObject([]), true);

        $rows = $query->all();
        $list = $rows->map(fn($r) => [$r->get('id'), $r->get('color')])->toList();

        $this->assertSame('red', $list[0][1]);
        $this->assertNull($list[1][1]);
    }

    public function testProjectionViaEavTypesOption(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Pre-project color via eavTypes so it becomes a selected alias without selecting a non-existent native column
        $rows = $this->table->find()
            ->applyOptions(['eavTypes' => ['color' => 'string']])
            ->select(['id'])
            ->all();

        // Assert values per row without using array keys (to avoid PHPStan key-type warnings)
        foreach ($rows as $row) {
            $id = (string)$row->get('id');
            if ($id === '22222222-2222-2222-2222-222222222222') {
                $this->assertSame('red', $row->get('color'));
            }
            if ($id === '33333333-3333-3333-3333-333333333333') {
                $this->assertNull($row->get('color'));
            }
        }
    }

    public function testUnknownAttributeConditionMakesQueryImpossible(): void
    {
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Attribute "no_such_attr" does not exist in EavAttributesFixture
        $rows = $this->table->find()->where(['no_such_attr' => 'x'])->all();
        $this->assertCount(0, $rows, 'Unknown attribute should yield 0 rows (0=1 guard)');
    }
}
