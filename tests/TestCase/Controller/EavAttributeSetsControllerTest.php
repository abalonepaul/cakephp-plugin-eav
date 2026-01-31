<?php
declare(strict_types=1);

namespace Eav\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Eav\Controller\EavAttributeSetsController;

/**
 * Eav\Controller\EavAttributeSetsController Test Case
 *
 * @link \Eav\Controller\EavAttributeSetsController
 */
class EavAttributeSetsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Eav.AttributeSets',
        'plugin.Eav.EavAttributes',
        'plugin.Eav.EavAttributeSetsEavAttributes',
    ];

    public function testIndex(): void
    {
        $this->get('/eav/eav-attribute-sets');
        $this->assertResponseOk();
        $this->assertStringContainsString('Default Set', (string)$this->_response->getBody());
    }

    public function testView(): void
    {
        $this->get('/eav/eav-attribute-sets/view/aaaaaaaa-0000-0000-0000-aaaaaaaaaaaa');
        $this->assertResponseOk();
        $this->assertStringContainsString('Default Set', (string)$this->_response->getBody());
    }

    public function testAdd(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->post('/eav/eav-attribute-sets/add', [
            'name' => 'Another Set',
            'eav_attributes' => ['_ids' => ['11111111-1111-1111-1111-111111111111']],
        ]);
        $this->assertResponseSuccess();

        $Sets = $this->getTableLocator()->get('Eav.EavAttributeSets');
        $set = $Sets->find()->where(['name' => 'Another Set'])->firstOrFail();

        $Through = $this->getTableLocator()->get('Eav.EavAttributeSetsEavAttributes');
        $count = $Through->find()->where([
            'attribute_set_id' => $set->id,
            'attribute_id' => '11111111-1111-1111-1111-111111111111',
        ])->count();
        $this->assertSame(1, $count);
    }

    public function testEdit(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        // Create a set first
        $Sets = $this->getTableLocator()->get('Eav.EavAttributeSets');
        $set = $Sets->newEntity(['name' => 'Temp Set']);
        $Sets->saveOrFail($set);

        // Attach color initially
        $Through = $this->getTableLocator()->get('Eav.EavAttributeSetsEavAttributes');
        $Through->saveOrFail($Through->newEntity(
            [
                'attribute_set_id' => $set->id,
                'attribute_id' => '11111111-1111-1111-1111-111111111111',
            ],
            ['accessibleFields' => ['attribute_set_id' => true, 'attribute_id' => true]]
        ));

        // Edit to also include spec
        $this->post('/eav/eav-attribute-sets/edit/' . $set->id, [
            'name' => 'Temp Set',
            'eav_attributes' => ['_ids' => [
                '11111111-1111-1111-1111-111111111111',
                '22222222-2222-2222-2222-222222222222',
            ]],
        ]);
        $this->assertResponseSuccess();

        $have = $Through->find()->where(['attribute_set_id' => $set->id])->all()->extract('attribute_id')->toList();
        sort($have);
        $this->assertSame([
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
        ], $have);
    }

    public function testDelete(): void
    {
        // Create a set to delete
        $Sets = $this->getTableLocator()->get('Eav.EavAttributeSets');
        $set = $Sets->newEntity(['name' => 'Del Set']);
        $Sets->saveOrFail($set);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/eav/eav-attribute-sets/delete/' . $set->id);
        $this->assertResponseSuccess();

        $exists = $Sets->find()->where(['id' => $set->id])->count() === 1;
        $this->assertFalse($exists);
    }
}
