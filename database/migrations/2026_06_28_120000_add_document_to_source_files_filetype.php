<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $with = [
        'pdf', 'word', 'image', 'video', 'audio',
        'spreadsheet', 'presentation', 'text', 'markdown', 'document',
    ];

    public function up(): void
    {
        $this->setFiletypes($this->with);
    }

    public function down(): void
    {
        $this->setFiletypes(array_values(array_diff($this->with, ['document'])));
    }

    private function setFiletypes(array $values): void
    {
        $list = implode(', ', array_map(static fn (string $v): string => "'".$v."'", $values));
        $constraint = 'source_files_filetype_check';

        DB::statement("ALTER TABLE source_files DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE source_files ADD CONSTRAINT {$constraint} CHECK ((filetype)::text = ANY ((ARRAY[{$list}])::text[]))");
    }
};
