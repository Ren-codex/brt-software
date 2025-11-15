<?php

namespace Cheesegrits\Iseed\Commands;

use Illuminate\Console\Command;
use function Cheesegrits\Iseed\env;

class IseedAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iseed:all {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate seed files for all tables except migrations';

    /**
     * Tables excluded.
     *
     * @var array
     */
    protected $exclusions = [
        'migrations',
        'audits',
        'jobs',
        'failed_jobs',
        'enquiries',
        'password_resets',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring'
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dbName = env('DB_DATABASE');

        $query = \DB::select("SHOW TABLES");
        $collection = new \Illuminate\Support\Collection($query);
        $tables = $collection->implode("Tables_in_$dbName", ',');

        $tables_array = explode(',', $tables);
        $allowed_tables = array_merge(array_diff($tables_array, $this->exclusions));

        $this->info(
            'Calling iseed for all tables except: ' . implode(
                ', ', $this->exclusions
            )
        );

        $this->call(
            'iseed', [
                'tables' => implode(',', $allowed_tables),
                '--force' => $this->option('force'),
            ]
        );

        return;
    }
}
