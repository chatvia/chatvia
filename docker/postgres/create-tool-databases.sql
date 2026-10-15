-- Runs once, when the Postgres container starts with an empty data volume.
--
-- Creates the `pulse` and `spectra` databases, where Pulse and Spectra keep
-- their data (the `pulse` and `spectra` connections in config/database.php).
-- `php artisan migrate` creates their tables. On a volume made before this
-- file existed, run `php artisan app:create-tool-databases` once instead.
SELECT 'CREATE DATABASE pulse'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'pulse')\gexec

SELECT 'CREATE DATABASE spectra'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'spectra')\gexec
