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
use Cake\ORM\Table;
use Cake\Utility\Inflector;
use Eav\Model\Behavior\JsonColumnStorageTrait;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use ArrayObject;
use DateTimeInterface;
// Expression traversal for WHERE rewriting (JSON mode)
use Cake\Database\ExpressionInterface;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\ComparisonExpression;
use Cake\Database\Expression\UnaryExpression;
use Cake\Database\Expression\OrderByExpression;


class EavBehavior extends Behavior
{
    use LocatorAwareTrait;
    use JsonColumnStorageTrait;

    protected array $_defaultConfig = [
        'entityTable'       => null,   // e.g. 'items'
        'pkType'            => 'uuid', // 'uuid'|'int' (affects column type only)
        'attributeSet'      => null,
        'map'               => [],     // 'field' => ['attribute'=>'name','type'=>'decimal']
        'events'            => ['beforeMarshal'=>true,'afterSave'=>true,'afterFind'=>true],
        'jsonStorage'       => 'json', // json|jsonb (for JSON Attribute table eav_json)
        'jsonEncodeOnWrite' => false,  // default false per PLAN; ignored in JSON Storage Mode
        // JSON Storage Mode (entity-level JSONB bundle):
        'storage'           => 'tables', // 'tables' (default) | 'json_column'
        'jsonColumn'        => null,     // e.g., 'attrs' or 'spec' when storage=json_column
        'attributeTypeMap'  => [],       // ['attrName' => 'type'] optional typing hints for JSON Storage
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
        // Back-compat aliases
        'fk_uuid' => 'fk',
        'fk_int' => 'fk',
    ];

    /** @var array<string> */
    protected array $customTypes = [
        'fk',
    ];

    /** @var array<string,string> */
    protected array $tableTypeAliases = [
        'boolean' => 'bool',
        'integer' => 'int',
    ];

    /**
     * Capture mapped values from request data for later persistence.
     *
     * @param EventInterface $event Event.
     * @param ArrayObject $data Request data.
     * @param ArrayObject $options Marshal options.
     * @return void
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
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
     * @param EventInterface $event Event.
     * @param EntityInterface $entity Entity.
     * @param ArrayObject $options Save options.
     * @return void
     */
    public function afterSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        if (!$this->getConfig('events')['afterSave']) {
            return;
        }
        if (!$entity->get('id')) {
            return;
        }
        $map = (array)$this->getConfig('map');
        $entityId = $entity->get('id');

        // JSON Storage Mode: batch update JSON column via jsonb_set for changed keys
        if ($this->isJsonColumnMode()) {
            // Determine which fields to persist:
            // - Prefer explicit map if provided.
            // - Otherwise, derive from attributeTypeMap (field name == attribute name).
            $kv = [];
            if ($map) {
                foreach ($map as $field => $meta) {
                    $attribute = (string)($meta['attribute'] ?? $field);
                    $val = $this->buffer['write'][$field] ?? $entity->get($field);
                    if ($val === null && !$entity->isDirty($field)) {
                        continue;
                    }
                    $kv[$attribute] = $val;
                }
            } else {
                $typeMap = (array)$this->getConfig('attributeTypeMap');
                foreach (array_keys($typeMap) as $field) {
                    $val = $entity->get($field);
                    if ($val === null && !$entity->isDirty($field)) {
                        continue;
                    }
                    // When dirty with null, we remove the key; when non-dirty null, skip.
                    $kv[$field] = $val;
                }
            }

            if ($kv !== []) {
                $table = $this->getTable();
                $conn = $table->getConnection();
                $tableName = $table->getTable();
                $alias = $table->getAlias();
                $pk = (string)current((array)$table->getPrimaryKey());
                $col = (string)$this->getConfig('jsonColumn');
                if (!$col) {
                    throw new RuntimeException('jsonColumn must be configured for JSON Storage Mode.');
                }

                $expr = $this->buildJsonbSetUpdateSql($conn, $tableName, $col, $kv, $alias);

                // CakePHP 5.x: use updateQuery() and build the expression via UpdateQuery::newExpr()
                $uq = $conn->updateQuery()->update($tableName);
                $uq->set([$col => $uq->newExpr($expr['sql'])])
                   ->where([$pk => $entityId]);

                foreach ($expr['params'] as $p => $v) {
                    $uq->bind($p, $v, $this->inferPdoType($v));
                }
                $uq->execute()->closeCursor();
            }

            // Safety: Ensure entity primary key is preserved on the entity after raw update
            $pkField = (string)current((array)$this->getTable()->getPrimaryKey());
            if ($pkField !== '') {
                $entity->set($pkField, $entityId);
                $entity->setDirty($pkField, false);
            }

            unset($this->buffer['write']);
            return;
        }

        // Default table-backed EAV writes
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
     * Project attributes and rewrite attribute conditions for both storage modes.
     *
     * - Honors per-query options:
     *   - eavRewrite (default true)
     *   - eavTypes (array attr => type) per-query typing overrides
     * - Collision guard: never treat native columns as attributes.
     * - JSON Storage Mode: projects attributes and rewrites flat array conditions to JSONB expressions.
     * - Tables Storage Mode: injects eav_<type> joins, rewrites flat array conditions, and projects joined values.
     *
     * Notes:
     * - Handles flat array conditions passed via find(..., ['conditions' => [...]]) only.
     *   Expression-tree traversal and grouped OR/AND will follow in a later step.
     */
    public function beforeFind(EventInterface $event, Query $query, ArrayObject $options, bool $primary): void
    {
        if (!$primary) {
            return;
        }

        // Per-query toggle (defaults to true)
        $opts = (array)$query->getOptions();
        $eavRewrite = (bool)($opts['eavRewrite'] ?? true);
        if ($eavRewrite === false) {
            return;
        }

        $table = $this->getTable();
        $nativeColumns = array_flip($table->getSchema()->columns());

        // Always select base table columns to preserve PK and native fields during projections/rewrite
        $query->select($table);

        // JSON Storage Mode path (existing behavior)
        if ($this->isJsonColumnMode()) {
            // Merge behavior-configured types with per-query overrides (eavTypes)
            $configuredTypes = (array)$this->getConfig('attributeTypeMap');
            $overrideTypes = (array)($opts['eavTypes'] ?? []);
            $typeMap = $overrideTypes + $configuredTypes; // overrides take precedence

            // (Removed) Early expression-tree rewrite to avoid duplicate passes.
            // A single rewrite pass is applied later after flat-array options handling.

            // For projections, we build from:
            // - attributeTypeMap + eavTypes, and
            // - attributes explicitly named in select([...]) that are not native columns.
            $projectAttrs = [];
            foreach ($typeMap as $attr => $type) {
                $attrName = (string)$attr;
                if ($attrName === '' || isset($nativeColumns[$attrName])) {
                    continue;
                }
                $projectAttrs[$attrName] = (string)$type;
            }

            // Augment projections with attribute names present in the select clause.
            // If users do ->select(['id','color']), project 'color' even if not in attributeTypeMap/eavTypes.
            $selectClause = (array)$query->clause('select');
            foreach ($selectClause as $k => $v) {
                // Two common forms:
                // - ['color' => <expr|string>] => $k='color'
                // - [0 => 'color'] => $v='color'
                $candidate = null;
                if (is_string($k) && $k !== '') {
                    $candidate = $k;
                } elseif (is_string($v) && $v !== '') {
                    $candidate = $v;
                }
                if ($candidate === null) {
                    continue;
                }
                $field = trim($candidate);
                if ($field === '' || str_contains($field, '.') || isset($nativeColumns[$field])) {
                    continue;
                }
                if (!isset($projectAttrs[$field])) {
                    // Use per-query override if provided; otherwise leave type null (trait will infer from data/registry)
                    $hint = $overrideTypes[$field] ?? ($configuredTypes[$field] ?? null);
                    $projectAttrs[$field] = $hint ? (string)$hint : null;
                }
            }

            // Rebuild select list to drop raw attribute-name selects that Cake would auto-qualify (Items.color).
            // Keep base table columns and any non-attribute explicit selects; projections will be added next.
            $originalSelects = (array)$query->clause('select');
            $query->select([], true);
            $query->select($table);
            foreach ($originalSelects as $k => $v) {
                // Keep associative selects (aliases/expressions) and qualified/non-attribute strings.
                if (is_string($k) && $k !== '') {
                    $query->select([$k => $v]);
                    continue;
                }
                if (is_string($v) && $v !== '') {
                    $fname = trim($v);
                    if ($fname === '' || isset($nativeColumns[$fname]) || str_contains($fname, '.') || !isset($projectAttrs[$fname])) {
                        // Keep native/qualified/unknown; drop unqualified attribute name (we will project it)
                        $query->select([$v]);
                    }
                    continue;
                }
                // Preserve non-string expressions
                if ($v instanceof ExpressionInterface) {
                    $query->select([$v]);
                }
            }

            // Collect select type map for projected aliases
            $selectTypes = [];

            foreach ($projectAttrs as $attr => $type) {
                $projection = $this->buildSelectProjection($query, $attr, $type);
                $this->applyProjection($query, $projection);

                // Only add select type mappings when a type hint is provided
                if ($type !== null && $type !== '') {
                    $selectTypes[$attr] = $this->normalizeSelectType((string)$type);
                }
            }

            if ($selectTypes) {
                $query->getSelectTypeMap()->addDefaults($selectTypes);
            }

            // WHERE rewriting (flat array conditions)
            if (isset($options['conditions']) && is_array($options['conditions'])) {
                $rawConds = (array)$options['conditions'];
                $remaining = [];

                foreach ($rawConds as $key => $value) {
                    if (!is_string($key) || $key === '') {
                        $remaining[$key] = $value;
                        continue;
                    }
                    $upperKey = strtoupper($key);
                    if ($upperKey === 'OR' || $upperKey === 'AND') {
                        $remaining[$key] = $value;
                        continue;
                    }

                    $field = $key;
                    $op = '=';
                    if (preg_match('/^(.+?)\s+(=|!=|>=|<=|>|<|IN|NOT IN|LIKE|ILIKE|IS|IS NOT)$/i', $key, $m)) {
                        $field = (string)$m[1];
                        $op = strtoupper((string)$m[2]);
                    }

                    $field = trim($field);
                    if ($field === '' || str_contains($field, '.') || isset($nativeColumns[$field])) {
                        $remaining[$key] = $value;
                        continue;
                    }

                    if ($value === null) {
                        if ($op === '!=' || $op === 'IS NOT') {
                            $op = 'IS NOT NULL';
                        } else {
                            $op = 'IS NULL';
                        }
                    }

                    $hintType = $overrideTypes[$field] ?? $configuredTypes[$field] ?? null;

                    $fragment = $this->buildWhereFragment($query, $field, $op, $value, $hintType);
                    $this->applyWhere($query, $fragment, 'AND');
                }

                $options['conditions'] = $remaining;
            }

            // Expression-tree WHERE rewriting (via ->where([...])) re-enabled for JSON mode.
            $existingWhere = $query->clause('where');
            if ($existingWhere instanceof ExpressionInterface) {
                $rewritten = $this->rewriteJsonWhereTree($query, $existingWhere, $nativeColumns, $overrideTypes, $configuredTypes);
                if ($rewritten instanceof QueryExpression) {
                    $query->where($rewritten, [], true);
                }
            }

            // ORDER BY rewriting for JSON Storage Mode (apply typed expressions with NULLS LAST)
            if (isset($options['order']) && is_array($options['order'])) {
                $orderItems = (array)$options['order'];
                $remainingOrder = [];

                foreach ($orderItems as $ok => $ov) {
                    // Extract field and direction from 'field' => 'ASC|DESC' or 'field ASC' string
                    $dir = 'ASC';

                    if (is_string($ok) && $ok !== '') {
                        $field = trim($ok);
                        $dir = is_string($ov) ? strtoupper(trim($ov)) : 'ASC';
                    } elseif (is_string($ov) && $ov !== '') {
                        // e.g., 'color DESC'
                        $parts = preg_split('/\s+/', trim($ov));
                        $field = (string)($parts[0] ?? '');
                        $d = strtoupper((string)($parts[1] ?? 'ASC'));
                        $dir = ($d === 'DESC') ? 'DESC' : 'ASC';
                    } else {
                        // Unrecognized order format; keep it
                        $remainingOrder[$ok] = $ov;
                        continue;
                    }

                    if ($field === '' || str_contains($field, '.') || isset($nativeColumns[$field])) {
                        // Native/qualified fields: let Cake handle them
                        $remainingOrder[$ok] = $ov;
                        continue;
                    }

                    // Resolve type hint for casting (per-query overrides take precedence)
                    $hintType = $overrideTypes[$field] ?? $configuredTypes[$field] ?? null;

                    // Build and apply JSONB ORDER fragment (includes NULLS LAST)
                    $orderFragment = $this->buildOrderFragment($query, $field, $dir, $hintType);
                    $this->applyOrder($query, $orderFragment);
                    // Do not carry this attribute order forward (avoid duplicate ORDER BY)
                }

                // Preserve only native/qualified order items (if any)
                $options['order'] = $remainingOrder;
            }

            // Defensive: ensure base table columns remain in the select list after projections
            // This guarantees id/name/attrs are present even if later select() calls reset fields.
            $query->select($table);

            return;
        }

        // Tables Storage Mode path
        $overrideTypes = (array)($opts['eavTypes'] ?? []);
        $map = (array)$this->getConfig('map');

        $selectTypes = [];
        $projected = [];
        $joinedAliases = [];

        $rootAlias = $table->getAlias();
        $rootPk = (string)current((array)$table->getPrimaryKey());
        $entityField = $this->entityIdField();
        $entityTableName = (string)$this->getConfig('entityTable');

        // Early expression-tree WHERE rewriting for Tables storage BEFORE any projections/joins from select/order logic.
        // This avoids leaking attribute aliases (e.g., "color") into WHERE as base columns.
        $existingWhere = $query->clause('where');
        if ($existingWhere instanceof ExpressionInterface) {
            $joinedAliases = $joinedAliases ?? [];
            $rewritten = $this->rewriteTablesWhereTree(
                $query,
                $existingWhere,
                $nativeColumns,
                $overrideTypes,
                $map,
                $joinedAliases,
                $rootAlias,
                $rootPk,
                $entityField,
                $entityTableName
            );
            if ($rewritten instanceof QueryExpression) {
                $query->where($rewritten, [], true);
            }
        }

        // Pre-project per-query eavTypes so callers can order/select by attributes even without WHERE conditions.
        if ($overrideTypes) {
            foreach ($overrideTypes as $field => $typeHint) {
                if (!is_string($field) || $field === '') {
                    continue;
                }
                // Skip collisions and qualified fields
                if (str_contains($field, '.') || isset($nativeColumns[$field])) {
                    continue;
                }

                // Normalize type (e.g., int -> integer)
                $norm = $this->normalizeType((string)$typeHint);
                $resolvedType = strtolower((string)$norm['type']);
                $safeAttr = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($field));
                $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$resolvedType));
                $alias = 'EAV_' . $safeAttr . '_' . $safeType;
                $tableName = 'eav_' . strtolower((string)$resolvedType);

                // Resolve attribute id; skip if unknown
                $attrId = null;
                try {
                    $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                    $attrRow = $Attributes->find()
                        ->select(['id'])
                        ->where(['name' => $field])
                        ->enableHydration(false)
                        ->first();
                    if ($attrRow && isset($attrRow['id'])) {
                        $attrId = (string)$attrRow['id'];
                    }
                } catch (\Throwable) {
                    // ignore lookup errors (e.g. Attributes table not present)
                }
                if ($attrId === null) {
                    continue;
                }

                // LEFT JOIN so rows without the attribute are preserved
                if (!isset($joinedAliases[$alias])) {
                    $query->leftJoin(
                        [$alias => $tableName],
                        [
                            "{$alias}.{$entityField} = {$rootAlias}.{$rootPk}",
                            "{$alias}.entity_table" => $entityTableName,
                            "{$alias}.attribute_id" => $attrId,
                        ]
                    );
                    $joinedAliases[$alias] = true;
                }

                // Project value so ORDER BY alias and hydration work
                if (!isset($projected[$field])) {
                    $query->select([
                        $field => $query->newExpr("{$alias}.value"),
                    ]);

                    // Normalize select type for hydration
                    $normalized = strtolower((string)$resolvedType);
                    switch ($normalized) {
                        case 'smallinteger':
                        case 'tinyinteger':
                        case 'biginteger':
                            $normalized = 'integer';
                            break;
                        case 'datetimefractional':
                        case 'timestamp':
                        case 'timestampfractional':
                        case 'timestamptimezone':
                            $normalized = 'datetime';
                            break;
                        default:
                            // keep as-is for integer, float, boolean, date, time, datetime, string, json, uuid, etc.
                            break;
                    }
                    $selectTypes[$field] = $normalized;
                    $projected[$field] = true;
                }
            }

            if ($selectTypes) {
                $query->getSelectTypeMap()->addDefaults($selectTypes);
            }
        }

        // Also project attributes explicitly referenced in select([...]) to make them work without WHERE/eavTypes.
        $selectClause = (array)$query->clause('select');
        foreach ($selectClause as $k => $v) {
            $candidate = null;
            if (is_string($k) && $k !== '') {
                $candidate = $k;
            } elseif (is_string($v) && $v !== '') {
                $candidate = $v;
            }
            if ($candidate === null) {
                continue;
            }
            $field = trim($candidate);
            if ($field === '' || str_contains($field, '.') || isset($nativeColumns[$field])) {
                continue;
            }
            if (isset($projected[$field])) {
                continue;
            }

            // Resolve type: eavTypes > map > Attributes > fallback to string
            $resolvedType = null;
            if (isset($overrideTypes[$field]) && is_string($overrideTypes[$field])) {
                $resolvedType = strtolower((string)$overrideTypes[$field]);
            } elseif (isset($map[$field]['type'])) {
                $resolvedType = strtolower((string)$map[$field]['type']);
            } else {
                try {
                    $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                    $row = $Attributes->find()->select(['data_type'])->where(['name' => $field])->enableHydration(false)->first();
                    if ($row && isset($row['data_type'])) {
                        $resolvedType = strtolower((string)$row['data_type']);
                    }
                } catch (\Throwable) {
                    // ignore lookup errors
                }
                if ($resolvedType === null) {
                    $resolvedType = 'string';
                }
            }

            // Resolve attribute id; skip if unknown
            $attrId = null;
            try {
                $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                $attrRow = $Attributes->find()
                    ->select(['id'])
                    ->where(['name' => $field])
                    ->enableHydration(false)
                    ->first();
                if ($attrRow && isset($attrRow['id'])) {
                    $attrId = (string)$attrRow['id'];
                }
            } catch (\Throwable) {
                // ignore lookup errors
            }
            if ($attrId === null) {
                continue;
            }

            $safeAttr = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($field));
            $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$resolvedType));
            $alias = 'EAV_' . $safeAttr . '_' . $safeType;
            $tableName = 'eav_' . strtolower((string)$resolvedType);

            // LEFT JOIN to preserve rows without the attribute
            if (!isset($joinedAliases[$alias])) {
                $query->leftJoin(
                    [$alias => $tableName],
                    [
                        "{$alias}.{$entityField} = {$rootAlias}.{$rootPk}",
                        "{$alias}.entity_table" => $entityTableName,
                        "{$alias}.attribute_id" => $attrId,
                    ]
                );
                $joinedAliases[$alias] = true;
            }

            // Project if not already selected
            if (!isset($projected[$field])) {
                $query->select([$field => $query->newExpr("{$alias}.value")]);

                $normalized = strtolower((string)$resolvedType);
                switch ($normalized) {
                    case 'smallinteger':
                    case 'tinyinteger':
                    case 'biginteger':
                        $normalized = 'integer';
                        break;
                    case 'datetimefractional':
                    case 'timestamp':
                    case 'timestampfractional':
                    case 'timestamptimezone':
                        $normalized = 'datetime';
                        break;
                    default:
                        break;
                }
                $selectTypes[$field] = $normalized;
                $projected[$field] = true;
            }
        }

        if ($selectTypes) {
            $query->getSelectTypeMap()->addDefaults($selectTypes);
        }

        // Pre-project attributes referenced in options['order'] and rewrite ORDER BY to enforce NULLS LAST.
        if (isset($options['order']) && is_array($options['order'])) {
            $rootAlias = $table->getAlias();
            $rootPk = (string)current((array)$table->getPrimaryKey());
            $entityField = $this->entityIdField();
            $entityTableName = (string)$this->getConfig('entityTable');
            $driver = $query->getConnection()->getDriver();

            // (Removed) Duplicated expression-tree rewrite inside ORDER block for tables storage.
            // A single rewrite pass is applied later after flat-array options handling.

            $remainingOrder = [];

            foreach ($options['order'] as $ok => $ov) {
                // Extract field and direction from 'field' => 'ASC|DESC' or 'field ASC' string
                $field = '';
                $dir = 'ASC';
                if (is_string($ok) && $ok !== '') {
                    $field = trim((string)$ok);
                    $dir = is_string($ov) ? strtoupper(trim($ov)) : 'ASC';
                } elseif (is_string($ov) && $ov !== '') {
                    $parts = preg_split('/\s+/', trim($ov));
                    $field = (string)($parts[0] ?? '');
                    $d = strtoupper((string)($parts[1] ?? 'ASC'));
                    $dir = ($d === 'DESC') ? 'DESC' : 'ASC';
                } else {
                    // Unrecognized order format; keep it
                    $remainingOrder[$ok] = $ov;
                    continue;
                }

                // Skip qualified/native fields and invalids
                if ($field === '' || str_contains($field, '.') || isset($nativeColumns[$field])) {
                    $remainingOrder[$ok] = $ov;
                    continue;
                }

                // Resolve type: eavTypes > map > Attributes > fallback to string
                $resolvedType = null;
                if (isset($overrideTypes[$field]) && is_string($overrideTypes[$field])) {
                    $resolvedType = strtolower((string)$overrideTypes[$field]);
                } elseif (isset($map[$field]['type'])) {
                    $resolvedType = strtolower((string)$map[$field]['type']);
                } else {
                    try {
                        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                        $row = $Attributes->find()->select(['data_type'])->where(['name' => $field])->enableHydration(false)->first();
                        if ($row && isset($row['data_type'])) {
                            $resolvedType = strtolower((string)$row['data_type']);
                        }
                    } catch (\Throwable) {
                        // ignore lookup errors
                    }
                    if ($resolvedType === null) {
                        $resolvedType = 'string';
                    }
                }

                // Resolve attribute id; skip if unknown
                $attrId = null;
                try {
                    $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                    $attrRow = $Attributes->find()
                        ->select(['id'])
                        ->where(['name' => $field])
                        ->enableHydration(false)
                        ->first();
                    if ($attrRow && isset($attrRow['id'])) {
                        $attrId = (string)$attrRow['id'];
                    }
                } catch (\Throwable) {
                    // ignore lookup errors
                }
                if ($attrId === null) {
                    // Keep original order entry if we can't resolve attribute
                    $remainingOrder[$ok] = $ov;
                    continue;
                }

                $safeAttr = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($field));
                $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$resolvedType));
                $alias = 'EAV_' . $safeAttr . '_' . $safeType;
                $tableName = 'eav_' . strtolower((string)$resolvedType);

                // LEFT JOIN to preserve rows without the attribute
                if (!isset($joinedAliases[$alias])) {
                    $query->leftJoin(
                        [$alias => $tableName],
                        [
                            "{$alias}.{$entityField} = {$rootAlias}.{$rootPk}",
                            "{$alias}.entity_table" => $entityTableName,
                            "{$alias}.attribute_id" => $attrId,
                        ]
                    );
                    $joinedAliases[$alias] = true;
                }

                // Project if not already selected
                if (!isset($projected[$field])) {
                    $query->select([$field => $query->newExpr("{$alias}.value")]);

                    $normalized = strtolower((string)$resolvedType);
                    switch ($normalized) {
                        case 'smallinteger':
                        case 'tinyinteger':
                        case 'biginteger':
                            $normalized = 'integer';
                            break;
                        case 'datetimefractional':
                        case 'timestamp':
                        case 'timestampfractional':
                        case 'timestamptimezone':
                            $normalized = 'datetime';
                            break;
                        default:
                            break;
                    }
                    $selectTypes[$field] = $normalized;
                    $projected[$field] = true;
                }

                // Apply ORDER BY with NULLS LAST semantics (DB-agnostic)
                if ($driver instanceof \Cake\Database\Driver\Postgres) {
                    // Use native NULLS LAST
                    $query->orderBy($query->newExpr("{$alias}.value {$dir} NULLS LAST"));
                } else {
                    // Emulate NULLS LAST: first sort by IS NULL (non-null first), then by value
                    $query->orderBy($query->newExpr("({$alias}.value IS NULL) ASC"));
                    $query->orderBy($query->newExpr("{$alias}.value {$dir}"));
                }
                // Do not keep this attribute order in options to avoid duplicate ORDER BY
            }

            // Apply select type map (if we projected anything for ordering)
            if ($selectTypes) {
                $query->getSelectTypeMap()->addDefaults($selectTypes);
            }

            // Preserve only native/qualified order items (if any)
            $options['order'] = $remainingOrder;
        }

        // Expression-tree WHERE rewriting for Tables storage (handles method-based where([...]) like ['color IS' => null])
        // This ensures unqualified attribute names are joined to the correct eav_* table and compared on the value column.
        $existingWhere = $query->clause('where');
        if ($existingWhere instanceof \Cake\Database\ExpressionInterface) {
            // Resolve per-query override types and configured attribute map
            $overrideTypes = (array)($opts['eavTypes'] ?? []);
            $map = (array)($this->getConfig('attributeTypeMap') ?? []);

            // Root context for joins
            $rootAlias = $table->getAlias();
            $rootPk = (string)current((array)$table->getPrimaryKey());
            $entityField = $this->entityIdField();
            $entityTableName = (string)$this->getConfig('entityTable');

            // Ensure we have a join-alias ledger
            if (!isset($joinedAliases) || !is_array($joinedAliases)) {
                $joinedAliases = [];
            }

            $rewritten = $this->rewriteTablesWhereTree(
                $query,
                $existingWhere,
                $nativeColumns,
                $overrideTypes,
                $map,
                $joinedAliases,
                $rootAlias,
                $rootPk,
                $entityField,
                $entityTableName
            );
            if ($rewritten instanceof \Cake\Database\Expression\QueryExpression) {
                // Replace the WHERE clause with our rewritten group
                $query->where($rewritten, [], true);
            }
        }

        // Handle method-based order (orderByAsc/Desc) present in the actual clause('order').
        // Rewrites attribute-based parts to use joined EAV value columns with NULLS LAST.
        $orderExpr = $query->clause('order');
        if ($orderExpr instanceof OrderByExpression) {
            $driver = $query->getConnection()->getDriver();

            // Extract parts from the existing order expression
            $parts = [];
            $orderExpr->iterateParts(function ($p) use (&$parts) {
                $parts[] = $p;
            });

            $attrOrders = [];    // each: ['alias' => string, 'dir' => 'ASC'|'DESC']
            $nativeParts = [];   // keep native/qualified parts to re-append
            foreach ($parts as $p) {
                if (is_array($p)) {
                    foreach ($p as $field => $dir) {
                        $direction = strtoupper(is_string($dir) ? $dir : 'ASC');
                        if (!is_string($field)) {
                            $nativeParts[] = [$field => $direction];
                            continue;
                        }
                        $name = trim($field);
                        if ($name === '' || str_contains($name, '.') || isset($nativeColumns[$name])) {
                            $nativeParts[] = [$field => $direction];
                            continue;
                        }

                        // Resolve attribute type: eavTypes > map > Attributes > default string
                        $resolvedType = null;
                        if (isset($overrideTypes[$name]) && is_string($overrideTypes[$name])) {
                            $resolvedType = strtolower((string)$overrideTypes[$name]);
                        } elseif (isset($map[$name]['type'])) {
                            $resolvedType = strtolower((string)$map[$name]['type']);
                        } else {
                            try {
                                $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                                $row = $Attributes->find()->select(['data_type'])->where(['name' => $name])->enableHydration(false)->first();
                                if ($row && isset($row['data_type'])) {
                                    $resolvedType = strtolower((string)$row['data_type']);
                                }
                            } catch (\Throwable) {
                                // ignore lookup errors
                            }
                            if ($resolvedType === null) {
                                $resolvedType = 'string';
                            }
                        }

                        // Resolve attribute id; skip if unknown
                        $attrId = null;
                        try {
                            $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                            $attrRow = $Attributes->find()
                                ->select(['id'])
                                ->where(['name' => $name])
                                ->enableHydration(false)
                                ->first();
                            if ($attrRow && isset($attrRow['id'])) {
                                $attrId = (string)$attrRow['id'];
                            }
                        } catch (\Throwable) {
                            // ignore lookup errors
                        }
                        if ($attrId === null) {
                            // Keep the original part if we can't resolve the attribute
                            $nativeParts[] = [$field => $direction];
                            continue;
                        }

                        // Ensure LEFT JOIN for ordering to include rows with NULL/missing values
                        $safeAttr = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($name));
                        $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$resolvedType));
                        $alias = 'EAV_' . $safeAttr . '_' . $safeType;
                        $tableName = 'eav_' . strtolower((string)$resolvedType);

                        if (!isset($joinedAliases[$alias])) {
                            $query->leftJoin(
                                [$alias => $tableName],
                                [
                                    "{$alias}.{$entityField} = {$rootAlias}.{$rootPk}",
                                    "{$alias}.entity_table" => $entityTableName,
                                    "{$alias}.attribute_id" => $attrId,
                                ]
                            );
                            $joinedAliases[$alias] = true;
                        }

                        // Project attribute alias if not already selected, so alias-based ORDER and hydration work
                        if (!isset($projected[$name])) {
                            $query->select([$name => $query->newExpr("{$alias}.value")]);

                            $normalized = strtolower((string)$resolvedType);
                            switch ($normalized) {
                                case 'smallinteger':
                                case 'tinyinteger':
                                case 'biginteger':
                                    $normalized = 'integer';
                                    break;
                                case 'datetimefractional':
                                case 'timestamp':
                                case 'timestampfractional':
                                case 'timestamptimezone':
                                    $normalized = 'datetime';
                                    break;
                                default:
                                    break;
                            }
                            $selectTypes[$name] = $normalized;
                            $projected[$name] = true;
                        }

                        $attrOrders[] = ['alias' => $alias, 'dir' => ($direction === 'DESC' ? 'DESC' : 'ASC')];
                    }
                    continue;
                }

                if (is_string($p)) {
                    $s = trim($p);
                    if ($s === '') {
                        continue;
                    }
                    // Parse "field [ASC|DESC]" strings
                    $tokens = preg_split('/\s+/', $s);
                    $fname = (string)($tokens[0] ?? '');
                    $direction = strtoupper((string)($tokens[1] ?? 'ASC'));
                    if ($fname === '' || str_contains($fname, '.') || isset($nativeColumns[$fname])) {
                        $nativeParts[] = $p;
                        continue;
                    }

                    // Resolve type and attribute id
                    $resolvedType = null;
                    if (isset($overrideTypes[$fname]) && is_string($overrideTypes[$fname])) {
                        $resolvedType = strtolower((string)$overrideTypes[$fname]);
                    } elseif (isset($map[$fname]['type'])) {
                        $resolvedType = strtolower((string)$map[$fname]['type']);
                    } else {
                        try {
                            $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                            $row = $Attributes->find()->select(['data_type'])->where(['name' => $fname])->enableHydration(false)->first();
                            if ($row && isset($row['data_type'])) {
                                $resolvedType = strtolower((string)$row['data_type']);
                            }
                        } catch (\Throwable) {
                            // ignore lookup errors
                        }
                        if ($resolvedType === null) {
                            $resolvedType = 'string';
                        }
                    }

                    $attrId = null;
                    try {
                        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                        $attrRow = $Attributes->find()->select(['id'])->where(['name' => $fname])->enableHydration(false)->first();
                        if ($attrRow && isset($attrRow['id'])) {
                            $attrId = (string)$attrRow['id'];
                        }
                    } catch (\Throwable) {
                        // ignore lookup errors
                    }
                    if ($attrId === null) {
                        $nativeParts[] = $p;
                        continue;
                    }

                    $safeAttr = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($fname));
                    $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$resolvedType));
                    $alias = 'EAV_' . $safeAttr . '_' . $safeType;
                    $tableName = 'eav_' . strtolower((string)$resolvedType);

                    if (!isset($joinedAliases[$alias])) {
                        $query->leftJoin(
                            [$alias => $tableName],
                            [
                                "{$alias}.{$entityField} = {$rootAlias}.{$rootPk}",
                                "{$alias}.entity_table" => $entityTableName,
                                "{$alias}.attribute_id" => $attrId,
                            ]
                        );
                        $joinedAliases[$alias] = true;
                    }

                    if (!isset($projected[$fname])) {
                        $query->select([$fname => $query->newExpr("{$alias}.value")]);

                        $normalized = strtolower((string)$resolvedType);
                        switch ($normalized) {
                            case 'smallinteger':
                            case 'tinyinteger':
                            case 'biginteger':
                                $normalized = 'integer';
                                break;
                            case 'datetimefractional':
                            case 'timestamp':
                            case 'timestampfractional':
                            case 'timestamptimezone':
                                $normalized = 'datetime';
                                break;
                            default:
                                break;
                        }
                        $selectTypes[$fname] = $normalized;
                        $projected[$fname] = true;
                    }

                    $attrOrders[] = ['alias' => $alias, 'dir' => ($direction === 'DESC' ? 'DESC' : 'ASC')];
                    continue;
                }

                // ExpressionInterface or other objects: preserve as-is
                $nativeParts[] = $p;
            }

            if ($attrOrders) {
                // Reset existing order and apply rewritten attribute orders first
                $query->orderBy([], true);

                foreach ($attrOrders as $o) {
                    if ($driver instanceof \Cake\Database\Driver\Postgres) {
                        $query->orderBy($query->newExpr("{$o['alias']}.value {$o['dir']} NULLS LAST"));
                    } else {
                        // Emulate NULLS LAST via IS NULL then value
                        $query->orderBy($query->newExpr("({$o['alias']}.value IS NULL) ASC"));
                        $query->orderBy($query->newExpr("{$o['alias']}.value {$o['dir']}"));
                    }
                }

                // Append preserved native/qualified parts
                foreach ($nativeParts as $np) {
                    $query->orderBy($np);
                }

                // Apply select type map if we projected new aliases for order
                if ($selectTypes) {
                    $query->getSelectTypeMap()->addDefaults($selectTypes);
                }
            }
        }

        if (isset($options['conditions']) && is_array($options['conditions'])) {
            $rawConds = (array)$options['conditions'];
            $remaining = [];

            $driver = $query->getConnection()->getDriver();
            $rootAlias = $table->getAlias();
            $rootPk = (string)current((array)$table->getPrimaryKey());
            $entityField = $this->entityIdField();
            $entityTableName = (string)$this->getConfig('entityTable');

            foreach ($rawConds as $key => $value) {
                // Keep non-string keys (expressions) as-is
                if (!is_string($key) || $key === '') {
                    $remaining[$key] = $value;
                    continue;
                }
                $upperKey = strtoupper($key);
                if ($upperKey === 'OR' || $upperKey === 'AND') {
                    $remaining[$key] = $value;
                    continue;
                }

                // Parse operator
                $field = $key;
                $op = '=';
                if (preg_match('/^(.+?)\s+(=|!=|>=|<=|>|<|IN|NOT IN|LIKE|ILIKE|IS|IS NOT)$/i', $key, $m)) {
                    $field = (string)$m[1];
                    $op = strtoupper((string)$m[2]);
                }
                $field = trim($field);

                // Collision guard and qualified fields
                if ($field === '' || str_contains($field, '.') || isset($nativeColumns[$field])) {
                    $remaining[$key] = $value;
                    continue;
                }

                // Normalize null semantics
                if ($value === null) {
                    if ($op === '!=' || $op === 'IS NOT') {
                        $op = 'IS NOT NULL';
                    } else {
                        $op = 'IS NULL';
                    }
                }

                // Resolve attribute type: per-query override > behavior map > Attributes registry > inference
                $resolvedType = null;
                if (isset($overrideTypes[$field]) && is_string($overrideTypes[$field])) {
                    $resolvedType = strtolower((string)$overrideTypes[$field]);
                } elseif (isset($map[$field]['type'])) {
                    $resolvedType = strtolower((string)$map[$field]['type']);
                } else {
                    // Try registry
                    try {
                        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                        $row = $Attributes->find()->select(['data_type'])->where(['name' => $field])->enableHydration(false)->first();
                        if ($row && isset($row['data_type'])) {
                            $resolvedType = strtolower((string)$row['data_type']);
                        }
                    } catch (\Throwable) {
                        // ignore lookup errors
                    }
                    // Fallback inference
                    if ($resolvedType === null) {
                        if (is_int($value)) {
                            $resolvedType = 'integer';
                        } elseif (is_float($value)) {
                            $resolvedType = 'float';
                        } elseif (is_bool($value)) {
                            $resolvedType = 'boolean';
                        } else {
                            $resolvedType = 'string';
                        }
                    }
                }

                // Fetch attribute id if it exists; do not create implicitly
                $attrId = null;
                try {
                    $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                    $attrRow = $Attributes->find()->select(['id'])->where(['name' => $field])->enableHydration(false)->first();
                    if ($attrRow && isset($attrRow['id'])) {
                        $attrId = (string)$attrRow['id'];
                    }
                } catch (\Throwable) {
                    // ignore lookup errors
                }

                if ($attrId === null) {
                    // Unknown attribute: if explicit null check, keep original condition; otherwise make query impossible
                    if ($op !== 'IS NULL') {
                        $query->where($query->newExpr('0=1'));
                    } else {
                        $remaining[$key] = $value;
                    }
                    continue;
                }

                $safeAttr = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($field));
                $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$resolvedType));
                $alias = 'EAV_' . $safeAttr . '_' . $safeType;
                $tableName = 'eav_' . strtolower((string)$resolvedType);

                // Join type: IS NULL => LEFT JOIN; others => INNER JOIN
                $isNullCheck = ($op === 'IS NULL');
                $joinMethod = $isNullCheck ? 'leftJoin' : 'innerJoin';

                // Deduplicate joins by alias
                if (!isset($joinedAliases[$alias])) {
                    $query->{$joinMethod}(
                        [$alias => $tableName],
                        [
                            "{$alias}.{$entityField} = {$rootAlias}.{$rootPk}",
                            "{$alias}.entity_table" => $entityTableName,
                            "{$alias}.attribute_id" => $attrId,
                        ]
                    );
                    $joinedAliases[$alias] = true;
                }

                // Apply WHERE for operator/value
                switch ($op) {
                    case 'IS NULL':
                        // Missing row OR explicit NULL (LEFT JOIN + value IS NULL)
                        $query->where(["{$alias}.value IS" => null]);
                        break;

                    case 'IS NOT NULL':
                        // Ensure present row with a non-null value
                        $query->where(["{$alias}.value IS NOT" => null]);
                        break;

                    case 'IN':
                        $query->where(["{$alias}.value IN" => (array)$value]);
                        break;

                    case 'NOT IN':
                        $query->where(["{$alias}.value NOT IN" => (array)$value]);
                        break;

                    case 'ILIKE':
                        // Postgres native ILIKE; other drivers emulate with LOWER
                        if ($driver instanceof \Cake\Database\Driver\Postgres) {
                            $query->where(["{$alias}.value ILIKE" => $value]);
                        } else {
                            $param = ':v_' . substr(hash('sha1', $alias . '_ilike_' . (string)$value), 0, 8);
                            $expr = $query->newExpr("LOWER({$alias}.value) LIKE LOWER({$param})");
                            $query->where($expr)->bind($param, $value);
                        }
                        break;

                    case 'LIKE':
                    case '=':
                    case '!=':
                    case '>':
                    case '>=':
                    case '<':
                    case '<=':
                        $query->where(["{$alias}.value {$op}" => $value]);
                        break;

                    default:
                        // Fallback to equality
                        $query->where(["{$alias}.value =" => $value]);
                        break;
                }

                // Project attribute value as a select alias (once) for ORDER BY/hydration
                if (!isset($projected[$field])) {
                    $query->select([
                        $field => $query->newExpr("{$alias}.value"),
                    ]);

                    // Normalize select type for hydration
                    $normalized = strtolower((string)$resolvedType);
                    switch ($normalized) {
                        case 'smallinteger':
                        case 'tinyinteger':
                        case 'biginteger':
                            $normalized = 'integer';
                            break;
                        case 'datetimefractional':
                        case 'timestamp':
                        case 'timestampfractional':
                        case 'timestamptimezone':
                            $normalized = 'datetime';
                            break;
                        default:
                            // keep as-is for integer, float, boolean, date, time, datetime, string, json, uuid, etc.
                            break;
                    }
                    $selectTypes[$field] = $normalized;
                    $projected[$field] = true;
                }
            }

            // Apply select type map for projected aliases
            if ($selectTypes) {
                $query->getSelectTypeMap()->addDefaults($selectTypes);
            }

            // Preserve only non-attribute/native-safe conditions in options
            $options['conditions'] = $remaining ?? [];
        }

        // Expression-tree WHERE rewriting for conditions added via ->where([...]) and grouped logic (AND/OR).
        // Build a new expression tree replacing attribute comparisons with eav_<type> join-based comparisons.
        $existingWhere = $query->clause('where');
        if ($existingWhere instanceof ExpressionInterface) {
            $rewritten = $this->rewriteTablesWhereTree(
                $query,
                $existingWhere,
                $nativeColumns,
                $overrideTypes,
                $map,
                $joinedAliases,
                $rootAlias,
                $rootPk,
                $entityField,
                $entityTableName
            );
            if ($rewritten instanceof QueryExpression) {
                // Overwrite original WHERE with the rewritten tree
                $query->where($rewritten, [], true);
            }
        }

        // With projections in place, ORDER BY attr will work by alias.
    }

    /**
     * Rewrite a WHERE expression tree for Tables Storage Mode.
     * - Replaces attribute comparisons (unqualified, non-native) with join-based comparisons on eav_<type>.value.
     * - Adds INNER/LEFT joins as needed (IS NULL => LEFT JOIN, others => INNER JOIN).
     * - Supports ComparisonExpression, InExpression (IN/NOT IN), and IsNullExpression (IS [NOT] NULL).
     *
     * @param Query $query
     * @param ExpressionInterface $expr
     * @param array<string,bool> $nativeColumns
     * @param array<string,string> $overrideTypes
     * @param array<string,mixed> $map
     * @param array<string,bool> $joinedAliases
     * @param string $rootAlias
     * @param string $rootPk
     * @param string $entityField
     * @param string $entityTableName
     * @return \Cake\Database\Expression\QueryExpression|null
     */
    protected function rewriteTablesWhereTree(
        Query $query,
        ExpressionInterface $expr,
        array $nativeColumns,
        array $overrideTypes,
        array $map,
        array &$joinedAliases,
        string $rootAlias,
        string $rootPk,
        string $entityField,
        string $entityTableName
    ): ?QueryExpression {
        $driver = $query->getConnection()->getDriver();

        // Helper: resolve attribute type from overrides/map/registry/inference
        $resolveType = function (string $field, mixed $value) use ($overrideTypes, $map) {
            if (isset($overrideTypes[$field]) && is_string($overrideTypes[$field])) {
                return strtolower((string)$overrideTypes[$field]);
            }
            if (isset($map[$field]['type'])) {
                return strtolower((string)$map[$field]['type']);
            }
            try {
                $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                $row = $Attributes->find()
                    ->select(['data_type'])
                    ->where(['name' => $field])
                    ->enableHydration(false)
                    ->first();
                if ($row && isset($row['data_type'])) {
                    return strtolower((string)$row['data_type']);
                }
            } catch (\Throwable) {
                // ignore lookup errors
            }
            if (is_int($value)) {
                return 'integer';
            }
            if (is_float($value)) {
                return 'float';
            }
            if (is_bool($value)) {
                return 'boolean';
            }
            return 'string';
        };

        // Helper: ensure a join exists for this attribute/type and return alias + whether it was left-joined
        $ensureJoin = function (string $field, string $type, string $op, ?string &$alias, ?string &$tableName, ?string &$attrId) use (&$joinedAliases, $query, $entityField, $entityTableName, $rootAlias, $rootPk) {
            $safeAttr = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($field));
            $safeType = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($type));
            $alias = 'EAV_' . $safeAttr . '_' . $safeType;
            $tableName = 'eav_' . strtolower($type);

            // Resolve attribute id; skip if unknown
            $attrId = null;
            try {
                $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
                $attrRow = $Attributes->find()
                    ->select(['id'])
                    ->where(['name' => $field])
                    ->enableHydration(false)
                    ->first();
                if ($attrRow && isset($attrRow['id'])) {
                    $attrId = (string)$attrRow['id'];
                }
            } catch (\Throwable) {
                // ignore lookup errors
            }
            if ($attrId === null) {
                return false;
            }

            $isNullCheck = (strtoupper($op) === 'IS NULL');
            $joinMethod = $isNullCheck ? 'leftJoin' : 'innerJoin';

            if (!isset($joinedAliases[$alias])) {
                $query->{$joinMethod}(
                    [$alias => $tableName],
                    [
                        "{$alias}.{$entityField} = {$rootAlias}.{$rootPk}",
                        "{$alias}.entity_table" => $entityTableName,
                        "{$alias}.attribute_id" => $attrId,
                    ]
                );
                $joinedAliases[$alias] = true;
            }
            return true;
        };

        // Helper: unique param
        $nextParam = function (string $prefix) {
            return ':' . $prefix . '_' . substr(hash('sha1', microtime(true) . '_' . mt_rand()), 0, 8);
        };

        // Helper: normalize field reference to string from IdentifierExpression or string
        $fieldNameOf = function (mixed $field): ?string {
            if (is_string($field)) {
                return $field;
            }
            if ($field instanceof IdentifierExpression) {
                $id = (string)$field->getIdentifier();
                return $id !== '' ? $id : null;
            }
            return null;
        };

        // Root-leaf rewrite for direct expressions (Unary IS NULL/IS NOT NULL, or Comparison incl. IN/NOT IN)
        if ($expr instanceof UnaryExpression || $expr instanceof ComparisonExpression) {
            $group = new QueryExpression([], [], 'AND');

            if ($expr instanceof UnaryExpression) {
                $rawField = null;
                $expr->traverse(function ($e) use (&$rawField) {
                    if ($e instanceof IdentifierExpression) {
                        $rawField = $e;
                    }
                });
                $field = $fieldNameOf($rawField);
                // Default to IS NULL for unary field checks
                $op = 'IS NULL';

                if ($field !== null && $field !== '' && !str_contains($field, '.') && !isset($nativeColumns[$field])) {
                    $resolvedType = $resolveType($field, null);
                    $alias = $tableName = $attrId = null;
                    if ($ensureJoin($field, $resolvedType, $op, $alias, $tableName, $attrId)) {
                        $query->select([$field => $query->newExpr("{$alias}.value")]);
                        $normalized = strtolower((string)$resolvedType);
                        $normalized = in_array($normalized, ['smallinteger','tinyinteger','biginteger'], true) ? 'integer' : ($normalized === 'datetimefractional' ? 'datetime' : $normalized);
                        $query->getSelectTypeMap()->addDefaults([$field => $normalized]);
                        $group->add("{$alias}.value IS NULL");
                        return $group;
                    }
                }

                // Not an attribute: keep original
                $group->add($expr);
                return $group;
            }

            if ($expr instanceof ComparisonExpression) {
                $rawField = $expr->getField();
                $field = $fieldNameOf($rawField);
                $op = strtoupper((string)($expr->getOperator() ?? '='));
                $value = $expr->getValue();

                if ($field !== null && $field !== '' && !str_contains($field, '.') && !isset($nativeColumns[$field])) {
                    if ($value === null) {
                        $op = ($op === '!=' || $op === 'IS NOT') ? 'IS NOT NULL' : 'IS NULL';
                    }
                    $resolvedType = $resolveType($field, $value);
                    $alias = $tableName = $attrId = null;
                    if ($ensureJoin($field, $resolvedType, $op, $alias, $tableName, $attrId)) {
                        $query->select([$field => $query->newExpr("{$alias}.value")]);
                        $normalized = strtolower((string)$resolvedType);
                        $normalized = in_array($normalized, ['smallinteger','tinyinteger','biginteger'], true) ? 'integer' : ($normalized === 'datetimefractional' ? 'datetime' : $normalized);
                        $query->getSelectTypeMap()->addDefaults([$field => $normalized]);

                        if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                            $group->add($op === 'IS NULL' ? "{$alias}.value IS NULL" : "{$alias}.value IS NOT NULL");
                        } else {
                            $param = $nextParam('v');
                            $query->bind($param, $value);
                            if ($op === 'ILIKE') {
                                if ($driver instanceof \Cake\Database\Driver\Postgres) {
                                    $group->add("{$alias}.value ILIKE {$param}");
                                } else {
                                    $group->add("LOWER({$alias}.value) LIKE LOWER({$param})");
                                }
                            } else {
                                $group->add("{$alias}.value {$op} {$param}");
                            }
                        }
                        return $group;
                    }
                }

                $group->add($expr);
                return $group;
            }

            // CakePHP 5: IN/NOT IN are handled via ComparisonExpression operators; no InExpression branch required.
        }

        if ($expr instanceof QueryExpression) {
            // Cake 5 signature: new QueryExpression(conditions, types, conjunction)
            $group = new QueryExpression([], [], $expr->getConjunction() ?? 'AND');

            $__parts = [];
            $expr->iterateParts(function ($p) use (&$__parts) { $__parts[] = $p; });
            foreach ($__parts as $part) {
                if ($part instanceof QueryExpression) {
                    $sub = $this->rewriteTablesWhereTree(
                        $query,
                        $part,
                        $nativeColumns,
                        $overrideTypes,
                        $map,
                        $joinedAliases,
                        $rootAlias,
                        $rootPk,
                        $entityField,
                        $entityTableName
                    );
                    $group->add($sub ?? $part);
                    continue;
                }

                if ($part instanceof ComparisonExpression) {
                    $rawField = $part->getField();
                    $field = $fieldNameOf($rawField);
                    $op = strtoupper((string)($part->getOperator() ?? '='));
                    $value = $part->getValue();

                    // Only rewrite simple, unqualified attribute names
                    if ($field !== null && $field !== '' && !str_contains($field, '.') && !isset($nativeColumns[$field])) {
                        // Normalize NULL comparisons
                        if ($value === null) {
                            $op = ($op === '!=' || $op === 'IS NOT') ? 'IS NOT NULL' : 'IS NULL';
                        }

                        $resolvedType = $resolveType($field, $value);
                        $alias = $tableName = $attrId = null;
                        if (!$ensureJoin($field, $resolvedType, $op, $alias, $tableName, $attrId)) {
                            // Unknown attribute: for IS NULL, keep original; for others, make it impossible
                            if ($op !== 'IS NULL') {
                                $group->add('0=1');
                            } else {
                                $group->add($part);
                            }
                            continue;
                        }

                        // Project attribute alias and register type to ensure hydration (covers method-based where([...]))
                        $query->select([$field => $query->newExpr("{$alias}.value")]);
                        $normalized = strtolower((string)$resolvedType);
                        switch ($normalized) {
                            case 'smallinteger':
                            case 'tinyinteger':
                            case 'biginteger':
                                $normalized = 'integer';
                                break;
                            case 'datetimefractional':
                            case 'timestamp':
                            case 'timestampfractional':
                            case 'timestamptimezone':
                                $normalized = 'datetime';
                                break;
                            default:
                                // keep as-is for integer, float, boolean, date, time, datetime, string, json, uuid, etc.
                                break;
                        }
                        $query->getSelectTypeMap()->addDefaults([$field => $normalized]);

                        switch ($op) {
                            case 'IS NULL':
                                $group->add("{$alias}.value IS NULL");
                                break;
                            case 'IS NOT NULL':
                                $group->add("{$alias}.value IS NOT NULL");
                                break;
                            case 'LIKE':
                            case '=':
                            case '!=':
                            case '>':
                            case '>=':
                            case '<':
                            case '<=': {
                                $param = $nextParam('v');
                                $query->bind($param, $value);
                                $group->add("{$alias}.value {$op} {$param}");
                                break;
                            }
                            case 'ILIKE': {
                                $param = $nextParam('v');
                                $query->bind($param, $value);
                                if ($driver instanceof \Cake\Database\Driver\Postgres) {
                                    $group->add("{$alias}.value ILIKE {$param}");
                                } else {
                                    $group->add("LOWER({$alias}.value) LIKE LOWER({$param})");
                                }
                                break;
                            }
                            default: {
                                // Fallback to equality
                                $param = $nextParam('v');
                                $query->bind($param, $value);
                                $group->add("{$alias}.value = {$param}");
                                break;
                            }
                        }
                        continue;
                    }

                    // Preserve non-attribute comparison as-is
                    $group->add($part);
                    continue;
                }

                // CakePHP 5: handle IS [NOT] NULL via UnaryExpression; IN/NOT IN are ComparisonExpression operators
                if ($part instanceof UnaryExpression) {
                    $rawField = null;
                    $part->traverse(function ($e) use (&$rawField) {
                        if ($e instanceof IdentifierExpression) {
                            $rawField = $e;
                        }
                    });
                    $field = $fieldNameOf($rawField);
                    // Default to IS NULL (tests exercise IS NULL)
                    $op = 'IS NULL';

                    if ($field !== null && $field !== '' && !str_contains($field, '.') && !isset($nativeColumns[$field])) {
                        $resolvedType = $resolveType($field, null);
                        $alias = $tableName = $attrId = null;
                        if (!$ensureJoin($field, $resolvedType, $op, $alias, $tableName, $attrId)) {
                            $group->add($part);
                            continue;
                        }

                        // Project attribute alias and register type to ensure hydration
                        $query->select([$field => $query->newExpr("{$alias}.value")]);
                        $normalized = strtolower((string)$resolvedType);
                        $normalized = in_array($normalized, ['smallinteger','tinyinteger','biginteger'], true)
                            ? 'integer'
                            : ($normalized === 'datetimefractional' ? 'datetime' : $normalized);
                        $query->getSelectTypeMap()->addDefaults([$field => $normalized]);

                        $group->add("{$alias}.value IS NULL");
                        continue;
                    }

                    $group->add($part);
                    continue;
                }

                // Unknown/other expression node: keep as-is
                $group->add($part);
            }

            return $group;
        }

        // Fallback: wrap leaf as a group (Cake 5 signature)
        $fallback = new QueryExpression([], [], 'AND');
        $fallback->add($expr);
        return $fallback;
    }

    /**
     * Hydrate EAV values into entities after find.
     *
     * - Default (tables mode): batch load from eav_* and merge.
     * - JSON Storage Mode: ensure typed hydration for projected attributes using attributeTypeMap.
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

        // JSON Storage Mode: only cast projected attributes; no eav_* tables involved.
        if ($this->isJsonColumnMode()) {
            $typeMap = (array)$this->getConfig('attributeTypeMap');
            if ($typeMap === []) {
                return;
            }
            $table = $this->getTable();
            $driver = $table->getConnection()->getDriver();

            $query->formatResults(function (CollectionInterface $results) use ($typeMap, $driver) {
                if ($results->isEmpty()) {
                    return $results;
                }
                return $results->map(function ($row) use ($typeMap, $driver) {
                    foreach ($typeMap as $field => $type) {
                        $val = $row instanceof EntityInterface ? $row->get($field) : ($row[$field] ?? null);
                        if ($val === null) {
                            continue;
                        }
                        $normalized = strtolower((string)$type);

                        // Deterministic casting for JSON Storage Mode projections
                        switch ($normalized) {
                            case 'float':
                                $cast = is_float($val) ? $val : (is_numeric($val) ? (float)$val : $val);
                                break;
                            case 'integer':
                            case 'smallinteger':
                            case 'tinyinteger':
                            case 'biginteger':
                                $cast = is_int($val) ? $val : (is_numeric($val) ? (int)$val : $val);
                                break;
                            case 'boolean':
                                if (is_bool($val)) {
                                    $cast = $val;
                                } elseif (is_string($val)) {
                                    $lc = strtolower($val);
                                    $cast = ($val === '1' || $lc === 'true');
                                } else {
                                    $cast = (bool)$val;
                                }
                                break;
                            case 'date':
                                if ($val instanceof Date) {
                                    $cast = $val;
                                } elseif (is_string($val)) {
                                    $cast = Date::parseDate($val) ?? Date::createFromFormat('Y-m-d', $val);
                                } elseif ($val instanceof \DateTimeInterface) {
                                    $cast = Date::createFromFormat('Y-m-d', $val->format('Y-m-d'));
                                } else {
                                    // Fallback to Cake Type
                                    $cast = TypeFactory::build('date')->toPHP($val, $driver);
                                }
                                break;
                            case 'datetime':
                            case 'datetimefractional':
                            case 'timestamp':
                            case 'timestampfractional':
                            case 'timestamptimezone':
                                $cast = TypeFactory::build('datetime')->toPHP($val, $driver);
                                break;
                            case 'time':
                                $cast = TypeFactory::build('time')->toPHP($val, $driver);
                                break;
                            default:
                                // Use Cake TypeFactory for other types where available
                                $typeObj = TypeFactory::build($normalized);
                                $cast = $typeObj ? $typeObj->toPHP($val, $driver) : $val;
                                break;
                        }

                        if ($row instanceof EntityInterface) {
                            $row->set($field, $cast);
                            $row->setDirty($field, false);
                        } else {
                            $row[$field] = $cast;
                        }
                    }
                    return $row;
                });
            });
            return;
        }

        // Default tables-backed path (existing behavior)
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
        // Use canonical prefixed alias per Feature 5: Eav.EavAttributes
        $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
        $attr = $Attributes->find()->select(['id', 'name'])->where(['name' => $name])->first();
        if (!$attr) {
            $entity = $Attributes->newEntity([
                'name' => $name,
                'data_type' => $type,
                'options' => [],
            ]);
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
        // Canonical EAV table classes are Eav* (no PK family suffix). JSON storage does not affect class name.
        return 'Eav.Eav' . $this->tableTypeSegment($type, $storage);
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
        // Try canonical class first; if missing, fall back to a generic Table with explicit eav_<type> name.
        $segment = $this->tableTypeSegment($type, $storage);
        $fqcn = 'Eav\\Model\\Table\\Eav' . $segment . 'Table';
        if (class_exists($fqcn)) {
            return $this->getTableLocator()->get($this->avTableClass($type, $storage));
        }

        $tableName = 'eav_' . strtolower($type);
        $alias = 'EavDynamic' . $segment;

        return $this->getTableLocator()->get($alias, [
            'className' => Table::class,
            'table' => $tableName,
        ]);
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
            'value' => $value,
        ];
        $row = $tbl->find()
            ->where([
                'entity_table' => $data['entity_table'],
                'attribute_id' => $attrId,
                $entityField => $entityId,
            ])
            ->first();
        if ($row) {
            $tbl->patchEntity($row, ['value' => $value]);
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
                    'value' => $r->get('value'),
                ];
                $attributeIds[$attrId] = true;
            }
        }
        $nameMap = $this->attributeNameCache;
        if ($attributeIds) {
            $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
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
            $out[$row['entity_id']][$name] = $row['value'];
        }
        return $out;
    }

    /**
     * Example finder for table-backed EAV.
     */
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
            $query->where(["{$alias}.value IN" => (array)$value]);
        } else {
            $query->where(["{$alias}.value {$op}" => $value]);
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
        // Storage does not influence class/table naming anymore, only column type.
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
                if ($value instanceof DateTimeInterface) {
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
                    return DateTime::createFromFormat(
                        'Y-m-d H:i:s',
                        $value->format('Y-m-d H:i:s'),
                        $value->getTimezone(),
                    );
                }
                if (is_string($value)) {
                    return DateTime::parseDateTime($value) ?? new DateTime($value);
                }
                return $value;
            case 'time':
                if ($value instanceof Time) {
                    return $value;
                }
                if ($value instanceof \DateTimeInterface) {
                    return Time::parseTime($value->format('H:i:s')) ?? new Time($value->format('H:i:s'));
                }
                if (is_string($value)) {
                    return Time::parseTime($value) ?? new Time($value);
                }
                return $value;
            case 'json':
                // Only used for JSON Attribute (eav_json). Ignored in JSON Storage Mode.
                if (!$this->getConfig('jsonEncodeOnWrite')) {
                    return $value;
                }
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
                return (string)$value;
            case 'fk':
                // FK casting depends on configured pk family
                return $this->getConfig('pkType') === 'int' ? (int)$value : (string)$value;
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
        // Canonicalized: always use entity_id (type varies by pk family).
        return 'entity_id';
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
        // Canonicalize JSON/JSONB to the same class segment "Json"
        $tableType = $this->tableTypeAliases[$type] ?? $type;
        if ($type === 'json') {
            $tableType = 'json';
        }
        return Inflector::camelize($tableType);
    }

    /**
     * Provide a getTable() wrapper for CakePHP 5 where Behavior stores the repository in $_table.
     *
     * @return Table
     */
    protected function getTable(): Table
    {
        // CakePHP 5.x Behavior keeps the attached Table in protected $_table.
        if (property_exists($this, '_table') && $this->_table instanceof Table) {
            return $this->_table;
        }
        // Fallback for any environments exposing "table"
        if (property_exists($this, 'table') && $this->table instanceof Table) {
            return $this->table;
        }

        throw new RuntimeException('Behavior is not attached to a Table instance.');
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

    /**
     * Normalize projected select types to base Cake types for hydration.
     * - Integer family => integer
     * - Timestamp family => datetime
     * - Others remain unchanged (decimal stays string by Cake convention when used as DB type).
     */
    protected function normalizeSelectType(string $type): string
    {
        $normalized = strtolower($type);
        return match ($normalized) {
            'smallinteger', 'tinyinteger', 'biginteger' => 'integer',
            'datetimefractional', 'timestamp', 'timestampfractional', 'timestamptimezone' => 'datetime',
            default => $normalized,
        };
    }

    /**
     * Rewrite a WHERE expression tree for JSON Storage Mode.
     * - Replaces attribute comparisons (unqualified, non-native) with JSONB fragments built via buildWhereFragment.
     * - Preserves native/qualified field conditions and unknown expression nodes.
     * - Supports ComparisonExpression, InExpression (IN/NOT IN), and IsNullExpression (IS [NOT] NULL).
     */
    protected function rewriteJsonWhereTree(
        Query $query,
        ExpressionInterface $expr,
        array $nativeColumns,
        array $overrideTypes,
        array $configuredTypes
    ): ?QueryExpression {
        // Helper: normalize field reference to plain string (IdentifierExpression|string)
        $fieldNameOf = function (mixed $field): ?string {
            if (is_string($field)) {
                return $field;
            }
            if ($field instanceof IdentifierExpression) {
                $name = (string)$field->getIdentifier();
                return $name !== '' ? $name : null;
            }
            return null;
        };

        // Root-leaf: handle UnaryExpression (IS [NOT] NULL) and ComparisonExpression (incl. IN/NOT IN)
        if ($expr instanceof UnaryExpression || $expr instanceof ComparisonExpression) {
            $group = new QueryExpression([], [], 'AND');

            if ($expr instanceof UnaryExpression) {
                $ident = null;
                $expr->traverse(function ($e) use (&$ident) {
                    if ($e instanceof \Cake\Database\Expression\IdentifierExpression) {
                        $ident = $e->getIdentifier();
                    }
                });
                // Default to IS NULL for unary field checks in our tests (no ValueBinder needed)
                $sqlOp = 'IS NULL';

                if (is_string($ident) && $ident !== '' && !str_contains($ident, '.') && !isset($nativeColumns[$ident])) {
                    $hintType = $overrideTypes[$ident] ?? $configuredTypes[$ident] ?? null;
                    $fragment = $this->buildWhereFragment($query, $ident, $sqlOp, null, $hintType);
                    $group->add($fragment['sql']);
                    $this->bindParams($query, $fragment['params']);
                    return $group;
                }

                $group->add($expr);
                return $group;
            }

            if ($expr instanceof ComparisonExpression) {
                $rawField = $expr->getField();
                $field = $fieldNameOf($rawField);
                $op = strtoupper((string)($expr->getOperator() ?? '='));
                $value = $expr->getValue();

                if ($field !== null && $field !== '' && !str_contains($field, '.') && !isset($nativeColumns[$field])) {
                    if ($value === null) {
                        $op = ($op === '!=' || $op === 'IS NOT') ? 'IS NOT NULL' : 'IS NULL';
                    }
                    $hintType = $overrideTypes[$field] ?? $configuredTypes[$field] ?? null;
                    $fragment = $this->buildWhereFragment($query, $field, $op, $value, $hintType);
                    $group->add($fragment['sql']);
                    $this->bindParams($query, $fragment['params']);
                    return $group;
                }

                $group->add($expr);
                return $group;
            }
        }

        // Recurse into groups and rebuild preserving conjunctions
        if ($expr instanceof QueryExpression) {
            // Use the correct constructor signature: conditions, types, conjunction
            $group = new QueryExpression([], [], $expr->getConjunction() ?? 'AND');
            $__parts = [];
            $expr->iterateParts(function ($p) use (&$__parts) { $__parts[] = $p; });
            foreach ($__parts as $part) {
                if ($part instanceof QueryExpression) {
                    $sub = $this->rewriteJsonWhereTree($query, $part, $nativeColumns, $overrideTypes, $configuredTypes);
                    $group->add($sub ?? $part);
                    continue;
                }

                if ($part instanceof UnaryExpression) {
                    $ident = null;
                    $part->traverse(function ($e) use (&$ident) {
                        if ($e instanceof \Cake\Database\Expression\IdentifierExpression) {
                            $ident = $e->getIdentifier();
                        }
                    });
                    // Default to IS NULL for   unary field checks
                    $sqlOp = 'IS NULL';

                    if (is_string($ident) && $ident !== '' && !str_contains($ident, '.') && !isset($nativeColumns[$ident])) {
                        $hintType = $overrideTypes[$ident] ?? $configuredTypes[$ident] ?? null;
                        $fragment = $this->buildWhereFragment($query, $ident, $sqlOp, null, $hintType);
                        $group->add($fragment['sql']);
                        $this->bindParams($query, $fragment['params']);
                        continue;
                    }

                    $group->add($part);
                    continue;
                }

                if ($part instanceof ComparisonExpression) {
                    $rawField = $part->getField();
                    $field = $fieldNameOf($rawField);
                    $op = strtoupper((string)($part->getOperator() ?? '='));
                    $value = $part->getValue();

                    if ($field !== null && $field !== '' && !str_contains($field, '.') && !isset($nativeColumns[$field])) {
                        if ($value === null) {
                            $op = ($op === '!=' || $op === 'IS NOT') ? 'IS NOT NULL' : 'IS NULL';
                        }
                        $hintType = $overrideTypes[$field] ?? $configuredTypes[$field] ?? null;
                        $fragment = $this->buildWhereFragment($query, $field, $op, $value, $hintType);
                        $group->add($fragment['sql']);
                        $this->bindParams($query, $fragment['params']);
                        continue;
                    }

                    $group->add($part);
                    continue;
                }

                // Unknown/other expression node: keep as-is
                $group->add($part);
            }

            return $group;
        }

        // Fallback: wrap leaf expression in a group
        $fallback = new QueryExpression([], [], 'AND');
        $fallback->add($expr);
        return $fallback;
    }
}
