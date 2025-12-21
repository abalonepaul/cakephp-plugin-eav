<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class JsonEntitiesFixture extends TestFixture
{
    public string $table = 'json_entities';

    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        // Use jsonb so jsonb_exists(...) works without casting
        'data' => ['type' => 'jsonb', 'null' => true],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    public function init(): void
    {
        $this->records = [
            [
                'id' => '33333333-3333-3333-3333-333333333331',
                'data' => ['color' => 'red', 'size' => 'M'],
            ],
            [
                'id' => '33333333-3333-3333-3333-333333333332',
                // No "color" key to ensure it’s not counted/migrated
                'data' => ['size' => 'L'],
            ],
            [
                'id' => '33333333-3333-3333-3333-333333333333',
                'data' => ['color' => 'blue'],
            ],
        ];
        parent::init();
    }
}