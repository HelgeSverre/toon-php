# Tutorial 3: Using TOON in Laravel Applications

**Level**: Intermediate
**Time**: 20-25 minutes
**PHP Version**: 8.2+
**Laravel Version**: 11.x

## What You'll Build

In this tutorial, you'll create a support ticket classification system that uses TOON to efficiently send ticket context to LLMs for automatic routing and priority assignment. You'll learn how to use TOON in Laravel applications without any special integration - it's just a library you composer require and use.

## Learning Objectives

By the end of this tutorial, you will:
- Use TOON directly in Laravel applications
- Create a simple service class for TOON operations
- Build a practical ticket classification system
- Write tests with Pest
- Optimize TOON encoding with caching

## Prerequisites

- Laravel 11 basics (models, controllers, migrations)
- Completed Tutorials 1 and 2
- Understanding of Eloquent ORM
- Basic knowledge of API integration

## Section 1: Introduction

TOON is a standalone PHP library that doesn't require any Laravel-specific integration. There are no facades, service providers, or configuration files needed. You simply install it via Composer and use its functions directly in your Laravel code.

The key insight is that TOON is designed to reduce token consumption when sending data to LLMs. In a Laravel application, this typically happens when:
- Sending database records to AI for analysis
- Providing context for AI-powered features
- Batching multiple records for bulk processing
- Caching formatted data for repeated API calls

This tutorial demonstrates practical patterns for using TOON in Laravel by building a ticket classification system. The system will:
1. Accept support tickets through an API
2. Format ticket data using TOON for efficient token usage
3. Send the formatted data to OpenAI for classification
4. Store the classification results back in the database

The approach is straightforward: treat TOON like any other PHP library. Use its functions where you need compact data representation, wrap it in service classes if you want abstraction, and test it like any other code.

## Section 2: Setup

Let's start by creating a new Laravel project and installing the necessary dependencies.

### Create the Laravel Project

```bash
laravel new ticket-classifier
cd ticket-classifier
```

### Install Dependencies

```bash
composer require helgesverre/toon
composer require openai-php/client
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
```

### Initialize Pest

```bash
./vendor/bin/pest --init
```

### Environment Configuration

Update your `.env` file with the necessary configuration:

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-your-api-key-here

# Cache Configuration (for optimizing TOON encoding)
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue Configuration (for background processing)
QUEUE_CONNECTION=database
```

### Verify Installation

Create a simple test route to verify TOON is working:

```php
// routes/web.php
use HelgeSverre\Toon\Toon;

Route::get('/test-toon', function () {
    $data = [
        'message' => 'TOON is working',
        'timestamp' => now()->toDateTimeString(),
        'features' => ['compact', 'efficient', 'readable']
    ];

    return response(Toon::encode($data), 200)
        ->header('Content-Type', 'text/plain');
});
```

Visit `http://localhost:8000/test-toon` and you should see TOON-formatted output:

```
message: TOON is working
timestamp: 2024-03-15 10:30:00
features[3]:
- compact
- efficient
- readable
```

## Section 3: Database Setup

Now let's create the database schema for our ticket system.

### Create the Migration

```bash
php artisan make:migration create_tickets_table
```

Edit the migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('description');
            $table->string('customer_email');
            $table->string('customer_name');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->nullable();
            $table->string('category')->nullable();
            $table->string('assigned_team')->nullable();
            $table->text('ai_analysis')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->decimal('processing_cost', 8, 4)->nullable();
            $table->timestamps();

            $table->index('priority');
            $table->index('category');
            $table->index('assigned_team');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
```

### Create the Ticket Model

```bash
php artisan make:model Ticket
```

Edit `app/Models/Ticket.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use HelgeSverre\Toon\Toon;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'description',
        'customer_email',
        'customer_name',
        'priority',
        'category',
        'assigned_team',
        'ai_analysis',
        'tokens_used',
        'processing_cost'
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'processing_cost' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get TOON-encoded ticket data for LLM processing
     */
    public function toToonFormat(): string
    {
        return Toon::encode([
            'subject' => $this->subject,
            'description' => $this->description,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email
            ],
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'ticket_id' => $this->id
        ], 'compact');
    }

    /**
     * Get ticket context for AI analysis
     */
    public function getAiContext(): array
    {
        return [
            'ticket' => $this->toArray(),
            'formatted' => $this->toToonFormat(),
            'metadata' => [
                'age_hours' => $this->created_at->diffInHours(now()),
                'word_count' => str_word_count($this->description)
            ]
        ];
    }
}
```

Run the migration:

```bash
php artisan migrate
```

## Section 4: Service Class

Create a simple service class to wrap TOON functionality with Laravel-specific features.

### Create ToonService

Create the file `app/Services/ToonService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use HelgeSverre\Toon\Toon;

class ToonService
{
    /**
     * Encode data with optional caching
     */
    public function encode(array $data, ?string $cacheKey = null, int $ttl = 3600): string
    {
        if ($cacheKey) {
            return Cache::remember($cacheKey, $ttl, function () use ($data) {
                return toon_compact($data);
            });
        }

        return toon_compact($data);
    }

    /**
     * Compare JSON vs TOON formats
     */
    public function compare(array $data): array
    {
        $json = json_encode($data);
        $toon = toon_compact($data);

        $jsonSize = strlen($json);
        $toonSize = strlen($toon);
        $savings = $jsonSize - $toonSize;
        $percentage = ($savings / $jsonSize) * 100;

        return [
            'json' => [
                'size' => $jsonSize,
                'format' => $json
            ],
            'toon' => [
                'size' => $toonSize,
                'format' => $toon
            ],
            'savings' => [
                'bytes' => $savings,
                'percentage' => round($percentage, 2)
            ]
        ];
    }

    /**
     * Estimate token count for data
     * Note: This is a rough estimate based on character count
     */
    public function estimateTokens(array $data): int
    {
        $toonFormat = toon_compact($data);
        // Rough estimate: 1 token ≈ 4 characters
        return (int) ceil(strlen($toonFormat) / 4);
    }

    /**
     * Batch encode multiple records
     */
    public function batchEncode(array $records): string
    {
        return toon_compact($records);
    }

    /**
     * Clear cached TOON data
     */
    public function clearCache(string $pattern): void
    {
        // Clear all cache keys matching the pattern
        $keys = Cache::get('toon_cache_keys', []);
        foreach ($keys as $key) {
            if (str_contains($key, $pattern)) {
                Cache::forget($key);
            }
        }
    }
}
```

### Optional: Register as Singleton

If you want to use dependency injection, register the service in `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    $this->app->singleton(ToonService::class);
}
```

## Section 5: Ticket Classification Service

Create the AI-powered ticket classification service that uses TOON for efficient API calls.

Create the file `app/Services/TicketClassifier.php`:

```php
<?php

namespace App\Services;

use App\Models\Ticket;
use OpenAI;
use Illuminate\Support\Facades\Log;

class TicketClassifier
{
    private $client;

    public function __construct(
        private ToonService $toonService
    ) {
        $this->client = OpenAI::client(config('services.openai.api_key'));
    }

    /**
     * Classify a support ticket using AI
     */
    public function classify(Ticket $ticket): array
    {
        // Get TOON-encoded ticket data
        $ticketData = $ticket->toToonFormat();

        // Build the classification prompt
        $prompt = $this->buildPrompt($ticketData);

        try {
            // Call OpenAI API
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a support ticket classifier. Analyze tickets and provide priority, category, and team assignment.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'max_tokens' => 150
            ]);

            // Parse the response
            $analysis = json_decode($response->choices[0]->message->content, true);

            // Update ticket with classification
            $ticket->update([
                'priority' => $analysis['priority'] ?? 'medium',
                'category' => $analysis['category'] ?? 'general',
                'assigned_team' => $analysis['team'] ?? 'support',
                'ai_analysis' => $analysis['analysis'] ?? '',
                'tokens_used' => $response->usage->totalTokens,
                'processing_cost' => $this->calculateCost($response->usage->totalTokens)
            ]);

            return [
                'success' => true,
                'analysis' => $analysis,
                'tokens_used' => $response->usage->totalTokens,
                'cost' => $ticket->processing_cost,
                'format_savings' => $this->calculateSavings($ticket)
            ];

        } catch (\Exception $e) {
            Log::error('Ticket classification failed', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build the classification prompt
     */
    private function buildPrompt(string $ticketData): string
    {
        return <<<PROMPT
        Analyze this support ticket and provide:
        1. Priority level (low/medium/high/urgent)
        2. Category (billing/technical/account/feature/bug/other)
        3. Suggested team (support/engineering/billing/product)
        4. Brief analysis (2-3 sentences)

        Respond in JSON format with keys: priority, category, team, analysis

        Ticket data:
        $ticketData
        PROMPT;
    }

    /**
     * Calculate API cost based on tokens
     */
    private function calculateCost(int $tokens): float
    {
        // GPT-3.5-turbo pricing: $0.002 per 1K tokens
        return ($tokens / 1000) * 0.002;
    }

    /**
     * Calculate token savings from using TOON
     */
    private function calculateSavings(Ticket $ticket): array
    {
        $comparison = $this->toonService->compare([
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'customer' => [
                'name' => $ticket->customer_name,
                'email' => $ticket->customer_email
            ]
        ]);

        return [
            'percentage' => $comparison['savings']['percentage'],
            'tokens_saved' => (int) ceil($comparison['savings']['bytes'] / 4)
        ];
    }

    /**
     * Batch classify multiple tickets
     */
    public function batchClassify(array $ticketIds): array
    {
        $results = [];
        $tickets = Ticket::whereIn('id', $ticketIds)->get();

        foreach ($tickets as $ticket) {
            $results[$ticket->id] = $this->classify($ticket);
        }

        return $results;
    }
}
```

### Configure OpenAI Service

Add to `config/services.php`:

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
],
```

## Section 6: Controller Implementation

Create controllers to handle ticket submission and classification.

### Create TicketController

```bash
php artisan make:controller Api/TicketController
```

Edit `app/Http/Controllers/Api/TicketController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketClassifier;
use App\Services\ToonService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function __construct(
        private TicketClassifier $classifier,
        private ToonService $toonService
    ) {}

    /**
     * Create and classify a new ticket
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'customer_email' => 'required|email',
            'customer_name' => 'required|string|max:100'
        ]);

        // Create the ticket
        $ticket = Ticket::create($validated);

        // Classify the ticket (in production, use a job)
        $result = $this->classifier->classify($ticket);

        return response()->json([
            'ticket' => $ticket->fresh(),
            'classification' => $result
        ]);
    }

    /**
     * Get ticket with TOON format comparison
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $comparison = $this->toonService->compare($ticket->toArray());

        return response()->json([
            'ticket' => $ticket,
            'toon_format' => $ticket->toToonFormat(),
            'format_comparison' => $comparison
        ]);
    }

    /**
     * Get format statistics for all tickets
     */
    public function stats(): JsonResponse
    {
        $tickets = Ticket::all();

        $totalTokensUsed = $tickets->sum('tokens_used');
        $totalCost = $tickets->sum('processing_cost');

        // Calculate potential savings
        $sampleData = $tickets->take(10)->map(fn($t) => $t->toArray())->toArray();

        if (empty($sampleData)) {
            return response()->json([
                'total_tickets' => 0,
                'message' => 'No tickets found'
            ]);
        }

        $comparison = $this->toonService->compare($sampleData);

        return response()->json([
            'total_tickets' => $tickets->count(),
            'total_tokens_used' => $totalTokensUsed,
            'total_cost' => round($totalCost, 4),
            'average_tokens_per_ticket' => $tickets->avg('tokens_used'),
            'format_efficiency' => [
                'sample_size' => 10,
                'savings_percentage' => $comparison['savings']['percentage'],
                'estimated_tokens_saved' => (int) ($totalTokensUsed * $comparison['savings']['percentage'] / 100)
            ]
        ]);
    }
}
```

### Add Routes

Edit `routes/api.php`:

```php
use App\Http\Controllers\Api\TicketController;

Route::prefix('tickets')->group(function () {
    Route::post('/', [TicketController::class, 'store']);
    Route::get('/{ticket}', [TicketController::class, 'show']);
    Route::get('/stats/overview', [TicketController::class, 'stats']);
});
```

## Section 7: Testing with Pest

Create comprehensive tests for the TOON integration.

### Create Factory

```bash
php artisan make:factory TicketFactory
```

Edit `database/factories/TicketFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'customer_email' => fake()->email(),
            'customer_name' => fake()->name(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'category' => fake()->randomElement(['billing', 'technical', 'general']),
            'assigned_team' => fake()->randomElement(['support', 'engineering', 'billing']),
        ];
    }
}
```

### Create Feature Test

Create `tests/Feature/TicketClassificationTest.php`:

```php
<?php

use App\Models\Ticket;
use App\Services\ToonService;
use App\Services\TicketClassifier;
use HelgeSverre\Toon\Toon;
use Illuminate\Support\Facades\Cache;

it('creates a ticket successfully', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Cannot log in to account',
        'description' => 'I keep getting an error message when trying to access my dashboard'
    ]);

    expect($ticket)->toBeInstanceOf(Ticket::class);
    expect($ticket->subject)->toBe('Cannot log in to account');
});

it('encodes ticket data to TOON format', function () {
    $ticket = Ticket::factory()->create();
    $encoded = $ticket->toToonFormat();

    expect($encoded)->toBeString();
    expect($encoded)->toContain($ticket->subject);
    expect($encoded)->toContain('customer:');

    // TOON should be more compact than JSON
    $jsonSize = strlen(json_encode($ticket->toArray()));
    $toonSize = strlen($encoded);
    expect($toonSize)->toBeLessThan($jsonSize);
});

it('compares JSON and TOON formats accurately', function () {
    $toonService = app(ToonService::class);

    $data = [
        'subject' => 'Test ticket',
        'description' => 'This is a test description',
        'customer' => [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ],
        'metadata' => [
            'source' => 'web',
            'ip' => '192.168.1.1'
        ]
    ];

    $comparison = $toonService->compare($data);

    expect($comparison)->toHaveKeys(['json', 'toon', 'savings']);
    expect($comparison['savings']['percentage'])->toBeGreaterThan(0);
    expect($comparison['savings']['bytes'])->toBeGreaterThan(0);
});

it('estimates token count correctly', function () {
    $toonService = app(ToonService::class);

    $data = [
        'test' => 'data',
        'array' => ['item1', 'item2', 'item3']
    ];

    $tokens = $toonService->estimateTokens($data);

    expect($tokens)->toBeInt();
    expect($tokens)->toBeGreaterThan(0);
    expect($tokens)->toBeLessThan(100); // Small data should have few tokens
});

it('caches encoded data when cache key is provided', function () {
    $toonService = app(ToonService::class);
    $data = ['test' => 'cache data'];
    $cacheKey = 'test_cache_' . time();

    // First call should encode and cache
    $result1 = $toonService->encode($data, $cacheKey);

    // Second call should retrieve from cache
    $result2 = $toonService->encode($data, $cacheKey);

    expect($result1)->toBe($result2);

    // Clean up
    Cache::forget($cacheKey);
});

it('handles batch encoding efficiently', function () {
    $toonService = app(ToonService::class);

    $tickets = Ticket::factory()->count(5)->create();
    $ticketData = $tickets->map(fn($t) => $t->toArray())->toArray();

    $batchEncoded = $toonService->batchEncode($ticketData);

    expect($batchEncoded)->toBeString();
    expect($batchEncoded)->toContain('[5]'); // Array length indicator
});

it('creates ticket via API endpoint', function () {
    $response = $this->postJson('/api/tickets', [
        'subject' => 'API test ticket',
        'description' => 'Testing ticket creation through API',
        'customer_email' => 'test@example.com',
        'customer_name' => 'Test User'
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'ticket' => ['id', 'subject', 'description'],
        'classification'
    ]);

    $this->assertDatabaseHas('tickets', [
        'subject' => 'API test ticket',
        'customer_email' => 'test@example.com'
    ]);
});
```

Run the tests:

```bash
php artisan test
```

## Section 8: Optimization with Caching

Implement caching strategies to optimize TOON encoding for frequently accessed data.

### Add Caching to Ticket Model

Update `app/Models/Ticket.php`:

```php
use Illuminate\Support\Facades\Cache;

class Ticket extends Model
{
    // ... existing code ...

    /**
     * Get cached TOON format
     */
    public function getCachedToonFormat(): string
    {
        $cacheKey = "ticket_toon_{$this->id}_{$this->updated_at->timestamp}";

        return Cache::remember($cacheKey, 3600, function () {
            return $this->toToonFormat();
        });
    }

    /**
     * Clear cache when ticket is updated
     */
    protected static function booted(): void
    {
        static::updated(function (Ticket $ticket) {
            // Clear all cache entries for this ticket
            $pattern = "ticket_toon_{$ticket->id}_*";
            // Note: Pattern-based cache clearing depends on cache driver
            Cache::forget($pattern);
        });

        static::deleted(function (Ticket $ticket) {
            $pattern = "ticket_toon_{$ticket->id}_*";
            Cache::forget($pattern);
        });
    }
}
```

### Create Cache Warming Command

```bash
php artisan make:command WarmToonCache
```

Edit `app/Console/Commands/WarmToonCache.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class WarmToonCache extends Command
{
    protected $signature = 'toon:warm-cache {--limit=100}';
    protected $description = 'Pre-encode tickets to TOON format and cache them';

    public function handle(): int
    {
        $limit = $this->option('limit');

        $tickets = Ticket::whereNull('priority')
            ->limit($limit)
            ->get();

        $this->info("Warming cache for {$tickets->count()} tickets...");

        $bar = $this->output->createProgressBar($tickets->count());

        foreach ($tickets as $ticket) {
            $ticket->getCachedToonFormat();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Cache warming complete!');

        return Command::SUCCESS;
    }
}
```

## Section 9: Performance Analysis

Create a command to analyze TOON performance in your application.

```bash
php artisan make:command AnalyzeToonPerformance
```

Edit `app/Console/Commands/AnalyzeToonPerformance.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\ToonService;
use Illuminate\Console\Command;
use HelgeSverre\Toon\Toon;

class AnalyzeToonPerformance extends Command
{
    protected $signature = 'toon:analyze {--tickets=100}';
    protected $description = 'Analyze TOON performance vs JSON';

    public function handle(ToonService $toonService): int
    {
        $ticketCount = $this->option('tickets');
        $tickets = Ticket::limit($ticketCount)->get();

        if ($tickets->isEmpty()) {
            $this->error('No tickets found. Create some tickets first.');
            return Command::FAILURE;
        }

        $this->info("Analyzing {$tickets->count()} tickets...\n");

        // Measure encoding time
        $jsonTimes = [];
        $toonTimes = [];
        $sizeSavings = [];

        foreach ($tickets as $ticket) {
            $data = $ticket->toArray();

            // Measure JSON encoding
            $start = microtime(true);
            $jsonEncoded = json_encode($data);
            $jsonTimes[] = microtime(true) - $start;

            // Measure TOON encoding
            $start = microtime(true);
            $toonEncoded = toon_compact($data);
            $toonTimes[] = microtime(true) - $start;

            // Calculate size savings
            $sizeSavings[] = (strlen($jsonEncoded) - strlen($toonEncoded)) / strlen($jsonEncoded) * 100;
        }

        // Calculate statistics
        $avgJsonTime = array_sum($jsonTimes) / count($jsonTimes) * 1000; // Convert to ms
        $avgToonTime = array_sum($toonTimes) / count($toonTimes) * 1000;
        $avgSizeSaving = array_sum($sizeSavings) / count($sizeSavings);

        // Estimate token savings
        $sampleTicket = $tickets->first();
        $comparison = $toonService->compare($sampleTicket->toArray());

        // Display results
        $this->table(
            ['Metric', 'JSON', 'TOON', 'Difference'],
            [
                ['Avg Encoding Time', sprintf('%.4f ms', $avgJsonTime), sprintf('%.4f ms', $avgToonTime), sprintf('%.2fx', $avgJsonTime / $avgToonTime)],
                ['Avg Size', $comparison['json']['size'] . ' bytes', $comparison['toon']['size'] . ' bytes', sprintf('-%d%%', $avgSizeSaving)],
                ['Est. Tokens (sample)', (int)($comparison['json']['size'] / 4), (int)($comparison['toon']['size'] / 4), sprintf('-%d tokens', (int)($comparison['savings']['bytes'] / 4))],
            ]
        );

        $this->newLine();
        $this->info('Summary:');
        $this->line(sprintf('• TOON reduces size by an average of %.1f%%', $avgSizeSaving));
        $this->line(sprintf('• Estimated token savings: %d tokens per ticket', (int)($comparison['savings']['bytes'] / 4)));
        $this->line(sprintf('• For %d tickets, that\'s approximately %d tokens saved',
            $ticketCount,
            $ticketCount * (int)($comparison['savings']['bytes'] / 4)
        ));

        // Cost calculation
        $tokensSaved = $ticketCount * (int)($comparison['savings']['bytes'] / 4);
        $costSaved = ($tokensSaved / 1000) * 0.002; // GPT-3.5 pricing
        $this->line(sprintf('• Estimated cost savings: $%.4f', $costSaved));

        return Command::SUCCESS;
    }
}
```

Run the performance analysis:

```bash
php artisan toon:analyze --tickets=100
```

## Section 10: Troubleshooting

Common issues and solutions when using TOON in Laravel applications.

### Issue: Cache Not Working

**Symptom**: Encoded data is not being cached despite providing cache keys.

**Solution**: Verify Redis is running and configured correctly:

```bash
# Check Redis connection
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
```

If Redis isn't working, check:
- Redis service is running: `redis-cli ping`
- `.env` has correct Redis settings
- `config/cache.php` is using the correct driver

### Issue: Tests Failing

**Symptom**: Feature tests fail with factory not found errors.

**Solution**: Ensure factories are properly namespaced and the model uses HasFactory trait:

```php
// In your model
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory;
    // ...
}
```

### Issue: Token Estimates Inaccurate

**Symptom**: Estimated tokens don't match actual API usage.

**Note**: The 1 token ≈ 4 characters rule is an approximation. For more accurate estimates, consider using tiktoken or the OpenAI tokenizer:

```php
// More accurate token counting (requires additional package)
public function countTokensAccurately(string $text): int
{
    // Use a tokenizer library for accurate counts
    // This is still an estimate but more accurate
    $words = str_word_count($text);
    return (int) ceil($words * 1.3);
}
```

### Issue: OpenAI Rate Limits

**Symptom**: API calls fail with rate limit errors.

**Solution**: Implement exponential backoff and queuing:

```php
// In TicketClassifier
use Illuminate\Support\Facades\RateLimiter;

public function classify(Ticket $ticket): array
{
    $key = 'openai-api';

    if (RateLimiter::tooManyAttempts($key, 10)) {
        $seconds = RateLimiter::availableIn($key);
        throw new \Exception("Rate limit exceeded. Try again in {$seconds} seconds.");
    }

    RateLimiter::hit($key, 60);

    // ... rest of classification logic
}
```

### Issue: Memory Usage with Large Datasets

**Symptom**: Memory exhaustion when encoding large arrays.

**Solution**: Use chunking for large datasets:

```php
// Process in chunks
Ticket::chunk(100, function ($tickets) use ($toonService) {
    foreach ($tickets as $ticket) {
        $toonService->encode($ticket->toArray());
    }
});
```

### Issue: TOON Not Found After Installation

**Symptom**: Class 'Toon\Toon' not found errors.

**Solution**: Clear composer autoload and cache:

```bash
composer dump-autoload
php artisan cache:clear
php artisan config:clear
```

## Summary

In this tutorial, you learned how to use TOON in a Laravel application without any special integration. Key takeaways:

1. **TOON is just a library** - No facades or service providers needed
2. **Service classes are optional** - Use them for abstraction and caching
3. **Token savings are real** - 30-60% reduction translates to cost savings
4. **Caching improves performance** - Cache encoded data for frequently accessed records
5. **Testing is straightforward** - Test TOON like any other PHP code

The ticket classification system demonstrates a practical use case where TOON's compact format reduces API costs when sending data to LLMs. The patterns shown here can be applied to any Laravel application that needs efficient data formatting.

## Next Steps

- Explore using TOON with queue jobs for batch processing
- Implement TOON formatting for API responses
- Create custom encoding presets for your domain
- Build a monitoring dashboard for token usage
- Integrate with other LLM providers (Claude, Gemini)

## Additional Resources

- [TOON PHP Documentation](https://github.com/helgesverre/toon-php)
- [Laravel Documentation](https://laravel.com/docs)
- [OpenAI PHP Client](https://github.com/openai-php/client)
- [Pest Testing Framework](https://pestphp.com)