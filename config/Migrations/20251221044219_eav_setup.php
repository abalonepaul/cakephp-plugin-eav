<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class EavSetup extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('attributes')) {
            $this->table('attributes', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'uuid', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 191, 'null' => false])
                ->addColumn('label', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('data_type', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('options', 'json', ['null' => false])
                ->addTimestamps('created', 'modified')
                ->addIndex(['name'], ['unique' => true, 'name' => 'idx_attributes_name'])
                ->create();
        }

        if (!$this->hasTable('attribute_sets')) {
            $this->table('attribute_sets', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'uuid', ['null' => false])
                ->addColumn('name', 'string', ['limit' => 191, 'null' => false])
                ->addTimestamps('created', 'modified')
                ->addIndex(['name'], ['unique' => true, 'name' => 'idx_attribute_sets_name'])
                ->create();
        }

        if (!$this->hasTable('attribute_set_attributes')) {
            $this->table('attribute_set_attributes', ['id' => false, 'primary_key' => ['attribute_set_id', 'attribute_id']])
                ->addColumn('attribute_set_id', 'uuid', ['null' => false])
                ->addColumn('attribute_id', 'uuid', ['null' => false])
                ->addColumn('position', 'integer', ['null' => true, 'default' => 0])
                ->addForeignKey('attribute_set_id', 'attribute_sets', 'id', ['delete' => 'CASCADE'])
                ->addForeignKey('attribute_id', 'attributes', 'id', ['delete' => 'CASCADE'])
                ->create();
        }

        $tables = [
            ['table' => 'eav_string', 'valType' => 'string', 'valOptions' => array (
  'limit' => 1024,
)],
            ['table' => 'eav_text', 'valType' => 'text', 'valOptions' => array (
)],
            ['table' => 'eav_integer', 'valType' => 'integer', 'valOptions' => array (
)],
            ['table' => 'eav_smallinteger', 'valType' => 'smallinteger', 'valOptions' => array (
)],
            ['table' => 'eav_tinyinteger', 'valType' => 'tinyinteger', 'valOptions' => array (
)],
            ['table' => 'eav_biginteger', 'valType' => 'biginteger', 'valOptions' => array (
)],
            ['table' => 'eav_decimal', 'valType' => 'decimal', 'valOptions' => array (
  'precision' => 18,
  'scale' => 6,
)],
            ['table' => 'eav_float', 'valType' => 'float', 'valOptions' => array (
)],
            ['table' => 'eav_boolean', 'valType' => 'boolean', 'valOptions' => array (
)],
            ['table' => 'eav_date', 'valType' => 'date', 'valOptions' => array (
)],
            ['table' => 'eav_datetime', 'valType' => 'datetime', 'valOptions' => array (
)],
            ['table' => 'eav_time', 'valType' => 'time', 'valOptions' => array (
)],
            ['table' => 'eav_timestamp', 'valType' => 'timestamp', 'valOptions' => array (
)],
            ['table' => 'eav_json', 'valType' => 'json', 'valOptions' => array (
)],
            ['table' => 'eav_uuid', 'valType' => 'uuid', 'valOptions' => array (
)],
            ['table' => 'eav_binaryuuid', 'valType' => 'binaryuuid', 'valOptions' => array (
)],
            ['table' => 'eav_nativeuuid', 'valType' => 'nativeuuid', 'valOptions' => array (
)],
            ['table' => 'eav_fk_uuid', 'valType' => 'uuid', 'valOptions' => array (
)],
            ['table' => 'eav_fk_int', 'valType' => 'biginteger', 'valOptions' => array (
)],
        ];

        foreach ($tables as $spec) {
            if ($this->hasTable($spec['table'])) {
                continue;
            }
            $table = $this->table($spec['table'], ['id' => false, 'primary_key' => ['id']]);
            $table
                ->addColumn('id', 'uuid', ['null' => false])
                ->addColumn('entity_table', 'string', ['limit' => 191, 'null' => false])
                ->addColumn('entity_id', 'uuid', ['null' => false])
                ->addColumn('attribute_id', 'uuid', ['null' => false])
                ->addColumn('value', $spec['valType'], $spec['valOptions'])
                ->addColumn('created', 'datetime', ['null' => false])
                ->addColumn('modified', 'datetime', ['null' => false])
                ->addIndex(['entity_table', 'entity_id', 'attribute_id'], ['unique' => true, 'name' => 'idx_' . $spec['table'] . '_lookup'])
                ->addForeignKey('attribute_id', 'attributes', 'id', ['delete' => 'CASCADE'])
                ->create();
        }
    }
}