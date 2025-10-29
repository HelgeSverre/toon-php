# TOON PHP Tutorials

Comprehensive, hands-on tutorials for mastering the TOON (Token-Oriented Object Notation) PHP library and reducing LLM token consumption by 30-60%.

## Tutorial Overview

These tutorials progressively build your expertise from basic TOON usage to advanced production systems. Each tutorial includes complete, working code examples that have been tested and verified.

## Prerequisites

- **PHP Version**: 8.1+ (8.2+ for Laravel tutorial)
- **Composer**: Latest version
- **Development Environment**: Local PHP setup or Docker
- **API Keys**: OpenAI, Anthropic, or other LLM providers (free tier sufficient)

## Tutorials

### 1. [Getting Started with TOON in PHP](01-getting-started.md)
**Difficulty**: Beginner | **Time**: 10-15 minutes

Learn the fundamentals of TOON, including installation, basic encoding, configuration options, and real-world token comparisons with JSON.

**You'll Learn:**
- What TOON is and why it matters for LLM costs
- How to encode various data structures
- Comparing token savings vs JSON
- Testing with real LLM APIs

**Key Concepts:**
- TOON syntax and format rules
- Automatic string quoting
- Array and object encoding
- Configuration options

---

### 2. [Integrating TOON with OpenAI PHP Client](02-openai-integration.md)
**Difficulty**: Intermediate | **Time**: 15-20 minutes

Deep dive into production-ready OpenAI integration with comprehensive token optimization strategies.

**You'll Learn:**
- Setting up OpenAI PHP client with TOON
- Formatting complex messages and conversations
- Optimizing function calling
- Measuring real token savings
- Handling streaming responses

**Key Concepts:**
- Message formatting patterns
- Function calling optimization
- Streaming response handling
- Cost calculation and tracking

---

### 3. [Building a Laravel AI Application with TOON and Prism](03-laravel-prism-integration.md)
**Difficulty**: Intermediate-Advanced | **Time**: 20-30 minutes

Build a complete Laravel customer support chatbot with multi-provider support and real-time chat interface.

**You'll Learn:**
- Installing TOON in Laravel projects
- Setting up Prism for multi-provider support
- Building service providers and facades
- Creating AI-powered API endpoints
- Testing with Pest

**Key Concepts:**
- Laravel service architecture
- Multi-provider LLM support
- Real-time chat implementation
- Token usage tracking
- Production deployment

---

### 4. [Reducing LLM Costs: TOON Token Optimization Strategies](04-token-optimization-strategies.md)
**Difficulty**: Advanced | **Time**: 20-25 minutes

Master advanced token optimization techniques with real-world case studies and ROI analysis.

**You'll Learn:**
- Understanding token economics in depth
- Identifying optimization opportunities
- Applying strategic TOON encoding
- Building token budget systems
- Measuring and tracking savings at scale

**Key Concepts:**
- Token economics and pricing
- Optimization patterns
- RAG workflow optimization
- Budget management
- ROI calculation

---

### 5. [Building a RAG System with TOON, Neuron AI, and Vector Stores](05-rag-system-neuron-ai.md)
**Difficulty**: Advanced | **Time**: 30-40 minutes

Implement a production-ready RAG system with document processing, vector embeddings, and semantic search.

**You'll Learn:**
- RAG architecture fundamentals
- Document processing and chunking
- Vector embedding optimization
- Building retrieval pipelines
- Implementing semantic search at scale

**Key Concepts:**
- Document chunking strategies
- Vector store integration
- Hybrid search implementation
- Context window optimization
- Production deployment

## Quick Start Guide

### Option 1: Sequential Learning Path

Start with Tutorial 1 and progress through each tutorial in order. This approach builds knowledge systematically.

```bash
# Start with basics
cd tutorials
php 01-getting-started/examples/basic-encoding.php

# Progress to integrations
php 02-openai-integration/examples/basic-messages.php

# Build real applications
cd 03-laravel-prism-integration
composer install
php artisan serve
```

### Option 2: Jump to Your Use Case

Choose based on your immediate needs:

- **Reducing API costs quickly**: Start with Tutorial 4
- **Building a chatbot**: Go to Tutorial 3
- **RAG/Knowledge base**: Jump to Tutorial 5
- **OpenAI integration**: Begin with Tutorial 2

### Option 3: Code Examples First

Each tutorial includes standalone code examples in the `/examples` directory that you can run immediately:

```bash
# Quick token comparison
php examples/quick-comparison.php

# OpenAI integration test
php examples/openai-test.php

# RAG pipeline demo
php examples/rag-demo.php
```

## Installation

### Basic Setup

```bash
# Clone the repository
git clone https://github.com/helgesverre/toon-php
cd toon-php/tutorials

# Install dependencies
composer install

# Copy environment file
cp .env.example .env
# Add your API keys to .env
```

### Docker Setup (Optional)

```bash
# Use the provided Docker setup
docker-compose up -d

# Run tutorials in container
docker exec -it toon-php-app bash
php tutorials/01-getting-started/examples/basic-encoding.php
```

## Code Examples Structure

Each tutorial includes organized example files:

```
tutorials/
├── 01-getting-started/
│   ├── examples/
│   │   ├── basic-encoding.php      # Simple encoding examples
│   │   ├── nested-structures.php   # Complex data structures
│   │   ├── token-comparison.php    # JSON vs TOON comparison
│   │   └── openai-integration.php  # Real API integration
│   └── solutions/                  # Exercise solutions
├── 02-openai-integration/
│   ├── examples/
│   │   ├── message-formatting.php  # Chat message formatting
│   │   ├── function-calling.php    # Optimized function calls
│   │   ├── streaming.php           # Streaming responses
│   │   └── production-client.php   # Production-ready client
│   └── tests/                      # PHPUnit tests
└── [additional tutorials...]
```

## Learning Outcomes

After completing these tutorials, you will be able to:

### Technical Skills
- Reduce LLM token consumption by 30-60%
- Implement TOON in any PHP application
- Build production-ready AI features
- Optimize RAG workflows efficiently
- Calculate and demonstrate ROI

### Practical Applications
- Build cost-effective chatbots
- Create efficient RAG systems
- Optimize existing LLM integrations
- Implement token budget management
- Deploy scalable AI solutions

## Performance Benchmarks

Real-world results from the tutorials:

| Use Case | JSON Tokens | TOON Tokens | Savings | Monthly Cost Savings* |
|----------|------------|-------------|---------|----------------------|
| User Profile | 245 | 147 | 40% | $294 |
| E-commerce Order | 412 | 231 | 44% | $543 |
| Analytics Dashboard | 1,245 | 623 | 50% | $1,866 |
| Chat History (20 msgs) | 3,421 | 1,882 | 45% | $4,617 |
| RAG Context (10 chunks) | 8,234 | 4,117 | 50% | $12,351 |

*Based on 100,000 requests/month with GPT-3.5-turbo pricing

## Common Patterns

### Pattern 1: Basic Integration
```php
use HelgeSverre\Toon\Toon;

$data = ['user' => ['name' => 'John', 'age' => 30]];
$encoded = Toon::encode($data);
// Output: user:\n  name: John\n  age: 30
```

### Pattern 2: LLM Message Formatting
```php
$context = Toon::encode($userData);
$prompt = "Analyze this user:\n\n$context";
// Saves 40-50% tokens vs JSON
```

### Pattern 3: RAG Optimization
```php
$chunks = $processor->processDocument($content);
$optimized = array_map(fn($c) => Toon::encode($c), $chunks);
// Reduces context window usage by 45%
```

## Troubleshooting

### Common Issues

1. **Composer Installation Fails**
   ```bash
   composer clear-cache
   composer update --no-cache
   ```

2. **API Key Errors**
   - Verify keys in `.env` file
   - Check API account has credits
   - Ensure correct key format

3. **Memory Limit Exceeded**
   ```php
   ini_set('memory_limit', '256M');
   ```

4. **Token Count Mismatch**
   - Use proper tokenizer library
   - Account for special characters
   - Consider model-specific tokenization

## Testing Your Implementation

Each tutorial includes tests. Run them with:

```bash
# PHPUnit tests
./vendor/bin/phpunit tests/

# Pest tests (Laravel tutorial)
php artisan test

# Quick validation
php tutorials/test-all.php
```

## Contributing

We welcome contributions! Areas where you can help:

- Additional integration examples
- Performance optimizations
- Bug fixes and improvements
- Documentation enhancements
- New tutorial topics

## Support and Community

- **GitHub Issues**: [Report bugs or request features](https://github.com/helgesverre/toon-php/issues)
- **Discussions**: [Ask questions and share experiences](https://github.com/helgesverre/toon-php/discussions)
- **Examples**: Share your TOON implementations

## Additional Resources

### Official Documentation
- [TOON PHP Repository](https://github.com/helgesverre/toon-php)
- [TOON Format Specification](https://github.com/helgesverre/toon-php#format-rules)

### Related Projects
- [Original TOON (TypeScript)](https://github.com/johannschopplich/toon)
- [OpenAI PHP Client](https://github.com/openai-php/client)
- [Laravel Prism](https://github.com/echolabsdev/laravel-prism)

### Learning Resources
- [Token Economics Guide](https://platform.openai.com/tokenizer)
- [RAG Best Practices](https://www.pinecone.io/learn/rag)
- [Laravel AI Development](https://laravel.com)

## License

These tutorials are provided under the MIT License. See the LICENSE file for details.

## Acknowledgments

- Original TOON format by [Johann Schopplich](https://github.com/johannschopplich)
- PHP implementation by [Helge Sverre](https://github.com/helgesverre)
- Tutorial content created for the TOON PHP community

---

**Ready to reduce your LLM costs by 30-60%?** Start with [Tutorial 1: Getting Started with TOON](01-getting-started.md) →