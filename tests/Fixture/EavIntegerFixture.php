<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class EavIntegerFixture extends TestFixture
{
    public string $table = 'eav_integer';

    public array $fields = [
        'eav_entity_id' => ['type' => 'uuid', 'null' => false],
        'entity_id' => ['type' => 'uuid', 'null' => false],
        'eav_attribute_id' => ['type' => 'uuid', 'null' => false],
        'value' => ['type' => 'integer', 'null' => true],
        'created' => ['type' => 'datetime', 'null' => false],
        'modified' => ['type' => 'datetime', 'null' => false],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['eav_entity_id', 'entity_id', 'eav_attribute_id']],
        ],
    ];

    public function init(): void
    {
        $this->records = [
            [
                'eav_entity_id' => '00000000-0000-0000-0000-000000000001', // test_entities
                'entity_id' => '22222222-2222-2222-2222-222222222222',
                'eav_attribute_id' => '33333333-3333-3333-3333-333333333333', // year_start
                'value' => 2010,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
