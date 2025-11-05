<?php

declare(strict_types=1);

namespace Benchmarks\Performance;

use HelgeSverre\Toon\Toon;

/**
 * Benchmarks scalability across increasing data sizes.
 *
 * Measures how performance characteristics change with data size:
 * - Execution time growth (O(n) complexity)
 * - Memory usage growth
 * - Performance curves for encode/decode operations
 * - Identifies potential performance bottlenecks at scale
 *
 * Tests data sizes: 10, 50, 100, 500, 1K, 5K, 10K, 50K, 100K items
 */
class ScalabilityBench
{
    private array $size10;

    private array $size50;

    private array $size100;

    private array $size500;

    private array $size1k;

    private array $size5k;

    private array $size10k;

    private array $size50k;

    private array $size100k;

    public function __construct()
    {
        $this->size10 = $this->generateData(10);
        $this->size50 = $this->generateData(50);
        $this->size100 = $this->generateData(100);
        $this->size500 = $this->generateData(500);
        $this->size1k = $this->generateData(1000);
        $this->size5k = $this->generateData(5000);
        $this->size10k = $this->generateData(10000);
        $this->size50k = $this->generateData(50000);
        $this->size100k = $this->generateData(100000);
    }

    /**
     * @ParamProviders("provideSizes")
     *
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchEncodeScalability(array $params): void
    {
        Toon::encode($params['data']);
    }

    /**
     * @ParamProviders("provideSizes")
     *
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchDecodeScalability(array $params): void
    {
        $toon = Toon::encode($params['data']);
        Toon::decode($toon);
    }

    /**
     * Provide different data sizes for parameterized benchmarks.
     */
    public function provideSizes(): \Generator
    {
        yield '10 items' => ['data' => $this->size10];
        yield '50 items' => ['data' => $this->size50];
        yield '100 items' => ['data' => $this->size100];
        yield '500 items' => ['data' => $this->size500];
        yield '1K items' => ['data' => $this->size1k];
        yield '5K items' => ['data' => $this->size5k];
        yield '10K items' => ['data' => $this->size10k];
        yield '50K items' => ['data' => $this->size50k];
        yield '100K items' => ['data' => $this->size100k];
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(10)
     */
    public function benchEncode10Items(): void
    {
        Toon::encode($this->size10);
    }

    /**
     * @Revs(5000)
     *
     * @Iterations(10)
     */
    public function benchEncode50Items(): void
    {
        Toon::encode($this->size50);
    }

    /**
     * @Revs(2000)
     *
     * @Iterations(10)
     */
    public function benchEncode100Items(): void
    {
        Toon::encode($this->size100);
    }

    /**
     * @Revs(500)
     *
     * @Iterations(10)
     */
    public function benchEncode500Items(): void
    {
        Toon::encode($this->size500);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(10)
     */
    public function benchEncode1kItems(): void
    {
        Toon::encode($this->size1k);
    }

    /**
     * @Revs(50)
     *
     * @Iterations(10)
     */
    public function benchEncode5kItems(): void
    {
        Toon::encode($this->size5k);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(10)
     */
    public function benchEncode10kItems(): void
    {
        Toon::encode($this->size10k);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(5)
     */
    public function benchEncode50kItems(): void
    {
        Toon::encode($this->size50k);
    }

    /**
     * @Revs(2)
     *
     * @Iterations(5)
     */
    public function benchEncode100kItems(): void
    {
        Toon::encode($this->size100k);
    }

    /**
     * Generate test data with consistent structure.
     */
    private function generateData(int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'id' => $i + 1,
                'name' => "Item {$i}",
                'value' => rand(1, 10000),
                'category' => ['A', 'B', 'C', 'D'][rand(0, 3)],
                'active' => (bool) ($i % 2),
                'price' => round(rand(100, 10000) / 100, 2),
                'created' => date('Y-m-d', strtotime("-{$i} days")),
            ];
        }

        return $result;
    }
}
