<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Behavior;

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\I18n\Time;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Eav\Model\Behavior\EavBehavior;
use Cake\Datasource\ConnectionManager;
use Cake\Database\Schema\TableSchema;

class TestEavBehavior extends EavBehavior
{
    public function exposeNormalizeType(string $type, array $meta = []): array
    {
        return $this->normalizeType($type, $meta);
    }

    public function exposeGetTable(): \Cake\ORM\Table
    {
        return $this->getTable();
    }

    public function exposeCastValue(string $type, mixed $value): mixed
    {
        return $this->castValueForType($type, $value);
    }

    public function exposeFetchEavValues(array $ids, array $map): array
    {
        return $this->fetchEavValues($ids, $map);
    }

    public function exposeAvTableClass(string $type, ?string $storage = null): string
    {
        return $this->avTableClass($type, $storage);
    }

    public function exposeNormalizeSelectType(string $type): string
    {
        return $this->normalizeSelectType($type);
    }

    public function exposeEntityIdField(): string
    {
        return $this->entityIdField();
    }

    public function exposeTableTypeSegment(string $type, ?string $storage = null): string
    {
        return $this->tableTypeSegment($type, $storage);
    }

    public function exposeTableFor(string $type, ?string $storage = null): \Cake\ORM\Table
    {
        return $this->tableFor($type, $storage);
    }

    // New: expose protected helpers for coverage
    public function exposePkSuffix(): string
    {
        return $this->pkSuffix();
    }

    public function exposeIsSupportedType(string $type): bool
    {
        return $this->isSupportedType($type);
    }

    public function exposeResolveJsonStorage(string $rawType, array $meta = []): ?string
    {
        return $this->resolveJsonStorage($rawType, $meta);
    }

    public function exposeResolveEavEntityId(): string
    {
        return $this->resolveEavEntityId();
    }
}

class EavBehaviorTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.EavAttributes',
        'plugin.Eav.EavEntities',
        'plugin.Eav.EavString',
        'plugin.Eav.EavJson',
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $connection = ConnectionManager::get('test');
        $existing = $connection->getSchemaCollection()->listTables();
        $definitions = [];

        if (!in_array('eav_attributes', $existing, true)) {
            $schema = new TableSchema('eav_attributes');
            $schema
                ->addColumn('id', ['type' => 'uuid', 'null' => false])
                ->addColumn('name', ['type' => 'string', 'length' => 255, 'null' => false])
                ->addColumn('data_type', ['type' => 'string', 'length' => 50, 'null' => false])
                ->addColumn('placeholder', ['type' => 'string', 'length' => 255, 'null' => true])
                ->addColumn('help_text', ['type' => 'string', 'length' => 255, 'null' => true])
                ->addColumn('created', ['type' => 'datetime', 'null' => false])
                ->addColumn('modified', ['type' => 'datetime', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['id']]);
            $definitions[] = $schema;
        }

        if (!in_array('eav_string', $existing, true)) {
            $schema = new TableSchema('eav_string');
            $schema
                ->addColumn('eav_entity_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('entity_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('eav_attribute_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('value', ['type' => 'string', 'length' => 1024, 'null' => true])
                ->addColumn('created', ['type' => 'datetime', 'null' => false])
                ->addColumn('modified', ['type' => 'datetime', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['eav_entity_id', 'entity_id', 'eav_attribute_id']]);
            $definitions[] = $schema;
        }

        if (!in_array('eav_json', $existing, true)) {
            $schema = new TableSchema('eav_json');
            $schema
                ->addColumn('eav_entity_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('entity_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('eav_attribute_id', ['type' => 'uuid', 'null' => false])
                ->addColumn('value', ['type' => 'json', 'null' => true])
                ->addColumn('created', ['type' => 'datetime', 'null' => false])
                ->addColumn('modified', ['type' => 'datetime', 'null' => false])
                ->addConstraint('primary', ['type' => 'primary', 'columns' => ['eav_entity_id', 'entity_id', 'eav_attribute_id']]);
            $definitions[] = $schema;
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

    private Table $table;
    private TestEavBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->table = TableRegistry::getTableLocator()->get('TestEntities', [
            'className' => Table::class,
            'table' => 'test_entities',
        ]);
        $this->table->setPrimaryKey('id');
        $this->behavior = new TestEavBehavior($this->table, [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'jsonStorage' => 'json',
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->behavior, $this->table);
        parent::tearDown();
    }

    public function testNormalizeTypeAliases(): void
    {
        $normalized = $this->behavior->exposeNormalizeType('bool');
        $this->assertSame('boolean', $normalized['type']);

        $normalized = $this->behavior->exposeNormalizeType('varchar');
        $this->assertSame('string', $normalized['type']);
    }

    public function testJsonStorageRouting(): void
    {
        $normalized = $this->behavior->exposeNormalizeType('jsonb');
        $this->assertSame('json', $normalized['type']);
        $this->assertSame('jsonb', $normalized['storage']);
        $this->assertSame('Eav.EavJson', $this->behavior->exposeAvTableClass('json', 'jsonb'));
    }

    public function testCastValuePreservesNativeTypes(): void
    {
        $this->assertSame(10, $this->behavior->exposeCastValue('integer', '10'));
        $this->assertSame(true, $this->behavior->exposeCastValue('boolean', 1));
        $this->assertSame('12.5', $this->behavior->exposeCastValue('decimal', 12.5));

        $date = $this->behavior->exposeCastValue('date', '2024-01-01');
        $this->assertInstanceOf(Date::class, $date);

        $dateTime = $this->behavior->exposeCastValue('datetime', '2024-01-01 12:00:00');
        $this->assertInstanceOf(DateTime::class, $dateTime);

        $time = $this->behavior->exposeCastValue('time', '12:00:00');
        $this->assertInstanceOf(Time::class, $time);

        $json = $this->behavior->exposeCastValue('json', ['a' => 1]);
        $this->assertSame(['a' => 1], $json);
    }

    public function testFetchEavValuesBatchLoadsAttributes(): void
    {
        $entityId = '22222222-2222-2222-2222-222222222222';
        $map = [
            'color' => ['attribute' => 'color', 'type' => 'string'],
            'spec' => ['attribute' => 'spec', 'type' => 'json'],
        ];
        $result = $this->behavior->exposeFetchEavValues([$entityId], $map);
        $this->assertSame('red', $result[$entityId]['color']);
        $this->assertSame(['foo' => 'bar'], $result[$entityId]['spec']);
    }

    public function testSaveEavValueCreatesAttributeWithNormalizedType(): void
    {
        $entityId = '33333333-3333-3333-3333-333333333333';
        $this->behavior->saveEavValue($entityId, 'legacy_code', 'varchar', 'LX-1');

        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $attr = $Attributes->find()->where(['name' => 'legacy_code'])->first();
        $this->assertNotEmpty($attr);
        $this->assertSame('string', $attr->data_type);
    }

    public function testNormalizeTypeUnsupportedThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->behavior->exposeNormalizeType('bogus-type');
    }

    public function testNormalizeSelectTypeCoversBranches(): void
    {
        $this->assertSame('integer', $this->behavior->exposeNormalizeSelectType('smallinteger'));
        $this->assertSame('integer', $this->behavior->exposeNormalizeSelectType('biginteger'));
        $this->assertSame('datetime', $this->behavior->exposeNormalizeSelectType('timestamptimezone'));
        $this->assertSame('decimal', $this->behavior->exposeNormalizeSelectType('decimal')); // passthrough
    }

    public function testEntityIdFieldIsEntityId(): void
    {
        $this->assertSame('entity_id', $this->behavior->exposeEntityIdField());
    }

    public function testTableForResolvesTypedTable(): void
    {
        $tbl = $this->behavior->exposeTableFor('string');
        $this->assertInstanceOf(\Cake\ORM\Table::class, $tbl);
        $this->assertSame('eav_string', $tbl->getTable());

        $tblJson = $this->behavior->exposeTableFor('json');
        $this->assertSame('eav_json', $tblJson->getTable());
    }

    public function testAvTableClassForString(): void
    {
        $this->assertSame('Eav.EavString', $this->behavior->exposeAvTableClass('string'));
    }

    public function testSaveEavValueUpdatesExistingRow(): void
    {
        // Create then update value to exercise update path
        $entityId = '66666666-6666-6666-6666-666666666666';
        $this->behavior->saveEavValue($entityId, 'color', 'string', 'green');

        // Update to new value
        $this->behavior->saveEavValue($entityId, 'color', 'string', 'yellow');

        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $colorAttr = $Attributes->find()->select(['id'])->where(['name' => 'color'])->enableHydration(false)->firstOrFail();

        $EavString = TableRegistry::getTableLocator()->get('Eav.EavString');
        $row = $EavString->find()
            ->where([
                'eav_entity_id' => '00000000-0000-0000-0000-000000000001', // test_entities
                'entity_id' => $entityId,
                'eav_attribute_id' => $colorAttr['id'],
            ])
            ->firstOrFail();
        $this->assertSame('yellow', $row->get('value'));
    }

    public function testJsonAttributeWriteWithoutEncoding(): void
    {
        // Save a JSON attribute; jsonEncodeOnWrite=false is default and must not encode
        $entityId = '77777777-7777-7777-7777-777777777777';
        $payload = ['a' => 1, 'b' => ['x' => true]];
        $this->behavior->saveEavValue($entityId, 'spec_payload', 'json', $payload);

        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $specAttr = $Attributes->find()->select(['id'])->where(['name' => 'spec_payload'])->enableHydration(false)->firstOrFail();

        $EavJson = TableRegistry::getTableLocator()->get('Eav.EavJson');
        $row = $EavJson->find()
            ->where([
                'eav_entity_id' => '00000000-0000-0000-0000-000000000001',
                'entity_id' => $entityId,
                'eav_attribute_id' => $specAttr['id'],
            ])
            ->firstOrFail();

        $this->assertSame($payload, $row->get('value'));
    }

    public function testGetTableReturnsAttachedTable(): void
    {
        $this->assertSame($this->table, $this->behavior->exposeGetTable());
    }

    // New coverage below

    public function testPkSuffixForPkTypes(): void
    {
        // Default is uuid
        $this->assertSame('Uuid', $this->behavior->exposePkSuffix());

        // Reconfigure behavior with int PK and assert suffix
        $intBehavior = new TestEavBehavior($this->table, [
            'entityTable' => 'test_entities',
            'pkType' => 'int',
        ]);
        $this->assertSame('Int', $intBehavior->exposePkSuffix());
    }

    public function testResolveJsonStorageNormalizationAndOverride(): void
    {
        // Normalization for jsonb
        $this->assertSame('jsonb', $this->behavior->exposeResolveJsonStorage('jsonb'));
        // Meta override wins
        $this->assertSame('jsonb', $this->behavior->exposeResolveJsonStorage('json', ['jsonStorage' => 'jsonb']));
        // Unsupported raw type should return null storage
        $this->assertNull($this->behavior->exposeResolveJsonStorage('integer'));
    }

    public function testIsSupportedTypeCoversTrueFalse(): void
    {
        $this->assertTrue($this->behavior->exposeIsSupportedType('integer'));
        $this->assertTrue($this->behavior->exposeIsSupportedType('fk')); // custom
        $this->assertFalse($this->behavior->exposeIsSupportedType('made_up_type'));
    }

    public function testResolveEavEntityIdReturnsRegistryId(): void
    {
        $id = $this->behavior->exposeResolveEavEntityId();
        $this->assertSame('00000000-0000-0000-0000-000000000001', $id);
    }

    public function testFkCastingBasedOnPkType(): void
    {
        // Default pkType uuid => FK cast should be string
        $uuidFk = $this->behavior->exposeCastValue('fk', 123);
        $this->assertSame('123', $uuidFk);

        // pkType int => FK cast should be int
        $intBehavior = new TestEavBehavior($this->table, [
            'entityTable' => 'test_entities',
            'pkType' => 'int',
        ]);
        $intFk = $intBehavior->exposeCastValue('fk', '123');
        $this->assertSame(123, $intFk);
    }
}
