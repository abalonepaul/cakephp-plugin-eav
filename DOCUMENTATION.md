# EAV Plugin for CakePHP 5 — Documentation

This document describes how to install, configure, and use the EAV plugin in a CakePHP 5.2 application. It covers both “typed EAV tables” (default) and the optional JSON Storage Mode (Postgres-only), the interactive and non-interactive setup commands, and the admin UI.

Code references
- Plugin bootstrap and routes: [EavPlugin](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/EavPlugin.php)
- Behavior: [EavBehavior](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Model/Behavior/EavBehavior.php)
- JSON Storage helpers: [JsonColumnStorageTrait](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Model/Behavior/JsonColumnStorageTrait.php)
- Setup (non-interactive): [EavSetupCommand](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupCommand.php)
- Setup (interactive wizard): [EavSetupInteractiveCommand](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupInteractiveCommand.php)
- Create Attribute: [EavCreateAttributeCommand](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavCreateAttributeCommand.php)
- JSONB → EAV migrator: [EavMigrateJsonbToEavCommand](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavMigrateJsonbToEavCommand.php)

1) Installation

- Requirements
  - CakePHP 5.2+
  - PHP 8.1+
  - Database drivers:
    - All: typed EAV tables (default) work across supported Cake drivers.
    - Postgres-only: JSON Storage Mode and jsonb features.
    - Raw SQL output is supported for Postgres/MySQL; migrations are universal.

- Installing the plugin
  - Until a Composer package is published, place the plugin at plugins/Eav and load it in your Application:
    - In src/Application.php:
      $this->addPlugin('Eav');
  - The plugin auto-registers console commands and a /eav route scope. See [EavPlugin](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/EavPlugin.php).

- Database initialization
  - Use the Setup command (interactive or non-interactive) to generate EAV schema migrations or raw SQL (see “Setup Commands” below).

2) Data Types

- Supported types
  - Defaults scaffolded by the generator:
    string, text, integer, smallinteger, tinyinteger, biginteger, decimal, float, boolean, date, datetime, time, json, uuid, binaryuuid, nativeuuid, fk
  - Optional/advanced (opt-in during setup):
    char, binary, enum, timestamp, datetimefractional, timestampfractional, timestamptimezone
    Geospatial (if your environment supports mapping): geometry, point, linestring, polygon
- Unified FK type
  - Custom type fk stores foreign keys in a single eav_fk table. The column type matches your PK family (uuid subtype or BIGINT).
- Casting
  - The behavior casts values using Cake’s TypeFactory and custom logic. Decimal values are preserved as strings; numeric/boolean/date/time/datetime hydrate as native PHP/Cake objects where applicable.

3) Primary Key (PK) types

- Families
  - uuid or int
  - If uuid: choose subtype by driver:
    - Postgres: nativeuuid (recommended)
    - MySQL/MariaDB: binaryuuid (recommended)
    - SQL Server: nativeuuid (recommended)
    - SQLite: uuid (string) (recommended)

4) Setup Commands

- Interactive wizard
  - Run:
    bin/cake eav setup
    or explicitly:
    bin/cake eav setup:interactive
  - Flow:
    - Connection selection
    - Output mode: migrations (default) or raw_sql (Postgres/MySQL)
    - PK family and UUID subtype
    - JSON Attribute storage for eav_json.value: json or jsonb (jsonb guarded to Postgres; non-interactive flag: --json-storage json|jsonb — controls JSON Attribute column type, not JSON Storage Mode)
    - Default behavior storage: tables (default) or json_column (Postgres-only)
    - JSON Storage Mode mapping (optional): choose app tables and JSON column names
    - jsonEncodeOnWrite (applies only to JSON Attribute writes)
    - Types to scaffold (defaults, all, custom CSV)
    - Migration name
  - eav.json persistence
    - The wizard writes plugins/Eav/config/eav.json. Example (anonymized):
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
        "types": ["string","integer","json","fk"],
        "migrationName": "EavSetup",
        "generatedAt": "2026-01-26T06:04:16+00:00"
      }

- Non-interactive (flags) — migrations
  - Generate a migration (dry-run):
    bin/cake eav setup --dry-run --connection default --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
  - Write the migration file:
    bin/cake eav setup --connection default --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
  - Apply:
    bin/cake migrations migrate -p Eav -c default

- Non-interactive (flags) — raw SQL
  - Generate SQL (dry-run):
    bin/cake eav setup --dry-run --connection default --output raw_sql --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
  - Write SQL to plugins/Eav/config/Sql/<timestamp>_eav_setup_postgres.sql (or mysql):
    bin/cake eav setup --connection default --output raw_sql --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
  - Apply SQL with your DB client (psql/mysql). On non-Postgres/MySQL drivers, raw SQL falls back to migrations.

- Notes
  - Generated migrations extend Migrations\BaseMigration (Cake 5). Do not commit site-specific “EavSetup” migrations generated by your environment.
  - The plugin generates canonical EAV schema, including eav_entities, eav_attributes, eav_attribute_sets, eav_attribute_sets_eav_attributes, and the typed eav_* value tables.
  - Value tables use a composite primary key (eav_entity_id, entity_id, eav_attribute_id), nullable value, created/modified timestamps, and FKs to eav_entities and eav_attributes. See [EavSetupCommand#buildMigration](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupCommand.php#buildMigration) and [#buildRawSql](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupCommand.php#buildRawSql).

5) Admin Routing

- Default plugin routes
  - The plugin exposes its UI under /eav with DashedRoute conventions. See [EavPlugin#routes](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/EavPlugin.php#routes).
  - Controllers:
    - /eav/eav-attributes
    - /eav/eav-attribute-sets
    - /eav/eav-entities

- Mount under /admin (host app)
  - Many apps prefer the UI behind an authenticated admin wall. In your app’s config/routes.php:
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

6) Operation

- Storage modes
  - tables (default): Attributes are stored in typed eav_* tables. This path is DB-agnostic.
  - json_column (Postgres-only): Attributes live in a single JSONB column on the entity table (e.g., contacts.attrs). Behavior rewrites queries and updates keys atomically via jsonb_set.

- Behavior configuration (attach to your Table)
  - One key: attributes (no map/attributeTypeMap).
  - Tables storage (example: ContactsTable):
    $this->addBehavior('Eav.Eav', [
        'entityTable' => 'contacts',
        'pkType' => 'uuid',
        'storage' => 'tables',
        'attributes' => [
            'favorite_color' => ['type' => 'string', 'persist' => true],
            'year_started'   => ['type' => 'integer', 'persist' => true],
        ],
    ]);
  - JSON Storage Mode (Postgres-only; example: ProductsTable):
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

- Querying (attributes behave like native fields)
  - Tables storage:
    $Products->find()
      ->select(['id', 'color'])
      ->where(['color' => 'red'])
      ->orderByDesc('year_started')
      ->all();
  - JSON Storage Mode (Postgres-only):
    $Contacts->find()
      ->select(['id', 'favorite_color'])
      ->where(['favorite_color ILIKE' => 'bl%'])
      ->orderByAsc('year_started')
      ->all();
  - Notes
    - Magic finders work (e.g., findByColor), conditions and order are rewritten automatically with typed casts.
    - Per-query options: eavRewrite (bool), eavTypes (['attr' => 'type']).

- Saving attributes
  - Tables storage: afterSave persists attributes with persist=true to the appropriate eav_* table.
  - JSON Storage Mode: afterSave applies jsonb_set to update only changed keys; passing null removes a key.
  - jsonEncodeOnWrite applies only to JSON Attribute (eav_json.value) when using tables storage; it is ignored for JSON Storage Mode.

7) Configuring Entities (registry)

- The eav_entities table maps an application table to an EAV entity id used in value rows (eav_entity_id).
- Create via UI (/eav/eav-entities) or seeding. Fields include: name (recommended: your app table name, e.g., contacts), model_alias/table_name (optional), storage_default (tables|json_column), json_column (optional), pk_type, uuid_subtype.
- The behavior resolves eav_entity_id automatically from entityTable; ensure an eav_entities row exists. See [EavBehavior#resolveEavEntityId](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Model/Behavior/EavBehavior.php#resolveEavEntityId).

8) Creating Attributes

- UI: /eav/eav-attributes add/edit. The data_type select is limited to types configured in eav.json. Optional fields: placeholder, help_text.
- CLI: Create Attribute command (flags-only):
  - Create:
    bin/cake eav create_attribute --name color --type string --connection default
  - Re-run is a no-op:
    Attribute already exists: color
  - Types are normalized (e.g., int → integer, jsonb → json, fk_uuid/fk_int → fk).

9) Attribute Sets

- Manage sets via /eav/eav-attribute-sets (checkbox-based membership). A delete guard prevents deleting attributes used in any set.
- Junction table name: eav_attribute_sets_eav_attributes (composite PK: attribute_set_id, attribute_id).

10) Data Migrations

- JSON → EAV (Postgres-only)
  - Migrate a JSON/JSONB key from a source table/column into typed EAV rows:
    bin/cake eav migrate_jsonb_to_eav contacts attrs --attribute favorite_color --type string --entity-table contacts --pk uuid --dry-run --connection default
  - Apply (omit --dry-run):
    bin/cake eav migrate_jsonb_to_eav contacts attrs --attribute favorite_color --type string --entity-table contacts --pk uuid --connection default
  - Notes:
    - Requires Postgres; uses jsonb_exists(...) to avoid PDO “?” conflicts.
    - Writes via the behavior to enforce casting and correct table resolution.

- Native field → EAV (Coming Soon)
  - A command to migrate native columns into EAV is planned. It will create attributes (if missing) and copy values into eav_* tables in batches.

- Native field → JSON (Coming Soon)
  - A command to aggregate selected native columns into a JSON/JSONB column is planned for JSON Storage Mode users.

11) Indexing (Postgres JSON Storage Mode)

- Recommended indexes:
  - GIN index on the JSONB column:
    CREATE INDEX IF NOT EXISTS idx_contacts_attrs_gin ON contacts USING GIN (attrs);
  - Functional indexes for hot keys (cast to the correct type):
    CREATE INDEX IF NOT EXISTS idx_contacts_year_started ON contacts (((attrs->>'year_started')::int));
    CREATE INDEX IF NOT EXISTS idx_contacts_color ON contacts ((attrs->>'color'));
- The interactive wizard can optionally emit these for raw SQL output on Postgres; otherwise, manage them via your migrations.

12) Notes and best practices

- Keep the default storage as tables unless you specifically need JSON Storage Mode on Postgres for “zero-join” reads.
- Do not commit environment-specific “EavSetup” migrations generated by the setup command.
- The plugin reads plugins/Eav/config/eav.json at bootstrap and exposes its contents via Configure::read('Eav'). See [EavPlugin#bootstrap](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/EavPlugin.php#bootstrap).
