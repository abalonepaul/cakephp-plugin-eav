<?php
declare(strict_types=1);

namespace Eav\Model\Behavior;

use Cake\Chronos\ChronosDate;
use Cake\Chronos\ChronosDateTime;
use Cake\Chronos\ChronosTime;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query\UpdateQuery;
use Cake\Database\TypeFactory;
use Cake\Datasource\ConnectionInterface;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\I18n\Time as I18nTime;
use Cake\ORM\Query;

/**
 * Internal helper for JSON Storage Mode (Postgres JSONB).
 *
 * Notes:
 * - Intended to be used from EavBehavior. The behavior should gate usage with storage === 'json_column'
 *   and driver instanceof Postgres.
 * - All methods assume Postgres JSONB. Other vendors can be added later behind mapped helpers.
 */
trait JsonColumnStorageTrait
{
    /**
     * Whether JSON Storage Mode is enabled.
     */
    protected function isJsonColumnMode(): bool
    {
        /** @var \Cake\ORM\Behavior $this */
        $storage = (string)($this->getConfig('storage') ?? 'tables');

        return $storage === 'json_column';
    }

    /**
     * Resolve configured JSON column name for the attached table.
     *
     * @throws \RuntimeException when missing.
     */
    protected function getJsonColumn(): string
    {
        /** @var \Cake\ORM\Behavior $this */
        $col = (string)($this->getConfig('jsonColumn') ?? '');

        if ($col === '') {
            throw new \RuntimeException('JSON Storage Mode requires jsonColumn config (e.g., attrs/spec).');
        }

        return $col;
    }

    /**
     * Get fully qualified column reference using the table alias from the query or the behavior's table alias.
     */
    protected function getAliasedJsonColumn(Query $query): string
    {
        /** @var \Cake\ORM\Behavior $this */
        $alias = $this->getTable()->getAlias();
        $column = $this->getJsonColumn();

        // Use dot-notation alias.column; quoting left to the driver.
        return $alias . '.' . $column;
    }

    /**
     * Map EAV/Cake types to Postgres casts used for JSONB text extraction.
     */
    protected function pgCastForType(string $type): ?string
    {
        $t = strtolower($type);
        return match ($t) {
            'integer', 'smallinteger', 'tinyinteger', 'biginteger' => 'int',
            'float', 'decimal' => 'numeric',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp', 'datetimefractional', 'timestampfractional', 'timestamptimezone' => 'timestamp',
            'time' => 'time',
            'uuid', 'binaryuuid', 'nativeuuid' => 'uuid',
            default => null, // string/json/others => no cast (text compare)
        };
    }

    /**
     * Try to resolve the attribute's logical type to drive SQL casting and PHP hydration.
     *
     * Precedence:
     *  1) behavior config attributeTypeMap[name] => type
     *  2) Eav.Attributes table (data_type)
     *  3) inference from provided filter value (PHP type or scalar format)
     *  4) default to 'string'
     *
     * @param string $attr Attribute (JSON key) name.
     * @param mixed $filterValue Optional condition value to help inference.
     * @return string Normalized type.
     */
    protected function resolveAttributeType(string $attr, mixed $filterValue = null): string
    {
        /** @var \Cake\ORM\Behavior $this */
        $map = (array)($this->getConfig('attributeTypeMap') ?? []);
        if (isset($map[$attr]) && is_string($map[$attr])) {
            return strtolower((string)$map[$attr]);
        }

        // Lookup in Eav.EavAttributes if present (CakePHP 5.x: use TableLocator via Behavior).
        try {
            /** @var \Cake\ORM\Behavior $this */
            $Attributes = $this->getTableLocator()->get('Eav.EavAttributes');
            $row = $Attributes->find()
                ->select(['data_type'])
                ->where(['name' => $attr])
                ->enableHydration(false)
                ->first();
            if ($row && isset($row['data_type'])) {
                return strtolower((string)$row['data_type']);
            }
        } catch (\Throwable) {
            // Ignore if table not available in context (tests or early boot).
        }

        // Infer from PHP/filter value when available.
        if (is_int($filterValue)) {
            return 'integer';
        }
        if (is_float($filterValue)) {
            return 'float';
        }
        if (is_bool($filterValue)) {
            return 'boolean';
        }
        if ($filterValue instanceof Date || $filterValue instanceof FrozenDate || $filterValue instanceof ChronosDate) {
            return 'date';
        }
        if ($filterValue instanceof DateTime || $filterValue instanceof FrozenTime || $filterValue instanceof ChronosDateTime || $filterValue instanceof I18nTime) {
            return 'datetime';
        }

        if (is_string($filterValue)) {
            // Strict numeric check first
            if (preg_match('/^-?\d+$/', $filterValue)) {
                return 'integer';
            }
            if (preg_match('/^-?\d+\.\d+$/', $filterValue)) {
                return 'float';
            }
            // Strict ISO-ish dates (very cautious to avoid false positives)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterValue)) {
                return 'date';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $filterValue)) {
                return 'datetime';
            }
            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $filterValue)) {
                return 'time';
            }
        }

        return 'string';
    }

    /**
     * Build a SELECT projection fragment to expose an attribute as a native field.
     *
     * Example result:
     *   [
     *     'sql' => "((alias.attrs ->> :k_color)) AS color",
     *     'params' => [':k_color' => 'color'],
     *     'alias' => 'color'
     *   ]
     */
    protected function buildSelectProjection(Query $query, string $attr, ?string $type = null): array
    {
        $col = $this->getAliasedJsonColumn($query);
        $cast = $this->pgCastForType($type ?? 'string');

        // Inline the JSON key as a safely quoted string literal to avoid binding in projections.
        $quotedKey = $this->quoteSqlLiteral($attr);
        $extract = "({$col} ->> {$quotedKey})";
        $expr = $cast ? "({$extract})::{$cast}" : $extract;

        return [
            'sql' => $expr,
            'params' => [],
            'alias' => $attr,
        ];
    }

    /**
     * Build a WHERE fragment for a single condition.
     *
     * Supports =, !=, >, >=, <, <=, IN, NOT IN, LIKE/ILIKE, IS NULL, IS NOT NULL
     */
    protected function buildWhereFragment(Query $query, string $attr, string $operator, mixed $value, ?string $type = null): array
    {
        $op = strtoupper(trim($operator));
        $col = $this->getAliasedJsonColumn($query);
        $resolvedType = $this->resolveAttributeType($attr, $value);
        $cast = $this->pgCastForType($type ?? $resolvedType);

        $keyParam = ':k_' . preg_replace('/[^a-z0-9_]+/i', '_', $attr);
        $valParam = ':v_' . substr(hash('sha1', $attr . '_' . (string)microtime(true)), 0, 8);

        $extract = "({$col} ->> {$keyParam})";
        $lhs = $cast ? "({$extract})::{$cast}" : $extract;

        $sql = '';
        $params = [$keyParam => $attr];

        switch ($op) {
            case '=':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                $sql = "{$lhs} {$op} {$valParam}";
                $params[$valParam] = $value;
                break;

            case 'IN':
            case 'NOT IN':
                // Expand as list params
                $placeholders = [];
                if (!is_iterable($value)) {
                    $value = [$value];
                }
                $i = 0;
                foreach ($value as $v) {
                    $p = "{$valParam}_{$i}";
                    $params[$p] = $v;
                    $placeholders[] = $p;
                    $i++;
                }
                $sql = "{$lhs} {$op} (" . implode(',', $placeholders) . ')';
                break;

            case 'LIKE':
            case 'ILIKE':
                $sql = "{$lhs} {$op} {$valParam}";
                $params[$valParam] = $value;
                break;

            case 'IS NULL':
            case 'IS NOT NULL':
                // Use jsonb_exists to avoid PDO "?" placeholder conflicts.
                $exists = "jsonb_exists(({$col})::jsonb, {$keyParam})";
                $sql = ($op === 'IS NULL') ? "NOT {$exists}" : $exists;
                break;

            default:
                // Fallback as equality
                $sql = "{$lhs} = {$valParam}";
                $params[$valParam] = $value;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Build an ORDER BY fragment for a single attribute.
     *
     * Returns raw SQL string like "((alias.attrs ->> :k_year_start)::int) DESC" and params.
     */
    protected function buildOrderFragment(Query $query, string $attr, string $direction = 'ASC', ?string $type = null): array
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $col = $this->getAliasedJsonColumn($query);
        $resolvedType = $this->resolveAttributeType($attr, null);
        $cast = $this->pgCastForType($type ?? $resolvedType);

        // Inline the JSON key as a safely quoted string literal to avoid binding in ORDER BY.
        $quotedKey = $this->quoteSqlLiteral($attr);
        $extract = "({$col} ->> {$quotedKey})";
        $lhs = $cast ? "({$extract})::{$cast}" : $extract;

        // Default NULLS LAST for more intuitive ordering of sparse attributes.
        return [
            'sql' => "{$lhs} {$dir} NULLS LAST",
            'params' => [],
        ];
    }

    /**
     * Add a projection to the Cake Query using a raw SQL fragment and parameters.
     * In CakePHP 5, select() appends by default (overwrite = false), preserving base columns.
     */
    protected function applyProjection(Query $query, array $projection): void
    {
        // Preserve existing base columns; append the projection.
        $query->select([
            $projection['alias'] => $query->newExpr($projection['sql']),
        ]);
        $this->bindParams($query, $projection['params']);
    }

    /**
     * Add a WHERE condition to the Cake Query using a raw SQL fragment and parameters.
     */
    protected function applyWhere(Query $query, array $fragment, string $boolean = 'AND'): void
    {
        $expr = new QueryExpression();
        $expr->add($fragment['sql']);
        if (strtoupper($boolean) === 'OR') {
            $query->where(function (QueryExpression $e) use ($expr) {
                return $e->or($expr);
            });
        } else {
            $query->where($expr);
        }
        $this->bindParams($query, $fragment['params']);
    }

    /**
     * Apply ORDER BY using a raw SQL fragment and parameters.
     */
    protected function applyOrder(Query $query, array $fragment): void
    {
        $query->orderBy($query->newExpr($fragment['sql']));
        $this->bindParams($query, $fragment['params']);
    }

    /**
     * Bind named params to the query safely.
     */
    protected function bindParams(Query $query, array $params): void
    {
        $conn = $query->getConnection();
        foreach ($params as $name => $value) {
            $type = $this->inferPdoType($value);
            $query->bind($name, $value, $type);
        }
    }

    /**
     * Infer PDO/Cake type for binding.
     */
    protected function inferPdoType(mixed $value): ?string
    {
        if (is_int($value)) {
            return TypeFactory::build('integer')->getBaseType();
        }
        if (is_float($value)) {
            return TypeFactory::build('float')->getBaseType();
        }
        if (is_bool($value)) {
            return TypeFactory::build('boolean')->getBaseType();
        }
        if ($value instanceof Date || $value instanceof FrozenDate || $value instanceof ChronosDate) {
            return TypeFactory::build('date')->getBaseType();
        }
        if ($value instanceof DateTime || $value instanceof FrozenTime || $value instanceof ChronosDateTime || $value instanceof I18nTime) {
            return TypeFactory::build('datetime')->getBaseType();
        }
        if (is_array($value) || is_object($value)) {
            return TypeFactory::build('json')->getBaseType();
        }
        return TypeFactory::build('string')->getBaseType();
    }

    /**
     * Build a jsonb_set(...) update expression that updates one or more keys atomically.
     *
     * Example usage:
     *  $sql = $this->buildJsonbSetUpdateSql($conn, 'engines', 'attrs', ['color' => 'red', 'year_start' => 2010]);
     * Then run an UpdateQuery setting "attrs = <returned SQL>".
     *
     * @param \Cake\Datasource\ConnectionInterface $conn Connection
     * @param string $table Fully qualified table name or unqualified (relies on search_path)
     * @param string $jsonColumn Column name (e.g., attrs)
     * @param array<string, mixed> $keyValues Map of key => value to set (null value will remove the key)
     * @param string|null $alias Optional table alias used in the update's FROM/SET context
     * @return array{sql:string,params:array} New column SQL expression and params.
     */
    protected function buildJsonbSetUpdateSql(
        ConnectionInterface $conn,
        string $table,
        string $jsonColumn,
        array $keyValues,
        ?string $alias = null
    ): array {
        // Start from COALESCE(column, '{}'::jsonb)
        $colRef = ($alias ?: $table) . '.' . $jsonColumn;
        $base = "COALESCE({$colRef}, '{}'::jsonb)";

        $sql = $base;
        $params = [];

        $i = 0;
        foreach ($keyValues as $key => $value) {
            if ($value === null) {
                // Remove key: (col - 'key')
                $sql = "({$sql} - :rmk_{$i})";
                $params[":rmk_{$i}"] = $key;
                $i++;
                continue;
            }

            // Set key: jsonb_set(sql, '{key}'::text[], to_jsonb(:v::type), true)
            $valueParam = ":v_{$i}";
            $keyParam = ":k_{$i}";
            $pgType = $this->pgCastForType($this->resolveAttributeType($key, $value));

            // If we know a pgType, cast appropriately to avoid string semantics.
            $valExpr = $pgType
                ? "to_jsonb({$valueParam}::{$pgType})"
                : "to_jsonb({$valueParam}::text)";

            // jsonb_set second argument must be text[] (path), not text
            $pathExpr = "('{' || {$keyParam} || '}')::text[]";
            $sql = "jsonb_set({$sql}, {$pathExpr}, {$valExpr}, true)";
            $params[$valueParam] = $value;
            $params[$keyParam] = $key;
            $i++;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Quote an identifier (column/alias) via the query's driver.
     */
    protected function quoteIdentifier(Query $query, string $identifier): string
    {
        return $query->getConnection()->getDriver()->quoteIdentifier($identifier);
    }

    /**
     * Safely quote a scalar value as an SQL string literal by doubling single quotes.
     * This is only used for embedding JSON keys (attribute names) into expressions
     * where PDO binding is not reliable (SELECT projection/ORDER BY).
     */
    protected function quoteSqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
