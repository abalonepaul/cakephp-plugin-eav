<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Eav\Controller\EavEntitiesController;

/**
 * Eav\Controller\EavEntitiesController Test Case
 *
 * @link \Eav\Controller\EavEntitiesController
 */
class EavEntitiesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Eav.EavEntities',
    ];

    /**
     * Test index method
     *
     * @return void
     * @link \Eav\Controller\EavEntitiesController::index()
     */
    public function testIndex(): void
    {
        $this->get('/eav/eav-entities');
        $this->assertResponseOk();
        $this->assertStringContainsString('test_entities', (string)$this->_response->getBody());
    }

    /**
     * Test view method
     *
     * @return void
     * @link \Eav\Controller\EavEntitiesController::view()
     */
    public function testView(): void
    {
        $this->get('/eav/eav-entities/view/00000000-0000-0000-0000-000000000001');
        $this->assertResponseOk();
        $this->assertStringContainsString('test_entities', (string)$this->_response->getBody());
    }

    /**
     * Test add method
     *
     * @return void
     * @link \Eav\Controller\EavEntitiesController::add()
     */
    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $name = 'temp_entities_' . substr(sha1((string)microtime(true)), 0, 6);
        $this->post('/eav/eav-entities/add', [
            'name' => $name,
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
            'uuid_subtype' => 'uuid',
        ]);
        $this->assertResponseSuccess();

        $Entities = $this->getTableLocator()->get('Eav.EavEntities');
        $exists = $Entities->find()->where(['name' => $name])->count() === 1;
        $this->assertTrue($exists);
    }

    public function testAddFormRendersChoices(): void
    {
        $this->get('/eav/eav-entities/add');
        $this->assertResponseOk();
    }

    public function testAddAppliesSmartDefaults(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $name = 'products'; // derive alias/table_name
        $this->post('/eav/eav-entities/add', [
            'name' => $name,
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
            'uuid_subtype' => 'uuid',
            'json_column' => 'spec', // should be cleared since storage_default != json_column
        ]);
        $this->assertResponseSuccess();

        $Entities = $this->getTableLocator()->get('Eav.EavEntities');
        $row = $Entities->find()->where(['name' => $name])->firstOrFail();

        $this->assertSame('Product', (string)$row->model_alias);
        $this->assertSame('products', (string)$row->table_name);
        $this->assertNull($row->json_column);
    }

    /**
     * Test edit method
     *
     * @return void
     * @link \Eav\Controller\EavEntitiesController::edit()
     */
    public function testEdit(): void
    {
        // Create an entity to edit
        $Entities = $this->getTableLocator()->get('Eav.EavEntities');
        $ent = $Entities->newEntity([
            'name' => 'edit_entities_' . substr(sha1((string)microtime(true)), 0, 6),
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
            'uuid_subtype' => 'uuid',
        ]);
        $saved = $Entities->saveOrFail($ent);

        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->post('/eav/eav-entities/edit/' . $saved->id, [
            'name' => $saved->name, // keep same name to avoid unique violation
            'model_alias' => 'EditedAlias',
            'table_name' => $saved->name,
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
            'uuid_subtype' => 'uuid',
        ]);
        $this->assertResponseSuccess();

        $row = $Entities->get($saved->id);
        $this->assertSame('EditedAlias', (string)$row->model_alias);
    }

    /**
     * Test delete method
     *
     * @return void
     * @link \Eav\Controller\EavEntitiesController::delete()
     */
    public function testDelete(): void
    {
        $Entities = $this->getTableLocator()->get('Eav.EavEntities');
        $ent = $Entities->newEntity([
            'name' => 'del_entities_' . substr(sha1((string)microtime(true)), 0, 6),
            'storage_default' => 'tables',
            'pk_type' => 'uuid',
            'uuid_subtype' => 'uuid',
        ]);
        $saved = $Entities->saveOrFail($ent);

        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->post('/eav/eav-entities/delete/' . $saved->id);
        $this->assertResponseSuccess();

        $exists = $Entities->find()->where(['id' => $saved->id])->count() === 1;
        $this->assertFalse($exists);
    }
}
