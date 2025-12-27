<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;

/**
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 *
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}>
 */
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