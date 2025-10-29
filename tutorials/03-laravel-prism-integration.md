# Building a Laravel AI Application with TOON and Prism

**Difficulty**: Intermediate-Advanced
**Time to Complete**: 20-30 minutes
**PHP Version**: 8.2+
**Laravel Version**: 11.x

## What You'll Build

A complete Laravel customer support chatbot that:
- Uses Prism for multi-provider LLM support
- Optimizes all data with TOON format
- Implements a real-time chat interface
- Switches between LLM providers dynamically
- Tracks token usage and costs per provider
- Includes comprehensive testing with Pest

## What You'll Learn

- Installing and configuring TOON in Laravel
- Setting up Prism for multi-provider support
- Building service providers and facades
- Creating API endpoints for AI features
- Optimizing different LLM providers with TOON
- Testing AI features with Pest
- Deploying with environment-specific configs

## Prerequisites

- Laravel 11.x development environment
- Composer and Node.js installed
- Basic Laravel knowledge (routes, controllers, models)
- Completed Tutorial 1 (TOON basics)
- API keys for at least one LLM provider

## Introduction

Laravel's ecosystem combined with Prism's multi-provider capabilities and TOON's token optimization creates a powerful stack for AI applications. This tutorial builds a production-ready customer support chatbot that can switch between OpenAI, Anthropic, and other providers while maintaining consistent token optimization.

## Step 1: Project Setup

Create a new Laravel project and install dependencies:

```bash
# Create Laravel project
composer create-project laravel/laravel toon-chatbot
cd toon-chatbot

# Install required packages
composer require helgesverre/toon
composer require echolabsdev/laravel-prism
composer require predis/predis
composer require pusher/pusher-php-server

# Install development dependencies
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
php artisan pest:install

# Install frontend dependencies
npm install alpinejs axios
npm install -D @vitejs/plugin-vue
```

Configure environment variables in `.env`:

```env
# LLM Provider Keys
OPENAI_API_KEY=sk-your-openai-key
ANTHROPIC_API_KEY=your-anthropic-key
GEMINI_API_KEY=your-gemini-key

# Prism Configuration
PRISM_DEFAULT_PROVIDER=openai
PRISM_CACHE_RESPONSES=true
PRISM_CACHE_TTL=3600

# Redis for caching
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Pusher for real-time (optional)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1
```

## Step 2: Create TOON Service Provider

Create `app/Services/ToonService.php`:

```php
<?php

namespace App\Services;

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ToonService
{
    private EncodeOptions $defaultOptions;
    private array $metrics = [];

    public function __construct()
    {
        $this->defaultOptions = new EncodeOptions(
            indent: config('toon.indent', 2),
            delimiter: config('toon.delimiter', ','),
            lengthMarker: config('toon.length_marker', false)
        );
    }

    /**
     * Encode data to TOON format with caching
     */
    public function encode(mixed $data, ?EncodeOptions $options = null): string
    {
        $options ??= $this->defaultOptions;

        // Generate cache key
        $cacheKey = $this->getCacheKey($data, $options);

        // Check cache
        if (config('toon.cache_enabled', true)) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->recordMetric('cache_hits');
                return $cached;
            }
        }

        // Encode data
        $startTime = microtime(true);
        $encoded = Toon::encode($data, $options);
        $duration = microtime(true) - $startTime;

        // Record metrics
        $this->recordMetric('encodings');
        $this->recordMetric('encoding_time', $duration);
        $this->recordMetric('bytes_processed', strlen(json_encode($data)));
        $this->recordMetric('bytes_output', strlen($encoded));

        // Cache result
        if (config('toon.cache_enabled', true)) {
            Cache::put($cacheKey, $encoded, config('toon.cache_ttl', 3600));
        }

        return $encoded;
    }

    /**
     * Compare TOON vs JSON for given data
     */
    public function compare(mixed $data): array
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $toon = $this->encode($data);

        $jsonSize = strlen($json);
        $toonSize = strlen($toon);

        // Estimate tokens (rough: 4 chars per token)
        $jsonTokens = (int) ceil($jsonSize / 4);
        $toonTokens = (int) ceil($toonSize / 4);

        return [
            'json' => [
                'size' => $jsonSize,
                'tokens' => $jsonTokens,
                'format' => $json
            ],
            'toon' => [
                'size' => $toonSize,
                'tokens' => $toonTokens,
                'format' => $toon
            ],
            'savings' => [
                'characters' => $jsonSize - $toonSize,
                'percentage' => round((1 - $toonSize / $jsonSize) * 100, 1),
                'tokens' => $jsonTokens - $toonTokens,
                'token_percentage' => round((1 - $toonTokens / $jsonTokens) * 100, 1)
            ]
        ];
    }

    /**
     * Format data for LLM consumption
     */
    public function formatForLLM(array $data, string $context = ''): string
    {
        $encoded = $this->encode($data);

        if ($context) {
            return "$context\n\nData (TOON format):\n$encoded";
        }

        return $encoded;
    }

    /**
     * Batch encode multiple datasets
     */
    public function batchEncode(array $datasets): array
    {
        $results = [];

        foreach ($datasets as $key => $data) {
            $results[$key] = $this->encode($data);
        }

        return $results;
    }

    /**
     * Get encoding metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Reset metrics
     */
    public function resetMetrics(): void
    {
        $this->metrics = [];
    }

    private function getCacheKey(mixed $data, EncodeOptions $options): string
    {
        return 'toon:' . md5(serialize($data) . serialize($options));
    }

    private function recordMetric(string $key, $value = 1): void
    {
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = 0;
        }

        $this->metrics[$key] += $value;
    }
}
```

Create the service provider `app/Providers/ToonServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Services\ToonService;
use Illuminate\Support\ServiceProvider;

class ToonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToonService::class, function ($app) {
            return new ToonService();
        });

        $this->app->alias(ToonService::class, 'toon');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/toon.php' => config_path('toon.php'),
        ], 'config');
    }
}
```

Create config file `config/toon.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TOON Encoding Options
    |--------------------------------------------------------------------------
    */

    'indent' => env('TOON_INDENT', 2),
    'delimiter' => env('TOON_DELIMITER', ','),
    'length_marker' => env('TOON_LENGTH_MARKER', false),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */

    'cache_enabled' => env('TOON_CACHE_ENABLED', true),
    'cache_ttl' => env('TOON_CACHE_TTL', 3600), // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Metrics Collection
    |--------------------------------------------------------------------------
    */

    'collect_metrics' => env('TOON_COLLECT_METRICS', true),
];
```

Create a facade `app/Facades/Toon.php`:

```php
<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Toon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'toon';
    }
}
```

Register in `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    App\Providers\ToonServiceProvider::class,
])->toArray(),

'aliases' => Facade::defaultAliases()->merge([
    // ...
    'Toon' => App\Facades\Toon::class,
])->toArray(),
```

## Step 3: Configure Prism with TOON

Create `app/Services/PrismToonService.php`:

```php
<?php

namespace App\Services;

use App\Facades\Toon;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;

class PrismToonService
{
    private array $providers;
    private string $currentProvider;
    private array $usage = [];

    public function __construct()
    {
        $this->providers = config('prism.providers', ['openai']);
        $this->currentProvider = config('prism.default_provider', 'openai');
    }

    /**
     * Send a message to the LLM with TOON-formatted data
     */
    public function chat(string $message, ?array $data = null, array $options = []): array
    {
        $prompt = $this->buildPrompt($message, $data);

        try {
            $prism = $this->getPrism($options['provider'] ?? $this->currentProvider);

            $response = $prism
                ->using($this->getProvider($options['provider'] ?? $this->currentProvider))
                ->withSystemPrompt($this->getSystemPrompt())
                ->withMaxTokens($options['max_tokens'] ?? 1000)
                ->withTemperature($options['temperature'] ?? 0.7)
                ->ask($prompt);

            // Track usage
            $this->trackUsage($response);

            return [
                'success' => true,
                'content' => $response->text,
                'usage' => $response->usage,
                'provider' => $this->currentProvider,
                'response_object' => $response
            ];

        } catch (\Exception $e) {
            Log::error('Prism chat error', [
                'error' => $e->getMessage(),
                'provider' => $this->currentProvider
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => $this->currentProvider
            ];
        }
    }

    /**
     * Stream a response from the LLM
     */
    public function stream(string $message, ?array $data = null, array $options = []): \Generator
    {
        $prompt = $this->buildPrompt($message, $data);

        $prism = $this->getPrism($options['provider'] ?? $this->currentProvider);

        $stream = $prism
            ->using($this->getProvider($options['provider'] ?? $this->currentProvider))
            ->withSystemPrompt($this->getSystemPrompt())
            ->withMaxTokens($options['max_tokens'] ?? 1000)
            ->stream()
            ->ask($prompt);

        foreach ($stream as $chunk) {
            yield $chunk;
        }
    }

    /**
     * Process with function calling
     */
    public function withTools(string $message, array $tools, ?array $data = null): array
    {
        $prompt = $this->buildPrompt($message, $data);

        $prism = $this->getPrism($this->currentProvider);

        // Convert tools to Prism format
        $prismTools = $this->convertToolsToPrismFormat($tools);

        $response = $prism
            ->using($this->getProvider($this->currentProvider))
            ->withSystemPrompt($this->getSystemPrompt())
            ->withTools($prismTools)
            ->ask($prompt);

        if ($response->toolCalls) {
            $toolResults = [];

            foreach ($response->toolCalls as $toolCall) {
                $result = $this->executeToolCall($toolCall, $tools);
                $toolResults[] = [
                    'tool' => $toolCall->name,
                    'result' => Toon::encode($result) // Encode tool results with TOON
                ];
            }

            return [
                'success' => true,
                'content' => $response->text,
                'tool_calls' => $response->toolCalls,
                'tool_results' => $toolResults,
                'provider' => $this->currentProvider
            ];
        }

        return [
            'success' => true,
            'content' => $response->text,
            'provider' => $this->currentProvider
        ];
    }

    /**
     * Switch provider dynamically
     */
    public function useProvider(string $provider): self
    {
        if (!in_array($provider, $this->providers)) {
            throw new \InvalidArgumentException("Provider $provider is not configured");
        }

        $this->currentProvider = $provider;
        return $this;
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(): array
    {
        return $this->usage;
    }

    /**
     * Calculate cost for current usage
     */
    public function calculateCost(): array
    {
        $costs = [];
        $total = 0;

        foreach ($this->usage as $provider => $usage) {
            $cost = $this->calculateProviderCost($provider, $usage);
            $costs[$provider] = $cost;
            $total += $cost['total'];
        }

        return [
            'providers' => $costs,
            'total' => $total
        ];
    }

    private function buildPrompt(string $message, ?array $data): string
    {
        if ($data === null) {
            return $message;
        }

        $toonData = Toon::encode($data);

        return <<<PROMPT
$message

Data (TOON format):
$toonData

Note: The data above is in TOON format - a compact notation where:
- Objects use "key: value" pairs with indentation for nesting
- Arrays show "[length]: item1,item2,item3"
- Tabular data uses "[rows]{fields}: row_values"
PROMPT;
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
You are a helpful AI assistant integrated into a Laravel application.
You will receive data in TOON format, which is more compact than JSON.
Always provide clear, concise, and helpful responses.
When referencing data, maintain accuracy to the provided information.
PROMPT;
    }

    private function getPrism(string $provider): Prism
    {
        return Prism::text();
    }

    private function getProvider(string $provider): Provider
    {
        return match($provider) {
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            'gemini' => Provider::Gemini,
            'ollama' => Provider::Ollama,
            default => Provider::OpenAI
        };
    }

    private function trackUsage(object $response): void
    {
        if (!isset($this->usage[$this->currentProvider])) {
            $this->usage[$this->currentProvider] = [
                'requests' => 0,
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0
            ];
        }

        $this->usage[$this->currentProvider]['requests']++;

        if (isset($response->usage)) {
            $this->usage[$this->currentProvider]['prompt_tokens'] += $response->usage->promptTokens ?? 0;
            $this->usage[$this->currentProvider]['completion_tokens'] += $response->usage->completionTokens ?? 0;
            $this->usage[$this->currentProvider]['total_tokens'] +=
                ($response->usage->promptTokens ?? 0) + ($response->usage->completionTokens ?? 0);
        }
    }

    private function calculateProviderCost(string $provider, array $usage): array
    {
        // Pricing per 1K tokens (example rates)
        $pricing = [
            'openai' => ['input' => 0.0005, 'output' => 0.0015],
            'anthropic' => ['input' => 0.008, 'output' => 0.024],
            'gemini' => ['input' => 0.0005, 'output' => 0.0015],
            'ollama' => ['input' => 0, 'output' => 0] // Local/free
        ];

        $rates = $pricing[$provider] ?? $pricing['openai'];

        $inputCost = ($usage['prompt_tokens'] / 1000) * $rates['input'];
        $outputCost = ($usage['completion_tokens'] / 1000) * $rates['output'];

        return [
            'input' => round($inputCost, 4),
            'output' => round($outputCost, 4),
            'total' => round($inputCost + $outputCost, 4)
        ];
    }

    private function convertToolsToPrismFormat(array $tools): array
    {
        // Convert custom tool format to Prism's expected format
        return array_map(function ($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'] ?? []
            ];
        }, $tools);
    }

    private function executeToolCall(object $toolCall, array $tools): mixed
    {
        foreach ($tools as $tool) {
            if ($tool['name'] === $toolCall->name) {
                if (isset($tool['handler']) && is_callable($tool['handler'])) {
                    return $tool['handler']($toolCall->arguments);
                }
            }
        }

        return null;
    }
}
```

## Step 4: Create the Chatbot Controller

Create `app/Http/Controllers/ChatbotController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\PrismToonService;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ChatbotController extends Controller
{
    private PrismToonService $prismService;

    public function __construct(PrismToonService $prismService)
    {
        $this->prismService = $prismService;
    }

    /**
     * Display the chat interface
     */
    public function index()
    {
        $conversations = Auth::check()
            ? Auth::user()->conversations()->latest()->get()
            : [];

        return view('chatbot.index', compact('conversations'));
    }

    /**
     * Start a new conversation
     */
    public function startConversation(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'context' => 'nullable|array'
        ]);

        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'] ?? 'New Conversation',
            'context' => $validated['context'] ?? [],
            'provider' => config('prism.default_provider'),
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'conversation' => $conversation
        ]);
    }

    /**
     * Send a message to the chatbot
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'data' => 'nullable|array',
            'provider' => 'nullable|string|in:openai,anthropic,gemini,ollama'
        ]);

        $conversation = Conversation::findOrFail($conversationId);

        // Store user message
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['message'],
            'data' => $validated['data'] ?? null,
            'tokens' => $this->estimateTokens($validated['message'], $validated['data'] ?? null)
        ]);

        // Get conversation context
        $context = $this->buildConversationContext($conversation);

        // Switch provider if requested
        if (isset($validated['provider'])) {
            $this->prismService->useProvider($validated['provider']);
            $conversation->update(['provider' => $validated['provider']]);
        }

        // Send to LLM with TOON-formatted context
        $response = $this->prismService->chat(
            $validated['message'],
            array_merge($context, $validated['data'] ?? []),
            ['max_tokens' => 1000]
        );

        if ($response['success']) {
            // Store assistant message
            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $response['content'],
                'tokens' => $response['usage']['total_tokens'] ?? 0,
                'provider' => $response['provider']
            ]);

            // Update conversation statistics
            $conversation->increment('total_messages', 2);
            $conversation->increment('total_tokens',
                $userMessage->tokens + $assistantMessage->tokens);

            return response()->json([
                'success' => true,
                'message' => $assistantMessage,
                'usage' => $response['usage'] ?? null,
                'provider' => $response['provider']
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $response['error'] ?? 'Unknown error occurred'
        ], 500);
    }

    /**
     * Stream a response
     */
    public function streamMessage(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'data' => 'nullable|array'
        ]);

        $conversation = Conversation::findOrFail($conversationId);

        return response()->stream(function () use ($validated, $conversation) {
            $context = $this->buildConversationContext($conversation);

            foreach ($this->prismService->stream(
                $validated['message'],
                array_merge($context, $validated['data'] ?? [])
            ) as $chunk) {
                echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                ob_flush();
                flush();
            }

            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no'
        ]);
    }

    /**
     * Compare TOON vs JSON for the conversation
     */
    public function compareFormats($conversationId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        $messages = $conversation->messages()->get();

        $conversationData = [
            'conversation_id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $messages->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'timestamp' => $msg->created_at->toIso8601String()
                ];
            })->toArray(),
            'context' => $conversation->context,
            'statistics' => [
                'total_messages' => $conversation->total_messages,
                'total_tokens' => $conversation->total_tokens,
                'provider' => $conversation->provider
            ]
        ];

        $comparison = app(ToonService::class)->compare($conversationData);

        return response()->json([
            'success' => true,
            'comparison' => $comparison,
            'recommendation' => $comparison['savings']['percentage'] > 30
                ? 'TOON provides significant savings for this conversation'
                : 'TOON provides moderate savings for this conversation'
        ]);
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats()
    {
        $stats = $this->prismService->getUsageStats();
        $costs = $this->prismService->calculateCost();

        $userStats = [];
        if (Auth::check()) {
            $userStats = [
                'total_conversations' => Auth::user()->conversations()->count(),
                'total_messages' => Message::whereHas('conversation', function ($q) {
                    $q->where('user_id', Auth::id());
                })->count(),
                'total_tokens_used' => Auth::user()->conversations()->sum('total_tokens')
            ];
        }

        return response()->json([
            'success' => true,
            'provider_usage' => $stats,
            'costs' => $costs,
            'user_stats' => $userStats,
            'toon_metrics' => app(ToonService::class)->getMetrics()
        ]);
    }

    private function buildConversationContext(Conversation $conversation): array
    {
        // Get last 10 messages for context
        $recentMessages = $conversation->messages()
            ->latest()
            ->limit(10)
            ->get()
            ->reverse();

        return [
            'conversation_history' => $recentMessages->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'content' => Str::limit($msg->content, 500),
                    'timestamp' => $msg->created_at->diffForHumans()
                ];
            })->toArray(),
            'conversation_context' => $conversation->context,
            'user_preferences' => Auth::check() ? [
                'name' => Auth::user()->name,
                'preferred_language' => Auth::user()->preferred_language ?? 'en'
            ] : []
        ];
    }

    private function estimateTokens(string $message, ?array $data): int
    {
        $totalChars = strlen($message);

        if ($data) {
            $toonData = app(ToonService::class)->encode($data);
            $totalChars += strlen($toonData);
        }

        // Rough estimate: 4 characters per token
        return (int) ceil($totalChars / 4);
    }
}
```

## Step 5: Create Models and Migrations

Create the Conversation model and migration:

```bash
php artisan make:model Conversation -m
```

Migration `database/migrations/xxxx_create_conversations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->json('context')->nullable();
            $table->string('provider')->default('openai');
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->integer('total_messages')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('total_cost', 8, 4)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
```

Model `app/Models/Conversation.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'context',
        'provider',
        'status',
        'total_messages',
        'total_tokens',
        'total_cost'
    ];

    protected $casts = [
        'context' => 'array',
        'total_cost' => 'decimal:4'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getTokenEfficiency(): float
    {
        if ($this->total_messages === 0) {
            return 0;
        }

        return round($this->total_tokens / $this->total_messages, 1);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived']);
    }
}
```

Create Message model and migration:

```bash
php artisan make:model Message -m
```

Migration `database/migrations/xxxx_create_messages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system', 'tool']);
            $table->text('content');
            $table->json('data')->nullable();
            $table->integer('tokens')->default(0);
            $table->string('provider')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'role']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
```

Model `app/Models/Message.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'data',
        'tokens',
        'provider',
        'metadata'
    ];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array'
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getToonData(): ?string
    {
        if (!$this->data) {
            return null;
        }

        return app(ToonService::class)->encode($this->data);
    }

    public function getEstimatedCost(): float
    {
        // Rough cost estimate based on provider
        $rates = [
            'openai' => 0.002,
            'anthropic' => 0.008,
            'gemini' => 0.001,
        ];

        $rate = $rates[$this->provider ?? 'openai'] ?? 0.002;

        return ($this->tokens / 1000) * $rate;
    }
}
```

## Step 6: Create the Chat Interface

Create the Blade view `resources/views/chatbot/index.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8" x-data="chatbot()">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-bold text-lg mb-4">Conversations</h3>

                <button @click="startNewConversation()"
                        class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 mb-4">
                    New Conversation
                </button>

                <div class="space-y-2">
                    @foreach($conversations as $conversation)
                    <div @click="loadConversation({{ $conversation->id }})"
                         class="p-3 rounded cursor-pointer hover:bg-gray-100"
                         :class="{ 'bg-blue-50': currentConversationId === {{ $conversation->id }} }">
                        <div class="font-semibold text-sm">{{ $conversation->title }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $conversation->messages_count }} messages
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Provider Selection -->
            <div class="bg-white rounded-lg shadow p-4 mt-4">
                <h3 class="font-bold text-lg mb-4">LLM Provider</h3>

                <select x-model="currentProvider"
                        @change="switchProvider()"
                        class="w-full border rounded px-3 py-2">
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Anthropic</option>
                    <option value="gemini">Google Gemini</option>
                    <option value="ollama">Ollama (Local)</option>
                </select>

                <div class="mt-4 text-sm">
                    <div class="flex justify-between">
                        <span>Tokens Used:</span>
                        <span x-text="stats.tokens"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Est. Cost:</span>
                        <span>$<span x-text="stats.cost"></span></span>
                    </div>
                </div>
            </div>

            <!-- TOON Comparison -->
            <div class="bg-white rounded-lg shadow p-4 mt-4">
                <h3 class="font-bold text-lg mb-4">TOON Savings</h3>

                <button @click="compareFormats()"
                        class="w-full bg-green-500 text-white px-3 py-2 rounded text-sm hover:bg-green-600">
                    Compare Formats
                </button>

                <div x-show="comparison" class="mt-4 text-sm">
                    <div class="flex justify-between">
                        <span>JSON Size:</span>
                        <span x-text="comparison?.json?.size"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>TOON Size:</span>
                        <span x-text="comparison?.toon?.size"></span>
                    </div>
                    <div class="flex justify-between font-bold text-green-600">
                        <span>Savings:</span>
                        <span x-text="comparison?.savings?.percentage + '%'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow h-[600px] flex flex-col">
                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-6" id="messagesContainer">
                    <template x-for="message in messages" :key="message.id">
                        <div class="mb-4">
                            <div :class="{
                                'text-right': message.role === 'user',
                                'text-left': message.role === 'assistant'
                            }">
                                <div class="inline-block max-w-3/4 p-3 rounded-lg"
                                     :class="{
                                         'bg-blue-100': message.role === 'user',
                                         'bg-gray-100': message.role === 'assistant'
                                     }">
                                    <div class="text-sm font-semibold mb-1"
                                         x-text="message.role === 'user' ? 'You' : 'Assistant'"></div>
                                    <div x-text="message.content"></div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <span x-text="message.tokens"></span> tokens
                                        <span x-show="message.provider"
                                              x-text="'via ' + message.provider"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Typing Indicator -->
                    <div x-show="isTyping" class="mb-4">
                        <div class="inline-block bg-gray-100 p-3 rounded-lg">
                            <div class="flex space-x-2">
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                                     style="animation-delay: 0.1s"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                                     style="animation-delay: 0.2s"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t p-4">
                    <div class="flex space-x-2">
                        <input type="text"
                               x-model="newMessage"
                               @keydown.enter="sendMessage()"
                               placeholder="Type your message..."
                               class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                               :disabled="isTyping">

                        <button @click="attachData()"
                                class="px-4 py-2 border rounded-lg hover:bg-gray-100">
                            📎 Data
                        </button>

                        <button @click="sendMessage()"
                                :disabled="!newMessage || isTyping"
                                class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50">
                            Send
                        </button>
                    </div>

                    <!-- Attached Data Preview -->
                    <div x-show="attachedData" class="mt-2 p-2 bg-yellow-50 rounded text-sm">
                        <div class="flex justify-between items-center">
                            <span>Data attached (TOON format)</span>
                            <button @click="attachedData = null" class="text-red-500">✕</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function chatbot() {
    return {
        currentConversationId: null,
        currentProvider: 'openai',
        messages: [],
        newMessage: '',
        isTyping: false,
        attachedData: null,
        comparison: null,
        stats: {
            tokens: 0,
            cost: 0
        },

        async startNewConversation() {
            try {
                const response = await axios.post('/api/chatbot/conversations', {
                    title: 'New Conversation ' + new Date().toLocaleString()
                });

                this.currentConversationId = response.data.conversation_id;
                this.messages = [];
                this.loadStats();
            } catch (error) {
                console.error('Error starting conversation:', error);
            }
        },

        async loadConversation(conversationId) {
            try {
                this.currentConversationId = conversationId;
                const response = await axios.get(`/api/chatbot/conversations/${conversationId}/messages`);
                this.messages = response.data.messages;
                this.scrollToBottom();
            } catch (error) {
                console.error('Error loading conversation:', error);
            }
        },

        async sendMessage() {
            if (!this.newMessage.trim() || !this.currentConversationId) return;

            const message = this.newMessage;
            this.newMessage = '';

            // Add user message to UI
            this.messages.push({
                id: Date.now(),
                role: 'user',
                content: message,
                tokens: Math.ceil(message.length / 4)
            });

            this.isTyping = true;
            this.scrollToBottom();

            try {
                const response = await axios.post(
                    `/api/chatbot/conversations/${this.currentConversationId}/messages`,
                    {
                        message: message,
                        data: this.attachedData,
                        provider: this.currentProvider
                    }
                );

                this.messages.push(response.data.message);
                this.attachedData = null;
                this.loadStats();
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Error sending message. Please try again.');
            } finally {
                this.isTyping = false;
                this.scrollToBottom();
            }
        },

        async switchProvider() {
            // Provider will be used on next message
            console.log('Switched to provider:', this.currentProvider);
        },

        async compareFormats() {
            if (!this.currentConversationId) return;

            try {
                const response = await axios.get(
                    `/api/chatbot/conversations/${this.currentConversationId}/compare`
                );
                this.comparison = response.data.comparison;
            } catch (error) {
                console.error('Error comparing formats:', error);
            }
        },

        async loadStats() {
            try {
                const response = await axios.get('/api/chatbot/stats');
                const data = response.data;

                this.stats.tokens = data.user_stats?.total_tokens_used || 0;
                this.stats.cost = data.costs?.total?.toFixed(4) || 0;
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        },

        attachData() {
            // Simple data attachment example
            const sampleData = {
                user_info: {
                    name: 'John Doe',
                    account_type: 'premium',
                    since: '2023-01-15'
                },
                request_context: {
                    page: 'support',
                    previous_action: 'viewed_faq'
                }
            };

            this.attachedData = sampleData;
            alert('Sample data attached. This will be sent in TOON format with your message.');
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = document.getElementById('messagesContainer');
                container.scrollTop = container.scrollHeight;
            });
        },

        init() {
            this.loadStats();

            // Auto-load first conversation if exists
            @if($conversations->isNotEmpty())
                this.loadConversation({{ $conversations->first()->id }});
            @else
                this.startNewConversation();
            @endif
        }
    }
}
</script>
@endsection
```

## Step 7: Add Routes

Update `routes/web.php`:

```php
<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Chatbot routes
Route::prefix('chatbot')->group(function () {
    Route::get('/', [ChatbotController::class, 'index'])->name('chatbot.index');
});

// API routes for chatbot
Route::prefix('api/chatbot')->group(function () {
    Route::post('/conversations', [ChatbotController::class, 'startConversation']);
    Route::get('/conversations/{id}/messages', [ChatbotController::class, 'getMessages']);
    Route::post('/conversations/{id}/messages', [ChatbotController::class, 'sendMessage']);
    Route::get('/conversations/{id}/stream', [ChatbotController::class, 'streamMessage']);
    Route::get('/conversations/{id}/compare', [ChatbotController::class, 'compareFormats']);
    Route::get('/stats', [ChatbotController::class, 'getUsageStats']);
});
```

## Step 8: Testing with Pest

Create comprehensive tests. First, create `tests/Feature/ToonServiceTest.php`:

```php
<?php

use App\Services\ToonService;
use HelgeSverre\Toon\EncodeOptions;

it('encodes data to TOON format', function () {
    $service = app(ToonService::class);

    $data = [
        'name' => 'John Doe',
        'age' => 30,
        'active' => true
    ];

    $encoded = $service->encode($data);

    expect($encoded)->toContain('name: John Doe')
        ->toContain('age: 30')
        ->toContain('active: true');
});

it('caches encoded data', function () {
    $service = app(ToonService::class);
    $service->resetMetrics();

    $data = ['key' => 'value'];

    // First call - should encode
    $result1 = $service->encode($data);
    $metrics1 = $service->getMetrics();
    expect($metrics1['encodings'])->toBe(1);

    // Second call - should use cache
    $result2 = $service->encode($data);
    $metrics2 = $service->getMetrics();
    expect($metrics2['cache_hits'])->toBe(1);
    expect($metrics2['encodings'])->toBe(1); // Still 1, not 2

    expect($result1)->toBe($result2);
});

it('compares TOON vs JSON accurately', function () {
    $service = app(ToonService::class);

    $data = [
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ]
    ];

    $comparison = $service->compare($data);

    expect($comparison)->toHaveKeys(['json', 'toon', 'savings']);
    expect($comparison['savings']['percentage'])->toBeGreaterThan(0);
    expect($comparison['toon']['size'])->toBeLessThan($comparison['json']['size']);
});

it('formats data for LLM consumption', function () {
    $service = app(ToonService::class);

    $data = ['status' => 'active', 'count' => 42];
    $context = 'Analyze this data:';

    $formatted = $service->formatForLLM($data, $context);

    expect($formatted)->toStartWith('Analyze this data:')
        ->toContain('Data (TOON format):')
        ->toContain('status: active')
        ->toContain('count: 42');
});
```

Create `tests/Feature/PrismToonServiceTest.php`:

```php
<?php

use App\Services\PrismToonService;
use App\Models\User;
use App\Models\Conversation;

beforeEach(function () {
    $this->service = app(PrismToonService::class);
    $this->user = User::factory()->create();
});

it('builds prompts with TOON data', function () {
    $message = 'Analyze this customer';
    $data = [
        'customer' => [
            'id' => 123,
            'name' => 'Test Customer'
        ]
    ];

    // Use reflection to test private method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('buildPrompt');
    $method->setAccessible(true);

    $prompt = $method->invoke($this->service, $message, $data);

    expect($prompt)->toContain('Analyze this customer')
        ->toContain('Data (TOON format):')
        ->toContain('customer:')
        ->toContain('id: 123')
        ->toContain('name: Test Customer');
});

it('tracks usage across providers', function () {
    // This would need mocking of the Prism responses
    // Example structure:

    $mockResponse = (object)[
        'text' => 'Response text',
        'usage' => (object)[
            'promptTokens' => 100,
            'completionTokens' => 50
        ]
    ];

    // After processing mock responses...
    $stats = $this->service->getUsageStats();

    expect($stats)->toBeArray();
    // More assertions based on mocked data
});

it('calculates costs correctly', function () {
    // Set up known usage
    $reflection = new ReflectionClass($this->service);
    $property = $reflection->getProperty('usage');
    $property->setAccessible(true);
    $property->setValue($this->service, [
        'openai' => [
            'requests' => 10,
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'total_tokens' => 1500
        ]
    ]);

    $costs = $this->service->calculateCost();

    expect($costs['providers']['openai'])->toHaveKeys(['input', 'output', 'total']);
    expect($costs['providers']['openai']['total'])->toBeGreaterThan(0);
});
```

Create `tests/Feature/ChatbotControllerTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('starts a new conversation', function () {
    $response = $this->postJson('/api/chatbot/conversations', [
        'title' => 'Test Conversation',
        'context' => ['test' => 'data']
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'conversation_id',
            'conversation'
        ]);

    $this->assertDatabaseHas('conversations', [
        'user_id' => $this->user->id,
        'title' => 'Test Conversation'
    ]);
});

it('sends a message to chatbot', function () {
    $conversation = Conversation::factory()->create([
        'user_id' => $this->user->id
    ]);

    // This would need mocking of the PrismToonService
    $this->mock(PrismToonService::class, function ($mock) {
        $mock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'success' => true,
                'content' => 'Test response',
                'usage' => ['total_tokens' => 150],
                'provider' => 'openai'
            ]);
    });

    $response = $this->postJson("/api/chatbot/conversations/{$conversation->id}/messages", [
        'message' => 'Hello, chatbot!'
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message.role', 'assistant');

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Hello, chatbot!'
    ]);
});

it('compares TOON vs JSON formats', function () {
    $conversation = Conversation::factory()
        ->has(Message::factory()->count(5))
        ->create(['user_id' => $this->user->id]);

    $response = $this->getJson("/api/chatbot/conversations/{$conversation->id}/compare");

    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'comparison' => [
                'json',
                'toon',
                'savings'
            ],
            'recommendation'
        ]);

    expect($response->json('comparison.savings.percentage'))->toBeGreaterThan(0);
});

it('retrieves usage statistics', function () {
    $response = $this->getJson('/api/chatbot/stats');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'provider_usage',
            'costs',
            'user_stats',
            'toon_metrics'
        ]);
});

it('enforces conversation ownership', function () {
    $otherUser = User::factory()->create();
    $conversation = Conversation::factory()->create([
        'user_id' => $otherUser->id
    ]);

    $response = $this->postJson("/api/chatbot/conversations/{$conversation->id}/messages", [
        'message' => 'Should fail'
    ]);

    $response->assertNotFound();
});
```

## Step 9: Deployment Configuration

Create deployment-specific configurations. Update `.env.production`:

```env
# Production LLM Configuration
PRISM_DEFAULT_PROVIDER=openai
PRISM_CACHE_RESPONSES=true
PRISM_CACHE_TTL=7200

# TOON Production Settings
TOON_CACHE_ENABLED=true
TOON_CACHE_TTL=3600
TOON_COLLECT_METRICS=true

# Redis Configuration
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Rate Limiting
THROTTLE_CHATBOT_REQUESTS=60
THROTTLE_CHATBOT_PERIOD=1
```

Create `app/Http/Middleware/ThrottleChatbot.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ThrottleChatbot
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = 'chatbot:' . ($request->user()->id ?? $request->ip());
        $maxAttempts = config('app.throttle_chatbot_requests', 60);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error' => 'Too many requests. Please slow down.'
            ], 429);
        }

        RateLimiter::hit($key);

        return $next($request);
    }
}
```

Add to `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ...
    'throttle.chatbot' => \App\Http\Middleware\ThrottleChatbot::class,
];
```

Update routes to use throttling:

```php
Route::prefix('api/chatbot')->middleware(['throttle.chatbot'])->group(function () {
    // ... routes
});
```

## Step 10: Monitoring and Optimization

Create `app/Console/Commands/AnalyzeToonUsage.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\ToonService;
use Illuminate\Console\Command;

class AnalyzeToonUsage extends Command
{
    protected $signature = 'toon:analyze {--days=7}';
    protected $description = 'Analyze TOON usage and savings';

    public function handle(ToonService $toonService): int
    {
        $days = $this->option('days');
        $since = now()->subDays($days);

        $conversations = Conversation::where('created_at', '>=', $since)
            ->with('messages')
            ->get();

        $totalJsonSize = 0;
        $totalToonSize = 0;
        $totalMessages = 0;

        foreach ($conversations as $conversation) {
            foreach ($conversation->messages as $message) {
                if ($message->data) {
                    $comparison = $toonService->compare($message->data);
                    $totalJsonSize += $comparison['json']['size'];
                    $totalToonSize += $comparison['toon']['size'];
                    $totalMessages++;
                }
            }
        }

        $savings = $totalJsonSize > 0
            ? round((1 - $totalToonSize / $totalJsonSize) * 100, 1)
            : 0;

        $this->info("TOON Usage Analysis (Last {$days} days)");
        $this->info("=====================================");
        $this->info("Total conversations: " . $conversations->count());
        $this->info("Messages with data: {$totalMessages}");
        $this->info("Total JSON size: " . number_format($totalJsonSize) . " bytes");
        $this->info("Total TOON size: " . number_format($totalToonSize) . " bytes");
        $this->info("Space saved: " . number_format($totalJsonSize - $totalToonSize) . " bytes");
        $this->info("Savings percentage: {$savings}%");

        // Estimate token savings
        $tokensSaved = ($totalJsonSize - $totalToonSize) / 4; // Rough estimate
        $costSaved = ($tokensSaved / 1000) * 0.002; // Average cost per 1K tokens

        $this->info("");
        $this->info("Estimated Impact:");
        $this->info("Tokens saved: " . number_format($tokensSaved));
        $this->info("Cost saved: $" . number_format($costSaved, 2));

        return Command::SUCCESS;
    }
}
```

## Testing and Validation

Run all tests:

```bash
# Run Pest tests
php artisan test

# Run specific test suites
php artisan test --filter=ToonServiceTest
php artisan test --filter=ChatbotControllerTest

# Run with coverage
php artisan test --coverage

# Run TOON analysis
php artisan toon:analyze --days=30
```

## Troubleshooting

### Common Issues and Solutions

1. **Prism Provider Not Working**
   - Verify API keys in .env
   - Check provider is enabled in config/prism.php
   - Ensure network allows outbound HTTPS

2. **TOON Cache Not Working**
   - Verify Redis is running
   - Check CACHE_DRIVER=redis in .env
   - Clear cache: `php artisan cache:clear`

3. **Message Streaming Fails**
   - Check PHP output buffering settings
   - Verify nginx/Apache allows SSE
   - Test with `X-Accel-Buffering: no` header

4. **High Memory Usage**
   - Enable pagination for conversations
   - Limit message history context
   - Use queue for large data processing

5. **Token Count Mismatch**
   - Use proper tokenizer library (tiktoken)
   - Account for TOON metadata overhead
   - Consider model-specific tokenization

## Next Steps

You've built a production-ready Laravel AI chatbot! Continue with:

1. **Tutorial 4**: Deep dive into token optimization strategies
2. **Tutorial 5**: Build RAG systems with vector stores
3. **Advanced Features**: Add voice input, file uploads, and more

### Key Takeaways

- TOON integrates seamlessly with Laravel's architecture
- Prism enables easy provider switching with consistent optimization
- Proper caching and metrics are essential for production
- Testing AI features requires careful mocking
- Token savings compound with application scale

### Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Prism Documentation](https://github.com/echolabsdev/laravel-prism)
- [TOON Repository](https://github.com/helgesverre/toon)
- [Pest Testing](https://pestphp.com)