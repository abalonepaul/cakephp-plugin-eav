<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;

class AvJsonUuidTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('av_json_uuid');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }
}
