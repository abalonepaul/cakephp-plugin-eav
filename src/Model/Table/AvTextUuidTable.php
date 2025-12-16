<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;

class AvTextUuidTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('av_text_uuid');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }
}
