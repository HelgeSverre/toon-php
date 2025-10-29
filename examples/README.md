# TOON PHP Examples

This directory contains real-world examples of using TOON with popular PHP AI/LLM libraries.

## Examples

### 1. OpenAI PHP Client
- **[openai-basic.php](openai-basic.php)** - Basic integration with OpenAI PHP client
- **[openai-chat.php](openai-chat.php)** - Chat completion with context data
- **[openai-function-calling.php](openai-function-calling.php)** - Function calling with structured data

### 2. Anthropic/Claude
- **[anthropic-basic.php](anthropic-basic.php)** - Basic integration with Anthropic PHP SDK
- **[anthropic-large-context.php](anthropic-large-context.php)** - Large context optimization

### 3. Token Comparison
- **[token-comparison.php](token-comparison.php)** - Compare TOON vs JSON token usage
- **[cost-calculator.php](cost-calculator.php)** - Calculate cost savings

## Prerequisites

To run these examples, you'll need:

```bash
# Install TOON
composer require helgesverre/toon

# For OpenAI examples
composer require openai-php/client

# For Anthropic examples
composer require anthropics/anthropic-sdk-php
```

You'll also need API keys:
- OpenAI: https://platform.openai.com/api-keys
- Anthropic: https://console.anthropic.com/

## Running Examples

```bash
# Set your API key
export OPENAI_API_KEY="your-key-here"
export ANTHROPIC_API_KEY="your-key-here"

# Run an example
php examples/openai-basic.php
```

## Token Savings

These examples demonstrate typical token savings:

| Use Case | JSON Tokens | TOON Tokens | Savings |
|----------|-------------|-------------|---------|
| User profiles | 450 | 270 | 40% |
| Product catalogs | 1,200 | 600 | 50% |
| Analytics data | 800 | 320 | 60% |
| Chat history | 600 | 360 | 40% |

## Need Help?

- [TOON Documentation](https://github.com/HelgeSverre/toon-php)
- [OpenAI PHP Docs](https://github.com/openai-php/client)
- [Anthropic PHP Docs](https://github.com/anthropics/anthropic-sdk-php)
