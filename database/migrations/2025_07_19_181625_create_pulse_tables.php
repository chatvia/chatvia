<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Support\PulseMigration;

return new class extends PulseMigration
{
    public function getConnection(): ?string
    {
        if (app()->environment('testing') || app()->runningUnitTests()) {
            return null;
        }

        return config('pulse.storage.database.connection');
    }

    public function up(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        if (app()->environment('testing') || app()->runningUnitTests()) {
            return;
        }

        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('pulse_values')) {
            $schema->create('pulse_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('timestamp');
                $table->string('type');
                $table->mediumText('key');
                match ($this->driver()) {
                    'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                    'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                    'sqlite' => $table->string('key_hash'),
                };
                $table->mediumText('value');

                $table->index('timestamp');
                $table->index('type');
                $table->unique(['type', 'key_hash']);
            });
        }

        if (! $schema->hasTable('pulse_entries')) {
            Schema::create('pulse_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('timestamp');
                $table->string('type');
                $table->mediumText('key');
                match ($this->driver()) {
                    'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                    'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                    'sqlite' => $table->string('key_hash'),
                };
                $table->bigInteger('value')->nullable();

                $table->index('timestamp');
                $table->index('type');
                $table->index('key_hash');
                $table->index(['timestamp', 'type', 'key_hash', 'value']);
            });
        }

        if (! $schema->hasTable('pulse_aggregates')) {
            Schema::create('pulse_aggregates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('bucket');
                $table->unsignedMediumInteger('period');
                $table->string('type');
                $table->mediumText('key');
                match ($this->driver()) {
                    'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                    'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                    'sqlite' => $table->string('key_hash'),
                };
                $table->string('aggregate');
                $table->decimal('value', 20, 2);
                $table->unsignedInteger('count')->nullable();

                $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
                $table->index(['period', 'bucket']);
                $table->index('type');
                $table->index(['period', 'type', 'aggregate', 'bucket']);
            });
        }
    }

    public function down(): void
    {
        if (app()->environment('testing') || app()->runningUnitTests()) {
            return;
        }

        $schema = Schema::connection($this->getConnection());
        if (! app()->isProduction()) {
            $schema->dropIfExists('pulse_values');
            $schema->dropIfExists('pulse_entries');
            $schema->dropIfExists('pulse_aggregates');
        }
    }
};
