<?php
declare(strict_types=1);

namespace Eav\Model\Behavior;

use Cake\Event\EventInterface;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\Utility\Hash;
use Cake\ORM\Locator\LocatorAwareTrait;

class EavBehavior extends Behavior
{
    use LocatorAwareTrait;

    protected array $_defaultConfig = [
        'entityTable'  => null,   // e.g. 'parts'
        'pkType'       => 'uuid', // 'uuid'|'int'
        'attributeSet' => null,
        'map'          => [],     // 'field' => ['attribute'=>'name','type'=>'decimal']
        'events'       => ['beforeMarshal'=>true,'afterSave'=>true,'afterFind'=>true],
    ];

    /** @var array<string,mixed> */
    protected array $buffer = [];

    public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        if (!$this->getConfig('events')['beforeMarshal']) { return; }
        $map = (array)$this->getConfig('map');
        foreach ($map as $field => $meta) {
            if ($data->offsetExists($field)) {
                $this->buffer['write'][$field] = $data[$field];
                // leave it on the entity too by default
            }
        }
    }

    public function afterSave(EventInterface $event, Entity $entity, \ArrayObject $options): void
    {
        if (!$this->getConfig('events')['afterSave']) { return; }
        if (!$entity->get('id')) { return; }
        $map = (array)$this->getConfig('map');
        $entityId = $entity->get('id');
        foreach ($map as $field => $meta) {
            $attribute = $meta['attribute'] ?? $field;
            $type = $meta['type'] ?? 'string';
            $val = $this->buffer['write'][$field] ?? $entity->get($field);
            if ($val === null) { continue; }
            $this->saveEavValue($entityId, (string)$attribute, (string)$type, $val);
        }
        unset($this->buffer['write']);
    }

    public function afterFind(EventInterface $event, Query $query, \ArrayObject $options, bool $primary): void
    {
        if (!$this->getConfig('events')['afterFind'] || !$primary) { return; }
        $map = (array)$this->getConfig('map');
        if (!$map) { return; }

        $query->formatResults(function ($results) use ($map) {
            $ids = [];
            foreach ($results as $row) { if ($row->id) { $ids[] = $row->id; } }
            $values = $this->fetchEavValues($ids, $map);
            return $results->map(function (Entity $row) use ($map, $values) {
                $eid = $row->id;
                foreach ($map as $field => $meta) {
                    $attr = $meta['attribute'] ?? $field;
                    $row->set($field, $values[$eid][$attr] ?? $row->get($field));
                    $row->setDirty($field, false);
                }
                return $row;
            });
        });
    }

    protected function attributeId(string $name): string
    {
        $Attributes = $this->getTableLocator()->get('Eav.Attributes');
        $attr = $Attributes->find()->select(['id'])->where(['name' => $name])->first();
        if (!$attr) { // create lazily
            $entity = $Attributes->newEntity(['name' => $name, 'data_type' => 'string']);
            $Attributes->saveOrFail($entity);
            return (string)$entity->id;
        }
        return (string)$attr->id;
    }

    protected function tableFor(string $type): \Cake\ORM\Table
    {
        $pk = $this->getConfig('pkType') === 'int' ? 'Int' : 'Uuid';
        $class = 'Eav.Av' . ucfirst($type) . $pk;
        return $this->getTableLocator()->get($class);
    }

    public function saveEavValue($entityId, string $attributeName, string $type, $val): void
    {
        $attrId = $this->attributeId($attributeName);
        $tbl = $this->tableFor($type);
        $data = [
            'entity_table' => (string)$this->getConfig('entityTable'),
            $this->getConfig('pkType') === 'int' ? 'entity_int_id' : 'entity_id' => $entityId,
            'attribute_id' => $attrId,
            'val' => (string)$val,
        ];
        $row = $tbl->find()
            ->where(['entity_table' => $data['entity_table'], 'attribute_id' => $attrId])
            ->where([$this->getConfig('pkType') === 'int' ? 'entity_int_id' : 'entity_id' => $entityId])
            ->first();
        if ($row) {
            $tbl->patchEntity($row, ['val' => (string)$val]);
        } else {
            $row = $tbl->newEntity($data);
        }
        $tbl->saveOrFail($row);
    }

    /**
     * @param array<string> $ids
     * @param array<string,array> $map
     * @return array<string,array<string,mixed>>
     */
    protected function fetchEavValues(array $ids, array $map): array
    {
        if (!$ids) { return []; }
        $byType = [];
        foreach ($map as $meta) {
            $type = $meta['type'] ?? 'string';
            $byType[$type] = true;
        }
        $out = [];
        foreach ($byType as $type => $_) {
            $tbl = $this->tableFor($type);
            $field = $this->getConfig('pkType') === 'int' ? 'entity_int_id' : 'entity_id';
            $rows = $tbl->find()
                ->where(['entity_table' => (string)$this->getConfig('entityTable')])
                ->where([$field . ' IN' => $ids])
                ->all();
            foreach ($rows as $r) {
                $eid = (string)$r->get($field);
                $attrId = (string)$r->get('attribute_id');
                // resolve attribute name
                $Attributes = $this->getTableLocator()->get('Eav.Attributes');
                $attr = $Attributes->find()->select(['name'])->where(['id' => $attrId])->first();
                $name = $attr ? (string)$attr->name : $attrId;
                $out[$eid][$name] = $r->get('val');
            }
        }
        return $out;
    }

    // Example finder
    public function findByAttribute(Query $query, array $options)
    {
        $attribute = (string)($options['attribute'] ?? '');
        $type = (string)($options['type'] ?? 'string');
        $op = (string)($options['op'] ?? '=');
        $value = $options['value'] ?? null;

        $attrId = $this->attributeId($attribute);
        $tbl = $this->tableFor($type);
        $alias = $tbl->getAlias();
        $pkField = $this->getConfig('pkType') === 'int' ? 'entity_int_id' : 'entity_id';

        $query->matching($alias, function (Query $q) use ($attrId, $pkField, $op, $value) {
            return $q->where(['entity_table' => (string)$this->getConfig('entityTable'),
                              'attribute_id' => $attrId])
                     ->where(["val {$op}" => $value]);
        });

        return $query;
    }
}
