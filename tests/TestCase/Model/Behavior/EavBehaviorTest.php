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

class TestEavBehavior extends EavBehavior
{
    public function exposeNormalizeType(string $type, array $meta = []): array
    {
        return $this->normalizeType($type, $meta);
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
}

class EavBehaviorTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.Attributes',
        'plugin.Eav.AvString',
        'plugin.Eav.AvJson',
    ];

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
        $this->assertSame('Eav.AvJsonbUuid', $this->behavior->exposeAvTableClass('json', 'jsonb'));
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
        $this->assertSame('{"a":1}', $json);
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

        $Attributes = TableRegistry::getTableLocator()->get('Eav.Attributes');
        $attr = $Attributes->find()->where(['name' => 'legacy_code'])->first();
        $this->assertNotEmpty($attr);
        $this->assertSame('string', $attr->data_type);
    }
}
