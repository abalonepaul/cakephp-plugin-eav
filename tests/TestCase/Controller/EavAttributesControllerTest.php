<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Eav\Controller\EavAttributesController;

/**
 * Eav\Controller\EavAttributesController Test Case
 *
 * @link \Eav\Controller\EavAttributesController
 */
class EavAttributesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Eav.EavAttributes',
    ];

    /**
     * Test index method
     *
     * @return void
     * @link \Eav\Controller\EavAttributesController::index()
     */
    public function testIndex(): void
    {
        $this->get('/eav/eav-attributes');
        $this->assertResponseOk();
        $this->assertStringContainsString('color', (string)$this->_response->getBody());
        $this->assertStringContainsString('Actions', (string)$this->_response->getBody());
    }

    /**
     * Test view method
     *
     * @return void
     * @link \Eav\Controller\EavAttributesController::view()
     */
    public function testView(): void
    {
        $this->get('/eav/eav-attributes/view/11111111-1111-1111-1111-111111111111');
        $this->assertResponseOk();
        $this->assertStringContainsString('color', (string)$this->_response->getBody());
    }

    /**
     * Test add method
     *
     * @return void
     * @link \Eav\Controller\EavAttributesController::add()
     */
    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $name = 'temp_attr_' . substr(sha1((string)microtime(true)), 0, 6);
        $this->post('/eav/eav-attributes/add', [
            'name' => $name,
            'data_type' => 'string',
        ]);
        $this->assertResponseSuccess();

        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
        $exists = $Attributes->find()->where(['name' => $name])->count() === 1;
        $this->assertTrue($exists);
    }

    /**
     * Test edit method
     *
     * @return void
     * @link \Eav\Controller\EavAttributesController::edit()
     */
    public function testEdit(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        // Create a fresh attribute to edit to avoid any implicit constraints on the seeded row
        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
        $orig = $Attributes->newEntity([
            'name' => 'temp_edit_' . substr(sha1((string)microtime(true)), 0, 6),
            'data_type' => 'string',
        ]);
        $saved = $Attributes->saveOrFail($orig);

        // Change the name to a unique value; include data_type to satisfy validation on update
        $newName = 'renamed_' . substr(sha1((string)microtime(true)), 0, 6);
        $this->post('/eav/eav-attributes/edit/' . $saved->id, [
            'name' => $newName,
            'data_type' => 'string',
        ]);

        // Successful edit redirects back to index (plugin routes default to /eav)
        $this->assertResponseCode(302);
        $this->assertRedirectContains('/eav');

        // Verify the row was updated
        $row = $Attributes->get($saved->id);
        $this->assertSame($newName, (string)$row->name);
    }

    /**
     * Test delete method
     *
     * @return void
     * @link \Eav\Controller\EavAttributesController::delete()
     */
    public function testDelete(): void
    {
        // Create an unused attribute to delete
        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
        $entity = $Attributes->newEntity(['name' => 'to_delete_' . substr(sha1((string)microtime(true)), 0, 4), 'data_type' => 'string']);
        $saved = $Attributes->saveOrFail($entity);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/eav/eav-attributes/delete/' . $saved->id);
        $this->assertResponseSuccess();

        $exists = $Attributes->find()->where(['id' => $saved->id])->count() === 1;
        $this->assertFalse($exists);
    }

    public function testDataTypeSelectOptionsFromConfig(): void
    {
        // We only assert presence for configured defaults; eav.json may include additional types (e.g., boolean)
        \Cake\Core\Configure::write('Eav.defaultTypes', ['string', 'integer']);
        $this->get('/eav/eav-attributes/add');
        $this->assertResponseOk();
        $html = (string)$this->_response->getBody();
        $this->assertStringContainsString('string', $html);
        $this->assertStringContainsString('integer', $html);
    }
}
