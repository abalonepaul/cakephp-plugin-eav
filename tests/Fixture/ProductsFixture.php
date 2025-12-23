<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Generic "products" table fixture with JSONB attribute bundle stored in "spec".
 * Used by JSON Storage Mode tests.
 */
class ProductsFixture extends TestFixture
{
    public string $table = 'products';

    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        'sku' => ['type' => 'string', 'length' => 100, 'null' => true],
        'spec' => ['type' => 'json', 'null' => true],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    public function init(): void
    {
        $this->records = [
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'sku' => 'P-001',
                'spec' => [
                    'color' => 'red',
                    'weight' => 1.5,
                    'is_active' => true,
                ],
            ],
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'sku' => 'P-002',
                'spec' => [
                    'color' => 'green',
                    'weight' => 2.0,
                    'is_active' => false,
                ],
            ],
        ];
        parent::init();
    }
}
