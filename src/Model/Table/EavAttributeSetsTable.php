<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Association\BelongsToMany;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Closure;
use Eav\Model\Entity\EavAttributeSet;
use Psr\SimpleCache\CacheInterface;

/**
 * EavAttributeSets Model
 *
 * @property EavAttributesTable&BelongsToMany $EavAttributes
 *
 * @method EavAttributeSet newEmptyEntity()
 * @method EavAttributeSet newEntity(array $data, array $options = [])
 * @method array<EavAttributeSet> newEntities(array $data, array $options = [])
 * @method EavAttributeSet get(mixed $primaryKey, array|string $finder = 'all', CacheInterface|string|null $cache = null, Closure|string|null $cacheKey = null, mixed ...$args)
 * @method EavAttributeSet findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method EavAttributeSet patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method array<EavAttributeSet> patchEntities(iterable $entities, array $data, array $options = [])
 * @method EavAttributeSet|false save(EntityInterface $entity, array $options = [])
 * @method EavAttributeSet saveOrFail(EntityInterface $entity, array $options = [])
 * @method iterable<EavAttributeSet>|ResultSetInterface<EavAttributeSet>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<EavAttributeSet>|ResultSetInterface<EavAttributeSet> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<EavAttributeSet>|ResultSetInterface<EavAttributeSet>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<EavAttributeSet>|ResultSetInterface<EavAttributeSet> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin TimestampBehavior
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
