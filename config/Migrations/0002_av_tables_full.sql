-- 0002_av_tables_full.sql
-- Creates typed AV tables for UUID and INT PK families.

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Helper: function to create if not exists for int family
-- (We explicitly write all tables to be clear and index properly)

-- UUID FAMILY
CREATE TABLE IF NOT EXISTS av_text_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val TEXT NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_text_uuid_lookup ON av_text_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_int_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val BIGINT NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_int_uuid_lookup ON av_int_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_decimal_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val NUMERIC(18,6) NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_decimal_uuid_lookup ON av_decimal_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_bool_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val BOOLEAN NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_bool_uuid_lookup ON av_bool_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_date_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val DATE NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_date_uuid_lookup ON av_date_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_datetime_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val TIMESTAMPTZ NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_datetime_uuid_lookup ON av_datetime_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_json_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val JSONB NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_json_uuid_lookup ON av_json_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_uuid_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val UUID NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_uuid_uuid_lookup ON av_uuid_uuid(entity_table, entity_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_fk_uuid (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_id UUID NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val UUID NOT NULL, -- points to another table's UUID PK
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_fk_uuid_lookup ON av_fk_uuid(entity_table, entity_id, attribute_id);

-- INT FAMILY
CREATE TABLE IF NOT EXISTS av_string_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val VARCHAR(1024) NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_string_int_lookup ON av_string_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_text_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val TEXT NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_text_int_lookup ON av_text_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_int_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val BIGINT NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_int_int_lookup ON av_int_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_decimal_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val NUMERIC(18,6) NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_decimal_int_lookup ON av_decimal_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_bool_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val BOOLEAN NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_bool_int_lookup ON av_bool_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_date_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val DATE NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_date_int_lookup ON av_date_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_datetime_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val TIMESTAMPTZ NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_datetime_int_lookup ON av_datetime_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_json_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val JSONB NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_json_int_lookup ON av_json_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_uuid_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val UUID NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_uuid_int_lookup ON av_uuid_int(entity_table, entity_int_id, attribute_id);

CREATE TABLE IF NOT EXISTS av_fk_int (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_table VARCHAR(191) NOT NULL,
  entity_int_id BIGINT NOT NULL,
  attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
  val BIGINT NOT NULL,
  created TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  modified TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (entity_table, entity_int_id, attribute_id)
);
CREATE INDEX IF NOT EXISTS idx_av_fk_int_lookup ON av_fk_int(entity_table, entity_int_id, attribute_id);
