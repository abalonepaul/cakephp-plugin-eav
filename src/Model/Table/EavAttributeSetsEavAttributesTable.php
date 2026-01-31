<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Closure;
use Eav\Model\Entity\EavAttributeSetsEavAttribute;
use Psr\SimpleCache\CacheInterface;

/**
 * EavAttributeSetsEavAttributes Model
 *
 * @property EavAttributeSetsTable&BelongsTo $EavAttributeSets
 * @property EavAttributesTable&BelongsTo $EavAttributes
 *
 * @method EavAttributeSetsEavAttribute newEmptyEntity()
 * @method EavAttributeSetsEavAttribute newEntity(array $data, array $options = [])
 * @method array<EavAttributeSetsEavAttribute> newEntities(array $data, array $options = [])
 * @method EavAttributeSetsEavAttribute get(mixed $primaryKey, array|string $finder = 'all', CacheInterface|string|null $cache = null, Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Eav.Model\Entity\EavAttributeSetsEavAttribute findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method EavAttributeSetsEavAttribute patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method array<EavAttributeSetsEavAttribute> patchEntities(iterable $entities, array $data, array $options = [])
 * @method EavAttributeSetsEavAttribute|false save(EntityInterface $entity, array $options = [])
 * @method EavAttributeSetsEavAttribute saveOrFail(EntityInterface $entity, array $options = [])
 * @method iterable<EavAttributeSetsEavAttribute>|ResultSetInterface<EavAttributeSetsEavAttribute>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<EavAttributeSetsEavAttribute>|ResultSetInterface<EavAttributeSetsEavAttribute> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<EavAttributeSetsEavAttribute>|ResultSetInterface<EavAttributeSetsEavAttribute>|false deleteMany(iterable $entities, array $options = [])
 */
class EavAttributeSetsEavAttributesTable extends Table
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

        $this->setTable('eav_attribute_sets_eav_attributes');
        $this->setDisplayField(['attribute_set_id', 'attribute_id']);
        $this->setPrimaryKey(['attribute_set_id', 'attribute_id']);

        // Keep created/modified in sync with schema (migrations add timestamps)
        $this->addBehavior('Timestamp');

        $this->belongsTo('EavAttributeSets', [
            'foreignKey' => 'attribute_set_id',
            'className' => 'Eav.EavAttributeSets',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('EavAttributes', [
            'foreignKey' => 'attribute_id',
            'className' => 'Eav.EavAttributes',
            'joinType' => 'INNER',
        ]);
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
            ->integer('position')
            ->allowEmptyString('position');

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
        $rules->add($rules->existsIn(['attribute_set_id'], 'EavAttributeSets'), ['errorField' => 'attribute_set_id']);
        $rules->add($rules->existsIn(['attribute_id'], 'EavAttributes'), ['errorField' => 'attribute_id']);

        return $rules;
    }
}
