## Project Description and History
This repository is an EAV Behavior plugin for CakePHP 5.x. This plugin provides the Entity, Attribute, Value Design Pattern which allows users to add dynamic fiels to a table to support variable variable data and store data efficiently without having many fields with empty values. This plugin was created close to 15 years ago for CakePHP 2.x  The orgiginal version is located in `/home/paul/dev/cakephp/cakephp-plugin-eav` The current README.md is for the old version and provides additional details on the orginal intent and usage. Version 2.x supported the 10 datatypes available in CakePHP at the time. It also supported incremental and UUID foreign keys. It also supported 3 different types of of Virtual Fields in CakePHP, the CakePHP native Virtual Fields, simulated Virtual Fields without subqueries, and array Virtual Fields, and relational fields that was represented as standard relational data from CakePHP's ORM queries.

## Project Scope & Structure
This objective of this project is to upgrade the original plugin to CakePHP 5.x. There have been sigificant changes to the CakePHP architecture since 2.x and CakePHP now supports 26 datatypes through its TypeFactory. This repo is nested inside of the ProtechParts app written in PHP with CakePHP 5.2.9. The project was started using ChatGPT in conversational mode. While it was supposed to be a complete upgrade, it was not. There were several errors, incorrect naming conventions and incorrect usages of CakePHP. The plugin is a CakePHP Behavior designed to be attached to a model that needs dynamic fields. In the ProtechParts application, both Engines and Parts have variable data depending on their source. The database currently contains most of the variable fields and the Engines and Parts tables both contain a JSONB field called either attrs or specs that contain all of the fields. ChatGPT added functionality to support that field and aCommand to migrate JSONB fields EAV Attributes. In addtion, it also added a Command to Create new Attributes. It also mentioned an option to use JSONB to store Attributes and Values, but I am not sure if that was implemented. I like the idea of the Commands and the support of JSONB and want to keep them. However, this was not a complete upgrade in that ChatGPT failed to create the Controllers and Views needed to manage the Entities, Attributes and Values. In addition, ChatGPT made several errors, did not use CakePHP properly, did not adhere to CakePHP naming conventions, and did not use CakePHP best practices. CakePHP now supports 26 datatypes through its TypeFactory, however, only the original 10 were implemented. The objective of this project is to is to complete this upgrade by fixing the bugs, correcting the naming conventions and usage of CakePHP, add support for all the current datatypes, and to add the UI to manage the fields.

## Environment Setup & Commands
Use PHP 8.1. The working directory for the application is `/home/paul/dev/cakephp/protech_parts`. The working directory for the plugin is `/home/paul/dev/cakephp/protech_parts/plugins/Eav`.

The DSN for the database is postgres://postgres:postgres@host:5432/protech_parts
The SQL Files used to create the EAV tables are in config/Migrations

When the schema changes are needed, they should be done using CakePHP Migrations Plugin.

You can execute Cake commands from the ProtechParts working directory using `bin/cake {command}`


## Testing & Validation
Appropriate PHPUnit tests should be written for the Plugin and should be created stored in `/home/paul/dev/cakephp/protech_parts/plugins/Eav/tests`. The user will do UI and database verification. The Commands should have a dry-run feature.

## Commit & Pull Request Guidelines
Both the application and the plug tasks are managed in JIRA. The repo for the application is located in BitBucket and the repo for the plugin is in GitHub at `https://github.com/abalonepaul/cakephp-plugin-eav`. Commit messages must be formatted with the Issue Key and branch name. (eg. EAV-3-AI-Convention-Bugs) PRs must list: (1) work completed, (2) commands executed, (3) table counts added, and (4) any schema or configuration changes. Include screenshots or snippets when SQL Migrations change.

## Current Work Plan (EAV Behavior Overhaul Branch)
The source of truth is `/home/paul/dev/cakephp/protech_parts/plugins/Eav`. Do not modify `/home/paul/dev/cakephp/cakephp-plugin-eav`.

Primary goals:
- Stabilize EavBehavior for CakePHP 5.2.x with TypeFactory-driven data types.
- Restore "virtual field" behavior so EAV fields appear native in queries and entities.
- Add full PHPUnit coverage for behavior and commands.
- Keep the plugin DB-agnostic (Postgres, MySQL/MariaDB, SQL Server, SQLite).

Decisions:
- Attribute sets remain supported and will be used to group attributes per entity.
- JSON/JSONB can be used as an attribute value type. JSONB as a storage backend is optional and will be configured per entity table.
- Use a setup command to generate schema and tables based on DB vendor, PK type, and UUID storage choice.
- Only one PK family of AV tables is created per install (uuid or int), based on setup command choices.

Planned work breakdown:
1) EavBehavior rewrite
   - Normalize type aliases (bool->boolean, int->integer, smallint->smallinteger, bigint->biginteger, double->float, timestamp->datetime).
   - Map types to AV tables using CakePHP naming conventions and TypeFactory.
   - Remove lossy string casting; persist native types.
   - Avoid N+1 queries when hydrating attribute values.
   - Keep buffered beforeMarshal/afterSave behavior.
   - Add PHPDoc blocks and remove unused imports.

2) Commands hardening
   - EavCreateAttributeCommand validates types, sets default label, and no-ops on duplicate names.
   - EavMigrateJsonbToEavCommand adds dry-run, batching, and DB vendor guards.
   - Tests for command validation and dry-run behavior.

3) Schema/setup
   - Add CakePHP migrations (phinx) and raw SQL snapshots.
   - Add a setup command to generate tables for the chosen DB vendor and PK type.
   - Support JSON vs JSONB where the DB allows it.

4) AttributeSets ORM + UI scaffolding
   - Create/verify tables and entities for attributes, attribute sets, and join table.
   - UI will be baked later; keep minimal server-side validation and associations.

5) Documentation refresh (later)
   - Update README after behavior and commands stabilize.

## Project Data Checklist (fill in)
- **Database schema reference:** _/home/paul/dev/boatsnet_data/import_data/protech_parts_schema.sql_
- **Engines fields that should remain in the Entity's table:**
  - id: a uuid primary key
  - brand_id: Foreign key to the Brand. The Honda brand_id is '68fa18c3-84bf-4fbd-9a36-fb6275b15bb2'
  - created: The record creation timestamp.
  - modified: The record modified timestamp.
  - horse_power: The engine horse power.
  - model_number: The parsed model number.
  - year_start: The starting year. We will only have one year so enter that.
  - year_stop: The ending year for this model. We only have one year so enter that.

- **Engines JSONB field that can be parsed to create EAV fields or used as a JSONB datatype to render attributes**
    - attrs: A JSONB field with all of the imported data fields and values.
- **Engines fields that should/could be converted to EAV Attributes:**
  - legacy_id: An integer (although the field is text) referencing the original Sierra Engine Id. This only needs to be added for new records and needs to be unique from the Sierra Numbers.
  - legacy_source: This is a text field referring to the soudrce of the data. It should be 'boats.net' for this project.
  - sierra_engine_row: this can be empty for new rows.
  - serial_start: The starting serial number for the range. This is a text field.
  - serial_stop: The ending serial number for the range. This is a text field.
  - serial_as_text: This is the whole serial range as text. Typically looks like 'VIN# BBAL-4200001 To BBAL-4299999' and parsed from the Model Text field in the xlsx file
  - serial_start_search: This gets the same data as serial_start
  - serial_stop_search: This gets the same data as serial_stop
  - year_start_display: This gets the same data as year_start
  - year_stop_display: This gets the same data as year_stop
  - manufacturer: This isn't always populated and not necessarily needed, but would hold the Manufacturer like 'Honda'
  - type: This should be populated with 'Motor'
  - category: For most of this data, this will be populated with 'Outboard'. Once we get into some other brands, this will change.

- **Part fields that should remain in the Entity's table:**
  - id: a uuid primary key
  - part_number: This is the Part Number.
  - description_short: This the Part Name.
  - sdc: is the Price
  - list_price: This is the MSRP price.
  - created: The record creation timestamp.
  - modified: The record modified timestamp.
- **Parts JSONB field that can be parsed to create EAV fields or used as a JSONB datatype to render attributes**
  - spec: This is a JSONB field for the attributes of the part. We can populate with our parsed data.
- **Part fields that should/could be converted to EAV fields:**
  - legacy_id: An integer (although the field is text) referencing the original Sierra Part Id. This only needs to be added for new records and needs to be unique from the Sierra Numbers. It could be a prefixed integer such as bn- or 214- and the incremental integer. Or starting the number at 100,000
  - legacy_source: This is a text field referring to the source of the data. It should be 'boats.net' for this project.
  - brand: This is the Brand Name such as Honda. We really should add a brand_id field, but Sierra isn't in the Brands table.
  - The rest of the fields are the extensive part attributes that Sierra provided. There are some redundant fields, but we don't need to populate them now.

# Revised Master Plan #
The revised master plan is located in `/home/paul/dev/cakephp/protech_parts/plugins/Eav/PLAN.md`
