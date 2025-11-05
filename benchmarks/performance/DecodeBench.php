<?php

declare(strict_types=1);

namespace Benchmarks\Performance;

use HelgeSverre\Toon\Toon;

/**
 * Benchmarks decoding performance across different data sizes and structures.
 *
 * Tests decoding of TOON-formatted strings to measure:
 * - Execution time for parsing
 * - Memory usage during decoding
 * - Performance across different data sizes
 * - Performance for different TOON format types
 */
class DecodeBench
{
    private string $smallToon;

    private string $mediumToon;

    private string $largeToon;

    private string $xlargeToon;

    private string $inlineFormatToon;

    private string $tabularFormatToon;

    private string $listFormatToon;

    private string $nestedArrayToon;

    public function __construct()
    {
        // Pre-encode test data for decoding benchmarks
        $this->smallToon = Toon::encode($this->generateObjectArray(10));
        $this->mediumToon = Toon::encode($this->generateObjectArray(100));
        $this->largeToon = Toon::encode($this->generateObjectArray(1000));
        $this->xlargeToon = Toon::encode($this->generateObjectArray(10000));

        // Format-specific test data
        $this->inlineFormatToon = Toon::encode(['values' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']]);
        $this->tabularFormatToon = Toon::encode($this->generateObjectArray(50));
        $this->listFormatToon = Toon::encode($this->generateMixedStructure());
        $this->nestedArrayToon = Toon::encode($this->generateNestedArrays());
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(10)
     */
    public function benchDecodeSmall(): void
    {
        Toon::decode($this->smallToon);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchDecodeMedium(): void
    {
        Toon::decode($this->mediumToon);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(10)
     */
    public function benchDecodeLarge(): void
    {
        Toon::decode($this->largeToon);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(10)
     */
    public function benchDecodeXLarge(): void
    {
        Toon::decode($this->xlargeToon);
    }

    /**
     * @Revs(5000)
     *
     * @Iterations(10)
     */
    public function benchDecodeInlineFormat(): void
    {
        Toon::decode($this->inlineFormatToon);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchDecodeTabularFormat(): void
    {
        Toon::decode($this->tabularFormatToon);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchDecodeListFormat(): void
    {
        Toon::decode($this->listFormatToon);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchDecodeNestedArrays(): void
    {
        Toon::decode($this->nestedArrayToon);
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(10)
     */
    public function benchDecodePrimitives(): void
    {
        $toon = Toon::encode([
            'string' => 'hello world',
            'int' => 42,
            'float' => 3.14159,
            'bool' => true,
            'null' => null,
        ]);
        Toon::decode($toon);
    }

    /**
     * @Revs(5000)
     *
     * @Iterations(10)
     */
    public function benchDecodeDeeplyNested(): void
    {
        $toon = Toon::encode([
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => [
                                'data' => 'deep value',
                                'count' => 123,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Toon::decode($toon);
    }

    /**
     * Generate array of objects (simulating typical API responses).
     */
    private function generateObjectArray(int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'id' => $i + 1,
                'name' => "Item {$i}",
                'value' => rand(1, 1000),
                'active' => (bool) ($i % 2),
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
            ];
        }

        return $result;
    }

    /**
     * Generate mixed structure with different types.
     */
    private function generateMixedStructure(): array
    {
        return [
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'posts' => [
                ['id' => 1, 'title' => 'First Post'],
                ['id' => 2, 'title' => 'Second Post'],
            ],
            'meta' => [
                'total' => 2,
                'page' => 1,
            ],
        ];
    }

    /**
     * Generate nested array structure (array-of-arrays format).
     */
    private function generateNestedArrays(): array
    {
        return [
            ['a', 'b', 'c'],
            ['d', 'e', 'f'],
            ['g', 'h', 'i'],
            ['j', 'k', 'l'],
        ];
    }
}
