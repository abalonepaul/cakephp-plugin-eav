<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EavAttributeSets Model
 *
 * @property \Eav\Model\Table\EavAttributesTable&\Cake\ORM\Association\BelongsToMany $EavAttributes
 *
 * @method \Eav\Model\Entity\EavAttributeSet newEmptyEntity()
 * @method \Eav\Model\Entity\EavAttributeSet newEntity(array $data, array $options = [])
 * @method array<\Eav\Model\Entity\EavAttributeSet> newEntities(array $data, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSet get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Eav\Model\Entity\EavAttributeSet findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSet patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Eav\Model\Entity\EavAttributeSet> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSet|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Eav\Model\Entity\EavAttributeSet saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavAttributeSet>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttributeSet>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavAttributeSet>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttributeSet> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavAttributeSet>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttributeSet>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Eav\Model\Entity\EavAttributeSet>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttributeSet> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class EavAttributeSetsTable extends Table
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

        $this->setTable('eav_attribute_sets');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        // Correct belongsToMany mapping through the canonical junction
        $this->belongsToMany('EavAttributes', [
            'className' => 'Eav.EavAttributes',
            'through' => 'Eav.EavAttributeSetsEavAttributes',
            'foreignKey' => 'attribute_set_id',
            'targetForeignKey' => 'attribute_id',
            'joinTable' => 'eav_attribute_sets_eav_attributes',
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
            ->scalar('name')
            ->maxLength('name', 191)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

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
