<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EavAttributeSetsEavAttributes Model
 *
 * @property \Eav\Model\Table\EavAttributeSetsTable&\Cake\ORM\Association\BelongsTo $EavAttributeSets
 * @property \Eav\Model\Table\EavAttributesTable&\Cake\ORM\Association\BelongsTo $EavAttributes
 *
 * @method \Eav\Model\Entity\EavAttributeSetsEavAttribute newEmptyEntity()
 * @method \Eav\Model\Entity\EavAttributeSetsEavAttribute newEntity(array $data, array $options = [])
 * @method array<\Eav\Model\Entity\EavAttributeSetsEavAttribute> newEntities(array $data, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSetsEavAttribute get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Eav.Model\Entity\EavAttributeSetsEavAttribute findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSetsEavAttribute patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Eav\Model\Entity\EavAttributeSetsEavAttribute> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSetsEavAttribute|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSetsEavAttribute saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavAttributeSetsEavAttribute>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttributeSetsEavAttribute>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavAttributeSetsEavAttribute>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttributeSetsEavAttribute> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavAttributeSetsEavAttribute>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttributeSetsEavAttribute>|false deleteMany(iterable $entities, array $options = [])
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
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
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
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['attribute_set_id'], 'EavAttributeSets'), ['errorField' => 'attribute_set_id']);
        $rules->add($rules->existsIn(['attribute_id'], 'EavAttributes'), ['errorField' => 'attribute_id']);

        return $rules;
    }
}
