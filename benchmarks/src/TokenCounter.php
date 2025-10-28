<?php

namespace Benchmarks;

use GuzzleHttp\Client;

class TokenCounter
{
    private ?string $apiKey;

    private Client $client;

    private bool $useApi;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: getenv('ANTHROPIC_API_KEY') ?: null;
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'timeout' => 30,
        ]);
        $this->useApi = ! empty($this->apiKey);
    }

    /**
     * Count tokens in the given text
     */
    public function count(string $text): int
    {
        if ($this->useApi) {
            return $this->countWithApi($text);
        }

        return $this->estimateTokens($text);
    }

    /**
     * Count tokens using the Anthropic API
     */
    private function countWithApi(string $text): int
    {
        try {
            $response = $this->client->post('/v1/messages/count_tokens', [
                'headers' => [
                    'anthropic-version' => '2023-06-01',
                    'x-api-key' => $this->apiKey,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $text,
                        ],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['input_tokens'] ?? 0;
        } catch (\Exception $e) {
            // Fall back to estimation if API fails
            echo "Warning: API token counting failed, using estimation: {$e->getMessage()}\n";

            return $this->estimateTokens($text);
        }
    }

    /**
     * Estimate token count based on character count
     * Claude tokens are roughly 4 characters per token on average
     */
    private function estimateTokens(string $text): int
    {
        // More sophisticated estimation based on Claude's tokenizer characteristics
        // Claude uses ~3.5-4 characters per token on average
        $charCount = mb_strlen($text, 'UTF-8');

        // Count words and special characters
        $wordCount = str_word_count($text);
        $specialChars = preg_match_all('/[^\w\s]/', $text);

        // Estimate: average of char/4 and word count (with special char penalty)
        $charEstimate = $charCount / 4;
        $wordEstimate = $wordCount + ($specialChars * 0.5);

        return (int) ceil(($charEstimate + $wordEstimate) / 2);
    }

    /**
     * Check if API-based counting is available
     */
    public function isUsingApi(): bool
    {
        return $this->useApi;
    }

    /**
     * Get the counting method being used
     */
    public function getMethod(): string
    {
        return $this->useApi ? 'Anthropic API' : 'Estimation (char/word count)';
    }
}
