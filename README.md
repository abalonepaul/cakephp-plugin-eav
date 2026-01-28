# EAV Plugin for CakePHP 5

A modern, typed EAV system for CakePHP 5.2 with optional Postgres JSON Storage, admin UI, and first-class ORM integration. Attach the behavior to your Tables and query attributes like native fields — with typed hydration, magic finders, and efficient writes.

Read the full documentation:
- [DOCUMENTATION.md](./DOCUMENTATION.md)
- [DOCUMENTATION.md (JetBrains link)](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/DOCUMENTATION.md)

## What is EAV?

Entity–Attribute–Value is a pattern that lets you add dynamic fields to entities without altering core tables. This plugin makes EAV feel native in Cake ORM:
- Filter/order by attributes (e.g., `where(['color' => 'red'])`).
- Select attributes as normal columns (e.g., `select(['id', 'color'])`).
- Hydrate typed values (dates, integers, booleans, decimals).

## History

- Originally a CakePHP 2.x EAV behavior/UI. Rebuilt for CakePHP 5 with a canonical schema, modern ORM patterns, and CLI tooling.
- The old 2.x README content is summarized here as history; the implementation/API were redesigned for Cake 5.

## Features (implemented)

- Storage modes
  - Typed EAV tables (default, DB-agnostic).
  - JSON Storage Mode (Postgres-only): store attributes in a single JSONB column per entity row.
- Query integration
  - Automatic WHERE/ORDER/SELECT rewriting; magic finders; typed hydration via Cake’s SelectTypeMap.
- Admin UI
  - CRUD for attributes, attribute sets, and entities under /eav (mountable under /admin).
- Setup automation
  - Interactive wizard (migrations or raw SQL), and non-interactive flags. Generates canonical schema and persists selections to plugins/Eav/config/eav.json.
- Data type support
  - Default types plus full TypeFactory coverage and a unified custom fk type.
- Primary keys
  - uuid or int families; driver-aware uuid subtypes; value tables use composite PK (eav_entity_id, entity_id, eav_attribute_id).
- Commands
  - Setup (interactive and standard).
  - Create Attribute (flags-only).
  - JSONB → EAV migrator (Postgres-only).
- Migrations/SQL
  - Migrations extend Migrations\BaseMigration; raw SQL snapshots supported for Postgres/MySQL.

Coming soon
- Native field → EAV migrator
- Native field → JSON migrator (for JSON Storage Mode)

## Suggested usage examples

- Contacts (tables storage)
  ```php
  // Attach behavior with typed EAV tables
  $this->addBehavior('Eav.Eav', [
      'entityTable' => 'contacts',
      'pkType' => 'uuid',
      'storage' => 'tables',
      'attributes' => [
          'favorite_color' => ['type' => 'string', 'persist' => true],
          'year_started'   => ['type' => 'integer', 'persist' => true],
      ],
  ]);

  // Query like native fields
  $Contacts->find()
      ->select(['id', 'favorite_color'])
      ->where(['favorite_color' => 'blue'])
      ->orderByDesc('year_started')
      ->all();
  ```

- Products (JSON Storage Mode; Postgres)
  ```php
  // Attach behavior with JSONB storage
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

  // Query like native fields
  $Products->find()
      ->select(['id', 'color'])
      ->where(['is_active' => true])
      ->orderByAsc('release_at')
      ->all();
  ```

## Quickstart

- Interactive setup
  ```sh
  bin/cake eav setup
  ```

- Non-interactive (migrations)
  ```sh
  bin/cake eav setup --connection default --pk-type uuid --uuid-type nativeuuid --types defaults --name EavSetup
  bin/cake migrations migrate -p Eav -c default
  ```

- Attach the behavior (tables mode)
  ```php
  $this->addBehavior('Eav.Eav', [
      'entityTable' => 'contacts',
      'pkType' => 'uuid',
      'storage' => 'tables',
      'attributes' => [
          'favorite_color' => ['type' => 'string', 'persist' => true],
          'year_started'   => ['type' => 'integer', 'persist' => true],
      ],
  ]);
  ```

- Create an attribute (CLI)
  ```sh
  bin/cake eav create_attribute --name color --type string --connection default
  ```

- JSONB → EAV migration (Postgres)
  ```sh
  bin/cake eav migrate_jsonb_to_eav contacts attrs \
    --attribute favorite_color --type string \
    --entity-table contacts --pk uuid \
    --connection default
  ```

## Links

- [EavBehavior.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Model/Behavior/EavBehavior.php)
- [EavSetupCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupCommand.php)
- [EavSetupInteractiveCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavSetupInteractiveCommand.php)
- [EavCreateAttributeCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavCreateAttributeCommand.php)
- [EavMigrateJsonbToEavCommand.php](file:///home/paul/dev/cakephp/protech_parts/plugins/Eav/src/Command/EavMigrateJsonbToEavCommand.php)
