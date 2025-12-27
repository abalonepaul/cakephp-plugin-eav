<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * @method \Eav\Model\Entity\Attribute newEmptyEntity()
 * @method \Eav\Model\Entity\Attribute newEntity(array $data, array $options = [])
 * @method \Eav\Model\Entity\Attribute[] newEntities(array $data, array $options = [])
 * @method \Eav\Model\Entity\Attribute get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Eav\Model\Entity\Attribute findOrCreate(\Cake\ORM\Query\SelectQuery|callable|array $search, ?callable $callback = null, array $options = [])
 * @method \Eav\Model\Entity\Attribute patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Eav\Model\Entity\Attribute[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Eav\Model\Entity\Attribute|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Eav\Model\Entity\Attribute saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Eav\Model\Entity\Attribute[]|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\Attribute>|false saveMany(iterable $entities, array $options = [])
 * @method \Eav\Model\Entity\Attribute[]|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\Attribute> saveManyOrFail(iterable $entities, array $options = [])
 * @method \Eav\Model\Entity\Attribute[]|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\Attribute>|false deleteMany(iterable $entities, array $options = [])
 * @method \Eav\Model\Entity\Attribute[]|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\Attribute> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 *
 * @extends \Cake\ORM\Table<array{Timestamp: \Cake\ORM\Behavior\TimestampBehavior}>
 */
class AttributesTable extends Table
{
    /**
     * @deprecated Use EavAttributesTable instead. This class now points to 'eav_attributes' to ease transition.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        // BC: point legacy alias to the new prefixed table
        $this->setTable('eav_attributes');
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
