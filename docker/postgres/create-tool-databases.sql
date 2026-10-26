-- Runs once, when the Postgres container starts with an empty data volume.
--
-- Creates the `analytics`, `pulse` and `spectra` databases, where the
-- analytics tables, Pulse and Spectra keep their data (the connections of the
-- same names in config/database.php).
-- `php artisan migrate` creates their tables. On a volume made before this
-- file existed, run `php artisan chatvia:db:create-tool-databases` once instead.
SELECT 'CREATE DATABASE analytics'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'analytics')\gexec

SELECT 'CREATE DATABASE pulse'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'pulse')\gexec

SELECT 'CREATE DATABASE spectra'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'spectra')\gexec
