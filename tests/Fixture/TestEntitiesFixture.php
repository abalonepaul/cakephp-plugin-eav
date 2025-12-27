<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class TestEntitiesFixture extends TestFixture
{
    public string $table = 'test_entities';

    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    public function init(): void
    {
        // Two entities: one with a color value in eav_string, one without any attribute row
        $this->records = [
            ['id' => '22222222-2222-2222-2222-222222222222'], // has color='red' in EavStringFixture
            ['id' => '33333333-3333-3333-3333-333333333333'], // no color row -> NULL semantics
        ];
        parent::init();
    }
}