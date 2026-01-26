<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EavEntitiesFixture
 */
class EavEntitiesFixture extends TestFixture
{
    public string $table = 'eav_entities';

    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false],
        'model_alias' => ['type' => 'string', 'length' => 255, 'null' => true],
        'table_name' => ['type' => 'string', 'length' => 255, 'null' => true],
        'storage_default' => ['type' => 'string', 'length' => 20, 'null' => false, 'default' => 'tables'],
        'json_column' => ['type' => 'string', 'length' => 255, 'null' => true],
        'pk_type' => ['type' => 'string', 'length' => 10, 'null' => false, 'default' => 'uuid'],
        'uuid_subtype' => ['type' => 'string', 'length' => 20, 'null' => true],
        'created' => ['type' => 'datetime', 'null' => false],
        'modified' => ['type' => 'datetime', 'null' => false],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            // Registry entry for tables using entityTable = 'test_entities'
            [
                'id' => '00000000-0000-0000-0000-000000000001',
                'name' => 'test_entities',
                'model_alias' => null,
                'table_name' => 'test_entities',
                'storage_default' => 'tables',
                'json_column' => null,
                'pk_type' => 'uuid',
                'uuid_subtype' => 'uuid',
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            // Registry entry for migrator tests using entityTable = 'json_entities'
            [
                'id' => '00000000-0000-0000-0000-000000000002',
                'name' => 'json_entities',
                'model_alias' => null,
                'table_name' => 'json_entities',
                'storage_default' => 'tables',
                'json_column' => null,
                'pk_type' => 'uuid',
                'uuid_subtype' => 'uuid',
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
