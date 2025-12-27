<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Behavior;

use Cake\I18n\Date;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Datasource\ConnectionManager;
use Cake\Database\Schema\TableSchema;

/**
 * JSON Storage Mode behavior tests (entity-level JSONB column).
 * Uses Postgres jsonb expressions in WHERE until automatic condition rewriting is finalized.
 *
 * Tables are intentionally generic (items/products) for plugin neutrality.
 */
class EavJsonStorageModeTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.Items',
        'plugin.Eav.Products',
    ];
    private Table $Items;
    private Table $Products;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = ConnectionManager::get('test');
        $existing = $connection->getSchemaCollection()->listTables();

        $schemas = [];

        if (!in_array('items', $existing, true)) {
            $items = new TableSchema('items');
            $items
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                // json column for JSON Storage Mode tests (we cast to ::jsonb in queries)
                ->addColumn('attrs', ['type' => 'json', 'null' => true])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            $schemas[] = $items;
        }

        if (!in_array('products', $existing, true)) {
            $products = new TableSchema('products');
            $products
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                // json column for JSON Storage Mode tests (we cast to ::jsonb in queries)
                ->addColumn('spec', ['type' => 'json', 'null' => true])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            $schemas[] = $products;
        }

        if ($schemas !== []) {
            $connection->disableConstraints(function ($connection) use ($schemas): void {
                foreach ($schemas as $schema) {
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

        $this->Items = TableRegistry::getTableLocator()->get('Items', [
            'className' => Table::class,
            'table' => 'items',
        ]);
        $this->Items->setPrimaryKey('id');
        // Attach behavior in JSON Storage Mode for items.attrs
        $this->Items->addBehavior('Eav.Eav', [
            'storage' => 'json_column',
            'jsonColumn' => 'attrs',
            'attributeTypeMap' => [
                'color' => 'string',
                'year_start' => 'integer',
                'is_active' => 'boolean',
                'manufactured_at' => 'date',
            ],
        ]);

        $this->Products = TableRegistry::getTableLocator()->get('Products', [
            'className' => Table::class,
            'table' => 'products',
        ]);
        $this->Products->setPrimaryKey('id');
        // Attach behavior in JSON Storage Mode for products.spec
        $this->Products->addBehavior('Eav.Eav', [
            'storage' => 'json_column',
            'jsonColumn' => 'spec',
            'attributeTypeMap' => [
                'color' => 'string',
                'weight' => 'float',
                'is_active' => 'boolean',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->Items, $this->Products);
        parent::tearDown();
    }

    /**
     * Helper to add a jsonb WHERE condition safely with bound parameters.
     *
     * @param \Cake\ORM\Table $table
     * @param \Cake\ORM\Query $query
     * @param string $jsonColumn
     * @param string $key
     * @param string $op SQL operator (=, ILIKE, >=, etc.)
     * @param mixed $value
     * @param string|null $pgCast e.g. 'int','boolean','date'
     * @return \Cake\ORM\Query
     */
    protected function whereJsonKey(Table $table, $query, string $jsonColumn, string $key, string $op, mixed $value, ?string $pgCast = null)
    {
        $alias = $table->getAlias();
        $kParam = ':k_' . substr(hash('sha1', $key), 0, 8);
        $vParam = ':v_' . substr(hash('sha1', (string)$key . '_' . (string)$value), 0, 8);

        $extract = "({$alias}.{$jsonColumn} ->> {$kParam})";
        $lhs = $pgCast ? "({$extract})::{$pgCast}" : $extract;

        return $query->where(function (QueryExpression $exp) use ($query, $kParam, $vParam, $lhs, $op, $value, $key) {
            $exp->add("{$lhs} {$op} {$vParam}");
            $query->bind($kParam, $key, 'string');
            $query->bind($vParam, $value);
            return $exp;
        });
    }

    /**
     * Helper to filter for missing key (NULL semantics for attribute).
     */
    protected function whereJsonKeyMissing(Table $table, $query, string $jsonColumn, string $key)
    {
        $alias = $table->getAlias();
        $kParam = ':k_' . substr(hash('sha1', $key), 0, 8);
        // Use jsonb_exists to avoid PDO "?" placeholder conflicts
        $cond = "NOT jsonb_exists(({$alias}.{$jsonColumn})::jsonb, {$kParam})";
        return $query->where(function (QueryExpression $exp) use ($query, $cond, $kParam, $key) {
            $exp->add($cond);
            $query->bind($kParam, $key, 'string');
            return $exp;
        });
    }

    public function testStringQueriesOnJsonAttributes(): void
    {
        // color ILIKE 're%' -> only Alpha
        $q1 = $this->Items->find();
        $q1 = $this->whereJsonKey($this->Items, $q1, 'attrs', 'color', 'ILIKE', 're%');
        $rows1 = $q1->all();
        $this->assertSame(1, $rows1->count());
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $rows1->first()->id);

        // color = 'blue' -> Gamma
        $q2 = $this->Items->find();
        $q2 = $this->whereJsonKey($this->Items, $q2, 'attrs', 'color', '=', 'blue');
        $rows2 = $q2->all();
        $this->assertSame(1, $rows2->count());
        $this->assertSame('cccccccc-cccc-cccc-cccc-cccccccccccc', $rows2->first()->id);
    }

    public function testNumericQueriesAndOrderingOnJsonAttributes(): void
    {
        // year_start >= 2010 should match Alpha (2010) and Gamma (2015)
        $q = $this->Items->find();
        $q = $this->whereJsonKey($this->Items, $q, 'attrs', 'year_start', '>=', 2010, 'int');
        // Order by projected alias (added via behavior beforeFind)
        $rows = $q->orderByDesc('year_start')->all()->toList();

        $this->assertCount(2, $rows);
        $this->assertSame('cccccccc-cccc-cccc-cccc-cccccccccccc', $rows[0]->id);
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $rows[1]->id);
    }

    public function testBooleanAndNullSemanticsOnJsonAttributes(): void
    {
        // is_active = true -> Alpha, Gamma
        $q = $this->Items->find();
        $q = $this->whereJsonKey($this->Items, $q, 'attrs', 'is_active', '=', true, 'boolean');
        $active = $q->all();
        $this->assertSame(2, $active->count());

        // Missing key (color) -> Beta
        $q2 = $this->Items->find();
        $q2 = $this->whereJsonKeyMissing($this->Items, $q2, 'attrs', 'color');
        $nullColor = $q2->all();
        $this->assertSame(1, $nullColor->count());
        $this->assertSame('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', $nullColor->first()->id);
    }

    public function testDateTypingAndHydration(): void
    {
        // manufactured_at >= 2020-01-01 -> Alpha and Gamma
        $q = $this->Items->find();
        $q = $this->whereJsonKey(
            $this->Items,
            $q,
            'attrs',
            'manufactured_at',
            '>=',
            '2020-01-01',
            'date'
        );
        $rows = $q->orderByAsc('manufactured_at')->all()->toList();

        $this->assertCount(2, $rows);
        // Ensure types are correct on hydration via projections + afterFind typing
        $first = $rows[0];
        $second = $rows[1];

        $this->assertInstanceOf(Date::class, $first->manufactured_at);
        $this->assertInstanceOf(Date::class, $second->manufactured_at);
        $this->assertSame('2020-01-01', $first->manufactured_at->format('Y-m-d'));
        $this->assertSame('2022-12-31', $second->manufactured_at->format('Y-m-d'));

        // Integer and boolean types
        $this->assertIsInt($first->year_start);
        $this->assertIsBool($first->is_active);
    }

    public function testJsonStorageOnProductsTable(): void
    {
        // Verify behavior works with a different column name and float typing
        $q = $this->Products->find();
        $q = $this->whereJsonKey($this->Products, $q, 'spec', 'color', '=', 'red');
        $red = $q->firstOrFail();

        $this->assertSame('11111111-1111-1111-1111-111111111111', $red->id);
        $this->assertIsFloat($red->weight);
        $this->assertIsBool($red->is_active);

        $q2 = $this->Products->find();
        $q2 = $this->whereJsonKey($this->Products, $q2, 'spec', 'weight', '>', 1.6, 'numeric');
        $heavier = $q2->all();
        $this->assertSame(1, $heavier->count());
        $this->assertSame('22222222-2222-2222-2222-222222222222', $heavier->first()->id);
    }

    public function testSaveSingleAttributeJsonbSet(): void
    {
        // Update a single attribute and verify jsonb_set write + typed hydration on reload
        $item = $this->Items->get('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $item->set('color', 'green');
        $this->Items->saveOrFail($item);

        // Reload by PK; projections applied in beforeFind so alias "color" is selected and typed in afterFind
        $reloaded = $this->Items->get($item->id);
        $this->assertSame('green', $reloaded->color);

        // Verify filtering by raw JSONB condition also matches the updated value
        $q = $this->Items->find();
        $q = $this->whereJsonKey($this->Items, $q, 'attrs', 'color', '=', 'green');
        $found = $q->all()->extract('id')->toArray();
        $this->assertContains($item->id, $found);
    }

    public function testWhereRewritingEqualityAndNulls(): void
    {
        // color = 'red' -> Alpha
        $rows = $this->Items->find()
            ->where(['color' => 'red'])
            ->all()
            ->extract('id')
            ->toList();
        $this->assertSame(['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'], $rows);

        // color IS NULL -> Beta (missing key treated as NULL)
        $rowsNull = $this->Items->find()
            ->where(['color IS' => null])
            ->all()
            ->extract('id')
            ->toList();
        $this->assertSame(['bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'], $rowsNull);
    }

    public function testWhereRewritingInOperator(): void
    {
        $ids = $this->Items->find()
            ->where(['color IN' => ['red', 'blue']])
            ->all()
            ->extract('id')
            ->toList();
        sort($ids);
        $this->assertSame(
            ['aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'cccccccc-cccc-cccc-cccc-cccccccccccc'],
            $ids
        );
    }

    public function testOrderRewritingAndProjection(): void
    {
        // ORDER BY year_start DESC with NULLS LAST behavior where applicable
        $ordered = $this->Items->find()
            ->orderBy(['year_start' => 'DESC'])
            ->all()
            ->extract('id')
            ->toList();

        $this->assertSame([
            'cccccccc-cccc-cccc-cccc-cccccccccccc', // 2015
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', // 2010
            'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', // 2005
        ], $ordered);

        // Explicit select of attribute alias works without raw JSON
        $row = $this->Items->find()
            ->select(['id', 'color'])
            ->where(['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'])
            ->firstOrFail();
        $this->assertSame('red', $row->color);
    }
}
