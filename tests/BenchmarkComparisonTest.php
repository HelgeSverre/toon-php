<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Tests\Support\BenchmarkComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BenchmarkComparisonTest extends TestCase
{
    public function test_threshold_boundary_and_speedups(): void
    {
        $this->assertFalse(BenchmarkComparison::compare(['case' => 100.0], ['case' => 115.0])['regression']);
        $this->assertFalse(BenchmarkComparison::compare(['case' => 100.0], ['case' => 70.0])['regression']);
        $result = BenchmarkComparison::compare(['case' => 100.0], ['case' => 116.0]);
        $this->assertTrue($result['regression']);
        $this->assertStringContainsString('+16.00%', $result['markdown']);
    }

    public function test_missing_workloads_fail_instead_of_passing(): void
    {
        $this->expectException(RuntimeException::class);
        BenchmarkComparison::compare(['first' => 1.0, 'second' => 2.0], ['first' => 1.0]);
    }

    public function test_medians_limit_the_effect_of_an_outlier(): void
    {
        $values = $this->read('<iteration time-avg="100"/><iteration time-avg="10000"/><iteration time-avg="101"/>');
        $this->assertSame([101.0], array_values($values));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMeasurements(): iterable
    {
        yield 'empty' => [''];
        yield 'too few samples' => ['<iteration time-avg="1"/>'];
        yield 'missing time' => ['<iteration/><iteration/><iteration/>'];
        yield 'zero' => ['<iteration time-avg="0"/><iteration time-avg="1"/><iteration time-avg="2"/>'];
        yield 'infinite' => ['<iteration time-avg="1e999"/><iteration time-avg="1"/><iteration time-avg="2"/>'];
        yield 'benchmark error' => ['<errors><error>crash</error></errors>'];
        yield 'assertion failure' => ['<failures><failure>slow</failure></failures>'];
    }

    #[DataProvider('invalidMeasurements')]
    public function test_invalid_measurements_fail_closed(string $xml): void
    {
        $this->expectException(RuntimeException::class);
        $this->read($xml);
    }

    public function test_parameter_sets_cannot_be_silently_dropped(): void
    {
        $before = $this->read('<iteration time-avg="1"/><iteration time-avg="1"/><iteration time-avg="1"/>');
        $after = $this->read('<iteration time-avg="1"/><iteration time-avg="1"/><iteration time-avg="1"/>', 'changed');
        $this->expectException(RuntimeException::class);
        BenchmarkComparison::compare($before, $after);
    }

    /** @return array<string, float> */
    private function read(string $iterations, string $parameters = ''): array
    {
        $file = tempnam(sys_get_temp_dir(), 'toon-benchmark-test-');
        if ($file === false) {
            throw new RuntimeException('Cannot create test input');
        }
        try {
            file_put_contents($file, '<phpbench><suite><benchmark class="Example"><subject name="case"><variant><parameter-set name="'.$parameters.'"/>'.$iterations.'</variant></subject></benchmark></suite></phpbench>');

            return BenchmarkComparison::read($file);
        } finally {
            unlink($file);
        }
    }
}
