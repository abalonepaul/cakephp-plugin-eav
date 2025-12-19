<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class AvStringFixture extends TestFixture
{
    public string $table = 'av_string_uuid';

    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        'entity_table' => ['type' => 'string', 'length' => 191, 'null' => false],
        'entity_id' => ['type' => 'uuid', 'null' => false],
        'attribute_id' => ['type' => 'uuid', 'null' => false],
        'val' => ['type' => 'string', 'length' => 1024, 'null' => false],
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
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'entity_table' => 'test_entities',
                'entity_id' => '22222222-2222-2222-2222-222222222222',
                'attribute_id' => '11111111-1111-1111-1111-111111111111',
                'val' => 'red',
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
