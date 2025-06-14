<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshBackend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:refresh-backend';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command refreshes the backend by clearing cache, config, and route caches';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('cache:clear');
        $this->info('Cache cleared successfully!');

        $this->call('config:clear');
        $this->info('Config cleared successfully!');

        $this->call('route:clear');
        $this->info('Route cache cleared successfully!');
    }
}
