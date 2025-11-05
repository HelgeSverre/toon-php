<?php

declare(strict_types=1);

namespace Benchmarks\Performance;

use HelgeSverre\Toon\Toon;

/**
 * Benchmarks throughput (operations per second) for encode/decode operations.
 *
 * Measures sustained performance to help understand:
 * - How many encode operations can be handled per second
 * - How many decode operations can be handled per second
 * - Throughput for typical real-world data structures
 * - Performance under continuous load
 */
class ThroughputBench
{
    private array $typicalApiResponse;

    private string $typicalApiResponseToon;

    public function __construct()
    {
        // Typical API response structure (realistic size)
        $this->typicalApiResponse = [
            'data' => $this->generateApiData(20),
            'meta' => [
                'total' => 20,
                'page' => 1,
                'per_page' => 20,
                'has_more' => false,
            ],
        ];

        $this->typicalApiResponseToon = Toon::encode($this->typicalApiResponse);
    }

    /**
     * @Revs(5000)
     *
     * @Iterations(10)
     */
    public function benchEncodeThroughput(): void
    {
        Toon::encode($this->typicalApiResponse);
    }

    /**
     * @Revs(5000)
     *
     * @Iterations(10)
     */
    public function benchDecodeThroughput(): void
    {
        Toon::decode($this->typicalApiResponseToon);
    }

    /**
     * @Revs(2000)
     *
     * @Iterations(10)
     */
    public function benchRoundTripThroughput(): void
    {
        // Encode then decode (complete round trip)
        $encoded = Toon::encode($this->typicalApiResponse);
        Toon::decode($encoded);
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(10)
     */
    public function benchEncodeSmallPayload(): void
    {
        // Small payload (like a single user object)
        $data = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'active' => true,
        ];
        Toon::encode($data);
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(10)
     */
    public function benchDecodeSmallPayload(): void
    {
        // Pre-encoded small payload
        $toon = "id: 123\nname: John Doe\nemail: john@example.com\nactive: true";
        Toon::decode($toon);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchEncodeLargePayload(): void
    {
        // Large payload (like paginated list of 100 items)
        $data = [
            'data' => $this->generateApiData(100),
            'meta' => [
                'total' => 1000,
                'page' => 1,
                'per_page' => 100,
            ],
        ];
        Toon::encode($data);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function benchDecodeLargePayload(): void
    {
        $data = [
            'data' => $this->generateApiData(100),
            'meta' => [
                'total' => 1000,
                'page' => 1,
                'per_page' => 100,
            ],
        ];
        $toon = Toon::encode($data);
        Toon::decode($toon);
    }

    /**
     * Generate realistic API data.
     */
    private function generateApiData(int $count): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'id' => $i + 1,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'role' => ['admin', 'user', 'moderator'][rand(0, 2)],
                'active' => (bool) ($i % 3),
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
                'metadata' => [
                    'login_count' => rand(1, 100),
                    'last_ip' => "192.168.1.{$i}",
                ],
            ];
        }

        return $result;
    }
}
