-- Enable PostgreSQL extensions for ChatVia
-- This script runs after database initialization

\echo 'Installing extensions...'

-- Core extensions
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS btree_gin;
CREATE EXTENSION IF NOT EXISTS btree_gist;

-- TimescaleDB (must be created first)
CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;

-- Vector search extensions
CREATE EXTENSION IF NOT EXISTS vector CASCADE;
CREATE EXTENSION IF NOT EXISTS vchord CASCADE;
CREATE EXTENSION IF NOT EXISTS pg_tokenizer CASCADE;
CREATE EXTENSION IF NOT EXISTS vchord_bm25 CASCADE;

-- Create llmlingua2 tokenizer
SELECT tokenizer_catalog.create_tokenizer('llmlingua2', $$ 'model' = 'llmlingua2' $$);

\echo 'All extensions installed successfully!'

