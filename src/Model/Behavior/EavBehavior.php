<?php
declare(strict_types=1);

namespace Eav\Model\Behavior;

use Cake\Collection\CollectionInterface;
use Cake\Database\TypeFactory;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\I18n\Time;
use Cake\ORM\Behavior;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query;
use Cake\Utility\Inflector;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class EavBehavior extends Behavior
{
    use LocatorAwareTrait;

    protected array $_defaultConfig = [
        'entityTable'  => null,   // e.g. 'items'
        'pkType'       => 'uuid', // 'uuid'|'int'
        'attributeSet' => null,
        'map'          => [],     // 'field' => ['attribute'=>'name','type'=>'decimal']
        'events'       => ['beforeMarshal'=>true,'afterSave'=>true,'afterFind'=>true],
        'jsonStorage'  => 'json', // json|jsonb
    ];

    /** @var array<string,mixed> */
    protected array $buffer = [];

    /** @var array<string,string> */
    protected array $attributeIdCache = [];

    /** @var array<string,string> */
    protected array $attributeNameCache = [];

    /** @var array<string,string> */
    protected array $typeAliases = [
        'bool' => 'boolean',
        'int' => 'integer',
        'smallint' => 'smallinteger',
        'bigint' => 'biginteger',
        'tinyint' => 'tinyinteger',
        'double' => 'float',
        'timestamp' => 'datetime',
        'varchar' => 'string',
    ];

    /** @var array<string> */
    protected array $customTypes = [
        'fk_uuid',
        'fk_int',
    ];

    /** @var array<string,string> */
    protected array $tableTypeAliases = [
        'boolean' => 'bool',
        'integer' => 'int',
    ];

    /**
     * Capture mapped values from request data for later persistence.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @param \ArrayObject $data Request data.
     * @param \ArrayObject $options Marshal options.
     * @return void
     */
    public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        if (!$this->getConfig('events')['beforeMarshal']) {
            return;
        }
        $map = (array)$this->getConfig('map');
        foreach ($map as $field => $meta) {
            if ($data->offsetExists($field)) {
                $this->buffer['write'][$field] = $data[$field];
                // leave it on the entity too by default
            }
        }
    }

    /**
     * Persist mapped attribute values after entity save.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @param \Cake\Datasource\EntityInterface $entity Entity.
     * @param \ArrayObject $options Save options.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        if (!$this->getConfig('events')['afterSave']) {
            return;
        }
        if (!$entity->get('id')) {
            return;
        }
        $map = (array)$this->getConfig('map');
        $entityId = $entity->get('id');
        foreach ($map as $field => $meta) {
            $attribute = $meta['attribute'] ?? $field;
            $type = $meta['type'] ?? 'string';
            $val = $this->buffer['write'][$field] ?? $entity->get($field);
            if ($val === null) {
                continue;
            }
            $this->saveEavValue($entityId, (string)$attribute, (string)$type, $val, (array)$meta);
        }
        unset($this->buffer['write']);
    }

    /**
     * Hydrate EAV values into entities after find.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @param \Cake\ORM\Query $query Query.
     * @param \ArrayObject $options Options.
     * @param bool $primary Is primary query.
     * @return void
     */
    public function afterFind(EventInterface $event, Query $query, \ArrayObject $options, bool $primary): void
    {
        if (!$this->getConfig('events')['afterFind'] || !$primary) {
            return;
        }
        $map = (array)$this->getConfig('map');
        if (!$map) {
            return;
        }

        $query->formatResults(function (CollectionInterface $results) use ($map) {
            if ($results->isEmpty()) {
                return $results;
            }
            $ids = [];
            foreach ($results as $row) {
                $id = $row instanceof EntityInterface ? $row->get('id') : ($row['id'] ?? null);
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
            if (!$ids) {
                return $results;
            }
            $values = $this->fetchEavValues($ids, $map);
            return $results->map(function ($row) use ($map, $values) {
                $eid = $row instanceof EntityInterface ? $row->get('id') : ($row['id'] ?? null);
                if ($eid === null) {
                    return $row;
                }
                foreach ($map as $field => $meta) {
                    $attr = $meta['attribute'] ?? $field;
                    $value = $values[$eid][$attr] ?? ($row instanceof EntityInterface ? $row->get($field) : ($row[$field] ?? null));
                    if ($row instanceof EntityInterface) {
                        $row->set($field, $value);
                        $row->setDirty($field, false);
                    } else {
                        $row[$field] = $value;
                    }
                }
                return $row;
            });
        });
    }

    /**
     * Resolve attribute ID and create attribute if missing.
     *
     * @param string $name Attribute name.
     * @param string $type Normalized type.
     * @return string Attribute ID.
     */
    protected function attributeId(string $name, string $type): string
    {
        if (isset($this->attributeIdCache[$name])) {
            return $this->attributeIdCache[$name];
        }
        $Attributes = $this->getTableLocator()->get('Eav.Attributes');
        $attr = $Attributes->find()->select(['id', 'name'])->where(['name' => $name])->first();
        if (!$attr) {
            $entity = $Attributes->newEntity(['name' => $name, 'data_type' => $type]);
            $Attributes->saveOrFail($entity);
            $id = (string)$entity->id;
            $this->attributeIdCache[$name] = $id;
            $this->attributeNameCache[$id] = $name;
            return $id;
        }
        $id = (string)$attr->id;
        $this->attributeIdCache[$name] = $id;
        $this->attributeNameCache[$id] = $name;
        return $id;
    }

    /**
     * Resolve AV table class name.
     *
     * @param string $type Normalized type.
     * @param string|null $storage JSON storage.
     * @return string
     */
    protected function avTableClass(string $type, ?string $storage = null): string
    {
        return 'Eav.Av' . $this->tableTypeSegment($type, $storage) . $this->pkSuffix();
    }

    /**
     * Get AV table instance for a type and storage.
     *
     * @param string $type Normalized type.
     * @param string|null $storage JSON storage.
     * @return \Cake\ORM\Table
     */
    protected function tableFor(string $type, ?string $storage = null): \Cake\ORM\Table
    {
        return $this->getTableLocator()->get($this->avTableClass($type, $storage));
    }

    /**
     * Save or update an EAV value for the entity.
     *
     * @param mixed $entityId Entity ID.
     * @param string $attributeName Attribute name.
     * @param string $type Type name (raw).
     * @param mixed $val Value.
     * @param array<string,mixed> $meta Attribute metadata for storage overrides.
     * @return void
     */
    public function saveEavValue(mixed $entityId, string $attributeName, string $type, mixed $val, array $meta = []): void
    {
        $normalized = $this->normalizeType($type, $meta);
        $attrId = $this->attributeId($attributeName, $normalized['type']);
        $tbl = $this->tableFor($normalized['type'], $normalized['storage']);
        $value = $this->castValueForType($normalized['type'], $val);
        $entityField = $this->entityIdField();
        $data = [
            'entity_table' => (string)$this->getConfig('entityTable'),
            $entityField => $entityId,
            'attribute_id' => $attrId,
            'val' => $value,
        ];
        $row = $tbl->find()
            ->where([
                'entity_table' => $data['entity_table'],
                'attribute_id' => $attrId,
                $entityField => $entityId,
            ])
            ->first();
        if ($row) {
            $tbl->patchEntity($row, ['val' => $value]);
        } else {
            $row = $tbl->newEntity($data);
        }
        try {
            $tbl->saveOrFail($row);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                sprintf('Failed to save EAV value for %s:%s', $attributeName, (string)$entityId),
                0,
                $e,
            );
        }
    }

    /**
     * @param array<string> $ids
     * @param array<string,array> $map
     * @return array<string,array<string,mixed>>
     */
    protected function fetchEavValues(array $ids, array $map): array
    {
        if (!$ids) {
            return [];
        }
        $byType = [];
        foreach ($map as $meta) {
            $type = $meta['type'] ?? 'string';
            $normalized = $this->normalizeType((string)$type, (array)$meta);
            $key = $normalized['type'] . ':' . ($normalized['storage'] ?? '');
            $byType[$key] = $normalized;
        }
        $entityField = $this->entityIdField();
        $rawRows = [];
        $attributeIds = [];
        foreach ($byType as $normalized) {
            $tbl = $this->tableFor($normalized['type'], $normalized['storage']);
            $rows = $tbl->find()
                ->where(['entity_table' => (string)$this->getConfig('entityTable')])
                ->where([$entityField . ' IN' => $ids])
                ->all();
            foreach ($rows as $r) {
                $attrId = (string)$r->get('attribute_id');
                $rawRows[] = [
                    'entity_id' => (string)$r->get($entityField),
                    'attribute_id' => $attrId,
                    'val' => $r->get('val'),
                ];
                $attributeIds[$attrId] = true;
            }
        }
        $nameMap = $this->attributeNameCache;
        if ($attributeIds) {
            $Attributes = $this->getTableLocator()->get('Eav.Attributes');
            $attrs = $Attributes->find()
                ->select(['id', 'name'])
                ->where(['id IN' => array_keys($attributeIds)])
                ->all();
            foreach ($attrs as $attr) {
                $id = (string)$attr->id;
                $nameMap[$id] = (string)$attr->name;
                $this->attributeNameCache[$id] = (string)$attr->name;
            }
        }
        $out = [];
        foreach ($rawRows as $row) {
            $name = $nameMap[$row['attribute_id']] ?? $row['attribute_id'];
            $out[$row['entity_id']][$name] = $row['val'];
        }
        return $out;
    }

    // Example finder
    public function findByAttribute(Query $query, array $options): Query
    {
        $attribute = (string)($options['attribute'] ?? '');
        if ($attribute === '') {
            return $query;
        }
        $type = (string)($options['type'] ?? 'string');
        $op = strtoupper((string)($options['op'] ?? '='));
        $value = $options['value'] ?? null;

        $normalized = $this->normalizeType($type, $options);
        $attrId = $this->attributeId($attribute, $normalized['type']);
        $avTable = $this->tableFor($normalized['type'], $normalized['storage']);
        $alias = $avTable->getAlias();
        $table = $avTable->getTable();
        $entityField = $this->entityIdField();
        $root = $query->getRepository()->getAlias();
        $rootPk = (string)current((array)$query->getRepository()->getPrimaryKey());

        $supported = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'ILIKE', 'IN'];
        if (!in_array($op, $supported, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported operator "%s".', $op));
        }

        $query->innerJoin(
            [$alias => $table],
            [
                "{$alias}.{$entityField} = {$root}.{$rootPk}",
                "{$alias}.entity_table" => (string)$this->getConfig('entityTable'),
                "{$alias}.attribute_id" => $attrId,
            ],
        );

        if ($op === 'IN') {
            $query->where(["{$alias}.val IN" => (array)$value]);
        } else {
            $query->where(["{$alias}.val {$op}" => $value]);
        }

        return $query;
    }

    /**
     * Normalize type names and extract JSON storage overrides.
     *
     * @param string $type Raw type.
     * @param array<string,mixed> $meta Metadata.
     * @return array{type: string, storage: string|null}
     */
    protected function normalizeType(string $type, array $meta = []): array
    {
        $raw = strtolower(trim($type));
        $storage = $this->resolveJsonStorage($raw, $meta);
        if ($raw === 'jsonb') {
            $raw = 'json';
            $storage = 'jsonb';
        }
        $normalized = $this->typeAliases[$raw] ?? $raw;
        if (!$this->isSupportedType($normalized)) {
            throw new InvalidArgumentException(sprintf('Unsupported EAV type "%s".', $type));
        }
        return ['type' => $normalized, 'storage' => $storage];
    }

    /**
     * Resolve JSON storage based on config and per-field metadata.
     *
     * @param string $rawType Raw type.
     * @param array<string,mixed> $meta Metadata.
     * @return string|null
     */
    protected function resolveJsonStorage(string $rawType, array $meta = []): ?string
    {
        if ($rawType !== 'json' && $rawType !== 'jsonb') {
            return null;
        }
        $storage = $meta['jsonStorage'] ?? $meta['storage'] ?? $this->getConfig('jsonStorage');
        if (!is_string($storage) || !in_array($storage, ['json', 'jsonb'], true)) {
            $storage = 'json';
        }
        return $storage;
    }

    /**
     * Check if a type is supported by TypeFactory or custom EAV types.
     *
     * @param string $type Normalized type.
     * @return bool
     */
    protected function isSupportedType(string $type): bool
    {
        if (in_array($type, $this->customTypes, true)) {
            return true;
        }
        return TypeFactory::getMap($type) !== null;
    }

    /**
     * Cast values based on type to avoid lossy string conversions.
     *
     * @param string $type Normalized type.
     * @param mixed $value Value.
     * @return mixed
     */
    protected function castValueForType(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        switch ($type) {
            case 'integer':
            case 'smallinteger':
            case 'tinyinteger':
            case 'biginteger':
                return (int)$value;
            case 'decimal':
                return is_string($value) ? $value : (string)$value;
            case 'float':
                return (float)$value;
            case 'boolean':
                return (bool)$value;
            case 'date':
                if ($value instanceof Date) {
                    return $value;
                }
                if ($value instanceof \DateTimeInterface) {
                    return Date::createFromFormat('Y-m-d', $value->format('Y-m-d'));
                }
                if (is_string($value)) {
                    return Date::parseDate($value) ?? $value;
                }
                return $value;
            case 'datetime':
            case 'datetimefractional':
            case 'timestamp':
            case 'timestampfractional':
            case 'timestamptimezone':
                if ($value instanceof DateTime) {
                    return $value;
                }
                if ($value instanceof \DateTimeInterface) {
                    return DateTime::createFromFormat('Y-m-d H:i:s', $value->format('Y-m-d H:i:s'), $value->getTimezone());
                }
                if (is_string($value)) {
                    return DateTime::parseDateTime($value) ?? $value;
                }
                return $value;
            case 'time':
                if ($value instanceof Time) {
                    return $value;
                }
                if ($value instanceof \DateTimeInterface) {
                    return Time::parseTime($value->format('H:i:s')) ?? $value;
                }
                if (is_string($value)) {
                    return Time::parseTime($value) ?? $value;
                }
                return $value;
            case 'json':
                if (is_array($value) || $value instanceof \JsonSerializable || is_object($value)) {
                    try {
                        return json_encode($value, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        throw new InvalidArgumentException('Invalid JSON value.', 0, $e);
                    }
                }
                return $value;
            case 'uuid':
            case 'binaryuuid':
            case 'nativeuuid':
            case 'fk_uuid':
                return (string)$value;
            default:
                return $value;
        }
    }

    /**
     * Get entity id field name based on pkType.
     *
     * @return string
     */
    protected function entityIdField(): string
    {
        return $this->getConfig('pkType') === 'int' ? 'entity_int_id' : 'entity_id';
    }

    /**
     * Build the table type segment for class naming.
     *
     * @param string $type Normalized type.
     * @param string|null $storage JSON storage.
     * @return string
     */
    protected function tableTypeSegment(string $type, ?string $storage = null): string
    {
        $tableType = $this->tableTypeAliases[$type] ?? $type;
        if ($type === 'json' && $storage === 'jsonb') {
            $tableType = 'jsonb';
        }
        return Inflector::camelize($tableType);
    }

    /**
     * Get the class suffix for the configured pkType.
     *
     * @return string
     */
    protected function pkSuffix(): string
    {
        return $this->getConfig('pkType') === 'int' ? 'Int' : 'Uuid';
    }
}
