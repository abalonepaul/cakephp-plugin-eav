-- 0001_initial.sql
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS attributes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name VARCHAR(191) NOT NULL UNIQUE,
  label VARCHAR(255),
  data_type VARCHAR(50) NOT NULL,
  options JSONB NOT NULL DEFAULT '{}'::jsonb,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS attribute_sets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name VARCHAR(191) NOT NULL UNIQUE,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS attribute_set_attributes (
  attribute_set_id UUID NOT NULL REFERENCES attribute_sets(id) ON DELETE CASCADE,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  position INT DEFAULT 0,
  PRIMARY KEY (attribute_set_id, attribute_id)
);

-- Example AV table: string + uuid PK
CREATE TABLE IF NOT EXISTS av_string_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val VARCHAR(1024) NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_string_uuid_lookup
  ON av_string_uuid(entity_table, entity_id, attribute_id);

-- TODO: Add additional AV tables as needed:
--   av_text_uuid, av_int_uuid, av_decimal_uuid, av_bool_uuid, av_date_uuid, av_datetime_uuid, av_json_uuid, av_uuid_uuid, av_fk_uuid
--   ...and '..._int' variants if you need integer PK interop.
