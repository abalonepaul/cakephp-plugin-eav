<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Eav\Model\Entity\EavAttribute;

/**
 * EAV attribute registry table (eav_attributes).
 *
 * @method EavAttribute newEmptyEntity()
 * @method EavAttribute newEntity(array $data, array $options = [])
 * @method EavAttribute[] newEntities(array $data, array $options = [])
 * @method EavAttribute get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method EavAttribute findOrCreate(\Cake\ORM\Query\SelectQuery|callable|array $search, ?callable $callback = null, array $options = [])
 * @method EavAttribute patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method EavAttribute[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method EavAttribute|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method EavAttribute saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method EavAttribute[]|\Cake\Datasource\ResultSetInterface<EavAttribute>|false saveMany(iterable $entities, array $options = [])
 * @method EavAttribute[]|\Cake\Datasource\ResultSetInterface<EavAttribute> saveManyOrFail(iterable $entities, array $options = [])
 * @method EavAttribute[]|\Cake\Datasource\ResultSetInterface<EavAttribute>|false deleteMany(iterable $entities, array $options = [])
 * @method EavAttribute[]|\Cake\Datasource\ResultSetInterface<EavAttribute> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class EavAttributesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('eav_attributes');
        $this->setPrimaryKey('id');
        $this->setEntityClass(EavAttribute::class);
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->scalar('name')
                ->maxLength('name', 191)
                ->requirePresence('name', 'create')
                ->notEmptyString('name')
            ->scalar('data_type')
                ->requirePresence('data_type', 'create')
                ->notEmptyString('data_type');
    }
}
