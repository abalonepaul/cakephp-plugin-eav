<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Behavior;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Datasource\ConnectionManager;

/**
 * Edge-case tests for EavBehavior internals:
 * - resolveEavEntityId error path when registry row is missing
 * - dynamic table fallback via saveEavValue (creates EavDynamic* alias)
 */
class EavBehaviorEdgeCasesTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Eav.EavAttributes',
        'plugin.Eav.EavEntities',
        'plugin.Eav.EavInteger',
        'plugin.Eav.TestEntities',
    ];

    public function testResolveEavEntityIdThrowsForMissingRegistry(): void
    {
        $Bogus = TableRegistry::getTableLocator()->get('BogusEntities', [
            'className' => Table::class,
            'table' => 'bogus_entities',
        ]);
        $Bogus->setPrimaryKey('id');
        $Bogus->addBehavior('Eav.Eav', [
            'entityTable' => 'no_such_entities_table',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EavBehavior: No eav_entities row found');
        // Trigger resolveEavEntityId via saveEavValue
        $Bogus->behaviors()->get('Eav')->saveEavValue(
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'color',
            'string',
            'red'
        );
    }

    public function testDynamicTableFallbackCreatesGenericTable(): void
    {
        $Entities = TableRegistry::getTableLocator()->get('TestEntities', [
            'className' => Table::class,
            'table' => 'test_entities',
        ]);
        $Entities->setPrimaryKey('id');
        $Entities->addBehavior('Eav.Eav', [
            'entityTable' => 'test_entities',
            'pkType' => 'uuid',
            'storage' => 'tables',
        ]);

        // Use integer type; persistence should land in eav_integer
        $Entities->behaviors()->get('Eav')->saveEavValue(
            '22222222-2222-2222-2222-222222222222',
            'year_start',
            'integer',
            2011
        );

        $EavInteger = TableRegistry::getTableLocator()->get('Eav.EavInteger');
        $row = $EavInteger->find()
            ->where([
                'entity_id' => '22222222-2222-2222-2222-222222222222',
            ])
            ->first();
        $this->assertNotNull($row, 'Expected row in eav_integer');
        $this->assertSame(2011, (int)$row->get('value'));
    }
}
