<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Generic "items" table fixture with JSONB attribute bundle stored in "attrs".
 * Used by JSON Storage Mode tests.
 */
class ItemsFixture extends TestFixture
{
    public string $table = 'items';

    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        'name' => ['type' => 'string', 'length' => 100, 'null' => true],
        'attrs' => ['type' => 'json', 'null' => true],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    public function init(): void
    {
        $this->records = [
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                'name' => 'Alpha',
                'attrs' => [
                    'color' => 'red',
                    'year_start' => 2010,
                    'is_active' => true,
                    'manufactured_at' => '2020-01-01',
                ],
            ],
            [
                'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'name' => 'Beta',
                'attrs' => [
                    // No color to exercise IS NULL/absence logic
                    'year_start' => 2005,
                    'is_active' => false,
                    'manufactured_at' => '2015-06-15',
                ],
            ],
            [
                'id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
                'name' => 'Gamma',
                'attrs' => [
                    'color' => 'blue',
                    'year_start' => 2015,
                    'is_active' => true,
                    'manufactured_at' => '2022-12-31',
                ],
            ],
        ];
        parent::init();
    }
}
