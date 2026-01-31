<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Behavior;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class EavBehaviorJsonColumnModeTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.Items',
    ];

    private Table $table;

    protected function setUp(): void
    {
        parent::setUp();
        // Use existing Items fixture with 'attrs' JSON column
        $this->table = TableRegistry::getTableLocator()->get('Items', [
            'className' => Table::class,
            'table' => 'items',
        ]);
        $this->table->setPrimaryKey('id');
        if ($this->table->hasBehavior('Eav')) {
            $this->table->removeBehavior('Eav');
        }

        // Attach behavior in JSON column mode targeting 'attrs'
        $this->table->addBehavior('Eav.Eav', [
            'entityTable' => 'items',
            'pkType' => 'uuid',
            'storage' => 'json_column',
            'jsonColumn' => 'attrs',
            // Provide types for afterFind casting
            'attributes' => [
                'color' => ['type' => 'string'],
                'year_start' => ['type' => 'integer'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->table) && $this->table->hasBehavior('Eav')) {
            $this->table->removeBehavior('Eav');
        }
        unset($this->table);
        parent::tearDown();
    }

    public function testAfterSaveJsonColumnSetsKeys(): void
    {
        // Insert a row; afterSave should jsonb_set keys based on buffered request data/entity values
        $id = 'dddddddd-dddd-dddd-dddd-dddddddddddd';
        $entity = $this->table->newEntity([
            'id' => $id,
            'name' => 'Delta',
            'color' => 'blue',
            'year_start' => 2010,
        ]);
        $this->assertNotFalse($this->table->save($entity));

        // Reload and verify attrs contains keys with proper types
        $row = $this->table->get($id);
        $attrs = $row->get('attrs');
        $this->assertIsArray($attrs);
        $this->assertSame('blue', $attrs['color'] ?? null);
        $this->assertSame(2010, $attrs['year_start'] ?? null);
    }

    public function testJsonModeProjectionWhereAndOrderAndCasting(): void
    {
        // Query existing seeded rows (Alpha, Beta, Gamma)
        $rows = $this->table->find()
            ->applyOptions([
                // Hints drive casting and projections in JSON mode
                'eavTypes' => ['color' => 'string', 'year_start' => 'integer'],
                'order' => ['color' => 'ASC'],               // NULLS LAST expected
                'conditions' => ['color IS NOT NULL'],       // exclude Beta
            ])
            ->select(['id', 'color', 'year_start'])
            ->all();

        // Alpha (red) and Gamma (blue) should remain, ordered by color ASC => blue, red
        $this->assertCount(2, $rows);
        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(
            ['cccccccc-cccc-cccc-cccc-cccccccccccc', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'],
            $ids,
            'Expected Gamma (blue) then Alpha (red)'
        );

        // Verify types for year_start are integers due to casting
        $first = $rows->toList()[0];  // Gamma
        $second = $rows->toList()[1]; // Alpha
        $this->assertIsInt($first->get('year_start'));
        $this->assertIsInt($second->get('year_start'));
        $this->assertSame(2015, $first->get('year_start'));
        $this->assertSame(2010, $second->get('year_start'));
    }

    public function testJsonModeNumericKeyStringConditionOption(): void
    {
        // Use numeric-key string in conditions array to hit the options['conditions'] numeric-key string branch
        $rows = $this->table->find()
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
                'conditions' => ['color IS NOT NULL'],
                'order' => ['color' => 'ASC'],
            ])
            ->select(['id', 'color'])
            ->all();

        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(
            ['cccccccc-cccc-cccc-cccc-cccccccccccc', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'],
            $ids
        );
    }

    public function testJsonModeOrderStringForm(): void
    {
        // Use string-form order item to hit the JSON mode 'field DESC' parsing; exclude NULLs for deterministic order
        $rows = $this->table->find()
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
                'conditions' => ['color IS NOT NULL'],
                'order' => ['color DESC'],
            ])
            ->select(['id', 'color'])
            ->all();

        // With NULLs excluded, DESC should be red then blue => Alpha then Gamma
        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(
            ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'cccccccc-cccc-cccc-cccc-cccccccccccc'],
            $ids
        );
    }

    public function testJsonModeOrderAssociativeWithNativeRetention(): void
    {
        // Attribute order should be rewritten; native 'id' should be retained in options (remainingOrder)
        $rows = $this->table->find()
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
                'order' => ['color' => 'ASC', 'id' => 'DESC'],
            ])
            ->select(['id', 'color'])
            ->all();

        // Expect color ASC with NULLS LAST: blue (Gamma), red (Alpha), then null (Beta)
        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(
            ['cccccccc-cccc-cccc-cccc-cccccccccccc', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'],
            $ids
        );
    }

    public function testJsonModeConditionsArrayNotIn(): void
    {
        // Exclude a known color (blue) so only Alpha (red) remains (NULLs excluded by NOT IN semantics)
        $rows = $this->table->find()
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
                'conditions' => ['color NOT IN' => ['blue']],
            ])
            ->select(['id', 'color'])
            ->all();

        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'], $ids);
    }

    public function testJsonModeStringConditionsIsNull(): void
    {
        // Exercise string conditions branch with "IS NULL" in JSON mode
        $rows = $this->table->find()
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
                'conditions' => 'color IS NULL',
            ])
            ->select(['id', 'color'])
            ->all();

        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(['bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'], $ids);
    }

    public function testJsonModeSelectClauseProjectionRebuild(): void
    {
        // Selecting 'color' directly should be rebuilt into a projection, not native column access
        $rows = $this->table->find()
            ->applyOptions(['eavTypes' => ['color' => 'string']])
            ->select(['id', 'color'])
            ->orderByAsc('id')
            ->all();

        // color should be available as projection (Alpha: red, Beta: null, Gamma: blue)
        $list = $rows->map(fn($r) => [$r->get('id'), $r->get('color')])->toList();
        $this->assertSame('red', $list[0][1]);   // Alpha
        $this->assertNull($list[1][1]);          // Beta
        $this->assertSame('blue', $list[2][1]);  // Gamma
    }

    public function testJsonModeUnaryIsNull(): void
    {
        // Use closure to generate a UnaryExpression isNull('color') and hit rewriteJsonWhereTree unary branch
        $rows = $this->table->find()
            ->where(function ($exp) {
                return $exp->isNull('color');
            })
            ->applyOptions(['eavTypes' => ['color' => 'string']])
            ->select(['id'])
            ->all();

        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(['bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'], $ids);
    }

    public function testJsonModeManualAfterFindCasts(): void
    {
        // Build a base query; beforeFind will handle projections. Manually invoke afterFind to register casting.
        $query = $this->table->find()
            ->applyOptions(['eavTypes' => ['color' => 'string', 'year_start' => 'integer']])
            ->select(['id', 'color', 'year_start'])
            ->orderByAsc('id');

        $behavior = $this->table->behaviors()->get('Eav');
        $behavior->afterFind(new \Cake\Event\Event('Model.afterFind'), $query, new \ArrayObject([]), true);

        $rows = $query->all();
        // Assert types are correctly cast when present
        foreach ($rows as $row) {
            if ($row->get('year_start') !== null) {
                $this->assertIsInt($row->get('year_start'));
            }
            if ($row->get('color') !== null) {
                $this->assertIsString($row->get('color'));
            }
        }
    }

    public function testJsonModeUnaryExpressionIsNull(): void
    {
        // color IS NULL should return only Beta
        $rows = $this->table->find()
            ->where(function ($exp) {
                return $exp->isNull('color');
            })
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
            ])
            ->select(['id'])
            ->all();

        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        $this->assertSame(['bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'], $ids);
    }

    public function testJsonModeNumericComparisonCasts(): void
    {
        // year_start > 2010 should return only Gamma (2015)
        $rows = $this->table->find()
            ->where(['year_start >' => 2010])
            ->applyOptions([
                'eavTypes' => ['year_start' => 'integer'],
            ])
            ->select(['id', 'year_start'])
            ->all();

        $this->assertCount(1, $rows);
        $r = $rows->first();
        $this->assertSame('cccccccc-cccc-cccc-cccc-cccccccccccc', $r->get('id'));
        $this->assertIsInt($r->get('year_start'));
        $this->assertSame(2015, $r->get('year_start'));
    }

    public function testJsonModeIlike(): void
    {
        // ILIKE on color should match Gamma (blue)
        $rows = $this->table->find()
            ->where(['color ILIKE' => 'BL%'])
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
            ])
            ->select(['id', 'color'])
            ->all();

        $this->assertCount(1, $rows);
        $r = $rows->first();
        $this->assertSame('cccccccc-cccc-cccc-cccc-cccccccccccc', $r->get('id'));
        $this->assertSame('blue', $r->get('color'));
    }

    public function testJsonModeStringConditionOptionIsNotNull(): void
    {
        // Pass conditions as a string to hit JSON-mode string branch
        $rows = $this->table->find()
            ->applyOptions([
                'eavTypes' => ['color' => 'string'],
                'conditions' => 'color IS NOT NULL',
            ])
            ->select(['id', 'color'])
            ->all();

        $ids = $rows->map(fn($r) => $r->get('id'))->toList();
        // Alpha and Gamma have color
        $this->assertSame(
            ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'cccccccc-cccc-cccc-cccc-cccccccccccc'],
            $ids
        );
    }
}
