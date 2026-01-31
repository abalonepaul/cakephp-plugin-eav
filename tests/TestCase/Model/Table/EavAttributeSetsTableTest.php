<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Eav\Model\Table\EavAttributeSetsTable;

/**
 * Eav\Model\Table\EavAttributeSetsTable Test Case
 */
class EavAttributeSetsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Eav\Model\Table\EavAttributeSetsTable
     */
    protected $EavAttributeSets;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Eav.EavAttributeSets',
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
        $config = $this->getTableLocator()->exists('EavAttributeSets') ? [] : ['className' => EavAttributeSetsTable::class];
        $this->EavAttributeSets = $this->getTableLocator()->get('EavAttributeSets', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EavAttributeSets);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \Eav\Model\Table\EavAttributeSetsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        // Blank name invalid
        $bad = $this->EavAttributeSets->newEntity(['name' => '']);
        $this->assertNotEmpty($bad->getErrors());

        // Too long name invalid
        $long = str_repeat('a', 300);
        $bad2 = $this->EavAttributeSets->newEntity(['name' => $long]);
        $this->assertNotEmpty($bad2->getErrors());

        // Valid
        $ok = $this->EavAttributeSets->newEntity(['name' => 'Valid Set']);
        $this->assertEmpty($ok->getErrors());
        $this->assertNotFalse($this->EavAttributeSets->save($ok));
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \Eav\Model\Table\EavAttributeSetsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        // Duplicate name should fail unique rule
        $dup = $this->EavAttributeSets->newEntity(['name' => 'Lorem ipsum dolor sit amet']);
        $this->assertFalse($this->EavAttributeSets->save($dup));
        $this->assertNotEmpty($dup->getErrors());
    }
}
