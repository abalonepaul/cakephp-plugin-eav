<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class AttributesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('attributes');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->scalar('name')->maxLength('name', 191)->requirePresence('name', 'create')->notEmptyString('name');
        $validator->scalar('data_type')->requirePresence('data_type','create')->notEmptyString('data_type');
        return $validator;
    }
}
