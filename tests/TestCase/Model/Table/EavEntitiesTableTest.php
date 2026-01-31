<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Eav\Model\Table\EavEntitiesTable;

/**
 * Eav\Model\Table\EavEntitiesTable Test Case
 */
class EavEntitiesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Eav\Model\Table\EavEntitiesTable
     */
    protected $EavEntities;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Eav.EavEntities',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EavEntities') ? [] : ['className' => EavEntitiesTable::class];
        $this->EavEntities = $this->getTableLocator()->get('EavEntities', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EavEntities);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \Eav\Model\Table\EavEntitiesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        // Missing name invalid
        $bad = $this->EavEntities->newEntity([
            'name' => '',
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
        ]);
        $this->assertNotEmpty($bad->getErrors());

        // Valid entity
        $ok = $this->EavEntities->newEntity([
            'name' => 'temp_entities_' . substr(sha1((string)microtime(true)), 0, 6),
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
            'uuid_subtype' => 'uuid',
        ]);
        $this->assertEmpty($ok->getErrors());
        $this->assertNotFalse($this->EavEntities->save($ok));
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \Eav\Model\Table\EavEntitiesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        // Duplicate name should fail
        $dup = $this->EavEntities->newEntity([
            'name' => 'test_entities', // from fixture
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
        ]);
        $this->assertFalse($this->EavEntities->save($dup));
        $this->assertNotEmpty($dup->getErrors());
    }
}
