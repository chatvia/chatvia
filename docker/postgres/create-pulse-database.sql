-- Runs once, when the Postgres container starts with an empty data volume.
--
-- Creates the `pulse` database, where Pulse keeps its data (the `pulse`
-- connection in config/database.php). `php artisan migrate` creates its
-- tables. On a volume made before this file existed, run
-- `php artisan app:create-pulse-database` once instead.
SELECT 'CREATE DATABASE pulse'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'pulse')\gexec
