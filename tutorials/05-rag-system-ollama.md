# Tutorial 5: Building a RAG System with TOON and Ollama

**Difficulty**: Advanced
**Time**: 30-40 minutes
**PHP Version**: 8.1+

## What You'll Build

In this tutorial, you'll create a knowledge base search system that:

- Uses Ollama for local embeddings (no cloud API needed)
- Implements a simple in-memory vector store in pure PHP
- Performs cosine similarity search
- Uses TOON to efficiently encode document metadata
- Searches through PHP documentation and articles

## Learning Objectives

By the end of this tutorial, you'll understand:

- RAG (Retrieval-Augmented Generation) architecture basics
- How to implement vector embeddings with Ollama
- How to build cosine similarity search from scratch
- How to use TOON for metadata compression
- How to create a working knowledge base Q&A system

## Prerequisites

- Completed Tutorials 1-2
- Ollama installed locally with embedding model
- Basic understanding of vectors and similarity
- PHP 8.1+ with Composer

---

## Section 1: Introduction

### What is RAG?

RAG (Retrieval-Augmented Generation) is a pattern that enhances LLM responses by first retrieving relevant information from a knowledge base. Instead of relying solely on the LLM's training data, RAG systems:

1. **Index** documents by converting them to vector embeddings
2. **Search** for relevant documents using similarity matching
3. **Augment** the LLM prompt with retrieved context
4. **Generate** more accurate, grounded responses

### TOON's Role in RAG

While TOON doesn't handle the core RAG functionality (embeddings, vector search), it plays a crucial role in optimizing the system:

- **Metadata Compression**: Reduces document metadata size by 40-60%
- **Context Optimization**: Minimizes tokens when sending context to LLMs
- **Efficient Storage**: Compact representation of search results

### Educational Focus

This tutorial implements a simplified RAG system to help you understand the building blocks. We'll build everything from scratch to demystify the process.

```php
<?php
/*
 * This tutorial implements a simplified RAG system for educational purposes.
 * For production use:
 * - Use dedicated vector databases (Pinecone, Qdrant, Weaviate, pgvector)
 * - Implement proper chunking strategies
 * - Add caching and optimization
 * - Consider hybrid search (vector + keyword)
 */
```

### System Architecture Overview

```
User Query → Generate Embedding → Vector Search → Retrieve Documents
                                         ↓
                                  TOON Compression
                                         ↓
                                  Build LLM Context → Generate Response
```

---

## Section 2: Setup

### Install Ollama

First, install Ollama on your system:

```bash
# Install Ollama (macOS/Linux)
curl https://ollama.ai/install.sh | sh

# For Windows, download from https://ollama.ai/download
```

### Pull Embedding Model

We'll use `mxbai-embed-large`, a high-quality embedding model:

```bash
# Pull the embedding model (this may take a few minutes)
ollama pull mxbai-embed-large

# Verify Ollama is running
curl http://localhost:11434/api/embed -d '{
  "model": "mxbai-embed-large",
  "input": "test"
}'
```

You should see a JSON response with an embedding array.

### Install PHP Dependencies

```bash
# Create project directory
mkdir rag-tutorial && cd rag-tutorial

# Initialize composer project
composer init --name="tutorial/rag-system" --type=project

# Install required packages
composer require helgesverre/toon
composer require guzzlehttp/guzzle
```

### Project Structure

Create the following file structure:

```
rag-tutorial/
├── composer.json
├── vendor/
├── src/
│   ├── OllamaClient.php
│   ├── VectorMath.php
│   ├── VectorStore.php
│   ├── KnowledgeBase.php
│   └── RAG.php
└── index.php
```

---

## Section 3: Ollama Embedding Client

Let's create a simple client to interact with Ollama's embedding API:

```php
<?php
// src/OllamaClient.php

declare(strict_types=1);

namespace Tutorial\RAG;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Simple client for Ollama embedding API
 */
class OllamaClient
{
    private Client $httpClient;
    private string $model;

    public function __construct(
        string $baseUri = 'http://localhost:11434',
        string $model = 'mxbai-embed-large'
    ) {
        $this->httpClient = new Client([
            'base_uri' => $baseUri,
            'timeout' => 30.0,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
        $this->model = $model;
    }

    /**
     * Get embedding vector from Ollama
     *
     * @param string $text Text to embed
     * @return array Float array representing the embedding
     * @throws \RuntimeException If embedding fails
     */
    public function getEmbedding(string $text): array
    {
        try {
            $response = $this->httpClient->post('/api/embed', [
                'json' => [
                    'model' => $this->model,
                    'input' => $text
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['embeddings'][0])) {
                throw new \RuntimeException('Invalid embedding response');
            }

            // Ollama returns array of embeddings, we take the first one
            return $data['embeddings'][0];

        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Failed to get embedding: " . $e->getMessage()
            );
        }
    }

    /**
     * Check if Ollama is running and model is available
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        try {
            $embedding = $this->getEmbedding("test");
            return !empty($embedding);
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

Test the client:

```php
<?php
require 'vendor/autoload.php';

use Tutorial\RAG\OllamaClient;

$client = new OllamaClient();

// Check if Ollama is running
if (!$client->healthCheck()) {
    echo "Error: Ollama is not running or model not available\n";
    echo "Please run: ollama pull mxbai-embed-large\n";
    exit(1);
}

// Test embedding generation
$embedding = $client->getEmbedding("Hello world");
echo "Embedding dimensions: " . count($embedding) . "\n";
echo "Sample values: " . implode(', ', array_slice($embedding, 0, 5)) . "...\n";
```

---

## Section 4: Cosine Similarity Implementation

Now let's implement cosine similarity to compare vectors:

```php
<?php
// src/VectorMath.php

declare(strict_types=1);

namespace Tutorial\RAG;

/**
 * Vector mathematics utilities for similarity calculations
 */
class VectorMath
{
    /**
     * Calculate cosine similarity between two vectors
     *
     * Cosine similarity measures the angle between two vectors,
     * returning a value between -1 and 1:
     * - 1 means vectors point in the same direction (most similar)
     * - 0 means vectors are orthogonal (unrelated)
     * - -1 means vectors point in opposite directions (least similar)
     *
     * Formula: cos(θ) = (A · B) / (||A|| * ||B||)
     *
     * @param array $vectorA First embedding vector
     * @param array $vectorB Second embedding vector
     * @return float Similarity score between -1 and 1
     * @throws \InvalidArgumentException If vectors have different dimensions
     */
    public static function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dimA = count($vectorA);
        $dimB = count($vectorB);

        if ($dimA !== $dimB) {
            throw new \InvalidArgumentException(
                "Vectors must have same dimensions. Got {$dimA} and {$dimB}"
            );
        }

        if ($dimA === 0) {
            return 0.0;
        }

        // Calculate dot product and magnitudes
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < $dimA; $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] * $vectorA[$i];
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }

        // Calculate magnitude (length) of each vector
        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        // Prevent division by zero
        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        // Return cosine similarity
        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Normalize a vector to unit length
     *
     * @param array $vector Input vector
     * @return array Normalized vector
     */
    public static function normalize(array $vector): array
    {
        $magnitude = 0.0;

        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }

        $magnitude = sqrt($magnitude);

        if ($magnitude == 0) {
            return $vector;
        }

        return array_map(fn($v) => $v / $magnitude, $vector);
    }
}
```

Test similarity calculations:

```php
<?php
require 'vendor/autoload.php';

use Tutorial\RAG\OllamaClient;
use Tutorial\RAG\VectorMath;

$client = new OllamaClient();

// Get embeddings for related and unrelated concepts
$vec1 = $client->getEmbedding("PHP programming language");
$vec2 = $client->getEmbedding("Python programming language");
$vec3 = $client->getEmbedding("JavaScript web development");
$vec4 = $client->getEmbedding("Cooking pasta recipes");

// Calculate similarities
$phpPython = VectorMath::cosineSimilarity($vec1, $vec2);
$phpJavaScript = VectorMath::cosineSimilarity($vec1, $vec3);
$phpCooking = VectorMath::cosineSimilarity($vec1, $vec4);

echo "Similarity Scores:\n";
echo "PHP vs Python: " . round($phpPython, 3) . "\n";
echo "PHP vs JavaScript: " . round($phpJavaScript, 3) . "\n";
echo "PHP vs Cooking: " . round($phpCooking, 3) . "\n";

// Expected: Programming languages should have higher similarity than cooking
```

---

## Section 5: In-Memory Vector Store

Now let's build a simple vector store to hold our documents:

```php
<?php
// src/VectorStore.php

declare(strict_types=1);

namespace Tutorial\RAG;

use HelgeSverre\Toon\Toon;

/**
 * Simple in-memory vector store for educational purposes
 *
 * In production, use:
 * - Pinecone, Qdrant, Weaviate for cloud
 * - pgvector for PostgreSQL
 * - Redis with RedisVL
 * - ChromaDB for local development
 */
class VectorStore
{
    private array $documents = [];
    private int $nextId = 1;
    private OllamaClient $embedder;
    private array $stats = [
        'total_indexed' => 0,
        'total_searches' => 0,
        'bytes_saved_by_toon' => 0
    ];

    public function __construct(OllamaClient $embedder)
    {
        $this->embedder = $embedder;
    }

    /**
     * Add document with its embedding and metadata
     *
     * @param string $text Document text content
     * @param array $metadata Additional metadata
     * @return int Document ID
     */
    public function addDocument(string $text, array $metadata = []): int
    {
        $id = $this->nextId++;

        // Generate embedding for the text
        $embedding = $this->embedder->getEmbedding($text);

        // Use TOON to compress metadata - this is where TOON helps!
        $compressedMetadata = Toon::encode($metadata);
        $jsonMetadata = json_encode($metadata);

        // Track compression savings
        $this->stats['bytes_saved_by_toon'] +=
            strlen($jsonMetadata) - strlen($compressedMetadata);

        $this->documents[$id] = [
            'id' => $id,
            'text' => $text,
            'embedding' => $embedding,
            'metadata_toon' => $compressedMetadata,
            'metadata_raw' => $metadata,
            'indexed_at' => time()
        ];

        $this->stats['total_indexed']++;

        return $id;
    }

    /**
     * Search for similar documents
     *
     * @param string $query Search query
     * @param int $topK Number of results to return
     * @param float $threshold Minimum similarity score (0-1)
     * @return array Matching documents with scores
     */
    public function search(
        string $query,
        int $topK = 5,
        float $threshold = 0.0
    ): array {
        $this->stats['total_searches']++;

        // Get query embedding
        $queryEmbedding = $this->embedder->getEmbedding($query);

        // Calculate similarity with all documents
        $results = [];
        foreach ($this->documents as $id => $doc) {
            $similarity = VectorMath::cosineSimilarity(
                $queryEmbedding,
                $doc['embedding']
            );

            if ($similarity >= $threshold) {
                $results[] = [
                    'id' => $id,
                    'score' => $similarity,
                    'text' => $doc['text'],
                    'metadata' => $doc['metadata_raw']
                ];
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top K results
        return array_slice($results, 0, $topK);
    }

    /**
     * Get statistics about the vector store
     *
     * @return array Store statistics
     */
    public function getStats(): array
    {
        $totalDocs = count($this->documents);
        $avgMetadataSize = 0;
        $totalEmbeddingSize = 0;

        foreach ($this->documents as $doc) {
            $avgMetadataSize += strlen($doc['metadata_toon']);
            $totalEmbeddingSize += count($doc['embedding']) * 4; // 4 bytes per float
        }

        return [
            'total_documents' => $totalDocs,
            'total_searches' => $this->stats['total_searches'],
            'avg_metadata_size_bytes' => $totalDocs > 0
                ? round($avgMetadataSize / $totalDocs, 2)
                : 0,
            'total_embedding_size_mb' => round($totalEmbeddingSize / 1024 / 1024, 2),
            'vector_dimensions' => !empty($this->documents)
                ? count(reset($this->documents)['embedding'])
                : 0,
            'bytes_saved_by_toon' => $this->stats['bytes_saved_by_toon'],
            'compression_ratio' => $this->stats['bytes_saved_by_toon'] > 0
                ? round($this->stats['bytes_saved_by_toon'] /
                    ($avgMetadataSize + $this->stats['bytes_saved_by_toon']) * 100, 1)
                : 0
        ];
    }

    /**
     * Clear all documents from the store
     */
    public function clear(): void
    {
        $this->documents = [];
        $this->nextId = 1;
        $this->stats['total_indexed'] = 0;
    }
}
```

---

## Section 6: Building Knowledge Base

Let's create a knowledge base with PHP documentation:

```php
<?php
// src/KnowledgeBase.php

declare(strict_types=1);

namespace Tutorial\RAG;

/**
 * Sample PHP documentation for our knowledge base
 */
class KnowledgeBase
{
    /**
     * Get sample PHP documentation articles
     *
     * @return array Documentation articles
     */
    public static function getDocuments(): array
    {
        return [
            [
                'title' => 'Arrays in PHP',
                'content' => 'Arrays in PHP are ordered maps that associate values to keys. They are extremely versatile data structures that can be used as arrays, lists, hash tables, dictionaries, collections, stacks, and queues. PHP arrays can hold values of any type, including other arrays, making multidimensional arrays possible.',
                'category' => 'Data Structures',
                'url' => 'https://php.net/manual/en/language.types.array.php',
                'version' => '8.1+',
                'tags' => ['arrays', 'data structures', 'collections']
            ],
            [
                'title' => 'PHP Functions',
                'content' => 'Functions are reusable blocks of code that perform specific tasks. PHP has thousands of built-in functions and also supports user-defined functions. Functions can accept parameters and return values. PHP 8 introduced named arguments and union types for better function signatures.',
                'category' => 'Language Reference',
                'url' => 'https://php.net/manual/en/language.functions.php',
                'version' => '8.1+',
                'tags' => ['functions', 'methods', 'procedures']
            ],
            [
                'title' => 'Object-Oriented Programming',
                'content' => 'PHP supports full object-oriented programming with classes, objects, inheritance, interfaces, traits, and abstract classes. Modern PHP development heavily uses OOP principles for better code organization, reusability, and maintainability. PHP 8 added constructor property promotion and match expressions.',
                'category' => 'OOP',
                'url' => 'https://php.net/manual/en/language.oop5.php',
                'version' => '8.1+',
                'tags' => ['oop', 'classes', 'objects', 'inheritance']
            ],
            [
                'title' => 'PDO Database Access',
                'content' => 'PHP Data Objects (PDO) provides a consistent interface for accessing databases in PHP. It supports prepared statements for security, multiple database drivers including MySQL, PostgreSQL, SQLite, and more. PDO offers both procedural and object-oriented interfaces.',
                'category' => 'Database',
                'url' => 'https://php.net/manual/en/book.pdo.php',
                'version' => '8.1+',
                'tags' => ['database', 'pdo', 'sql', 'mysql', 'postgresql']
            ],
            [
                'title' => 'Composer Package Manager',
                'content' => 'Composer is the dependency manager for PHP. It allows you to declare the libraries your project depends on and manages installation and updates. Composer uses a composer.json file to define dependencies and supports autoloading through PSR-4 standards.',
                'category' => 'Tools',
                'url' => 'https://getcomposer.org/',
                'version' => 'N/A',
                'tags' => ['composer', 'dependencies', 'packages', 'autoloading']
            ],
            [
                'title' => 'Error Handling and Exceptions',
                'content' => 'PHP provides comprehensive error handling through exceptions and error handlers. Exceptions allow you to handle errors gracefully using try-catch blocks. PHP 8 introduced throw expressions and improved type system for better error handling.',
                'category' => 'Error Handling',
                'url' => 'https://php.net/manual/en/language.exceptions.php',
                'version' => '8.1+',
                'tags' => ['exceptions', 'errors', 'debugging', 'try-catch']
            ],
            [
                'title' => 'PHP Sessions',
                'content' => 'Sessions provide a way to store information across multiple pages for individual users. PHP sessions work by creating a unique ID for each visitor and storing variables based on this ID. Sessions are commonly used for user authentication and shopping carts.',
                'category' => 'Web Features',
                'url' => 'https://php.net/manual/en/book.session.php',
                'version' => '8.1+',
                'tags' => ['sessions', 'cookies', 'authentication', 'state']
            ],
            [
                'title' => 'File Handling',
                'content' => 'PHP provides extensive functions for file system operations including reading, writing, uploading, and manipulating files. Functions like fopen, fread, fwrite, and file_get_contents make file handling straightforward. PHP also supports streams for advanced I/O operations.',
                'category' => 'File System',
                'url' => 'https://php.net/manual/en/book.filesystem.php',
                'version' => '8.1+',
                'tags' => ['files', 'io', 'streams', 'uploads']
            ],
            [
                'title' => 'PHP Security Best Practices',
                'content' => 'Security in PHP involves protecting against common vulnerabilities like SQL injection, XSS, CSRF, and session hijacking. Best practices include using prepared statements, validating input, escaping output, using HTTPS, and keeping PHP updated.',
                'category' => 'Security',
                'url' => 'https://php.net/manual/en/security.php',
                'version' => '8.1+',
                'tags' => ['security', 'validation', 'sanitization', 'best practices']
            ],
            [
                'title' => 'PHP Performance Optimization',
                'content' => 'PHP performance can be optimized through opcache, efficient algorithms, database query optimization, and caching strategies. PHP 8 brought JIT compilation for significant performance improvements. Profiling tools help identify bottlenecks.',
                'category' => 'Performance',
                'url' => 'https://php.net/manual/en/book.opcache.php',
                'version' => '8.1+',
                'tags' => ['performance', 'optimization', 'caching', 'jit']
            ]
        ];
    }

    /**
     * Index all documents into vector store
     *
     * @param VectorStore $store Vector store to index into
     * @return array Indexing statistics
     */
    public static function indexDocuments(VectorStore $store): array
    {
        $documents = self::getDocuments();
        $stats = [
            'indexed' => 0,
            'failed' => 0,
            'titles' => []
        ];

        echo "Indexing PHP documentation...\n";

        foreach ($documents as $doc) {
            try {
                // Combine title and content for richer embeddings
                $text = $doc['title'] . '. ' . $doc['content'];

                // Metadata gets TOON-encoded automatically in the store
                $metadata = [
                    'title' => $doc['title'],
                    'category' => $doc['category'],
                    'url' => $doc['url'],
                    'version' => $doc['version'],
                    'tags' => $doc['tags']
                ];

                $id = $store->addDocument($text, $metadata);
                echo "  ✓ Indexed: {$doc['title']} (ID: {$id})\n";

                $stats['indexed']++;
                $stats['titles'][] = $doc['title'];

            } catch (\Exception $e) {
                echo "  ✗ Failed: {$doc['title']} - {$e->getMessage()}\n";
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
```

---

## Section 7: Search and Q&A

Now let's implement search functionality with TOON compression:

```php
<?php
// src/RAG.php

declare(strict_types=1);

namespace Tutorial\RAG;

use HelgeSverre\Toon\Toon;

/**
 * Complete RAG system for PHP documentation
 */
class RAG
{
    private VectorStore $vectorStore;
    private array $searchHistory = [];

    public function __construct(VectorStore $vectorStore)
    {
        $this->vectorStore = $vectorStore;
    }

    /**
     * Build context for LLM from search results
     *
     * @param string $query User query
     * @param array $results Search results
     * @return string Context formatted with TOON
     */
    public function buildContext(string $query, array $results): string
    {
        if (empty($results)) {
            return "No relevant documentation found for: {$query}";
        }

        // Prepare results for TOON encoding
        $contextDocs = [];
        foreach ($results as $result) {
            $contextDocs[] = [
                'title' => $result['metadata']['title'],
                'category' => $result['metadata']['category'],
                'relevance' => round($result['score'], 2),
                'summary' => $this->truncateText($result['text'], 200)
            ];
        }

        // Use TOON to encode the context efficiently (tabular format for uniform results)
        $encodedContext = toon_tabular($contextDocs);

        return "Question: {$query}\n\n" .
               "Relevant Documentation:\n" .
               $encodedContext;
    }

    /**
     * Answer a question using RAG
     *
     * @param string $question User question
     * @param int $topK Number of documents to retrieve
     * @param float $threshold Minimum similarity threshold
     * @return array Answer details
     */
    public function answer(
        string $question,
        int $topK = 3,
        float $threshold = 0.3
    ): array {
        // Step 1: Retrieve relevant documents
        $startTime = microtime(true);
        $results = $this->vectorStore->search($question, $topK, $threshold);
        $searchTime = microtime(true) - $startTime;

        // Step 2: Build context with TOON
        $context = $this->buildContext($question, $results);

        // Step 3: Compare compression efficiency
        $jsonContext = json_encode([
            'question' => $question,
            'results' => array_map(fn($r) => [
                'title' => $r['metadata']['title'],
                'category' => $r['metadata']['category'],
                'score' => $r['score'],
                'text' => $this->truncateText($r['text'], 200)
            ], $results)
        ], JSON_PRETTY_PRINT);

        $toonSize = strlen($context);
        $jsonSize = strlen($jsonContext);
        $savings = round(($jsonSize - $toonSize) / $jsonSize * 100, 1);

        // Step 4: Store in history
        $this->searchHistory[] = [
            'question' => $question,
            'timestamp' => time(),
            'results_count' => count($results)
        ];

        return [
            'question' => $question,
            'context' => $context,
            'results' => $results,
            'stats' => [
                'results_count' => count($results),
                'search_time_ms' => round($searchTime * 1000, 2),
                'toon_size_bytes' => $toonSize,
                'json_size_bytes' => $jsonSize,
                'compression_savings' => $savings . '%',
                'sources' => array_map(
                    fn($r) => $r['metadata']['title'],
                    $results
                )
            ]
        ];
    }

    /**
     * Perform multiple searches and show comparisons
     *
     * @param array $queries List of queries to test
     * @return array Comparison results
     */
    public function compareSearches(array $queries): array
    {
        $comparisons = [];

        foreach ($queries as $query) {
            echo "\n=== Query: {$query} ===\n";

            $answer = $this->answer($query);

            echo "Found {$answer['stats']['results_count']} relevant documents:\n";
            foreach ($answer['results'] as $i => $result) {
                echo sprintf(
                    "  %d. %s (%.1f%% match)\n",
                    $i + 1,
                    $result['metadata']['title'],
                    $result['score'] * 100
                );
            }

            echo "\nCompression Stats:\n";
            echo "  JSON size: {$answer['stats']['json_size_bytes']} bytes\n";
            echo "  TOON size: {$answer['stats']['toon_size_bytes']} bytes\n";
            echo "  Savings: {$answer['stats']['compression_savings']}\n";

            $comparisons[] = $answer;
        }

        return $comparisons;
    }

    /**
     * Get search history
     *
     * @return array Search history
     */
    public function getHistory(): array
    {
        return $this->searchHistory;
    }

    /**
     * Truncate text to specified length
     *
     * @param string $text Text to truncate
     * @param int $maxLength Maximum length
     * @return string Truncated text
     */
    private function truncateText(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3) . '...';
    }
}
```

---

## Section 8: Complete RAG Example

Let's put it all together in a complete working example:

```php
<?php
// index.php - Complete RAG System

declare(strict_types=1);

require 'vendor/autoload.php';

use Tutorial\RAG\OllamaClient;
use Tutorial\RAG\VectorStore;
use Tutorial\RAG\KnowledgeBase;
use Tutorial\RAG\RAG;

// Initialize components
echo "=== PHP Documentation RAG System ===\n\n";

// Step 1: Setup Ollama client
echo "Initializing Ollama client...\n";
$embedder = new OllamaClient();

if (!$embedder->healthCheck()) {
    echo "Error: Ollama is not running or model not available\n";
    echo "Please ensure Ollama is running and execute:\n";
    echo "  ollama pull mxbai-embed-large\n";
    exit(1);
}
echo "✓ Ollama client ready\n\n";

// Step 2: Create vector store
echo "Creating vector store...\n";
$vectorStore = new VectorStore($embedder);
echo "✓ Vector store initialized\n\n";

// Step 3: Index documents
$indexStats = KnowledgeBase::indexDocuments($vectorStore);
echo "\nIndexing complete:\n";
echo "  Documents indexed: {$indexStats['indexed']}\n";
echo "  Failed: {$indexStats['failed']}\n";

// Show vector store statistics
$storeStats = $vectorStore->getStats();
echo "\nVector Store Statistics:\n";
echo "  Total documents: {$storeStats['total_documents']}\n";
echo "  Vector dimensions: {$storeStats['vector_dimensions']}\n";
echo "  Embedding storage: {$storeStats['total_embedding_size_mb']} MB\n";
echo "  Metadata compression: {$storeStats['compression_ratio']}%\n";
echo "  Bytes saved by TOON: {$storeStats['bytes_saved_by_toon']}\n";

// Step 4: Create RAG system
echo "\n=== Starting Q&A System ===\n";
$rag = new RAG($vectorStore);

// Test queries
$testQueries = [
    "How do I connect to a database in PHP?",
    "What are the different ways to handle errors?",
    "How can I manage dependencies in my PHP project?",
    "What security measures should I implement?",
    "How do I work with files and uploads?"
];

// Run searches
$results = $rag->compareSearches($testQueries);

// Summary statistics
echo "\n=== Summary ===\n";
$totalSavings = 0;
$totalQueries = count($results);

foreach ($results as $result) {
    $savings = floatval(str_replace('%', '', $result['stats']['compression_savings']));
    $totalSavings += $savings;
}

$avgSavings = round($totalSavings / $totalQueries, 1);
echo "Average TOON compression savings: {$avgSavings}%\n";
echo "Total searches performed: {$storeStats['total_searches']}\n";

// Interactive mode
echo "\n=== Interactive Mode ===\n";
echo "Enter your questions (or 'quit' to exit):\n";

while (true) {
    echo "\n> ";
    $input = trim(fgets(STDIN));

    if (strtolower($input) === 'quit') {
        break;
    }

    if (empty($input)) {
        continue;
    }

    $answer = $rag->answer($input);

    echo "\nRelevant documents found: {$answer['stats']['results_count']}\n";

    if (!empty($answer['results'])) {
        echo "\nTop matches:\n";
        foreach ($answer['results'] as $i => $result) {
            echo sprintf(
                "%d. %s (%.1f%% relevant)\n   Category: %s\n   URL: %s\n",
                $i + 1,
                $result['metadata']['title'],
                $result['score'] * 100,
                $result['metadata']['category'],
                $result['metadata']['url']
            );
        }

        echo "\nContext (TOON format):\n";
        echo "------------------------\n";
        echo $answer['context'];
        echo "\n------------------------\n";
        echo "Compression: {$answer['stats']['compression_savings']} saved vs JSON\n";
    } else {
        echo "No relevant documents found. Try rephrasing your question.\n";
    }
}

echo "\nGoodbye!\n";
```

---

## Section 9: Troubleshooting

### Common Issues and Solutions

**Ollama not running:**

```bash
# Check if Ollama is running
curl http://localhost:11434

# If not, start Ollama
ollama serve

# Or on macOS with brew
brew services start ollama
```

**Model not found:**

```bash
# List available models
ollama list

# Pull the embedding model
ollama pull mxbai-embed-large

# Alternative smaller model
ollama pull all-minilm
```

**Low similarity scores:**

- Different embedding models have different score ranges
- Adjust threshold based on your model (try 0.2-0.5)
- Ensure text preprocessing is consistent

**Memory issues:**

- Vector stores hold everything in RAM
- For this demo, limit to 10-20 documents
- In production, use a vector database

**Slow embedding generation:**

- Ollama runs on CPU by default
- For GPU acceleration, ensure CUDA/Metal support
- Consider batching embeddings for bulk indexing

**Connection errors:**

```php
// Add retry logic for resilience
$maxRetries = 3;
$retryDelay = 1; // seconds

for ($i = 0; $i < $maxRetries; $i++) {
    try {
        $embedding = $client->getEmbedding($text);
        break;
    } catch (\Exception $e) {
        if ($i === $maxRetries - 1) throw $e;
        sleep($retryDelay);
    }
}
```

---

## Section 10: Production Considerations

### What to Use in Production

**Vector Databases:**

- **Cloud**: Pinecone, Qdrant Cloud, Weaviate Cloud
- **Self-hosted**: pgvector, Qdrant, Milvus, ChromaDB
- **Serverless**: Upstash Vector, Cloudflare Vectorize

**Document Processing:**

- **Chunking**: Split documents into overlapping chunks (500-1000 tokens)
- **Metadata extraction**: Parse structured data from documents
- **Deduplication**: Remove duplicate or near-duplicate content

**Search Improvements:**

- **Hybrid search**: Combine vector search with keyword search (BM25)
- **Reranking**: Use cross-encoders to rerank results
- **Query expansion**: Generate multiple query variations

**Performance Optimization:**

- **Caching**: Cache embeddings and frequent queries
- **Batch processing**: Process multiple documents in parallel
- **Async operations**: Use async/await for I/O operations

**Where TOON Provides Value:**

1. **Metadata Compression**: 40-60% reduction in metadata storage
2. **Context Windows**: More documents fit in LLM context limits
3. **API Costs**: Reduced token usage when calling LLMs
4. **Network Transfer**: Smaller payloads between services

### Example Production Architecture

```
User Query → API Gateway → Query Service
                               ↓
                         Vector Database
                               ↓
                         Reranking Model
                               ↓
                         TOON Compression → LLM Service
                                               ↓
                                           Response
```

---

## Summary

Congratulations! You've built a functional RAG system from scratch. You learned:

1. **Vector Embeddings**: How to generate embeddings with Ollama
2. **Similarity Search**: Implementing cosine similarity from scratch
3. **Vector Storage**: Building a simple in-memory vector store
4. **TOON Integration**: Using TOON to compress metadata efficiently
5. **RAG Architecture**: Understanding the complete retrieval pipeline

### Key Takeaways

- RAG enhances LLM responses with relevant context
- Vector embeddings capture semantic meaning
- TOON reduces metadata size by 40-60%
- Production systems need specialized vector databases
- Hybrid approaches (vector + keyword) work best

### Next Steps

1. **Try different embedding models**: Experiment with sentence-transformers
2. **Implement chunking**: Split long documents intelligently
3. **Add persistence**: Save vector store to disk
4. **Build an API**: Create REST endpoints for your RAG system
5. **Explore vector databases**: Try pgvector or Qdrant

### Additional Resources

- [Ollama Documentation](https://github.com/ollama/ollama)
- [TOON PHP Documentation](https://github.com/helgesverre/toon-php)
- [Vector Database Comparison](https://vectordb.com)
- [RAG Best Practices](https://www.pinecone.io/learn/retrieval-augmented-generation/)

---

## Complete Code Repository

Find the complete working code at:

```bash
git clone https://github.com/your-username/php-rag-tutorial
cd php-rag-tutorial
composer install
php index.php
```

Remember: This tutorial provides a foundation for understanding RAG systems. For production use, leverage specialized tools and databases designed for scale and performance.
