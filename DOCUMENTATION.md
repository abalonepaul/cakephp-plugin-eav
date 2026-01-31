# EAV Plugin for CakePHP 5 — Documentation

This guide explains how to install, configure, and use the EAV plugin in a CakePHP 5.2 application. It covers:
- Storage modes: typed EAV tables (default) and optional JSON Storage Mode (Postgres-only)
- Setup commands (interactive and non-interactive)
- Admin routing
- Behavior configuration, querying, saving
- Data types, PK types, attribute sets, and data migration tools

Code references (JetBrains navigation)
- [EavPlugin.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/EavPlugin.php)
- [EavBehavior.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Model/Behavior/EavBehavior.php)
- [EavSetupCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupCommand.php)
- [EavSetupInteractiveCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupInteractiveCommand.php)
- [EavCreateAttributeCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavCreateAttributeCommand.php)
- [EavMigrateJsonbToEavCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavMigrateJsonbToEavCommand.php)

1) Installation

- Requirements
  - CakePHP 5.2+
  - PHP 8.1+
  - Database drivers:
    - All: typed EAV tables (default) work across supported Cake drivers.
    - Postgres-only: JSON Storage Mode and jsonb features.
    - Raw SQL output: Postgres/MySQL; migrations are universal.

- Plugin loading (no Composer package yet)
  - Place the plugin at plugins/Eav and load it in your Application:
    ```php
    // src/Application.php
    public function bootstrap(): void
    {
        parent::bootstrap();
        $this->addPlugin('Eav');
    }
    ```
  - The plugin auto-registers console commands and a /eav route scope. See [EavPlugin.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/EavPlugin.php).

- Database initialization
  - Use the Setup command (interactive or non-interactive) to generate EAV schema migrations or raw SQL (see “Setup Commands”).

2) Data Types

- Defaults scaffolded by the generator:
  - string, text, integer, smallinteger, tinyinteger, biginteger, decimal, float, boolean, date, datetime, time, json, uuid, binaryuuid, nativeuuid, fk
- Optional/advanced (opt-in during setup):
  - char, binary, enum, timestamp, datetimefractional, timestampfractional, timestamptimezone
  - Geospatial (if mapped in your environment): geometry, point, linestring, polygon
- Unified foreign key type
  - Custom type fk stores foreign keys in a single eav_fk table. The column type matches your PK family (uuid subtype or BIGINT).
- Casting
  - Values are cast via Cake’s TypeFactory and plugin logic. Decimals preserve scale (string), numerics/booleans/dates/datetimes hydrate to appropriate native/Cake types.

3) Primary Key (PK) Types

- Families: uuid or int
- If uuid, recommended subtype by driver:
  - Postgres: nativeuuid
  - MySQL/MariaDB: binaryuuid
  - SQL Server: nativeuuid
  - SQLite: uuid (string)

4) Setup Commands

- Interactive wizard
  - Run:
    ```sh
    bin/cake eav setup
    # or explicitly
    bin/cake eav setup:interactive
    ```
  - Flow:
    - Connection selection
    - Output mode: migrations (default) or raw_sql (Postgres/MySQL)
    - PK family and UUID subtype
    - JSON Attribute storage for eav_json.value: json or jsonb (jsonb guarded to Postgres; this controls the JSON Attribute column type, not JSON Storage Mode)
    - Default behavior storage: tables (default) or json_column (Postgres-only)
    - JSON Storage Mode mapping (optional): choose app tables and JSON column names
    - jsonEncodeOnWrite (applies only to JSON Attribute writes)
    - Types to scaffold (defaults, all, or custom CSV)
    - Migration name
  - eav.json persistence (anonymized example):
    ```json
    {
      "connection": "default",
      "driver": "Cake\\Database\\Driver\\Postgres",
      "outputMode": "migrations",
      "pkType": "uuid",
      "uuidType": "nativeuuid",
      "jsonAttributeStorage": "jsonb",
      "jsonEncodeOnWrite": false,
      "storageDefault": "tables",
      "jsonColumns": {},
      "types": ["string", "integer", "json", "fk"],
      "migrationName": "EavSetup",
      "generatedAt": "2026-01-26T06:04:16+00:00"
    }
    ```

- Non-interactive (flags) — migrations
  - Generate a migration (dry-run):
    ```sh
    bin/cake eav setup --dry-run --connection default --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
    ```
  - Write the migration file:
    ```sh
    bin/cake eav setup --connection default --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
    ```
  - Apply:
    ```sh
    bin/cake migrations migrate -p Eav -c default
    ```

- Non-interactive (flags) — raw SQL
  - Generate SQL (dry-run):
    ```sh
    bin/cake eav setup --dry-run --connection default --output raw_sql --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
    ```
  - Write SQL to plugins/Eav/config/Sql/<timestamp>_eav_setup_postgres.sql (or mysql):
    ```sh
    bin/cake eav setup --connection default --output raw_sql --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
    ```
  - Apply SQL with your DB client (psql/mysql). On non-Postgres/MySQL drivers, raw SQL falls back to migrations.

- Notes
  - Generated migrations extend Migrations\BaseMigration (Cake 5).
  - Do not commit environment-specific “EavSetup” migrations generated by your environment.
  - The schema includes eav_entities, eav_attributes, eav_attribute_sets, eav_attribute_sets_eav_attributes, and typed value tables (eav_string, eav_integer, eav_json, etc.).
  - Value tables use a composite primary key (eav_entity_id, entity_id, eav_attribute_id), nullable value, created/modified timestamps, and FKs to eav_entities/eav_attributes.

5) Admin Routing

- Default plugin routes
  - The plugin exposes its UI under /eav with DashedRoute conventions. See [EavPlugin.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/EavPlugin.php).
  - Controllers:
    - /eav/eav-attributes
    - /eav/eav-attribute-sets
    - /eav/eav-entities

- Mount under /admin (host app)
  - Most apps prefer the UI behind an authenticated admin wall. In your app’s config/routes.php:
    ```php
    // config/routes.php
    use Cake\Routing\Route\DashedRoute;
    use Cake\Routing\RouteBuilder;

    $routes->prefix('Admin', function (RouteBuilder $builder): void {
        // Mount the plugin under /admin/eav
        $builder->plugin('Eav', ['path' => '/eav'], function (RouteBuilder $p): void {
            $p->setRouteClass(DashedRoute::class);
            $p->connect('/', ['controller' => 'EavAttributes', 'action' => 'index']);
            $p->fallbacks(DashedRoute::class);
        });
    });
    ```

6) Operation

- Storage modes
  - tables (default): Attributes stored in typed eav_* tables. DB-agnostic.
  - json_column (Postgres-only): Attributes stored in a JSONB column on the entity table (e.g., contacts.attrs). Query/ORDER/SELECT rewrite and atomic updates via jsonb_set.

- Behavior configuration (attach to your Table)
  - One key: attributes (no map/attributeTypeMap).
  - Tables storage (example: ContactsTable):
    ```php
    // src/Model/Table/ContactsTable.php
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->addBehavior('Eav.Eav', [
            'entityTable' => 'contacts',
            'pkType' => 'uuid',
            'storage' => 'tables',
            'attributes' => [
                'favorite_color' => ['type' => 'string', 'persist' => true],
                'year_started'   => ['type' => 'integer', 'persist' => true],
            ],
        ]);
    }
    ```
  - JSON Storage Mode (Postgres-only; example: ProductsTable):
    ```php
    // src/Model/Table/ProductsTable.php
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->addBehavior('Eav.Eav', [
            'entityTable' => 'products',
            'pkType' => 'uuid',
            'storage' => 'json_column',
            'jsonColumn' => 'attrs',
            'attributes' => [
                'color'       => ['type' => 'string'],
                'release_at'  => ['type' => 'datetime'],
                'is_active'   => ['type' => 'boolean'],
            ],
        ]);
    }
    ```

- Querying (attributes behave like native fields)
  - Tables storage:
    ```php
    $Contacts->find()
        ->select(['id', 'favorite_color'])
        ->where(['favorite_color' => 'blue'])
        ->orderByDesc('year_started')
        ->all();
    ```
  - JSON Storage Mode (Postgres-only):
    ```php
    $Products->find()
        ->select(['id', 'color'])
        ->where(['is_active' => true])
        ->orderByAsc('release_at')
        ->all();
    ```
  - Notes
    - Magic finders work (e.g., findByColor); WHERE/ORDER/SELECT are rewritten with typed casts.
    - Per-query options: eavRewrite (bool), eavTypes (['attr' => 'type']).

- Null predicates (IS NULL / IS NOT NULL)
  - Summary
    - In CakePHP 5.2, the safest, supported way to filter on NULL is to use string-form predicates, which the EAV behavior rewrites correctly in both storage modes (tables and JSON Storage Mode).
    - Array-form comparisons with a NULL right-hand side (e.g., ['color !=' => null] or ['color IS NOT' => null]) are not reliably modeled by the ORM and are not supported in method-based where([...]) in this plugin.
  - Supported forms (both storage modes)
    - Method-based where (string literal):
      ```php
      $query->where('color IS NOT NULL');
      $query->where('color IS NULL');
      ```
    - Method-based where (array of string parts):
      ```php
      $query->where(['color IS NOT NULL']);
      $query->where(['color IS NULL']);
      ```
    - Named argument "conditions" in find():
      ```php
      $Products->find('all', conditions: ['color IS NOT NULL']);
      $Products->find('all', conditions: ['color IS NULL']);
      ```
  - Notes on unsupported array forms
    - The following are not supported in method-based where([...]):
      ```php
      $query->where(['color !=' => null]);
      $query->where(['color IS NOT' => null]);
      ```
    - If you need an array-form in named-argument conditions for find(), prefer the string predicate inside the array:
      ```php
      $Products->find('all', conditions: ['color IS NOT NULL']);
      ```
  - Rationale
    - CakePHP 5.2 builds different internal expression nodes for array-form NULL comparisons that can lose the "NOT" operator context. The plugin’s rewriters operate safely on string predicates and ComparisonExpression/UnaryExpression nodes that preserve intent.
    - Supporting the unsupported array-forms would require brittle pre-parsing and is intentionally avoided.

- Saving attributes
  - Tables storage: afterSave persists attributes with persist=true to typed eav_* tables.
  - JSON Storage Mode: afterSave applies jsonb_set to update only changed keys; passing null removes a key.
  - jsonEncodeOnWrite applies only to JSON Attribute (eav_json.value) in tables storage; ignored in JSON Storage Mode.

7) Configuring Entities (registry)

- The eav_entities row maps an application table to an EAV entity id used by value tables (eav_entity_id).
- Create via UI (/eav/eav-entities) or seeds. Recommended fields:
  - name: your app table name (e.g., contacts, products)
  - storage_default: tables|json_column
  - json_column: if using JSON Storage Mode (e.g., attrs)
  - pk_type: uuid|int
  - uuid_subtype: uuid|binaryuuid|nativeuuid (driver-specific guidance applies)
- The behavior resolves the eav_entity_id from entityTable automatically; ensure a matching row exists.

8) Creating Attributes

- UI: /eav/eav-attributes add/edit. The data_type select is limited to types configured in eav.json. Optional fields: placeholder, help_text.
- CLI: Create Attribute command (flags-only):
  ```sh
  bin/cake eav create_attribute --name color --type string --connection default
  ```
  - Re-running with an existing name is a no-op (idempotent).
  - Type normalization: e.g., int → integer, jsonb → json, fk_uuid/fk_int → fk.

9) Attribute Sets

- Manage sets via /eav/eav-attribute-sets (checkbox membership). Delete is guarded if in use.
- Junction table: eav_attribute_sets_eav_attributes (composite PK: attribute_set_id, attribute_id).

10) Data Migrations

- JSON → EAV (Postgres-only)
  - Dry run:
    ```sh
    bin/cake eav migrate_jsonb_to_eav contacts attrs \
      --attribute favorite_color --type string \
      --entity-table contacts --pk uuid \
      --dry-run --connection default
    ```
  - Apply:
    ```sh
    bin/cake eav migrate_jsonb_to_eav contacts attrs \
      --attribute favorite_color --type string \
      --entity-table contacts --pk uuid \
      --connection default
    ```
  - Notes:
    - Requires Postgres; uses jsonb_* functions and careful parameter binding.
    - Writes via the behavior to enforce casting and correct table resolution.

- Native field → EAV (Coming Soon)
  - Planned command to migrate native columns into EAV value tables (creates attributes if missing; batched copy).

- Native field → JSON (Coming Soon)
  - Planned command to aggregate native columns into a JSON/JSONB column for JSON Storage Mode.

11) Indexing (Postgres JSON Storage Mode)

- Recommended indexes:
  ```sql
  -- GIN index on the JSONB column
  CREATE INDEX IF NOT EXISTS idx_contacts_attrs_gin ON contacts USING GIN (attrs);

  -- Functional indexes for hot keys (cast appropriately)
  CREATE INDEX IF NOT EXISTS idx_contacts_year_started ON contacts (((attrs->>'year_started')::int));
  CREATE INDEX IF NOT EXISTS idx_contacts_color ON contacts ((attrs->>'color'));
  ```
- These can be added in your migrations or emitted by the wizard when generating raw SQL for Postgres.

12) Notes and best practices

- Default to tables storage unless you specifically need JSON Storage Mode (Postgres-only) for “zero-join” reads.
- Do not commit environment-specific “EavSetup” migrations generated by the setup command.
- The plugin reads plugins/Eav/config/eav.json at bootstrap and surfaces it via Configure::read('Eav').
