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
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \Eav\Model\Table\EavAttributeSetsEavAttributesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
