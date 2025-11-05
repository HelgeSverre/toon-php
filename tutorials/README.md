# TOON PHP Tutorials

Learn how to reduce LLM token consumption by 30-60% using TOON (Token-Oriented Object Notation) in your PHP applications. These hands-on tutorials take you from basic encoding to advanced production systems with real-world examples and measurable cost savings.

## Introduction

TOON is a PHP encoder that transforms your data into a compact format optimized for Large Language Models (LLMs). Unlike JSON, which wastes tokens on brackets, quotes, and commas, TOON uses indentation-based formatting that preserves structure while dramatically reducing token consumption.

These tutorials provide practical, hands-on learning with real code examples that you can run immediately. Each tutorial builds on the previous ones, progressing from simple encoding to complex production systems. You'll work with actual APIs, build real applications, and see concrete token savings in action.

### Why Learn TOON?

Every token sent to an LLM costs money. When you're processing thousands or millions of requests, a 30-60% reduction in token consumption translates directly to significant cost savings. These tutorials show you exactly how to achieve these savings in your applications.

### What You'll Learn

Throughout this tutorial series, you'll master:
- TOON encoding fundamentals and configuration options
- Integration with OpenAI and other LLM providers
- Building token-efficient Laravel applications
- Advanced optimization strategies for maximum savings
- Creating production-ready RAG systems with vector search

Each tutorial includes working code, practical exercises, and real-world examples from e-commerce, customer support, document processing, and more.

## Learning Paths

Choose a learning path based on your goals and available time. Each path is designed for specific outcomes.

### Path 1: Quick Start (2-3 hours)

**For developers who want to understand TOON basics and see immediate results**

This fast track gets you up and running with TOON, demonstrating real token savings with minimal time investment.

1. **Tutorial 1: Getting Started** (20 minutes)
   - Install TOON and encode your first data
   - Compare token consumption with JSON
   - Understand basic configuration options

2. **Tutorial 2: OpenAI Integration** (20 minutes)
   - Connect TOON with OpenAI's API
   - Format messages efficiently
   - Measure actual token savings

3. **Tutorial 4: Token Optimization** (15 minutes - quick scan)
   - Review optimization patterns
   - Understand cost impact
   - Learn when and how to optimize

**Outcome**: You'll be able to integrate TOON into existing projects and achieve 30-40% token savings immediately.

### Path 2: Application Developer (4-5 hours)

**For developers building TOON into production applications**

This path covers everything needed to build and deploy TOON-powered applications with real users.

1. **Tutorial 1: Getting Started** (20 minutes)
   - Master TOON fundamentals
   - Understand encoding rules
   - Practice with different data types

2. **Tutorial 2: OpenAI Integration** (20 minutes)
   - Build production-ready clients
   - Handle streaming responses
   - Implement error handling

3. **Tutorial 3: Laravel Integration** (25 minutes)
   - Integrate TOON with Laravel 11
   - Build service providers
   - Create AI-powered APIs

4. **Tutorial 4: Token Optimization** (25 minutes)
   - Apply advanced optimization strategies
   - Build token budget systems
   - Calculate and track ROI

**Outcome**: You'll have the skills to build production applications with 40-50% token savings and proper architecture.

### Path 3: Complete Mastery (6-8 hours)

**For developers who want deep expertise in TOON and advanced AI systems**

This comprehensive path covers everything from basics to advanced RAG implementation.

1. **Tutorial 1: Getting Started** (20 minutes)
2. **Tutorial 2: OpenAI Integration** (25 minutes)
3. **Tutorial 3: Laravel Integration** (30 minutes)
4. **Tutorial 4: Token Optimization** (30 minutes)
5. **Tutorial 5: RAG System with Ollama** (40 minutes)
6. **Tutorial 6: Anthropic/Claude Integration** (25 minutes)

**Outcome**: Complete understanding of TOON with ability to build complex AI systems achieving 50-60% token savings.

## Tutorial Summaries

### Tutorial 1: Getting Started with TOON

**Difficulty**: Beginner
**Time**: 15-20 minutes
**What you'll build**: A receipt parsing system that demonstrates TOON's token efficiency. You'll encode receipt data, compare token consumption with JSON, and see real savings percentages.

**Key concepts covered**:
- Installing and configuring TOON
- Basic encoding with `Toon::encode()`
- Understanding automatic string quoting
- Working with nested arrays and objects
- Using EncodeOptions for customization
- Token estimation with helper functions
- Comparing TOON vs JSON token consumption

**Prerequisites**: PHP 8.1+, Composer installed

**File**: `01-getting-started.md`

### Tutorial 2: Integrating TOON with OpenAI

**Difficulty**: Intermediate
**Time**: 20-25 minutes
**What you'll build**: Two complete systems - an email classification service and an invoice validation system. Both demonstrate real-world integration patterns with concrete token savings.

**Key concepts covered**:
- Setting up OpenAI PHP client
- Formatting chat messages with TOON
- Optimizing system prompts
- Batch processing for efficiency
- Calculating cost savings
- Handling API responses
- Error handling and retries

**Prerequisites**: Tutorial 1 completed, OpenAI API key

**File**: `02-openai-integration.md`

### Tutorial 3: Using TOON in Laravel

**Difficulty**: Intermediate
**Time**: 20-25 minutes
**What you'll build**: A customer support ticket classification system integrated into Laravel 11. Includes service providers, API endpoints, and Pest tests.

**Key concepts covered**:
- Laravel service provider setup
- Dependency injection patterns
- Building AI service classes
- Creating RESTful endpoints
- Writing Pest tests for AI features
- Implementing response caching
- Production deployment considerations

**Prerequisites**: Laravel 11 basics, Tutorials 1-2 completed

**File**: `03-laravel-prism-integration.md`

### Tutorial 4: Token Optimization Strategies

**Difficulty**: Advanced
**Time**: 25-30 minutes
**What you'll build**: A PDF metadata extraction system and a product catalog classifier, both optimized for maximum token efficiency.

**Key concepts covered**:
- Analyzing data for optimization opportunities
- Choosing optimal TOON formats
- Preprocessing strategies
- Building token budgets
- Measuring optimization impact
- Calculating ROI for stakeholders
- Production monitoring patterns

**Prerequisites**: Tutorials 1-2 completed, understanding of LLM pricing models

**File**: `04-token-optimization-strategies.md`

### Tutorial 5: Building a RAG System

**Difficulty**: Advanced
**Time**: 30-40 minutes
**What you'll build**: A PHP documentation search system using Ollama for local embeddings, vector similarity search, and TOON-optimized context compression.

**Key concepts covered**:
- Setting up Ollama locally
- Generating text embeddings
- Implementing cosine similarity
- Building semantic search
- Optimizing document chunks
- Compressing metadata with TOON
- Query expansion techniques
- Production RAG pipelines

**Prerequisites**: Tutorials 1-2 completed, Ollama installed locally

**File**: `05-rag-system-ollama.md`

### Tutorial 6: Integrating TOON with Anthropic/Claude

**Difficulty**: Intermediate
**Time**: 20-25 minutes
**What you'll build**: A large dataset analysis system using Claude's 200K context window, optimized with TOON to fit 40-60% more data in each request.

**Key concepts covered**:
- Setting up Anthropic PHP SDK
- Leveraging Claude's large context window
- Formatting large datasets with TOON
- Batch analysis of support tickets
- Token savings specific to Claude
- Cost calculation for Anthropic API
- Context window optimization strategies

**Prerequisites**: Tutorial 1 completed, Anthropic API key

**File**: `06-anthropic-integration.md`

## Prerequisites & Setup

### System Requirements

The following software is required or optional depending on which tutorials you plan to complete:

```bash
# Required for all tutorials
PHP 8.1 or higher
Composer 2.x

# Optional dependencies by tutorial
OpenAI API key     # Tutorial 2 (free tier sufficient)
Laravel 11         # Tutorial 3 (fresh installation)
Ollama             # Tutorial 5 (local installation)
```

### Installation

Install TOON via Composer:

```bash
composer require helgesverre/toon
```

### Additional Packages by Tutorial

Each tutorial may require additional packages. Install them as needed:

**Tutorial 2 - OpenAI Integration**:
```bash
composer require openai-php/client
```

**Tutorial 3 - Laravel Integration**:
```bash
# Create new Laravel project
composer create-project laravel/laravel support-system
cd support-system
composer require helgesverre/toon
```

**Tutorial 4 - Token Optimization**:
```bash
# No additional packages required
# Uses TOON core features only
```

**Tutorial 5 - RAG System**:
```bash
# Install HTTP client for Ollama
composer require guzzlehttp/guzzle

# Install Ollama (macOS)
brew install ollama
ollama pull mxbai-embed-large
```

**Tutorial 6 - Anthropic Integration**:
```bash
composer require anthropics/anthropic-sdk-php
```

### Verifying Installation

Test your TOON installation:

```bash
php -r "require 'vendor/autoload.php'; use HelgeSverre\Toon\Toon; echo Toon::encode(['test' => 'success']);"
```

Expected output:
```
test: success
```

## What to Expect

### Code Examples

All code in these tutorials is production-ready and follows best practices:

- **Valid PHP**: Every example is executable PHP code that runs without modification
- **Real scenarios**: Examples use actual use cases like receipts, emails, invoices, and support tickets
- **No fictional code**: All classes, methods, and APIs shown actually exist
- **Clear boundaries**: We clearly distinguish between TOON features and custom application code

### Learning Approach

These tutorials use progressive, hands-on learning:

- **Start simple**: Begin with basic encoding, progress to complex systems
- **Learn by doing**: Write and run code at every step
- **See real results**: Measure actual token savings and cost reductions
- **Build understanding**: Each concept builds on previous knowledge

### Production Considerations

We're honest about production requirements:

- **Performance**: TOON encoding is fast but adds processing time
- **Compatibility**: Not all LLM providers handle TOON identically
- **Testing**: You'll need comprehensive tests for production systems
- **Monitoring**: Track token usage and costs in production

### Not Included

These tutorials focus on TOON specifically and assume:

- **PHP knowledge**: We don't teach PHP basics
- **LLM fundamentals**: Basic understanding of how LLMs work
- **API concepts**: Familiarity with REST APIs and HTTP
- **Deployment**: We don't cover server configuration or CI/CD

## Getting Help

### Troubleshooting

Each tutorial includes a dedicated troubleshooting section with solutions for common issues. Additionally, check these resources:

**Common Issues**:
- Installation problems: Check PHP version (8.1+ required)
- API errors: Verify your API keys are valid and have credits
- Memory issues: Increase PHP memory limit for large datasets
- Token mismatches: Different models use different tokenizers

**TOON Documentation**:
- Configuration options: See TOON PHP README
- Format specification: Check the TOON spec document
- Helper functions: Review the API documentation

### Community

Get help and share experiences:

- **GitHub Issues**: Report bugs or request features at the TOON PHP repository
- **Discussions**: Ask questions in the GitHub Discussions section
- **Examples**: Check the `/examples` directory for additional code samples

## Performance Benchmarks

### Real-World Token Savings

These benchmarks come from actual examples in the tutorials:

**Data Structure Savings**:
- Receipt data: 35-45% reduction
- Email classification: 40-50% reduction
- Invoice validation: 45-55% reduction
- Support tickets: 35-45% reduction
- PDF metadata: 50-60% reduction
- Product catalogs: 60-70% reduction (with preprocessing)
- RAG contexts: 45-55% reduction

### Cost Impact

Based on OpenAI GPT-3.5-turbo pricing ($0.0015/1K input tokens):

**Monthly savings at different scales**:
- 100K requests/month: $10-30 savings
- 1M requests/month: $100-300 savings
- 10M requests/month: $1,000-3,000 savings

**Example calculation** (1M requests/month):
- Average JSON tokens per request: 500
- Average TOON tokens per request: 275 (45% reduction)
- Tokens saved monthly: 225M tokens
- Monthly cost savings: $337.50

Note: Actual savings depend on your specific data structures, optimization strategies, and chosen LLM model. GPT-4 models have higher per-token costs, resulting in proportionally larger savings.

## Next Steps

After completing these tutorials, consider these next steps:

1. **Review TOON documentation**: Explore advanced configuration options and edge cases
2. **Audit existing code**: Identify where TOON can reduce costs in your current projects
3. **Implement gradually**: Start with high-volume endpoints for maximum impact
4. **Measure everything**: Track token usage before and after TOON implementation
5. **Share your results**: Contribute examples and optimizations back to the community

### Advanced Topics to Explore

- Custom delimiters for specialized use cases
- Streaming TOON encoding for large datasets
- Building TOON-aware caching layers
- Integrating with other LLM providers
- Creating domain-specific optimization strategies

### Production Deployment

When deploying TOON in production:
- Add comprehensive logging for token usage
- Implement fallback to JSON if needed
- Monitor performance impact
- Set up alerts for cost thresholds
- Document your optimization strategies

---

Ready to start saving on LLM costs? Begin with [Tutorial 1: Getting Started with TOON](01-getting-started.md) and see immediate results in just 20 minutes.