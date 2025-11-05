# Tutorial 6: Integrating TOON with Anthropic/Claude

**Difficulty**: Intermediate
**Time to Complete**: 20-25 minutes
**PHP Version**: 8.1+

## What You'll Build

In this tutorial, you'll create a working integration between TOON and Anthropic's Claude API to leverage Claude's large context window (200K tokens) more effectively. You'll build:

1. **Large Dataset Analysis**: Process 50+ customer support tickets efficiently
2. **Context Window Optimization**: Fit more data into Claude's context window using TOON's compression

You'll see how TOON's 40-60% token reduction allows you to maximize Claude's massive context window.

## Learning Objectives

By the end of this tutorial, you will:
- Install and configure the Anthropic PHP SDK
- Format large datasets with TOON for Claude's context window
- Measure token savings specific to Claude's models
- Leverage Claude's long-context capabilities effectively
- Calculate real cost savings for Anthropic API usage

## Prerequisites

- Completed Tutorial 1 (Getting Started with TOON)
- PHP 8.1 or higher
- Composer installed
- Anthropic API key (free tier available)
- Basic understanding of API requests

---

## Introduction

Claude (Anthropic's LLM) offers a massive 200K token context window, allowing you to send large amounts of data in a single request. However, this still has cost implications - every token counts.

TOON's format optimization becomes even more powerful with Claude because:
- You can fit 40-60% more data in the same context window
- Claude's models are particularly good at understanding structured formats
- Costs scale linearly with tokens, so savings compound with large contexts

Let's see how to maximize Claude's capabilities with TOON.

## Section 1: Setup and Installation

First, let's set up a new project with the required dependencies.

### Step 1: Create Project Directory

```bash
mkdir toon-anthropic-integration
cd toon-anthropic-integration
```

### Step 2: Install Required Packages

```bash
composer require helgesverre/toon
composer require anthropics/anthropic-sdk-php
composer require vlucas/phpdotenv
```

The `anthropics/anthropic-sdk-php` is the official PHP SDK for Anthropic's API.

### Step 3: Configure Environment

Create a `.env` file to store your Anthropic API key:

```bash
# .env
ANTHROPIC_API_KEY=sk-ant-your-actual-api-key-here
```

You can get your API key from: https://console.anthropic.com/

### Step 4: Verify Setup

Create `verify-setup.php` to test your configuration:

```php
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate API key exists
if (!isset($_ENV['ANTHROPIC_API_KEY']) || str_starts_with($_ENV['ANTHROPIC_API_KEY'], 'sk-ant-your')) {
    die("Error: Please set a valid ANTHROPIC_API_KEY in your .env file\n");
}

echo "Environment configured successfully!\n";
echo "API key found: " . substr($_ENV['ANTHROPIC_API_KEY'], 0, 15) . "...\n";
```

Run it:

```bash
php verify-setup.php
```

You should see confirmation that your API key is configured.

## Section 2: Large Dataset Analysis

Claude's strength is analyzing large amounts of data in context. Let's build a system to analyze customer support tickets.

### The Scenario

Your company has accumulated 50+ support tickets that need analysis. You want Claude to:
- Identify high-priority open tickets
- Detect common issue patterns
- Recommend actions for the support team
- Summarize the overall support situation

### Step 1: Create the Ticket Analyzer

Create `ticket-analyzer.php`:

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use Anthropic\Anthropic;
use Anthropic\Resources\Messages\MessageParam;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize Anthropic client
$apiKey = $_ENV['ANTHROPIC_API_KEY'];
$client = Anthropic::factory()
    ->withApiKey($apiKey)
    ->make();

// Generate sample dataset of 50 customer support tickets
$supportTickets = [];
for ($i = 1; $i <= 50; $i++) {
    $supportTickets[] = [
        'id' => $i,
        'customer' => "Customer {$i}",
        'subject' => "Issue #{$i}",
        'priority' => $i % 3 === 0 ? 'high' : ($i % 2 === 0 ? 'medium' : 'low'),
        'status' => $i > 40 ? 'open' : 'closed',
        'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
    ];
}

// Compare encoding sizes
$jsonEncoded = json_encode(['tickets' => $supportTickets], JSON_PRETTY_PRINT);
$toonEncoded = Toon::encode(['tickets' => $supportTickets], EncodeOptions::compact());

echo "=== Large Context Optimization for Claude ===\n\n";
echo "Dataset: 50 customer support tickets\n\n";

echo "JSON Encoding:\n";
echo '  Size: '.strlen($jsonEncoded)." characters\n";
echo '  Estimated tokens: '.ceil(strlen($jsonEncoded) / 4)."\n\n";

echo "TOON Encoding:\n";
echo '  Size: '.strlen($toonEncoded)." characters\n";
echo '  Estimated tokens: '.ceil(strlen($toonEncoded) / 4)."\n\n";

$savings = strlen($jsonEncoded) - strlen($toonEncoded);
$savingsPercent = ($savings / strlen($jsonEncoded)) * 100;
echo "Savings: {$savings} characters (".number_format($savingsPercent, 1)."%)\n\n";

echo "TOON Preview (first 500 chars):\n";
echo str_repeat('-', 70)."\n";
echo substr($toonEncoded, 0, 500)."...\n";
echo str_repeat('-', 70)."\n\n";

// Send to Claude with TOON-encoded context
try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 300,
        'messages' => [
            MessageParam::with(
                role: 'user',
                content: <<<EOT
Here is a dataset of customer support tickets in TOON format (a compact, readable format).
Please analyze the tickets and provide a summary of:
1. High priority open tickets
2. Most common issues
3. Recommended actions

Data:
{$toonEncoded}
EOT
            ),
        ],
    ]);

    echo "=== Claude's Analysis ===\n\n";
    echo $response->content[0]->text."\n";
} catch (Exception $e) {
    echo "Error: ".$e->getMessage()."\n";
}

// Context window utilization comparison
echo "\n=== Context Window Utilization ===\n\n";
echo "If you were sending this data 100 times in a conversation:\n";
echo 'JSON:  '.number_format(ceil(strlen($jsonEncoded) / 4) * 100)." tokens\n";
echo 'TOON:  '.number_format(ceil(strlen($toonEncoded) / 4) * 100)." tokens\n";
echo 'Saved: '.number_format((ceil(strlen($jsonEncoded) / 4) - ceil(strlen($toonEncoded) / 4)) * 100)." tokens\n\n";
echo 'With TOON, you can fit '.number_format($savingsPercent, 1)."% more data in the same context window!\n";
```

### Understanding the Code

This example demonstrates several key concepts:

1. **Large Dataset**: We're working with 50 tickets - a realistic batch for analysis
2. **Compact Encoding**: Using `EncodeOptions::compact()` for maximum compression
3. **Claude's Model**: Using `claude-sonnet-4-20250514` for analysis
4. **Token Estimation**: Claude's tokenization is similar but not identical to OpenAI's
5. **Context Window**: Showing how much more you can fit with TOON

### Running the Analyzer

```bash
php ticket-analyzer.php
```

Expected output shows:
- Encoding comparison (JSON vs TOON)
- Token savings (typically 40-50%)
- Claude's analysis of the tickets
- Context window utilization statistics

## Section 3: Advanced Example - Detailed Ticket Analysis

Let's build a more sophisticated example with richer ticket data.

Create `advanced-ticket-analysis.php`:

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use Anthropic\Anthropic;
use Anthropic\Resources\Messages\MessageParam;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize Anthropic client
$client = Anthropic::factory()
    ->withApiKey($_ENV['ANTHROPIC_API_KEY'])
    ->make();

// Create more detailed support tickets
$detailedTickets = [
    [
        'id' => 1,
        'customer' => [
            'name' => 'Sarah Chen',
            'email' => 'sarah.chen@techcorp.com',
            'account_type' => 'Enterprise',
            'since' => '2023-01-15'
        ],
        'issue' => [
            'title' => 'API rate limit exceeded',
            'category' => 'Technical',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'Our application is hitting rate limits during peak hours, affecting production operations.',
            'created' => '2025-01-15 09:30:00',
            'updated' => '2025-01-15 14:22:00'
        ],
        'interactions' => [
            ['agent' => 'John', 'time' => '2025-01-15 10:00:00', 'note' => 'Investigating rate limit configuration'],
            ['agent' => 'John', 'time' => '2025-01-15 12:30:00', 'note' => 'Escalated to engineering team']
        ]
    ],
    [
        'id' => 2,
        'customer' => [
            'name' => 'Marcus Williams',
            'email' => 'marcus@startup.io',
            'account_type' => 'Professional',
            'since' => '2024-06-20'
        ],
        'issue' => [
            'title' => 'Billing discrepancy',
            'category' => 'Billing',
            'priority' => 'medium',
            'status' => 'open',
            'description' => 'Charged twice for the same month. Request refund for duplicate charge.',
            'created' => '2025-01-14 16:45:00',
            'updated' => '2025-01-15 09:15:00'
        ],
        'interactions' => [
            ['agent' => 'Lisa', 'time' => '2025-01-15 09:15:00', 'note' => 'Verified duplicate charge, processing refund']
        ]
    ],
    [
        'id' => 3,
        'customer' => [
            'name' => 'Emily Rodriguez',
            'email' => 'emily.r@designco.com',
            'account_type' => 'Basic',
            'since' => '2024-11-03'
        ],
        'issue' => [
            'title' => 'Feature request: Dark mode',
            'category' => 'Feature Request',
            'priority' => 'low',
            'status' => 'open',
            'description' => 'Would love to have a dark mode option for the dashboard.',
            'created' => '2025-01-10 11:20:00',
            'updated' => '2025-01-10 11:20:00'
        ],
        'interactions' => []
    ]
];

// Build analysis prompt
function buildAnalysisPrompt(array $tickets): string
{
    // Use TOON's tabular format for the structured ticket data
    // This will be extremely efficient for uniform data
    $encoded = toon_compact($tickets);

    return <<<PROMPT
You are a customer support analysis system. Analyze this support ticket data and provide:

1. **Urgency Assessment**: Which tickets need immediate attention?
2. **Pattern Analysis**: What common issues or trends do you see?
3. **Resource Allocation**: Which team (technical, billing, product) should handle each ticket?
4. **Customer Sentiment**: Based on the issues, how would you rate customer satisfaction?
5. **Action Items**: Specific next steps for the support team.

Ticket data in TOON format:
{$encoded}

Provide a clear, actionable analysis.
PROMPT;
}

echo "=== Advanced Support Ticket Analysis ===\n\n";

// Compare encoding formats
$jsonFormat = json_encode($detailedTickets, JSON_PRETTY_PRINT);
$toonFormat = Toon::encode($detailedTickets);
$toonCompact = toon_compact($detailedTickets);

echo "Encoding comparison for detailed tickets:\n";
echo "- JSON: " . strlen($jsonFormat) . " characters\n";
echo "- TOON standard: " . strlen($toonFormat) . " characters\n";
echo "- TOON compact: " . strlen($toonCompact) . " characters\n\n";

$savingsStandard = round((1 - strlen($toonFormat) / strlen($jsonFormat)) * 100, 1);
$savingsCompact = round((1 - strlen($toonCompact) / strlen($jsonFormat)) * 100, 1);

echo "- Standard TOON saves: {$savingsStandard}%\n";
echo "- Compact TOON saves: {$savingsCompact}%\n\n";

// Perform the analysis
try {
    $analysisPrompt = buildAnalysisPrompt($detailedTickets);

    echo "=== Sending to Claude for Analysis ===\n";
    echo "Prompt size: " . strlen($analysisPrompt) . " characters\n";
    echo "Estimated tokens: " . ceil(strlen($analysisPrompt) / 4) . "\n\n";

    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 800,
        'messages' => [
            MessageParam::with(
                role: 'user',
                content: $analysisPrompt
            ),
        ],
    ]);

    echo "=== Claude's Analysis ===\n\n";
    echo $response->content[0]->text."\n\n";

    // Calculate token usage
    echo "=== Token Usage ===\n";
    echo "Input tokens: {$response->usage->inputTokens}\n";
    echo "Output tokens: {$response->usage->outputTokens}\n";
    echo "Total tokens: " . ($response->usage->inputTokens + $response->usage->outputTokens) . "\n\n";

    // Calculate costs (Claude Sonnet pricing as of 2025)
    $inputCostPer1M = 3.00;  // $3 per million input tokens
    $outputCostPer1M = 15.00;  // $15 per million output tokens

    $inputCost = ($response->usage->inputTokens / 1_000_000) * $inputCostPer1M;
    $outputCost = ($response->usage->outputTokens / 1_000_000) * $outputCostPer1M;
    $totalCost = $inputCost + $outputCost;

    echo "=== Cost Analysis ===\n";
    echo "Input cost: $" . number_format($inputCost, 6) . "\n";
    echo "Output cost: $" . number_format($outputCost, 6) . "\n";
    echo "Total cost: $" . number_format($totalCost, 6) . "\n\n";

    // Project savings at scale
    $jsonTokens = ceil(strlen($jsonFormat) / 4);
    $toonTokens = ceil(strlen($toonCompact) / 4);
    $tokensSaved = $jsonTokens - $toonTokens;
    $costSavedPer = ($tokensSaved / 1_000_000) * $inputCostPer1M;

    echo "=== Projected Savings ===\n";
    echo "Tokens saved per request: {$tokensSaved}\n";
    echo "Cost saved per request: $" . number_format($costSavedPer, 6) . "\n";
    echo "Monthly savings (10,000 analyses): $" . number_format($costSavedPer * 10000, 2) . "\n";
    echo "Annual savings (120,000 analyses): $" . number_format($costSavedPer * 120000, 2) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Key Differences from OpenAI

1. **Model Names**: Claude uses different model identifiers (`claude-sonnet-4-20250514`)
2. **Message Format**: Uses `MessageParam::with()` for structured messages
3. **Token Counting**: Claude returns actual token usage in the response
4. **Pricing Structure**: Claude pricing is per million tokens, not per 1K
5. **Context Window**: Claude's 200K context window vs OpenAI's 128K (GPT-4 Turbo)

## Section 4: Best Practices for Claude

Create `claude-helper.php` for reusable patterns:

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use Anthropic\Anthropic;
use Anthropic\Resources\Messages\MessageParam;
use HelgeSverre\Toon\Toon;

/**
 * Helper class for Claude integration with TOON
 */
class ClaudeHelper
{
    private $client;
    private $defaultModel = 'claude-sonnet-4-20250514';
    private $metrics = [];

    public function __construct(string $apiKey)
    {
        $this->client = Anthropic::factory()
            ->withApiKey($apiKey)
            ->make();

        $this->metrics = [
            'total_requests' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_cost' => 0.0,
            'tokens_saved' => 0
        ];
    }

    /**
     * Send a message with TOON-encoded data
     */
    public function analyzeWithData(string $userMessage, array $data, array $options = []): array
    {
        // Encode data with TOON (compact format)
        $toonData = toon_compact($data);

        // Track savings
        $jsonSize = strlen(json_encode($data));
        $toonSize = strlen($toonData);
        $this->metrics['tokens_saved'] += ceil(($jsonSize - $toonSize) / 4);

        // Build the full message
        $fullMessage = $userMessage . "\n\nData in TOON format:\n" . $toonData;

        // Make API call
        try {
            $response = $this->client->messages()->create([
                'model' => $options['model'] ?? $this->defaultModel,
                'max_tokens' => $options['max_tokens'] ?? 1000,
                'messages' => [
                    MessageParam::with(
                        role: 'user',
                        content: $fullMessage
                    ),
                ],
            ]);

            // Update metrics
            $this->metrics['total_requests']++;
            $this->metrics['total_input_tokens'] += $response->usage->inputTokens;
            $this->metrics['total_output_tokens'] += $response->usage->outputTokens;

            // Calculate cost (Claude Sonnet pricing)
            $cost = $this->calculateCost(
                $response->usage->inputTokens,
                $response->usage->outputTokens
            );
            $this->metrics['total_cost'] += $cost;

            return [
                'success' => true,
                'content' => $response->content[0]->text,
                'usage' => [
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'total_tokens' => $response->usage->inputTokens + $response->usage->outputTokens
                ],
                'cost' => $cost,
                'model' => $response->model
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get metrics for this session
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'avg_tokens_per_request' => $this->metrics['total_requests'] > 0
                ? round(($this->metrics['total_input_tokens'] + $this->metrics['total_output_tokens']) / $this->metrics['total_requests'])
                : 0,
            'estimated_savings' => ($this->metrics['tokens_saved'] / 1_000_000) * 3.00
        ]);
    }

    private function calculateCost(int $inputTokens, int $outputTokens): float
    {
        // Claude Sonnet-4 pricing (as of 2025)
        $inputCostPer1M = 3.00;
        $outputCostPer1M = 15.00;

        return ($inputTokens / 1_000_000 * $inputCostPer1M) +
               ($outputTokens / 1_000_000 * $outputCostPer1M);
    }
}

// Example usage
echo "=== Claude Helper Demo ===\n\n";

$helper = new ClaudeHelper($_ENV['ANTHROPIC_API_KEY'] ?? 'demo-key');

// Test data
$ticketData = [
    'ticket_id' => 'TKT-001',
    'customer' => 'John Doe',
    'issue' => 'Cannot login to account',
    'priority' => 'high',
    'created_at' => '2025-01-20 10:30:00',
    'history' => [
        ['action' => 'Created', 'time' => '2025-01-20 10:30:00'],
        ['action' => 'Assigned to support', 'time' => '2025-01-20 10:35:00'],
    ]
];

$result = $helper->analyzeWithData(
    'Analyze this support ticket and suggest next steps:',
    $ticketData,
    ['max_tokens' => 300]
);

if ($result['success']) {
    echo "Analysis:\n";
    echo $result['content'] . "\n\n";

    echo "Token usage: {$result['usage']['total_tokens']}\n";
    echo "Cost: $" . number_format($result['cost'], 6) . "\n\n";

    // Show session metrics
    $metrics = $helper->getMetrics();
    echo "Session metrics:\n";
    echo "  Total requests: {$metrics['total_requests']}\n";
    echo "  Input tokens: {$metrics['total_input_tokens']}\n";
    echo "  Output tokens: {$metrics['total_output_tokens']}\n";
    echo "  Tokens saved: {$metrics['tokens_saved']}\n";
    echo "  Total cost: $" . number_format($metrics['total_cost'], 4) . "\n";
    echo "  Estimated savings: $" . number_format($metrics['estimated_savings'], 4) . "\n";
} else {
    echo "Error: {$result['error']}\n";
}
```

### Best Practices Summary

1. **When to Use Claude with TOON**:
   - Large document analysis (leveraging 200K context)
   - Batch processing of structured data
   - Multi-document comparison
   - Complex data relationships

2. **Choosing TOON Format for Claude**:
   - Use `toon_compact()` for maximum compression
   - Use `toon_tabular()` for uniform arrays
   - Claude handles TOON format exceptionally well

3. **Cost Optimization**:
   - Claude's output tokens cost 5x more than input tokens
   - Minimize output length in your prompt
   - Batch multiple analyses when possible
   - Monitor actual token usage

4. **Context Window Strategy**:
   - With TOON, you can fit ~40-60% more data
   - Use this for richer context, not just more records
   - Consider chunking only when necessary

## Section 5: Troubleshooting

### Common Issues and Solutions

#### 1. API Key Errors

**Problem**: Authentication failures

**Solution**:
```php
// Verify API key format
if (!str_starts_with($_ENV['ANTHROPIC_API_KEY'], 'sk-ant-')) {
    echo "Warning: Anthropic API keys should start with 'sk-ant-'\n";
}
```

#### 2. Token Count Differences

**Problem**: Token estimates don't match Claude's counts

**Solution**:
- Claude uses its own tokenizer (different from OpenAI's)
- Always use actual usage from the API response
- The 4-char estimate is approximate
- Claude returns exact counts in `usage` field

#### 3. Rate Limiting

**Problem**: Too many requests

**Solution**:
- Implement exponential backoff
- Batch requests where possible
- Monitor rate limit headers

#### 4. Context Window Management

**Problem**: Still hitting limits even with TOON

**Solution**:
- Preprocess data before encoding
- Remove unnecessary fields
- Summarize verbose content
- Use chunking for extremely large datasets

## Next Steps

Congratulations! You've successfully integrated TOON with Claude and learned how to:
- Leverage Claude's large context window efficiently
- Measure real token savings with Anthropic's API
- Build production-ready analysis systems
- Calculate cost savings specific to Claude

### Where to Go From Here

1. **Combine with RAG**: Use TOON in retrieval-augmented generation pipelines
2. **Multi-Document Analysis**: Leverage Claude's context for document comparison
3. **Streaming Responses**: Implement streaming with TOON-encoded context
4. **Caching**: Build context caching strategies with TOON

### Key Takeaways

- **TOON reduces tokens by 40-60%** with Claude, just like with OpenAI
- **Claude's 200K context** becomes even more powerful with TOON compression
- **Different pricing model**: Claude charges per million tokens, not per 1K
- **Actual token counts**: Claude returns exact usage, no estimation needed
- **Output costs more**: Claude's output tokens cost 5x input tokens

### Additional Resources

- [Anthropic Documentation](https://docs.anthropic.com/)
- [Claude API Reference](https://docs.anthropic.com/claude/reference)
- [Claude Pricing](https://www.anthropic.com/pricing)
- [TOON Format Specification](https://github.com/toon-format/spec)

---

*Remember: Claude's massive context window combined with TOON's compression lets you include much richer context in your prompts. Use this to your advantage for more accurate, context-aware responses!*
