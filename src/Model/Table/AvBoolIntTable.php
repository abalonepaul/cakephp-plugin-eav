<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;

class AvBoolIntTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('av_bool_int');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }
}
