<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EavEntities Model
 *
 * @method \Eav\Model\Entity\EavEntity newEmptyEntity()
 * @method \Eav\Model\Entity\EavEntity newEntity(array $data, array $options = [])
 * @method array<\Eav\Model\Entity\EavEntity> newEntities(array $data, array $options = [])
 * @method \Eav\Model\Entity\EavEntity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Eav\Model\Entity\EavEntity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Eav\Model\Entity\EavEntity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Eav\Model\Entity\EavEntity> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Eav\Model\Entity\EavEntity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Eav\Model\Entity\EavEntity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavEntity>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavEntity>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavEntity>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavEntity> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavEntity>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavEntity>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavEntity>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavEntity> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class EavEntitiesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('eav_entities');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 191)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('model_alias')
            ->maxLength('model_alias', 191)
            ->allowEmptyString('model_alias');

        $validator
            ->scalar('table_name')
            ->maxLength('table_name', 191)
            ->allowEmptyString('table_name');

        $validator
            ->scalar('storage_default')
            ->maxLength('storage_default', 20)
            ->notEmptyString('storage_default');

        $validator
            ->scalar('json_column')
            ->maxLength('json_column', 191)
            ->allowEmptyString('json_column');

        $validator
            ->scalar('pk_type')
            ->maxLength('pk_type', 10)
            ->notEmptyString('pk_type');

        $validator
            ->scalar('uuid_subtype')
            ->maxLength('uuid_subtype', 20)
            ->allowEmptyString('uuid_subtype');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }
}
