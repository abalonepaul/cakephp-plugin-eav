<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Closure;
use Eav\Model\Entity\EavEntity;
use Psr\SimpleCache\CacheInterface;

/**
 * EavEntities Model
 *
 * @method EavEntity newEmptyEntity()
 * @method EavEntity newEntity(array $data, array $options = [])
 * @method array<EavEntity> newEntities(array $data, array $options = [])
 * @method EavEntity get(mixed $primaryKey, array|string $finder = 'all', CacheInterface|string|null $cache = null, Closure|string|null $cacheKey = null, mixed ...$args)
 * @method EavEntity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method EavEntity patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method array<EavEntity> patchEntities(iterable $entities, array $data, array $options = [])
 * @method EavEntity|false save(EntityInterface $entity, array $options = [])
 * @method EavEntity saveOrFail(EntityInterface $entity, array $options = [])
 * @method iterable<EavEntity>|ResultSetInterface<EavEntity>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<EavEntity>|ResultSetInterface<EavEntity> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<EavEntity>|ResultSetInterface<EavEntity>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<EavEntity>|ResultSetInterface<EavEntity> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin TimestampBehavior
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
     * @param Validator $validator Validator instance.
     * @return Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('model_alias')
            ->maxLength('model_alias', 255)
            ->allowEmptyString('model_alias');

        $validator
            ->scalar('table_name')
            ->maxLength('table_name', 255)
            ->allowEmptyString('table_name');

        $validator
            ->scalar('storage_default')
            ->maxLength('storage_default', 20)
            ->notEmptyString('storage_default');

        $validator
            ->scalar('json_column')
            ->maxLength('json_column', 255)
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
     * @param RulesChecker $rules The rules object to be modified.
     * @return RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }
}
