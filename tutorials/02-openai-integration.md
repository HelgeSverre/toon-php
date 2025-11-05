# Tutorial 2: Integrating TOON with OpenAI PHP Client

**Difficulty**: Intermediate
**Time to Complete**: 15-20 minutes
**PHP Version**: 8.2+ (required by openai-php/client)

## What You'll Build

In this tutorial, you'll create a working integration between TOON and the OpenAI PHP client for two realistic scenarios:

1. **Email Data Extraction**: Process support emails to extract sentiment, categorize issues, and determine urgency
2. **Invoice Processing**: Validate invoices from OCR/PDF extraction and identify errors

You'll see exactly how TOON reduces token consumption and learn to measure actual cost savings.

## Learning Objectives

By the end of this tutorial, you will:
- Install and configure the official openai-php/client package
- Format complex data structures with TOON for LLM context
- Measure actual token savings between JSON and TOON
- Handle OpenAI API responses properly
- Calculate real cost savings for production use cases

## Prerequisites

- Completed Tutorial 1 (Getting Started with TOON)
- PHP 8.2 or higher
- Composer installed
- OpenAI API key (free tier is sufficient)
- Basic understanding of API requests

---

## Introduction

TOON is a data encoding format that reduces token consumption when working with Large Language Models. It achieves this through:
- Removing redundant syntax (braces, quotes, brackets)
- Using indentation-based nesting
- Employing compact tabular formats for uniform data
- Including explicit array lengths and field declarations

**Important**: TOON is just an encoder - it converts PHP arrays to a compact string format. The integration with OpenAI is something you build using standard API calls. This tutorial shows you exactly how to combine these tools effectively.

When you send structured data to OpenAI's API, you're charged by the number of tokens. By encoding your data with TOON instead of JSON, you can reduce costs by 30-60% while maintaining the same functionality.

Let's see how this works in practice.

## Section 1: Setup and Installation

First, let's set up a new project and install the required dependencies.

### Step 1: Create Project Directory

```bash
mkdir toon-openai-integration
cd toon-openai-integration
```

### Step 2: Install Required Packages

```bash
composer require helgesverre/toon
composer require openai-php/client
composer require vlucas/phpdotenv
```

The `openai-php/client` is the official PHP client for OpenAI's API. The `phpdotenv` package helps manage environment variables securely.

### Step 3: Configure Environment

Create a `.env` file to store your OpenAI API key:

```bash
# .env
OPENAI_API_KEY=sk-your-actual-api-key-here
```

### Step 4: Create Bootstrap File

Create `bootstrap.php` to set up the environment:

```php
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate API key exists
if (!isset($_ENV['OPENAI_API_KEY']) || $_ENV['OPENAI_API_KEY'] === 'sk-your-actual-api-key-here') {
    die("Error: Please set a valid OPENAI_API_KEY in your .env file\n");
}

echo "Environment configured successfully!\n";
```

Test the setup:

```bash
php bootstrap.php
```

You should see "Environment configured successfully!"

## Section 2: Basic Example - User Profile Processing

Before diving into complex examples, let's start with a simple user profile example to understand the basics of TOON + OpenAI integration.

### Creating Your First Integration

Create a file called `basic-example.php`:

```php
<?php
declare(strict_types=1);

require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;
use OpenAI\Client;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize OpenAI client
$apiKey = $_ENV['OPENAI_API_KEY'] ?? 'your-api-key-here';
$client = OpenAI::client($apiKey);

// Example: User profile data
$userData = [
    'id' => 12345,
    'name' => 'Alice Johnson',
    'email' => 'alice@example.com',
    'preferences' => [
        'language' => 'en',
        'timezone' => 'America/New_York',
        'notifications' => true,
    ],
    'subscription' => [
        'plan' => 'premium',
        'status' => 'active',
        'expires_at' => '2025-12-31',
    ],
];

// Encode with TOON (compact format)
$toonData = Toon::encode($userData);

echo "=== TOON Encoding Demo ===\n\n";
echo "Original Data (JSON):\n";
echo json_encode($userData, JSON_PRETTY_PRINT)."\n\n";
echo "TOON Encoded:\n";
echo $toonData."\n\n";

// Token comparison
$stats = toon_compare($userData);
echo "Token Comparison:\n";
echo "- JSON: {$stats['json']} characters\n";
echo "- TOON: {$stats['toon']} characters\n";
echo "- Savings: {$stats['savings']} characters ({$stats['savings_percent']})\n\n";

// Send to OpenAI with TOON-encoded context
$response = $client->chat()->create([
    'model' => 'gpt-4o-mini',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant. User data is provided in TOON format (a compact, readable format).',
        ],
        [
            'role' => 'user',
            'content' => "Here is the user data:\n\n{$toonData}\n\nGenerate a personalized welcome message for this user.",
        ],
    ],
    'max_tokens' => 150,
]);

echo "=== OpenAI Response ===\n\n";
echo $response->choices[0]->message->content."\n";
```

### Understanding the Basic Example

This example demonstrates the fundamental pattern for all TOON + OpenAI integrations:

1. **Prepare your data** - Create a PHP array with the information you need to send
2. **Encode with TOON** - Use `Toon::encode()` to convert it to a compact format
3. **Compare savings** - Use `toon_compare()` helper to see the token reduction
4. **Send to OpenAI** - Include the TOON-encoded data in your message content
5. **Process the response** - Handle OpenAI's response as normal

**Key Observations**:
- The system prompt explains what TOON format is so the model understands it
- We use `gpt-4o-mini` for cost efficiency in this basic example
- The TOON-encoded data is readable by both humans and LLMs
- We see immediate token savings even with small data structures

### Running the Basic Example

```bash
php basic-example.php
```

Expected output shows:
- The original JSON encoding
- The TOON encoding (significantly shorter)
- Character/token savings percentage
- A personalized response from OpenAI based on the user data

This basic pattern forms the foundation for all the more complex examples that follow.

## Section 3: Example 1 - Email Classification

Let's build our first real-world example: processing support emails to extract key information and classify them for routing.

### The Scenario

Your company receives hundreds of support emails daily. You need to:
- Extract customer sentiment
- Identify the issue category
- Determine urgency level
- Route to the appropriate team

### Step 1: Create the Email Processor

Create a file called `email-processor.php`:

```php
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use OpenAI;
use HelgeSverre\Toon\Toon;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize OpenAI client
$client = OpenAI::client($_ENV['OPENAI_API_KEY']);

// Sample email data structure
$email = [
    'from' => [
        'name' => 'John Customer',
        'email' => 'john@example.com'
    ],
    'to' => 'support@company.com',
    'subject' => 'Urgent: Cannot access my account',
    'date' => '2025-01-20 10:30:00',
    'body' => "I have been trying to log into my account for the past hour but keep getting an error message saying 'Invalid credentials'. I'm certain my password is correct. I have an important presentation in 2 hours and desperately need access to my files. This is affecting my business operations. Please help immediately!",
    'headers' => [
        'received' => '2025-01-20 10:30:15',
        'message_id' => '<abc123@example.com>',
        'reply_to' => 'john.customer@example.com',
        'priority' => 'high'
    ],
    'attachments' => [
        ['filename' => 'screenshot.png', 'size' => 45823],
        ['filename' => 'error_log.txt', 'size' => 2341]
    ],
    'customer_info' => [
        'account_type' => 'premium',
        'customer_since' => '2021-03-15',
        'support_tier' => 'gold'
    ]
];

// Step 2: Compare JSON vs TOON encoding
echo "=== Encoding Comparison ===\n\n";

$jsonEncoded = json_encode($email, JSON_PRETTY_PRINT);
$toonEncoded = Toon::encode($email);

echo "JSON encoding (" . strlen($jsonEncoded) . " characters):\n";
echo substr($jsonEncoded, 0, 300) . "...\n\n";

echo "TOON encoding (" . strlen($toonEncoded) . " characters):\n";
echo $toonEncoded . "\n\n";

$reduction = round((1 - strlen($toonEncoded) / strlen($jsonEncoded)) * 100, 1);
echo "Character reduction: {$reduction}%\n\n";

// Step 3: Build the analysis prompt
function formatEmailForLLM(array $emailData): string {
    // Use TOON to encode the email data compactly
    $encoded = toon_compact($emailData);

    return "Analyze this support email and extract the following information:
- Customer sentiment (positive/neutral/negative/urgent)
- Primary issue category (login/billing/technical/feature_request/other)
- Urgency level (low/medium/high/critical)
- Suggested team routing (technical_support/billing/customer_success/engineering)
- Key problems mentioned
- Recommended actions

Email data in TOON format:
" . $encoded;
}

// Step 4: Make the API call
echo "=== Sending to OpenAI API ===\n\n";

$prompt = formatEmailForLLM($email);
echo "Prompt length: " . strlen($prompt) . " characters\n";
echo "Estimated tokens: " . ceil(strlen($prompt) / 4) . "\n\n";

try {
    $response = $client->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a support ticket analyzer. You receive email data in TOON format (a compact notation where objects use key:value pairs, arrays show [length]: items, and indentation indicates nesting). Extract key information and provide actionable insights.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.3,  // Lower temperature for consistent analysis
        'max_tokens' => 500
    ]);

    // Step 5: Process the response
    echo "=== AI Analysis ===\n";
    echo $response->choices[0]->message->content . "\n\n";

    // Step 6: Extract token usage
    echo "=== Token Usage ===\n";
    $promptTokens = $response->usage->promptTokens;
    $completionTokens = $response->usage->completionTokens;
    $totalTokens = $response->usage->totalTokens;

    echo "Prompt tokens: {$promptTokens}\n";
    echo "Completion tokens: {$completionTokens}\n";
    echo "Total tokens: {$totalTokens}\n\n";

    // Step 7: Calculate costs
    echo "=== Cost Analysis ===\n";

    // GPT-3.5-turbo pricing (as of 2024)
    $inputCostPer1k = 0.0005;
    $outputCostPer1k = 0.0015;

    $inputCost = ($promptTokens / 1000) * $inputCostPer1k;
    $outputCost = ($completionTokens / 1000) * $outputCostPer1k;
    $totalCost = $inputCost + $outputCost;

    echo "Input cost: $" . number_format($inputCost, 5) . "\n";
    echo "Output cost: $" . number_format($outputCost, 5) . "\n";
    echo "Total cost: $" . number_format($totalCost, 5) . "\n\n";

    // Compare with JSON equivalent
    $jsonTokenEstimate = ceil(strlen($jsonEncoded) / 4);
    $toonTokenEstimate = ceil(strlen($toonEncoded) / 4);
    $tokensSaved = $jsonTokenEstimate - $toonTokenEstimate;

    echo "=== TOON vs JSON Comparison ===\n";
    echo "Estimated JSON tokens: {$jsonTokenEstimate}\n";
    echo "Estimated TOON tokens: {$toonTokenEstimate}\n";
    echo "Tokens saved: {$tokensSaved}\n";
    echo "Cost saved per request: $" . number_format(($tokensSaved / 1000) * $inputCostPer1k, 5) . "\n";

    // Project savings at scale
    echo "\n=== Projected Savings (Email Processing) ===\n";
    $savedPerRequest = ($tokensSaved / 1000) * $inputCostPer1k;
    echo "Per 1,000 emails: $" . number_format($savedPerRequest * 1000, 2) . "\n";
    echo "Per 10,000 emails: $" . number_format($savedPerRequest * 10000, 2) . "\n";
    echo "Per 100,000 emails: $" . number_format($savedPerRequest * 100000, 2) . "\n";

} catch (\Exception $e) {
    echo "Error calling OpenAI API: " . $e->getMessage() . "\n";
    echo "\nMake sure your API key is valid and you have credits available.\n";
}
```

### Understanding the Code

This example demonstrates several key concepts:

1. **Data Structure**: We're working with a realistic email object containing nested data
2. **TOON Encoding**: The `Toon::encode()` function converts the PHP array to compact format
3. **Token Estimation**: We estimate tokens as roughly 1 token per 4 characters
4. **Cost Calculation**: We use actual OpenAI pricing to show real savings
5. **Error Handling**: Proper try-catch blocks for API failures

## Section 4: Example 2 - Invoice Validation

Now let's build a more complex example: validating invoices extracted from PDFs or OCR systems.

### The Scenario

Your accounting system processes thousands of invoices monthly. You need to:
- Validate mathematical calculations
- Check for missing required fields
- Identify unusual patterns or potential fraud
- Flag discrepancies for human review

### Create the Invoice Validator

Create `invoice-validator.php`:

```php
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use OpenAI;
use HelgeSverre\Toon\Toon;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize OpenAI client
$client = OpenAI::client($_ENV['OPENAI_API_KEY']);

// Sample invoice data (as might come from OCR/PDF extraction)
$invoice = [
    'invoice_number' => 'INV-2025-0234',
    'date' => '2025-01-15',
    'due_date' => '2025-02-15',
    'vendor' => [
        'name' => 'Office Supplies Co',
        'address' => '456 Business Park, Suite 200',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip' => '94105',
        'tax_id' => '12-3456789'
    ],
    'client' => [
        'name' => 'Tech Startup Inc',
        'billing_address' => '789 Innovation Drive',
        'city' => 'Palo Alto',
        'state' => 'CA',
        'zip' => '94301',
        'po_number' => 'PO-2025-8923'
    ],
    'line_items' => [
        [
            'description' => 'Printer Paper (Box of 10 reams)',
            'quantity' => 5,
            'unit_price' => 24.99,
            'total' => 124.95
        ],
        [
            'description' => 'Black Ink Cartridges (HP 962XL)',
            'quantity' => 3,
            'unit_price' => 45.00,
            'total' => 135.00
        ],
        [
            'description' => 'Manila File Folders (Box of 100)',
            'quantity' => 10,
            'unit_price' => 8.50,
            'total' => 85.00
        ],
        [
            'description' => 'Wireless Mouse',
            'quantity' => 4,
            'unit_price' => 29.99,
            'total' => 119.96
        ],
        [
            'description' => 'USB-C Cables (6ft)',
            'quantity' => 12,
            'unit_price' => 12.99,
            'total' => 155.88
        ]
    ],
    'subtotal' => 620.79,
    'tax_rate' => 0.0875,  // 8.75% CA sales tax
    'tax_amount' => 54.32,
    'shipping' => 15.00,
    'total' => 690.11,
    'payment_terms' => 'Net 30',
    'notes' => 'Please reference PO number on payment'
];

// Function to build validation prompt
function buildInvoiceValidationPrompt(array $invoice): string {
    // Use TOON's tabular format for line items - perfect for uniform data
    $encoded = toon_tabular($invoice);

    return "Validate this invoice for accuracy and completeness. Check for:

1. Mathematical errors in calculations (line items, tax, totals)
2. Missing required fields
3. Unusual patterns or anomalies
4. Data consistency issues
5. Potential red flags

Provide your analysis in the following format:
- Calculation Verification: [PASS/FAIL with details]
- Required Fields: [COMPLETE/INCOMPLETE with missing items]
- Anomalies Detected: [List any unusual patterns]
- Risk Assessment: [LOW/MEDIUM/HIGH with reasoning]
- Recommendations: [Specific actions to take]

Invoice data in TOON format:
" . $encoded;
}

echo "=== Invoice Validation System ===\n\n";

// Compare encoding formats
$jsonFormat = json_encode($invoice, JSON_PRETTY_PRINT);
$toonFormat = Toon::encode($invoice);

// Also try compact format specifically optimized for structured data
$toonCompact = toon_compact($invoice);  // Using helper function

echo "Encoding comparison:\n";
echo "- JSON: " . strlen($jsonFormat) . " characters\n";
echo "- TOON standard: " . strlen($toonFormat) . " characters\n";
echo "- TOON compact: " . strlen($toonCompact) . " characters\n";

$savingsStandard = round((1 - strlen($toonFormat) / strlen($jsonFormat)) * 100, 1);
$savingsCompact = round((1 - strlen($toonCompact) / strlen($jsonFormat)) * 100, 1);

echo "- Standard TOON saves: {$savingsStandard}%\n";
echo "- Compact TOON saves: {$savingsCompact}%\n\n";

// Show a portion of the TOON encoded invoice
echo "TOON encoded invoice (first 400 chars):\n";
echo substr($toonFormat, 0, 400) . "...\n\n";

// Make the API call for validation
try {
    $validationPrompt = buildInvoiceValidationPrompt($invoice);

    echo "=== Sending to OpenAI for Validation ===\n";
    echo "Prompt size: " . strlen($validationPrompt) . " characters\n\n";

    $response = $client->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert invoice auditor. You receive invoice data in TOON format - a compact notation where:
- Objects use "key: value" pairs
- Arrays show "[count]: item1,item2"
- Nested structures use indentation
- Tables use "[rows]{fields}: values"

Perform thorough validation and identify any issues.'
            ],
            [
                'role' => 'user',
                'content' => $validationPrompt
            ]
        ],
        'temperature' => 0.2,  // Low temperature for consistent validation
        'max_tokens' => 800
    ]);

    echo "=== Validation Results ===\n";
    echo $response->choices[0]->message->content . "\n\n";

    // Calculate token usage and costs
    $tokensUsed = $response->usage->totalTokens;
    $cost = ($response->usage->promptTokens / 1000 * 0.0005) +
            ($response->usage->completionTokens / 1000 * 0.0015);

    echo "=== Performance Metrics ===\n";
    echo "Tokens used: {$tokensUsed}\n";
    echo "Cost: $" . number_format($cost, 5) . "\n\n";

    // Calculate savings for batch processing
    $jsonTokens = ceil(strlen($jsonFormat) / 4);
    $toonTokens = ceil(strlen($toonFormat) / 4);
    $tokensSaved = $jsonTokens - $toonTokens;
    $costSavedPer = ($tokensSaved / 1000) * 0.0005;

    echo "=== Batch Processing Projections ===\n";
    echo "For invoice validation at scale:\n";
    echo "- Tokens saved per invoice: {$tokensSaved}\n";
    echo "- Cost saved per invoice: $" . number_format($costSavedPer, 5) . "\n";
    echo "- Monthly savings (1,000 invoices): $" . number_format($costSavedPer * 1000, 2) . "\n";
    echo "- Monthly savings (10,000 invoices): $" . number_format($costSavedPer * 10000, 2) . "\n";
    echo "- Annual savings (120,000 invoices): $" . number_format($costSavedPer * 120000, 2) . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Demonstrate batch processing simulation
echo "\n=== Batch Processing Simulation ===\n";

function simulateBatchProcessing(int $count): array {
    $results = [
        'total_json_chars' => 0,
        'total_toon_chars' => 0,
        'total_json_tokens' => 0,
        'total_toon_tokens' => 0
    ];

    for ($i = 0; $i < $count; $i++) {
        // Generate variations of invoice data
        $batchInvoice = [
            'invoice_number' => 'INV-2025-' . str_pad((string)($i + 1000), 4, '0', STR_PAD_LEFT),
            'date' => date('Y-m-d', strtotime("-$i days")),
            'vendor' => ['name' => 'Vendor ' . $i, 'tax_id' => '99-' . rand(1000000, 9999999)],
            'line_items' => []
        ];

        // Add random number of line items
        $itemCount = rand(3, 10);
        for ($j = 0; $j < $itemCount; $j++) {
            $qty = rand(1, 20);
            $price = rand(10, 200) + (rand(0, 99) / 100);
            $batchInvoice['line_items'][] = [
                'description' => 'Item ' . ($j + 1),
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => $qty * $price
            ];
        }

        $jsonSize = strlen(json_encode($batchInvoice));
        $toonSize = strlen(Toon::encode($batchInvoice));

        $results['total_json_chars'] += $jsonSize;
        $results['total_toon_chars'] += $toonSize;
        $results['total_json_tokens'] += ceil($jsonSize / 4);
        $results['total_toon_tokens'] += ceil($toonSize / 4);
    }

    return $results;
}

$batchResults = simulateBatchProcessing(100);

echo "Results for 100 invoices:\n";
echo "- Total JSON characters: " . number_format($batchResults['total_json_chars']) . "\n";
echo "- Total TOON characters: " . number_format($batchResults['total_toon_chars']) . "\n";
echo "- Character reduction: " . round((1 - $batchResults['total_toon_chars'] / $batchResults['total_json_chars']) * 100, 1) . "%\n";
echo "- Estimated token savings: " . number_format($batchResults['total_json_tokens'] - $batchResults['total_toon_tokens']) . "\n";

$batchCostSaved = (($batchResults['total_json_tokens'] - $batchResults['total_toon_tokens']) / 1000) * 0.0005;
echo "- Cost saved on batch: $" . number_format($batchCostSaved, 2) . "\n";
```

## Section 5: Measuring Token Savings

Understanding and measuring token savings is crucial for calculating ROI. Let's create a utility to help with this.

Create `token-analysis.php`:

```php
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;

/**
 * Compare token usage between JSON and TOON encodings
 */
function compareTokenUsage(array $data): array {
    // Use the built-in comparison helper
    $comparison = toon_compare($data);

    return [
        'json' => [
            'size' => $comparison['json'],
            'estimated_tokens' => (int)ceil($comparison['json'] / 4),
            'readable' => number_format($comparison['json']) . ' chars'
        ],
        'toon' => [
            'size' => $comparison['toon'],
            'estimated_tokens' => (int)ceil($comparison['toon'] / 4),
            'readable' => number_format($comparison['toon']) . ' chars'
        ],
        'savings' => [
            'characters' => $comparison['savings'],
            'tokens' => (int)ceil($comparison['savings'] / 4),
            'percentage' => $comparison['savings_percent']
        ]
    ];
}

/**
 * Calculate cost savings based on token reduction
 */
function calculateCostSavings(int $tokensSaved, string $model = 'gpt-3.5-turbo'): array {
    // Current OpenAI pricing (as of 2024)
    $pricing = [
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],  // per 1K tokens
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03]
    ];

    $rate = $pricing[$model]['input'] ?? $pricing['gpt-3.5-turbo']['input'];
    $costSavedPer = ($tokensSaved / 1000) * $rate;

    return [
        'per_request' => $costSavedPer,
        'per_1k_requests' => $costSavedPer * 1000,
        'per_10k_requests' => $costSavedPer * 10000,
        'per_100k_requests' => $costSavedPer * 100000,
        'monthly_10k' => $costSavedPer * 10000 * 30,  // Assuming 10k requests/day
        'annual_10k' => $costSavedPer * 10000 * 365
    ];
}

// Test with different data structures
echo "=== Token Usage Analysis ===\n\n";

// Example 1: User profile data
$userProfile = [
    'user_id' => 'USR-2025-4821',
    'username' => 'tech_enthusiast',
    'email' => 'user@example.com',
    'profile' => [
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'bio' => 'Software developer with 10 years of experience in web technologies.',
        'location' => 'San Francisco, CA',
        'joined_date' => '2020-03-15'
    ],
    'preferences' => [
        'theme' => 'dark',
        'notifications' => true,
        'language' => 'en-US',
        'timezone' => 'America/Los_Angeles'
    ],
    'activity' => [
        'last_login' => '2025-01-20 09:45:00',
        'posts_count' => 234,
        'followers' => 1523,
        'following' => 487
    ]
];

echo "1. User Profile Data:\n";
$profileComparison = compareTokenUsage($userProfile);
echo "   JSON: {$profileComparison['json']['readable']} (~{$profileComparison['json']['estimated_tokens']} tokens)\n";
echo "   TOON: {$profileComparison['toon']['readable']} (~{$profileComparison['toon']['estimated_tokens']} tokens)\n";
echo "   Savings: {$profileComparison['savings']['percentage']}% ({$profileComparison['savings']['tokens']} tokens)\n\n";

// Example 2: E-commerce order
$order = [
    'order_id' => 'ORD-2025-98234',
    'customer' => [
        'id' => 'CUST-4521',
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ],
    'items' => [
        ['sku' => 'LAPTOP-001', 'name' => 'ThinkPad X1', 'qty' => 1, 'price' => 1899.99],
        ['sku' => 'MOUSE-002', 'name' => 'Wireless Mouse', 'qty' => 2, 'price' => 29.99],
        ['sku' => 'CABLE-003', 'name' => 'USB-C Cable', 'qty' => 3, 'price' => 19.99]
    ],
    'shipping' => [
        'method' => 'express',
        'address' => '123 Main St, Anytown, CA 94105',
        'cost' => 25.99
    ],
    'totals' => [
        'subtotal' => 2019.93,
        'tax' => 176.74,
        'shipping' => 25.99,
        'total' => 2222.66
    ]
];

echo "2. E-commerce Order:\n";
$orderComparison = compareTokenUsage($order);
echo "   JSON: {$orderComparison['json']['readable']} (~{$orderComparison['json']['estimated_tokens']} tokens)\n";
echo "   TOON: {$orderComparison['toon']['readable']} (~{$orderComparison['toon']['estimated_tokens']} tokens)\n";
echo "   Savings: {$orderComparison['savings']['percentage']}% ({$orderComparison['savings']['tokens']} tokens)\n\n";

// Example 3: Analytics data (larger dataset)
$analytics = [
    'period' => '2025-01',
    'metrics' => [
        'visitors' => 125847,
        'page_views' => 458921,
        'unique_visitors' => 89234,
        'bounce_rate' => 42.3,
        'avg_session_duration' => 186,
        'pages_per_session' => 3.64
    ],
    'top_pages' => [],
    'traffic_sources' => [],
    'conversions' => [
        'total' => 3847,
        'rate' => 3.06,
        'value' => 284739.50
    ]
];

// Add 20 top pages
for ($i = 1; $i <= 20; $i++) {
    $analytics['top_pages'][] = [
        'url' => '/page-' . $i,
        'views' => rand(5000, 50000),
        'avg_time' => rand(30, 300),
        'bounce_rate' => rand(20, 60) + (rand(0, 99) / 100)
    ];
}

// Add traffic sources
$sources = ['organic', 'direct', 'social', 'paid', 'email', 'referral'];
foreach ($sources as $source) {
    $analytics['traffic_sources'][] = [
        'source' => $source,
        'sessions' => rand(10000, 50000),
        'conversion_rate' => rand(1, 5) + (rand(0, 99) / 100)
    ];
}

echo "3. Analytics Dashboard (larger dataset):\n";
$analyticsComparison = compareTokenUsage($analytics);
echo "   JSON: {$analyticsComparison['json']['readable']} (~{$analyticsComparison['json']['estimated_tokens']} tokens)\n";
echo "   TOON: {$analyticsComparison['toon']['readable']} (~{$analyticsComparison['toon']['estimated_tokens']} tokens)\n";
echo "   Savings: {$analyticsComparison['savings']['percentage']}% ({$analyticsComparison['savings']['tokens']} tokens)\n\n";

// Calculate cost savings
echo "=== Cost Savings Analysis ===\n\n";

$models = ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo'];

foreach ($models as $model) {
    echo "Model: {$model}\n";

    $savings = calculateCostSavings($analyticsComparison['savings']['tokens'], $model);

    echo "  Per request: $" . number_format($savings['per_request'], 5) . "\n";
    echo "  Per 1K requests: $" . number_format($savings['per_1k_requests'], 2) . "\n";
    echo "  Per 10K requests: $" . number_format($savings['per_10k_requests'], 2) . "\n";
    echo "  Monthly (10K/day): $" . number_format($savings['monthly_10k'], 2) . "\n";
    echo "  Annual (10K/day): $" . number_format($savings['annual_10k'], 2) . "\n\n";
}

// Show cumulative savings
echo "=== Cumulative Savings Example ===\n\n";
echo "If you process these three data types regularly:\n\n";

$totalTokensSaved = $profileComparison['savings']['tokens'] +
                    $orderComparison['savings']['tokens'] +
                    $analyticsComparison['savings']['tokens'];

echo "Total tokens saved per batch: {$totalTokensSaved}\n";

$batchSavings = calculateCostSavings($totalTokensSaved, 'gpt-3.5-turbo');
echo "Processing 1,000 of each daily (3,000 total requests):\n";
echo "  Daily savings: $" . number_format($batchSavings['per_request'] * 3000, 2) . "\n";
echo "  Monthly savings: $" . number_format($batchSavings['per_request'] * 3000 * 30, 2) . "\n";
echo "  Annual savings: $" . number_format($batchSavings['per_request'] * 3000 * 365, 2) . "\n";
```

## Section 6: Best Practices

Let's create a helper class that encapsulates best practices for using TOON with OpenAI.

Create `openai-helper.php`:

```php
<?php
declare(strict_types=1);

require 'vendor/autoload.php';

use OpenAI;
use HelgeSverre\Toon\Toon;

/**
 * Helper class for OpenAI integration with TOON
 */
class OpenAIHelper {
    private $client;
    private $defaultModel = 'gpt-3.5-turbo';
    private $metrics = [];

    public function __construct(string $apiKey) {
        $this->client = OpenAI::client($apiKey);
        $this->metrics = [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'tokens_saved' => 0
        ];
    }

    /**
     * Send a chat request with TOON-encoded data
     */
    public function chatWithData(string $systemPrompt, string $userMessage, array $data, array $options = []): array {
        // Encode data with TOON
        $toonData = Toon::encode($data);

        // Track savings
        $jsonSize = strlen(json_encode($data));
        $toonSize = strlen($toonData);
        $this->metrics['tokens_saved'] += ceil(($jsonSize - $toonSize) / 4);

        // Build messages
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt . "\n\nNote: Data is provided in TOON format for efficiency."
            ],
            [
                'role' => 'user',
                'content' => $userMessage . "\n\nData:\n" . $toonData
            ]
        ];

        // Make API call
        try {
            $response = $this->client->chat()->create(array_merge([
                'model' => $options['model'] ?? $this->defaultModel,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 1000
            ], $options));

            // Update metrics
            $this->metrics['total_requests']++;
            $this->metrics['total_tokens'] += $response->usage->totalTokens;

            // Calculate cost
            $cost = $this->calculateCost(
                $options['model'] ?? $this->defaultModel,
                $response->usage->promptTokens,
                $response->usage->completionTokens
            );
            $this->metrics['total_cost'] += $cost;

            return [
                'success' => true,
                'content' => $response->choices[0]->message->content,
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens,
                    'completion_tokens' => $response->usage->completionTokens,
                    'total_tokens' => $response->usage->totalTokens
                ],
                'cost' => $cost,
                'model' => $response->model
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Choose the best TOON format for your data
     */
    public function optimizeEncoding(array $data): array {
        $formats = [
            'standard' => Toon::encode($data),
            'compact' => toon_compact($data),
            'tabular' => toon_tabular($data)
        ];

        $best = 'standard';
        $minSize = strlen($formats['standard']);

        foreach ($formats as $name => $encoded) {
            $size = strlen($encoded);
            if ($size < $minSize) {
                $minSize = $size;
                $best = $name;
            }
        }

        return [
            'best_format' => $best,
            'encoded' => $formats[$best],
            'size' => $minSize,
            'all_formats' => array_map('strlen', $formats)
        ];
    }

    /**
     * Get metrics for this session
     */
    public function getMetrics(): array {
        return array_merge($this->metrics, [
            'avg_tokens_per_request' => $this->metrics['total_requests'] > 0
                ? round($this->metrics['total_tokens'] / $this->metrics['total_requests'])
                : 0,
            'estimated_savings' => ($this->metrics['tokens_saved'] / 1000) * 0.0005
        ]);
    }

    private function calculateCost(string $model, int $inputTokens, int $outputTokens): float {
        $pricing = [
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03]
        ];

        $rates = $pricing[$model] ?? $pricing['gpt-3.5-turbo'];

        return ($inputTokens / 1000 * $rates['input']) +
               ($outputTokens / 1000 * $rates['output']);
    }
}

// Example usage
echo "=== OpenAI Helper Demo ===\n\n";

// Initialize helper
$helper = new OpenAIHelper($_ENV['OPENAI_API_KEY'] ?? 'demo-key');

// Test data optimization
$testData = [
    'products' => [
        ['id' => 1, 'name' => 'Laptop', 'price' => 999.99, 'stock' => 15],
        ['id' => 2, 'name' => 'Mouse', 'price' => 29.99, 'stock' => 50],
        ['id' => 3, 'name' => 'Keyboard', 'price' => 79.99, 'stock' => 32],
        ['id' => 4, 'name' => 'Monitor', 'price' => 299.99, 'stock' => 8],
        ['id' => 5, 'name' => 'Webcam', 'price' => 89.99, 'stock' => 22]
    ]
];

echo "Testing encoding optimization:\n";
$optimal = $helper->optimizeEncoding($testData);
echo "Best format: {$optimal['best_format']} ({$optimal['size']} chars)\n";
echo "All formats:\n";
foreach ($optimal['all_formats'] as $format => $size) {
    echo "  - {$format}: {$size} chars\n";
}
echo "\n";

// Simulate API usage (comment out if no API key)
/*
$result = $helper->chatWithData(
    'You are a inventory analyst.',
    'Analyze this product inventory and identify items that need restocking (stock < 20).',
    $testData,
    ['temperature' => 0.3, 'max_tokens' => 200]
);

if ($result['success']) {
    echo "Analysis result:\n";
    echo $result['content'] . "\n\n";

    echo "Token usage: {$result['usage']['total_tokens']}\n";
    echo "Cost: $" . number_format($result['cost'], 5) . "\n\n";

    // Show session metrics
    $metrics = $helper->getMetrics();
    echo "Session metrics:\n";
    echo "  Total requests: {$metrics['total_requests']}\n";
    echo "  Total tokens: {$metrics['total_tokens']}\n";
    echo "  Tokens saved: {$metrics['tokens_saved']}\n";
    echo "  Total cost: $" . number_format($metrics['total_cost'], 4) . "\n";
    echo "  Estimated savings: $" . number_format($metrics['estimated_savings'], 4) . "\n";
} else {
    echo "Error: {$result['error']}\n";
}
*/
```

### Best Practices Summary

1. **When to Use TOON**:
   - Large structured data (invoices, orders, analytics)
   - Repeated API calls with similar data structures
   - Cost-sensitive applications
   - High-volume batch processing

2. **Choosing the Right TOON Format**:
   - Use `toon_compact()` for maximum compression
   - Use `toon_tabular()` for uniform arrays (like line items)
   - Use `toon_readable()` when debugging

3. **Error Handling**:
   - Always wrap API calls in try-catch blocks
   - Implement retry logic for transient failures
   - Log errors for debugging

4. **Performance Optimization**:
   - Cache encoded data when processing the same data multiple times
   - Batch similar requests together
   - Monitor token usage and costs

## Section 7: Troubleshooting

### Common Issues and Solutions

#### 1. API Key Errors

**Problem**: "Invalid API key" or authentication errors

**Solution**:
```php
// Verify your API key format
if (!preg_match('/^sk-[a-zA-Z0-9]{48}$/', $_ENV['OPENAI_API_KEY'])) {
    echo "Warning: API key format appears invalid\n";
}

// Test the API key with a minimal request
try {
    $client = OpenAI::client($_ENV['OPENAI_API_KEY']);
    $response = $client->models()->list();
    echo "API key is valid!\n";
} catch (\Exception $e) {
    echo "API key error: " . $e->getMessage() . "\n";
}
```

#### 2. Token Count Estimation Inaccuracy

**Problem**: Estimated tokens don't match actual API usage

**Solution**:
```php
// More accurate token estimation
function estimateTokens(string $text): int {
    // OpenAI's rule of thumb: ~1 token per 4 characters for English
    // Adjust for TOON's compact format
    $baseEstimate = ceil(strlen($text) / 4);

    // TOON uses less punctuation, adjust down slightly
    return (int)($baseEstimate * 0.95);
}

// For exact counts, use the tiktoken library (requires Python)
// Or use OpenAI's tokenizer: https://platform.openai.com/tokenizer
```

#### 3. Rate Limiting

**Problem**: "Rate limit exceeded" errors

**Solution**:
```php
// Implement exponential backoff
function callWithRetry($client, array $params, int $maxAttempts = 3): ?array {
    $attempt = 0;
    $delay = 1; // Start with 1 second

    while ($attempt < $maxAttempts) {
        try {
            return $client->chat()->create($params)->toArray();
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'rate_limit') !== false) {
                $attempt++;
                if ($attempt < $maxAttempts) {
                    sleep($delay);
                    $delay *= 2; // Exponential backoff
                }
            } else {
                throw $e; // Re-throw non-rate-limit errors
            }
        }
    }

    return null;
}
```

#### 4. TOON Format Not Recognized by Model

**Problem**: The model doesn't understand TOON format

**Solution**:
```php
// Always explain TOON format in your system prompt
$systemPrompt = "You receive data in TOON format, where:
- Objects use 'key: value' pairs
- Arrays show '[count]: item1,item2,item3'
- Nesting uses indentation (2 spaces per level)
- Tables use '[rows]{fields}: values'

Example:
user:
  name: John
  skills[3]: PHP,Python,JavaScript

Parse this format carefully when analyzing data.";
```

#### 5. Memory Issues with Large Datasets

**Problem**: PHP memory exhausted with large data arrays

**Solution**:
```php
// Process data in chunks
function processLargeDataset(array $items, int $chunkSize = 100): void {
    $chunks = array_chunk($items, $chunkSize);

    foreach ($chunks as $i => $chunk) {
        echo "Processing chunk " . ($i + 1) . " of " . count($chunks) . "\n";

        // Encode just this chunk
        $encoded = Toon::encode(['batch' => $chunk]);

        // Process with API
        // ... your API call here ...

        // Free memory
        unset($encoded);
    }
}

// Or stream data directly
function streamEncoding($items): \Generator {
    foreach ($items as $item) {
        yield Toon::encode($item);
    }
}
```

## Next Steps

Congratulations! You've successfully integrated TOON with the OpenAI PHP client and learned how to:
- Format complex data structures efficiently
- Measure and calculate real token savings
- Handle API responses properly
- Implement best practices for production use

### Where to Go From Here

1. **Optimize Your Existing Applications**: Look for places in your current code where you're sending JSON to OpenAI and replace with TOON
2. **Build a Token Budget Monitor**: Create a system to track your token usage and savings over time
3. **Experiment with Different Models**: Test TOON's effectiveness with GPT-4, Claude, and other LLMs
4. **Create Domain-Specific Formatters**: Build specialized TOON encoders for your specific data types

### Key Takeaways

- **TOON reduces tokens by 30-60%** compared to JSON, resulting in direct cost savings
- **The official openai-php/client** works seamlessly with TOON-encoded data
- **Token estimation** can be approximated as 1 token per 4 characters
- **Different TOON formats** (compact, tabular, readable) suit different data structures
- **Production systems** should include error handling, retries, and metrics tracking

### Additional Resources

- [OpenAI PHP Client Documentation](https://github.com/openai-php/client)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference)
- [OpenAI Tokenizer Tool](https://platform.openai.com/tokenizer)
- [TOON PHP Repository](https://github.com/helgesverre/toon)
- [TOON Format Specification](https://github.com/toon-format/spec)

---

*Remember: The key to maximizing your savings with TOON is to use it consistently across all your LLM interactions. Start with your highest-volume API calls and work your way down. Every token saved is money in your pocket!*