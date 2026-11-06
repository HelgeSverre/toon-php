<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

require_once __DIR__.'/Fixtures/mate/ResponseEncoder.php';

final class MateCompatibilityTest extends TestCase
{
    public function test_response_encoder_keeps_payload_and_notice_separate(): void
    {
        $payload = ['_security_notice' => 'application value', 'entries' => [['message' => '#hello', 'level' => 'INFO']]];
        $encoded = ResponseEncoder::encodeUntrusted($payload);
        $decoded = ResponseEncoder::decode($encoded);
        $this->assertIsArray($decoded);
        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);
        $this->assertSame($payload, $decoded['untrusted_data']);
        $this->assertSame($decoded, ResponseEncoder::tryDecode($encoded));
        $empty = ResponseEncoder::decode(ResponseEncoder::encodeUntrusted([]));
        $this->assertIsArray($empty);
        $this->assertSame([], $empty['untrusted_data']);
    }

    public function test_mate_keeps_plain_text_and_decodes_structures_as_arrays(): void
    {
        foreach (['42', 'true', 'null', 'plain text'] as $text) {
            $this->assertSame($text, ResponseEncoder::tryDecode($text));
        }
        $data = ['tools' => [['name' => 'inspect', 'description' => 'Inspect a service']], 'count' => 1];
        $this->assertSame($data, ResponseEncoder::decode(ResponseEncoder::encode($data)));
    }
}
