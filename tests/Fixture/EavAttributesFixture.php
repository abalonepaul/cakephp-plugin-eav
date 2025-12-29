<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EavAttributesFixture
 *
 * Provides canonical attribute rows so FK constraints in
 * eav_attribute_sets_eav_attributes are satisfied during tests.
 */
class EavAttributesFixture extends TestFixture
{
    /**
     * Explicit table name to avoid introspection issues.
     *
     * @var string
     */
    public string $table = 'eav_attributes';

    /**
     * Schema for eav_attributes so the table is created if missing on the test connection.
     *
     * @var array<string, mixed>
     */
    public array $fields = [
        'id' => ['type' => 'uuid', 'null' => false],
        'name' => ['type' => 'string', 'length' => 191, 'null' => false],
        'label' => ['type' => 'string', 'length' => 255, 'null' => true],
        'data_type' => ['type' => 'string', 'length' => 50, 'null' => false],
        'options' => ['type' => 'json', 'null' => false],
        'created' => ['type' => 'datetime', 'null' => false],
        'modified' => ['type' => 'datetime', 'null' => false],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
    ];

    /**
     * Seed two attributes referenced by EavAttributeSetsEavAttributesFixture:
     * - 1111... => color (string)
     * - 2222... => spec (json)
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'name' => 'color',
                'label' => 'Color',
                'data_type' => 'string',
                'options' => [],
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'name' => 'spec',
                'label' => 'Spec',
                'data_type' => 'json',
                'options' => [],
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
