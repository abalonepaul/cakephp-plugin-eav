<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Eav\Model\Table\EavAttributesTable;

/**
 * Eav\Model\Table\EavAttributesTable Test Case
 */
class EavAttributesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Eav\Model\Table\EavAttributesTable
     */
    protected $EavAttributes;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Eav.EavAttributes',
        'plugin.Eav.AttributeSets',
        'plugin.Eav.EavAttributeSetsEavAttributes',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EavAttributes') ? [] : ['className' => EavAttributesTable::class];
        $this->EavAttributes = $this->getTableLocator()->get('EavAttributes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->EavAttributes);

        parent::tearDown();
    }

    /**
     * Verify beforeDelete guard prevents deleting an attribute used in any set.
     */
    public function testDeleteGuardPreventsDeletionWhenInUse(): void
    {
        // Referenced by the junction fixture: 1111... and 2222... are in Default Set
        $attr = $this->EavAttributes->get('11111111-1111-1111-1111-111111111111');
        $result = $this->EavAttributes->delete($attr);
        $this->assertFalse($result, 'Delete must be blocked when attribute is in use');

        // Guard should place a validation-style error on the id field
        $errors = $attr->getErrors();
        $this->assertArrayHasKey('id', $errors);
    }

    /**
     * Verify deleting an unused attribute succeeds.
     */
    public function testDeleteUnusedAttributeSucceeds(): void
    {
        // Create an attribute not referenced by any set
        $new = $this->EavAttributes->newEntity([
            'name' => 'temp_attr_' . substr(sha1((string)microtime(true)), 0, 8),
            'label' => 'Temp',
            'data_type' => 'string',
            'options' => [],
        ]);
        $saved = $this->EavAttributes->saveOrFail($new);

        $fetched = $this->EavAttributes->get($saved->id);
        $result = $this->EavAttributes->delete($fetched);
        $this->assertTrue($result, 'Unused attribute should be deletable');
    }
}
