<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class AvJsonFixture extends TestFixture
{
    public string $table = 'av_json_uuid';

    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        'entity_table' => ['type' => 'string', 'length' => 191, 'null' => false],
        'entity_id' => ['type' => 'uuid', 'null' => false],
        'attribute_id' => ['type' => 'uuid', 'null' => false],
        'val' => ['type' => 'json', 'null' => false],
        'created' => ['type' => 'datetime', 'null' => false],
        'modified' => ['type' => 'datetime', 'null' => false],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    public function init(): void
    {
        $this->records = [
            [
                'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'entity_table' => 'test_entities',
                'entity_id' => '22222222-2222-2222-2222-222222222222',
                'attribute_id' => '22222222-2222-2222-2222-222222222222',
                'val' => ['foo' => 'bar'],
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
