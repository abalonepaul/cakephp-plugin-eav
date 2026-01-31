<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Behavior;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Eav\Model\Behavior\EavBehavior;

/**
 * Probe behavior to expose protected JsonColumnStorageTrait helpers for unit testing.
 */
class ProbeJsonBehavior extends EavBehavior
{
    // JsonColumnStorageTrait helpers
    public function exposePgCastForType(string $t): ?string { return $this->pgCastForType($t); }
    public function exposeGetAliasedJsonColumn(\Cake\ORM\Query $q): string { return $this->getAliasedJsonColumn($q); }
    public function exposeBuildSelectProjection(\Cake\ORM\Query $q, string $a, ?string $t): array { return $this->buildSelectProjection($q, $a, $t); }
    public function exposeBuildWhereFragment(\Cake\ORM\Query $q, string $a, string $op, mixed $v, ?string $t): array { return $this->buildWhereFragment($q, $a, $op, $v, $t); }
    public function exposeBuildOrderFragment(\Cake\ORM\Query $q, string $a, string $dir, ?string $t): array { return $this->buildOrderFragment($q, $a, $dir, $t); }
    public function exposeApplyProjection(\Cake\ORM\Query $q, array $p): void { $this->applyProjection($q, $p); }
    public function exposeApplyWhere(\Cake\ORM\Query $q, array $f): void { $this->applyWhere($q, $f, 'AND'); }
    public function exposeApplyOrder(\Cake\ORM\Query $q, array $f): void { $this->applyOrder($q, $f); }
    public function exposeInferPdoType(mixed $v): ?string { return $this->inferPdoType($v); }
    public function exposeQuoteSqlLiteral(string $v): string { return $this->quoteSqlLiteral($v); }

    // EavBehavior internals for coverage
    public function exposeAttributeId(string $name, string $type): string { return $this->attributeId($name, $type); }
}

class JsonColumnStorageTraitUnitTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.Items',
        'plugin.Eav.EavAttributes',
    ];

    private Table $Items;
    private ProbeJsonBehavior $behavior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->Items = TableRegistry::getTableLocator()->get('Items', [
            'className' => Table::class,
            'table' => 'items',
        ]);
        $this->Items->setPrimaryKey('id');

        $this->behavior = new ProbeJsonBehavior($this->Items, [
            'storage' => 'json_column',
            'jsonColumn' => 'attrs',
            // Provide types for clean casts
            'attributes' => [
                'color' => ['type' => 'string'],
                'year_start' => ['type' => 'integer'],
                'is_active' => ['type' => 'boolean'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->behavior, $this->Items);
        parent::tearDown();
    }

    public function testPgCastForTypeCoversFamilies(): void
    {
        $this->assertSame('int', $this->behavior->exposePgCastForType('integer'));
        $this->assertSame('numeric', $this->behavior->exposePgCastForType('decimal'));
        $this->assertSame('boolean', $this->behavior->exposePgCastForType('boolean'));
        $this->assertSame('date', $this->behavior->exposePgCastForType('date'));
        $this->assertSame('timestamp', $this->behavior->exposePgCastForType('datetime'));
        $this->assertSame('time', $this->behavior->exposePgCastForType('time'));
        $this->assertSame('uuid', $this->behavior->exposePgCastForType('nativeuuid'));
        $this->assertNull($this->behavior->exposePgCastForType('string'));
    }

    public function testGetAliasedJsonColumn(): void
    {
        $q = $this->Items->find();
        $this->assertSame('Items.attrs', $this->behavior->exposeGetAliasedJsonColumn($q));
    }

    public function testBuildSelectProjectionAndApply(): void
    {
        $q = $this->Items->find();
        // project integer attribute year_start with cast
        $proj = $this->behavior->exposeBuildSelectProjection($q, 'year_start', 'integer');
        $this->assertStringContainsString('::int', $proj['sql']);
        $this->behavior->exposeApplyProjection($q, $proj);

        $row = $q->where(['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'])->firstOrFail();
        $this->assertSame(2010, $row->year_start);
    }

    public function testBuildWhereFragmentIsNotNullAndApply(): void
    {
        $q = $this->Items->find();
        $frag = $this->behavior->exposeBuildWhereFragment($q, 'color', 'IS NOT NULL', null, 'string');
        $this->behavior->exposeApplyWhere($q, $frag);

        $ids = $q->all()->extract('id')->toList();
        sort($ids);
        $this->assertSame([
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'cccccccc-cccc-cccc-cccc-cccccccccccc',
        ], $ids);
    }

    public function testBuildOrderFragmentAscNullsLastAndApply(): void
    {
        $q = $this->Items->find();
        $frag = $this->behavior->exposeBuildOrderFragment($q, 'color', 'ASC', 'string');
        $this->assertStringContainsString('NULLS LAST', $frag['sql']);
        $this->behavior->exposeApplyOrder($q, $frag);

        $ordered = $q->all()->extract('id')->toList();
        // blue < red ; Beta (missing) last
        $this->assertSame([
            'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ], $ordered);
    }

    public function testInferPdoTypeCoversFamilies(): void
    {
        $this->assertSame('integer', $this->behavior->exposeInferPdoType(1));
        $this->assertSame('float', $this->behavior->exposeInferPdoType(1.2));
        $this->assertSame('boolean', $this->behavior->exposeInferPdoType(true));
        $this->assertSame('date', $this->behavior->exposeInferPdoType(new \Cake\I18n\Date('2024-01-01')));
        $this->assertSame('datetime', $this->behavior->exposeInferPdoType(new \Cake\I18n\DateTime('2024-01-01 00:00:00')));
        $this->assertSame('json', $this->behavior->exposeInferPdoType(['a' => 1]));
        $this->assertSame('string', $this->behavior->exposeInferPdoType('x'));
    }

    public function testQuoteSqlLiteralEscapes(): void
    {
        $this->assertSame("'a''b'", $this->behavior->exposeQuoteSqlLiteral("a'b"));
    }

    public function testAttributeIdCreateAndCache(): void
    {
        $name = 'temp_attr_' . substr(sha1((string)microtime(true)), 0, 6);
        $id1 = $this->behavior->exposeAttributeId($name, 'string');
        $id2 = $this->behavior->exposeAttributeId($name, 'string');
        $this->assertNotSame('', $id1);
        $this->assertSame($id1, $id2);

        $Attributes = TableRegistry::getTableLocator()->get('Eav.EavAttributes');
        $exists = $Attributes->find()->where(['id' => $id1, 'name' => $name])->count();
        $this->assertSame(1, $exists);
    }
}
