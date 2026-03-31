<?php

namespace App\Console\Commands;

use App\Models\ImportedFile;
use App\Services\DisplayNameGenerator;
use Illuminate\Console\Command;

class BackfillImportedFileDisplayNames extends Command
{
    protected $signature = 'imports:backfill-display-names';

    protected $description = 'Populate display_name for imported files that do not have one';

    public function handle(DisplayNameGenerator $generator): int
    {
        $count = 0;

        ImportedFile::withTrashed()
            ->whereNull('display_name')
            ->with('creditCard')
            ->each(function (ImportedFile $file) use ($generator, &$count): void {
                $this->info("Processing imported file id `{$file->id}`...");
                $file->update(['display_name' => $generator->generate($file)]);
                $count++;
            });

        $this->info("Backfilled display_name for {$count} imported file(s).");

        return self::SUCCESS;
    }
}
