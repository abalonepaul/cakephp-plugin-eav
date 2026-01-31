<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Eav\Model\Table\EavAttributeSetsEavAttributesTable;

/**
 * Eav\Model\Table\EavAttributeSetsEavAttributesTable Test Case
 */
class EavAttributeSetsEavAttributesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Eav\Model\Table\EavAttributeSetsEavAttributesTable
     */
    protected $EavAttributeSetsEavAttributes;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Eav.EavAttributeSetsEavAttributes',
        'plugin.Eav.AttributeSets',
        'plugin.Eav.EavAttributes',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EavAttributeSetsEavAttributes') ? [] : ['className' => EavAttributeSetsEavAttributesTable::class];
        $this->EavAttributeSetsEavAttributes = $this->getTableLocator()->get('EavAttributeSetsEavAttributes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EavAttributeSetsEavAttributes);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \Eav\Model\Table\EavAttributeSetsEavAttributesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $table = $this->EavAttributeSetsEavAttributes;

        $entity = $table->newEntity(
            [
                'attribute_set_id' => 'aaaaaaaa-0000-0000-0000-aaaaaaaaaaaa', // from AttributeSetsFixture
                'attribute_id' => '11111111-1111-1111-1111-111111111111',     // from EavAttributesFixture
                'position' => 5,
            ],
            [
                'accessibleFields' => [
                    'attribute_set_id' => true,
                    'attribute_id' => true,
                    'position' => true,
                ],
            ]
        );
        $this->assertEmpty($entity->getErrors());
        $this->assertNotFalse($table->save($entity));
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \Eav\Model\Table\EavAttributeSetsEavAttributesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $table = $this->EavAttributeSetsEavAttributes;

        $bad = $table->newEntity(
            [
                'attribute_set_id' => 'ffffffff-0000-0000-0000-ffffffffffff', // non-existent
                'attribute_id' => 'eeeeeeee-0000-0000-0000-eeeeeeeeeeee',     // non-existent
                'position' => 1,
            ],
            [
                'accessibleFields' => [
                    'attribute_set_id' => true,
                    'attribute_id' => true,
                    'position' => true,
                ],
            ]
        );
        $this->assertFalse($table->save($bad));
        $this->assertNotEmpty($bad->getErrors());
    }
}
