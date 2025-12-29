<?php
declare(strict_types=1);

namespace Eav\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EavAttributeSetsFixture
 */
class EavAttributeSetsFixture extends TestFixture
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
                'id' => '7109f779-c9f7-479a-aa3d-91eae4d29597',
                'name' => 'Lorem ipsum dolor sit amet',
                'created' => 1766954631,
                'modified' => 1766954631,
            ],
        ];
        parent::init();
    }
}
