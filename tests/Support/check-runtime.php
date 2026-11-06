<?php

declare(strict_types=1);

// Run with php -n: Composer's development polyfills must not hide runtime dependencies.
spl_autoload_register(static function (string $class): void {
    $prefix = 'HelgeSverre\\Toon\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2).'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});
foreach ([HelgeSverre\Toon\SpecVersion::V4_1, HelgeSverre\Toon\SpecVersion::V4_4] as $version) {
    $encode = new HelgeSverre\Toon\EncodeOptions(version: $version);
    $decode = new HelgeSverre\Toon\DecodeOptions(version: $version);
    $input = ['users' => ['a' => ['name' => 'Ada', 'address' => ['city' => 'Oslo']], 'b' => ['name' => 'Bo', 'address' => ['city' => 'Bergen']]]];
    $document = HelgeSverre\Toon\Toon::encode($input, $encode);
    if (HelgeSverre\Toon\Toon::decode("#comment\n".$document, $decode) !== $input) {
        throw new RuntimeException('Nested keyed table failed to round-trip');
    }
    if (HelgeSverre\Toon\Toon::decode('"\\u0041\\u00e9\\u20ac"', $decode) !== 'Aé€') {
        throw new RuntimeException('Unicode escape conversion failed');
    }
    if (HelgeSverre\Toon\Toon::validate("\xFF", $decode)) {
        throw new RuntimeException('Invalid UTF-8 was accepted');
    }
    echo $version->value." runtime checks passed without Composer or optional extensions.\n";
}
