<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EavEntitiesFixture
 */
class EavEntitiesFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => '19dac9a9-3cf7-4e92-9452-98782800e735',
                'name' => 'Lorem ipsum dolor sit amet',
                'model_alias' => 'Lorem ipsum dolor sit amet',
                'table_name' => 'Lorem ipsum dolor sit amet',
                'storage_default' => 'Lorem ipsum dolor ',
                'json_column' => 'Lorem ipsum dolor sit amet',
                'pk_type' => 'Lorem ip',
                'uuid_subtype' => 'Lorem ipsum dolor ',
                'created' => 1766960673,
                'modified' => 1766960673,
            ],
        ];
        parent::init();
    }
}
