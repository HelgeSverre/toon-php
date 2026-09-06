<?php

declare(strict_types=1);

// Run with php -n: Composer's development polyfills must not hide runtime dependencies.
spl_autoload_register(static function (string $class): void {
    $prefix = 'HelgeSverre\\Toon\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2).'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});
$encode = new HelgeSverre\Toon\EncodeOptions(version: HelgeSverre\Toon\SpecVersion::V4_1);
$decode = new HelgeSverre\Toon\DecodeOptions(version: HelgeSverre\Toon\SpecVersion::V4_1);
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
echo "4.1 runtime checks passed without Composer or optional extensions.\n";
