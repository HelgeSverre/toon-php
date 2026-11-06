<?php

declare(strict_types=1);

// Run in separate processes against the same Mate installation before and after
// swapping only its TOON dependency, then compare stdout byte-for-byte.
// php tests/Support/probe-mate.php /path/to/mate/vendor/autoload.php
require $argv[1] ?? throw new InvalidArgumentException('Supply Mate\'s Composer autoloader');

use Symfony\AI\Mate\Encoding\ResponseEncoder;

$payloads = [
    'empty' => [],
    'metadata' => ['name' => 'server-info', 'description' => 'Inspect the runtime', 'enabled' => true],
    'table' => ['tools' => [['name' => 'inspect', 'count' => 1], ['name' => 'logs', 'count' => 2]]],
    'nested' => ['request' => ['headers' => ['content-type' => 'application/json'], 'body' => ['ok' => true, 'value' => null]]],
    'quoted keys' => ['App\\Service::__invoke()' => 'callable', 'order:id' => 7, 'a,b' => 'comma'],
    'numeric strings' => ['id' => '007', 'answer' => '42', 'enabled' => 'true', 'nothing' => 'null', 'plus' => '+1'],
    'text' => ['log' => "#comment\nline\twith \"quotes\" and \\slashes", 'unicode' => 'Oslo é 😀'],
    'empty values' => ['empty' => [], 'object' => new stdClass, 'blank' => '', 'nothing' => null],
    'numeric keys' => ['values' => [0 => 'zero', 2 => 'two']],
    'mixed list' => ['items' => [1, 'two', false, null, ['nested' => 'value']]],
    'numbers' => ['integer' => PHP_INT_MAX, 'fraction' => 1.25, 'small' => 1e-7, 'large' => 1e21],
    'notice collision' => ['_security_notice' => 'application value', 'untrusted_data' => ['text' => 'application value']],
];

$results = [];
foreach ($payloads as $name => $payload) {
    $wire = ResponseEncoder::encode($payload);
    $untrusted = ResponseEncoder::encodeUntrusted($payload);
    $envelope = ResponseEncoder::decode($untrusted);
    if (! is_array($envelope) || $envelope['_security_notice'] !== ResponseEncoder::UNTRUSTED_NOTICE) {
        throw new RuntimeException('Untrusted envelope changed: '.$name);
    }
    $results[$name] = [
        'wire' => $wire,
        'decoded' => ResponseEncoder::decode($wire),
        'tryDecoded' => ResponseEncoder::tryDecode($wire),
        'untrustedWire' => $untrusted,
        'untrustedDecoded' => $envelope,
    ];
}
foreach (['42', 'true', 'null', '#hello', 'plain text', '"unterminated'] as $text) {
    $results['text/'.$text] = ResponseEncoder::tryDecode($text);
}

// serialize retains PHP integer/float/string types and array key order.
echo serialize($results);
