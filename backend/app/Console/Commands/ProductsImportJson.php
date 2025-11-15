<?php

namespace App\Console\Commands;

use Database\Seeders\ProductsFromJsonSeeder;
use Illuminate\Console\Command;

class ProductsImportJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import-json {--force : Force import even if products exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from database/data/products.json (idempotent - safe to re-run)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting product import from JSON...');
        $this->newLine();

        $seeder = new ProductsFromJsonSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        return Command::SUCCESS;
    }
}

