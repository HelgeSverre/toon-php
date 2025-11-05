<?php

declare(strict_types=1);

namespace Benchmarks\Performance;

use HelgeSverre\Toon\Toon;

/**
 * Benchmarks encoding performance across different data sizes and structures.
 *
 * Tests encoding of various data structures to measure:
 * - Execution time (via PHPBench iterations)
 * - Memory usage (via PHPBench memory tracking)
 * - Performance across different data sizes
 * - Performance for different TOON format types
 */
class EncodeBench
{
    private array $smallData;

    private array $mediumData;

    private array $largeData;

    private array $xlargeData;

    private array $inlineFormatData;

    private array $tabularFormatData;

    private array $listFormatData;

    private array $nestedArrayData;

    public function __construct()
    {
        // Size-based test data
        $this->smallData = $this->generateObjectArray(10);
        $this->mediumData = $this->generateObjectArray(100);
        $this->largeData = $this->generateObjectArray(1000);
        $this->xlargeData = $this->generateObjectArray(10000);

        // Format-specific test data
        $this->inlineFormatData = ['values' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']];
        $this->tabularFormatData = $this->generateObjectArray(50);
        $this->listFormatData = $this->generateMixedStructure();
        $this->nestedArrayData = $this->generateNestedArrays();
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(10)
     */
    public function benchEncodeSmall(): void
    {
        Toon::encode($this->smallData);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchEncodeMedium(): void
    {
        Toon::encode($this->mediumData);
    }

    /**
     * @Revs(100)
     *
     * @Iterations(10)
     */
    public function benchEncodeLarge(): void
    {
        Toon::encode($this->largeData);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(10)
     */
    public function benchEncodeXLarge(): void
    {
        Toon::encode($this->xlargeData);
    }

    /**
     * @Revs(5000)
     *
     * @Iterations(10)
     */
    public function benchEncodeInlineFormat(): void
    {
        Toon::encode($this->inlineFormatData);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchEncodeTabularFormat(): void
    {
        Toon::encode($this->tabularFormatData);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchEncodeListFormat(): void
    {
        Toon::encode($this->listFormatData);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchEncodeNestedArrays(): void
    {
        Toon::encode($this->nestedArrayData);
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(10)
     */
    public function benchEncodePrimitives(): void
    {
        $data = [
            'string' => 'hello world',
            'int' => 42,
            'float' => 3.14159,
            'bool' => true,
            'null' => null,
        ];
        Toon::encode($data);
    }

    /**
     * @Revs(5000)
     *
     * @Iterations(10)
     */
    public function benchEncodeDeeplyNested(): void
    {
        $data = [
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
        ];
        Toon::encode($data);
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
