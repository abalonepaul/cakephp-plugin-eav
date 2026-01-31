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
        'plugin.Eav.EavAttributes',
        'plugin.Eav.EavEntities',
        'plugin.Eav.EavString',
        'plugin.Eav.EavJson',
        'plugin.Eav.EavInteger',
        'plugin.Eav.TestEntities',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Schema for test_entities is provided by TestEntitiesFixture.
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
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->Entities);
        parent::tearDown();
    }

    public function testWhereEqualityRewriting(): void
    {
        $rows = $this->Entities->find()->where(['color' => 'red'])->all()->toList();

        $this->assertCount(1, $rows);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $rows[0]->id);
        $this->assertSame('red', $rows[0]->color);
    }

    public function testIsNullSemantics(): void
    {
        $rows = $this->Entities->find()->where(['color IS' => null])->all()->toList();

        $this->assertCount(1, $rows);
        $this->assertSame('33333333-3333-3333-3333-333333333333', $rows[0]->id);
        $this->assertNull($rows[0]->color);
    }

    public function testInLikeIlikeAndRangeOnAttributes(): void
    {
        // LIKE
        $likeIds = $this->Entities->find()
            ->where(['color LIKE' => 're%'])
            ->all()
            ->extract('id')
            ->toList();
        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $likeIds);

        // ILIKE (native on PG; emulated otherwise)
        $ilikeIds = $this->Entities->find()
            ->where(['color ILIKE' => 'RE%'])
            ->all()
            ->extract('id')
            ->toList();
        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ilikeIds);

        // Range on integer attribute year_start (>= and <)
        $gte = $this->Entities->find()
            ->where(['year_start >=' => 2010])
            ->all()
            ->extract('id')
            ->toList();
        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $gte);

        $lt = $this->Entities->find()
            ->where(['year_start <' => 2015])
            ->all()
            ->extract('id')
            ->toList();
        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $lt);
    }

    public function testOrderByAttributeNullsLast(): void
    {
        $rows = $this->Entities->find()
            ->orderByAsc('color')
            ->all()
            ->toList();

        $this->assertCount(2, $rows);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $rows[0]->id);
        $this->assertSame('33333333-3333-3333-3333-333333333333', $rows[1]->id);
    }

    public function testTypedOrderOnIntegerWithNullsLast(): void
    {
        $rows = $this->Entities->find()
            ->orderBy(['year_start' => 'ASC'])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $rows
        );
    }

    public function testOrderByNamedArgumentTables(): void
    {
        // Attribute order via named-argument order should be rewritten and preserve native ordering semantics
        $ids = $this->Entities->find('all', order: ['color' => 'ASC'])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $ids
        );
    }

    public function testAliasMappingHydrationAndPersist(): void
    {
        // Reattach with alias mapping sku => color and persist
        $this->Entities->removeBehavior('Eav');
        $this->Entities->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'sku' => ['attribute' => 'color', 'type' => 'string', 'persist' => true],
            ],
        ]);

        // Persist via mapped alias (updates eav_string 'color')
        $entity = $this->Entities->get('22222222-2222-2222-2222-222222222222');
        $entity->set('sku', 'blue');
        $this->Entities->saveOrFail($entity);

        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $colorAttr = $Attributes->find()
            ->select(['id'])
            ->where(['name' => 'color'])
            ->enableHydration(false)
            ->firstOrFail();

        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $row = $EavString->find()
            ->where([
                'eav_entity_id' => '00000000-0000-0000-0000-000000000001', // test_entities
                'entity_id' => '22222222-2222-2222-2222-222222222222',
                'eav_attribute_id' => $colorAttr['id'],
            ])
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('blue', $row->get('value'));
    }

    public function testAfterSavePersistsConfiguredAttributes(): void
    {
        // Reattach behavior with attributes config (persist=true by default)
        $this->Entities->removeBehavior('Eav');
        $this->Entities->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'color' => ['type' => 'string', 'persist' => true],
            ],
        ]);

        // Create a new entity and set an attribute configured to persist
        $newId = '44444444-4444-4444-4444-444444444444';
        $entity = $this->Entities->newEntity(['id' => $newId]);
        $entity->set('color', 'blue');
        $this->Entities->saveOrFail($entity);

        // Verify a row exists in eav_string for this entity/attribute
        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $colorAttr = $Attributes->find()->select(['id'])->where(['name' => 'color'])->enableHydration(false)->firstOrFail();

        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $row = $EavString->find()
            ->where([
                'eav_entity_id' => '00000000-0000-0000-0000-000000000001', // test_entities
                'entity_id' => $newId,
                'eav_attribute_id' => $colorAttr['id'],
            ])
            ->first();

        $this->assertNotNull($row, 'Expected persisted EAV value for "color"');
        $this->assertSame('blue', $row->get('value'));
    }

    public function testUnknownAttributeConditionYieldsNoRows(): void
    {
        $count = $this->Entities->find()->where(['does_not_exist' => 'x'])->count();
        $this->assertSame(0, $count);
    }

    public function testFindByAttributeFinder(): void
    {
        // Pass finder options via named "options" to match finder(Query $q, array $options) signature
        $ids = $this->Entities->find(
            'byAttribute',
            options: [
                'attribute' => 'color',
                'type' => 'string',
                'op' => '=',
                'value' => 'red',
            ]
        )->all()->extract('id')->toList();

        $this->assertContains('22222222-2222-2222-2222-222222222222', $ids);
    }

    public function testGroupedOrConditionRewrite(): void
    {
        $ids = $this->Entities->find()
            ->where(['OR' => [
                ['color' => 'red'],
                ['year_start >=' => 2010],
            ]])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertContains('22222222-2222-2222-2222-222222222222', $ids);
    }

    // Supported forms for IS NOT NULL (string-based)
    public function testIsNotNullStringOperator(): void
    {
        $ids = $this->Entities->find('all')
            ->where('color IS NOT NULL')
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    public function testIsNotNullArrayOperator(): void
    {
        $ids = $this->Entities->find('all')
            ->where(['color IS NOT NULL'])
            ->all()
            ->extract('id')
            ->toList();

        // Only 2222... has color
        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    // Unsupported forms: we assert framework-level exceptions so behavior stays honest
    public function testIsNotEqualOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Cake 5.2 rejects "!= null" in array-form comparisons
        $this->Entities->find('all')
            ->where(['color !=' => null])
            ->all()
            ->extract('id')
            ->toList();
    }

    public function testNotEqualNullOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Named-argument conditions with "!= null" are also rejected by Cake 5.2
        $this->Entities->find('all', conditions: ['color !=' => null])
            ->all()
            ->toList();
    }

    public function testPerQueryEavTypesOverrideForOrder(): void
    {
        $ids = $this->Entities->find(options: ['eavTypes' => ['year_start' => 'integer']])
            ->orderBy(['year_start' => 'ASC'])
            ->all()
            ->extract('id')
            ->toList();

        // Non-null comes first; null last
        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $ids
        );
    }

    public function testOrderMixedNativeAndAttribute(): void
    {
        // Mix attribute ordering (color) with native field (id) to ensure native parts are preserved
        $ids = $this->Entities->find()
            ->orderBy(['color' => 'ASC', 'id' => 'ASC'])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(
            ['22222222-2222-2222-2222-222222222222', '33333333-3333-3333-3333-333333333333'],
            $ids
        );
    }

    public function testNotInOperator(): void
    {
        // NOT IN excludes rows with missing attribute (INNER JOIN semantics for value predicates)
        $ids = $this->Entities->find()
            ->where(['color NOT IN' => ['blue']]) // blue doesn't exist; red should match (2222...)
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    public function testFindByAttributeUnsupportedOperatorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Call the behavior's finder directly with a Query and options array to exercise the operator guard
        $behavior = $this->Entities->behaviors()->get('Eav');
        $query = $this->Entities->find();
        $behavior->findByAttribute($query, [
            'attribute' => 'color',
            'attrType' => 'string', // prefer attrType per Cake 5.2 (avoid clashing with Table::find's "type")
            'op' => 'BETWEEN',
            'value' => ['a', 'b'],
        ]);
    }

    public function testUnknownAttributeStringIsNullRaisesQueryException(): void
    {
        // Unknown attribute with "IS NULL" in string form should leak to SQL per current behavior (documented)
        $this->expectException(\Cake\Database\Exception\QueryException::class);
        $this->Entities->find()
            ->where('does_not_exist IS NULL')
            ->all()
            ->toList();
    }

    public function testUnknownAttributeStringIsNotNullYieldsNoRows(): void
    {
        $ids = $this->Entities->find()
            ->where('does_not_exist IS NOT NULL')
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame([], $ids, 'Unknown attribute IS NOT NULL should yield no rows');
    }

    public function testFindByAttributeWithInOperator(): void
    {
        $ids = $this->Entities->find(
            'byAttribute',
            attribute: 'color',
            attrType: 'string',
            op: 'IN',
            value: ['red', 'blue'] // 'red' exists; 'blue' will be written in another test
        )->all()->extract('id')->toList();

        $this->assertContains('22222222-2222-2222-2222-222222222222', $ids, 'Expected entity with color=red');
    }

    public function testFindByAttributeWithIlikeOperator(): void
    {
        $ids = $this->Entities->find(
            'byAttribute',
            attribute: 'color',
            attrType: 'string',
            op: 'ILIKE',
            value: 'RE%' // should match 'red'
        )->all()->extract('id')->toList();

        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    public function testFindByAttributeWithLikeOperator(): void
    {
        $ids = $this->Entities->find(
            'byAttribute',
            attribute: 'color',
            attrType: 'string',
            op: 'LIKE',
            value: 're%'
        )->all()->extract('id')->toList();

        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    public function testIsNullNamedArgumentConditionsTables(): void
    {
        $ids = $this->Entities->find('all', conditions: ['color IS NULL'])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(['33333333-3333-3333-3333-333333333333'], $ids);
    }

    public function testFlatArrayConditionsLikeTables(): void
    {
        // Named-argument conditions array path exercises beforeFind flat-array rewriter
        $ids = $this->Entities->find('all', conditions: ['color LIKE' => 're%'])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    public function testFlatArrayConditionsEqualityTables(): void
    {
        $ids = $this->Entities->find('all', conditions: ['color' => 'red'])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame(['22222222-2222-2222-2222-222222222222'], $ids);
    }

    public function testFindByAttributeWithNotInOperatorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->Entities->find(
            'byAttribute',
            attribute: 'color',
            attrType: 'string',
            op: 'NOT IN',
            value: ['red']
        )->all()->toList();
    }

    public function testFindByAttributeUnknownAttributeYieldsNoRows(): void
    {
        $count = $this->Entities->find(
            'byAttribute',
            attribute: 'no_such_attr',
            attrType: 'string',
            op: '=',
            value: 'x'
        )->count();
        $this->assertSame(0, $count);
    }

    public function testBeforeMarshalCapturesAndPersistsOnSave(): void
    {
        // Reattach behavior with attributes config to exercise beforeMarshal + afterSave flow
        $this->Entities->removeBehavior('Eav');
        $this->Entities->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'color' => ['type' => 'string', 'persist' => true],
            ],
        ]);

        $newId = '55555555-5555-5555-5555-555555555555';
        // beforeMarshal should capture 'color' from request data path
        $entity = $this->Entities->newEntity(['id' => $newId, 'color' => 'green']);
        $this->Entities->saveOrFail($entity);

        // Verify persisted into eav_string for the captured attribute
        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $colorAttr = $Attributes->find()->select(['id'])->where(['name' => 'color'])->enableHydration(false)->firstOrFail();

        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $row = $EavString->find()
            ->where([
                'eav_entity_id' => '00000000-0000-0000-0000-000000000001', // test_entities
                'entity_id' => $newId,
                'eav_attribute_id' => $colorAttr['id'],
            ])->first();

        $this->assertNotNull($row);
        $this->assertSame('green', $row->get('value'));
    }

    public function testAfterFindHydratesMappedAttributes(): void
    {
        // Reattach behavior with attributes map
        $this->Entities->removeBehavior('Eav');
        $this->Entities->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'color' => ['type' => 'string'],
            ],
        ]);

        // Use a rewritten condition that projects the attribute alias (no base column leak)
        $row = $this->Entities->find()
            ->where(['id' => '22222222-2222-2222-2222-222222222222', 'color ILIKE' => 're%'])
            ->firstOrFail();

        $this->assertSame('red', $row->get('color'));
    }
}
