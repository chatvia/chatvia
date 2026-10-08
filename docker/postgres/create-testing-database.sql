-- Runs once, when the Postgres container starts with an empty data volume.
--
-- Creates the `testing` database that the test suite connects to (see
-- phpunit.xml). Only the empty database is needed here: on the first test run
-- the suite installs the extensions, runs the migrations and seeds it.
SELECT 'CREATE DATABASE testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec
