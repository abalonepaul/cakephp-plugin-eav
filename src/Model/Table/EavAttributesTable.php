<?php
declare(strict_types=1);

namespace Eav\Model\Table;

use ArrayObject;
use Cake\Datasource\ResultSetInterface;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Behavior\TimestampBehavior;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Closure;
use Eav\Model\Entity\EavAttribute;
use Psr\SimpleCache\CacheInterface;

/**
 * EavAttributes Model
 *
 * @method EavAttribute newEmptyEntity()
 * @method EavAttribute newEntity(array $data, array $options = [])
 * @method array<EavAttribute> newEntities(array $data, array $options = [])
 * @method EavAttribute get(mixed $primaryKey, array|string $finder = 'all', CacheInterface|string|null $cache = null, Closure|string|null $cacheKey = null, mixed ...$args)
 * @method EavAttribute findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method EavAttribute patchEntity(EntityInterface $entity, array $data, array $options = [])
 * @method array<EavAttribute> patchEntities(iterable $entities, array $data, array $options = [])
 * @method EavAttribute|false save(EntityInterface $entity, array $options = [])
 * @method EavAttribute saveOrFail(EntityInterface $entity, array $options = [])
 * @method iterable<EavAttribute>|ResultSetInterface<EavAttribute>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Eav.Model\Entity\EavAttribute>|\Cake\Datasource\ResultSetInterface<\Eav\Model\Entity\EavAttribute> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<EavAttribute>|ResultSetInterface<EavAttribute>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<EavAttribute>|ResultSetInterface<EavAttribute> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin TimestampBehavior
 */
class EavAttributesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('eav_attributes');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        // Correct belongsToMany mapping through junction with canonical columns
        $this->belongsToMany('EavAttributeSets', [
            'className' => 'Eav.EavAttributeSets',
            'through' => 'Eav.EavAttributeSetsEavAttributes',
            'foreignKey' => 'attribute_id',
            'targetForeignKey' => 'attribute_set_id',
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
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('data_type')
            ->maxLength('data_type', 50)
            ->requirePresence('data_type', 'create')
            ->notEmptyString('data_type');

        // Optional UI hints
        $validator
            ->scalar('placeholder')
            ->maxLength('placeholder', 255)
            ->allowEmptyString('placeholder');

        $validator
            ->scalar('help_text')
            ->maxLength('help_text', 255)
            ->allowEmptyString('help_text');

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
        // Name must be unique
        $rules->add($rules->isUnique(['name'], 'This name is already in use.'), ['errorField' => 'name']);

        return $rules;
    }

    /**
     * Prevent deleting an attribute that is used in any attribute set.
     *
     * @param EventInterface $event Event instance.
     * @param EntityInterface $entity Entity being deleted.
     * @param ArrayObject $options Options.
     * @return void
     */
    public function beforeDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        // Resolve the junction table robustly (junction() returns a Table; getThrough() may be a string alias)
        $association = $this->getAssociation('EavAttributeSets');

        // Prefer junction() if available (CakePHP API); fallback to resolving getThrough() alias
        if (method_exists($association, 'junction')) {
            $through = $association->junction();
        } else {
            $through = $association->getThrough();
            if (is_string($through)) {
                $through = $this->getTableLocator()->get($through);
            }
        }

        // If we could not resolve a Table instance for any reason, allow delete to proceed safely
        if (!$through instanceof Table) {
            return;
        }

        $inUse = $through->find()
            ->where(['attribute_id' => $entity->get('id')])
            ->enableHydration(false)
            ->count() > 0;

        if ($inUse) {
            // Surface a validation-style error and abort delete
            $entity->setError('id', 'This attribute is used in one or more Attribute Sets and cannot be deleted.');
            // Stop propagation and explicitly set result to false for clarity
            $event->stopPropagation();
            if (method_exists($event, 'setResult')) {
                $event->setResult(false);
            }
        }
    }
}
