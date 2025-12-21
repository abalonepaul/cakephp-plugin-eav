<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;

class EavStringTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('eav_string');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }
}