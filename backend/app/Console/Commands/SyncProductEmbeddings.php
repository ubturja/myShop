<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AIService;
use App\Services\PineconeService;
use Illuminate\Console\Command;

/**
 * Sync product embeddings to vector database.
 * 
 * Generates embeddings for all products and uploads to Pinecone
 * for semantic search and AI-powered recommendations.
 * 
 * Usage:
 *   php artisan products:sync-embeddings
 *   php artisan products:sync-embeddings --limit=10
 *   php artisan products:sync-embeddings --product=123
 */
class SyncProductEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-embeddings
                            {--limit= : Limit number of products to sync}
                            {--product= : Sync specific product ID}
                            {--force : Force re-sync even if already synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync product embeddings to vector database for AI recommendations';

    /**
     * AIService instance.
     */
    protected AIService $aiService;

    /**
     * PineconeService instance.
     */
    protected PineconeService $pineconeService;

    /**
     * Constructor - inject dependencies.
     */
    public function __construct(AIService $aiService, PineconeService $pineconeService)
    {
        parent::__construct();
        
        $this->aiService = $aiService;
        $this->pineconeService = $pineconeService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting product embedding sync...');
        $this->newLine();

        // Check if services are in mock mode
        if ($this->aiService->isMockMode()) {
            $this->warn('⚠️  AIService is in MOCK mode - using fake embeddings');
        }
        if ($this->pineconeService->isMockMode()) {
            $this->warn('⚠️  PineconeService is in MOCK mode - using cache instead of Pinecone');
        }
        $this->newLine();

        // Build query for products to sync
        $query = Product::where('status', 'active');

        // Filter by specific product if specified
        if ($productId = $this->option('product')) {
            $query->where('id', $productId);
            $this->info("Syncing specific product: {$productId}");
        }

        // Apply limit if specified
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
            $this->info("Limiting to {$limit} products");
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->error('No products found to sync');
            return self::FAILURE;
        }

        $this->info("Found {$products->count()} product(s) to sync");
        $this->newLine();

        // Progress bar for visual feedback
        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->setFormat('verbose');

        $successCount = 0;
        $errorCount = 0;
        $vectors = [];

        foreach ($products as $product) {
            try {
                // Generate embedding text from product data
                $embeddingText = $product->embedding_text;

                // Generate embedding using AI service
                $embeddingResult = $this->aiService->generateEmbedding($embeddingText);

                if (!$embeddingResult['success']) {
                    $this->error("Failed to generate embedding for product {$product->id}: {$embeddingResult['error']}");
                    $errorCount++;
                    $progressBar->advance();
                    continue;
                }

                // Prepare vector for Pinecone
                $vector = [
                    'id' => "product_{$product->id}",
                    'values' => $embeddingResult['embedding'],
                    'metadata' => [
                        'product_id' => $product->id,
                        'title' => $product->title,
                        'category' => $product->category,
                        'price_cents' => $product->price_cents,
                        'seller_id' => $product->seller_id,
                        'is_quick_item' => $product->is_quick_item,
                        'tags' => $product->tags ?? [],
                    ],
                ];

                $vectors[] = $vector;

                // Batch upsert every 100 vectors (Pinecone recommended)
                if (count($vectors) >= 100) {
                    $upsertResult = $this->pineconeService->upsert($vectors);
                    
                    if (!$upsertResult['success']) {
                        $this->error("Batch upsert failed: {$upsertResult['error']}");
                        $errorCount += count($vectors);
                    } else {
                        $successCount += count($vectors);
                    }
                    
                    $vectors = []; // Reset batch
                }

                $progressBar->advance();

            } catch (\Exception $e) {
                $this->error("Exception processing product {$product->id}: {$e->getMessage()}");
                $errorCount++;
                $progressBar->advance();
            }
        }

        // Upload remaining vectors
        if (!empty($vectors)) {
            $upsertResult = $this->pineconeService->upsert($vectors);
            
            if (!$upsertResult['success']) {
                $this->error("Final batch upsert failed: {$upsertResult['error']}");
                $errorCount += count($vectors);
            } else {
                $successCount += count($vectors);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Sync completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Products', $products->count()],
                ['Successfully Synced', $successCount],
                ['Errors', $errorCount],
            ]
        );

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
