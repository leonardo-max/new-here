<?php

namespace LeonardoMax\NewHere\Commands;

use Illuminate\Console\Command;
use LeonardoMax\NewHere\NewHere;

class PruneCommand extends Command
{
    protected $signature = 'new-here:prune';

    protected $description = 'Delete "seen" rows of features that expired long ago';

    public function handle(NewHere $newHere): int
    {
        $deleted = $newHere->prune();

        $this->components->info("Deleted {$deleted} old rows.");

        return self::SUCCESS;
    }
}
