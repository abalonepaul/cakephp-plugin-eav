<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EavAttributeSetsEavAttributesFixture
 *
 * Creates the join table for tests to avoid depending on prior setup migrations.
 */
class EavAttributeSetsEavAttributesFixture extends TestFixture
{
    /**
     * Explicit table name for clarity.
     *
     * @var string
     */
    public string $table = 'eav_attribute_sets_eav_attributes';

    /**
     * Schema for the junction table (composite PK; no timestamps per generator).
     *
     * @var array<string, mixed>
     */
    public array $fields = [
        'attribute_set_id' => ['type' => 'uuid', 'null' => false],
        'attribute_id' => ['type' => 'uuid', 'null' => false],
        'position' => ['type' => 'integer', 'null' => true, 'default' => 0],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['attribute_set_id', 'attribute_id']],
        ],
    ];

    /**
     * Seed records (FKs align with AttributeSetsFixture and EavAttributesFixture).
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'attribute_set_id' => 'aaaaaaaa-0000-0000-0000-aaaaaaaaaaaa',
                'attribute_id' => '11111111-1111-1111-1111-111111111111',
                'position' => 1,
            ],
            [
                'attribute_set_id' => 'aaaaaaaa-0000-0000-0000-aaaaaaaaaaaa',
                'attribute_id' => '22222222-2222-2222-2222-222222222222',
                'position' => 2,
            ],
        ];
        parent::init();
    }
}
