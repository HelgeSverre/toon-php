<?php

namespace Benchmarks;

use GuzzleHttp\Client;
use Throwable;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Builds the ordered list of token counters usable in the current environment.
 *
 * Local counters (a rough char/4 estimate and the tiktoken tokenizer) are always
 * available. Hosted counters (Anthropic, Gemini, OpenAI) are included only when
 * their API key is present and a probe call succeeds; otherwise they are skipped
 * with a one-line reason so the benchmark still runs cleanly.
 */
final class Counters
{
    /**
     * @return array<int, TokenCounter>
     */
    public static function build(): array
    {
        $counters = [];

        // --- Local: always available, no API key ---

        // Rough guess: ~4 characters per token.
        $counters[] = new TokenCounter(
            'Estimate (chars/4)',
            static fn (string $text): int => (int) ceil(mb_strlen($text) / 4)
        );

        // Real OpenAI tokenizer (o200k_base, used by GPT-4o/5), counted locally.
        $tiktoken = self::tiktoken();
        if ($tiktoken !== null) {
            $counters[] = $tiktoken;
        }

        // --- Hosted: only when the key is set and a probe succeeds ---

        $anthropicKey = getenv('ANTHROPIC_API_KEY') ?: null;
        if ($anthropicKey !== null) {
            $models = ['claude-haiku-4-5' => 'Anthropic Haiku 4.5', 'claude-sonnet-5' => 'Anthropic Sonnet 5'];
            foreach ($models as $model => $label) {
                $counter = self::probe($label, static fn (string $text): int => self::anthropic($anthropicKey, $model, $text));
                if ($counter !== null) {
                    $counters[] = $counter;
                }
            }
        } else {
            self::skip('Anthropic', 'ANTHROPIC_API_KEY');
        }

        $geminiKey = getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: null;
        if ($geminiKey !== null) {
            $counter = self::probe('Gemini 2.5 Flash', static fn (string $text): int => self::gemini($geminiKey, 'gemini-2.5-flash', $text));
            if ($counter !== null) {
                $counters[] = $counter;
            }
        } else {
            self::skip('Gemini', 'GEMINI_API_KEY');
        }

        $openaiKey = getenv('OPENAI_API_KEY') ?: null;
        if ($openaiKey !== null) {
            $counter = self::probe('OpenAI GPT-5.1', static fn (string $text): int => self::openai($openaiKey, 'gpt-5.1', $text));
            if ($counter !== null) {
                $counters[] = $counter;
            }
        } else {
            self::skip('OpenAI', 'OPENAI_API_KEY');
        }

        return $counters;
    }

    /**
     * Validate a hosted counter with a single probe call. Returns null (and prints
     * a skip notice) if the probe fails — bad key, retired model, network, etc.
     *
     * @param  callable(string): int  $counter
     */
    private static function probe(string $label, callable $counter): ?TokenCounter
    {
        try {
            $counter('probe');
        } catch (Throwable $e) {
            fwrite(STDERR, "  ⚠️  Skipping {$label}: ".self::shorten($e->getMessage())."\n");

            return null;
        }

        return new TokenCounter($label, $counter);
    }

    private static function tiktoken(): ?TokenCounter
    {
        // Optional dependency — install it to enable this counter.
        if (! class_exists(EncoderProvider::class)) {
            fwrite(STDERR, "  ⚠️  Skipping tiktoken (o200k): not installed (composer require --dev yethee/tiktoken).\n");

            return null;
        }

        try {
            $encoder = (new EncoderProvider)->get('o200k_base');
            $encoder->encode('probe');
        } catch (Throwable $e) {
            fwrite(STDERR, '  ⚠️  Skipping tiktoken (o200k): '.self::shorten($e->getMessage())."\n");

            return null;
        }

        return new TokenCounter(
            'tiktoken (o200k)',
            static fn (string $text): int => count($encoder->encode($text))
        );
    }

    private static function anthropic(string $key, string $model, string $text): int
    {
        $response = self::http()->post('https://api.anthropic.com/v1/messages/count_tokens', [
            'headers' => [
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $text]],
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        return (int) ($data['input_tokens'] ?? 0);
    }

    private static function gemini(string $key, string $model, string $text): int
    {
        $response = self::http()->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:countTokens?key={$key}",
            [
                'headers' => ['content-type' => 'application/json'],
                'json' => ['contents' => [['parts' => [['text' => $text]]]]],
            ]
        );

        $data = json_decode((string) $response->getBody(), true);

        return (int) ($data['totalTokens'] ?? 0);
    }

    private static function openai(string $key, string $model, string $text): int
    {
        $response = self::http()->post('https://api.openai.com/v1/responses/input_tokens', [
            'headers' => [
                'authorization' => "Bearer {$key}",
                'content-type' => 'application/json',
            ],
            'json' => ['model' => $model, 'input' => $text],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        return (int) ($data['input_tokens'] ?? 0);
    }

    private static function http(): Client
    {
        static $client = null;

        return $client ??= new Client(['timeout' => 30]);
    }

    private static function skip(string $provider, string $envVar): void
    {
        fwrite(STDERR, "  ⚠️  Skipping {$provider}: {$envVar} not set.\n");
    }

    private static function shorten(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);

        return mb_strlen($message) > 120 ? mb_substr($message, 0, 117).'...' : $message;
    }
}
