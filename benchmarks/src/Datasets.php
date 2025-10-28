<?php

namespace Benchmarks;

use Faker\Factory;
use Faker\Generator;

class Datasets
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
        $this->faker->seed(42); // For reproducibility
    }

    /**
     * Generate tabular employee data (100 records)
     */
    public function generateEmployees(int $count = 100): array
    {
        $employees = [];
        for ($i = 0; $i < $count; $i++) {
            $employees[] = [
                'id' => $i + 1,
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'department' => $this->faker->randomElement(['Engineering', 'Sales', 'Marketing', 'HR', 'Finance']),
                'salary' => $this->faker->numberBetween(50000, 150000),
                'years' => $this->faker->numberBetween(0, 20),
                'active' => $this->faker->boolean(90),
            ];
        }

        return $employees;
    }

    /**
     * Generate nested e-commerce order data (50 orders)
     */
    public function generateOrders(int $count = 50): array
    {
        $orders = [];
        for ($i = 0; $i < $count; $i++) {
            $itemCount = $this->faker->numberBetween(1, 4);
            $items = [];

            for ($j = 0; $j < $itemCount; $j++) {
                $items[] = [
                    'sku' => strtoupper($this->faker->bothify('???###')),
                    'name' => $this->faker->words(3, true),
                    'quantity' => $this->faker->numberBetween(1, 5),
                    'price' => $this->faker->randomFloat(2, 10, 500),
                ];
            }

            $orders[] = [
                'id' => $i + 1,
                'customer' => [
                    'id' => $this->faker->numberBetween(1000, 9999),
                    'name' => $this->faker->name(),
                    'email' => $this->faker->email(),
                ],
                'items' => $items,
                'total' => array_sum(array_map(fn ($item) => $item['price'] * $item['quantity'], $items)),
                'status' => $this->faker->randomElement(['pending', 'shipped', 'delivered']),
            ];
        }

        return $orders;
    }

    /**
     * Generate analytics time-series data (180 days)
     */
    public function generateAnalytics(int $days = 180): array
    {
        $analytics = [];
        $startDate = new \DateTime('-180 days');

        for ($i = 0; $i < $days; $i++) {
            $date = clone $startDate;
            $date->modify("+{$i} days");

            // Simulate realistic web traffic with weekend variations
            $isWeekend = in_array((int) $date->format('N'), [6, 7]);
            $baseViews = $isWeekend ? 5000 : 10000;

            $views = (int) ($baseViews + $this->faker->numberBetween(-2000, 2000));
            $clicks = (int) ($views * $this->faker->randomFloat(2, 0.05, 0.15));
            $conversions = (int) ($clicks * $this->faker->randomFloat(2, 0.02, 0.08));

            $analytics[] = [
                'date' => $date->format('Y-m-d'),
                'views' => $views,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'revenue' => round($conversions * $this->faker->randomFloat(2, 50, 200), 2),
                'bounce_rate' => $this->faker->randomFloat(2, 0.3, 0.7),
            ];
        }

        return $analytics;
    }

    /**
     * Generate GitHub repository data (100 repos)
     */
    public function generateRepositories(int $count = 100): array
    {
        $repos = [];
        $languages = ['JavaScript', 'TypeScript', 'Python', 'Go', 'Rust', 'PHP', 'Java', 'C++'];

        for ($i = 0; $i < $count; $i++) {
            $stars = $this->faker->numberBetween(1000, 100000);
            $forks = (int) ($stars * $this->faker->randomFloat(2, 0.1, 0.3));
            $watchers = (int) ($stars * $this->faker->randomFloat(2, 0.05, 0.15));

            $repos[] = [
                'id' => $i + 1,
                'name' => $this->faker->words(2, true),
                'owner' => $this->faker->userName(),
                'description' => $this->faker->sentence(),
                'language' => $this->faker->randomElement($languages),
                'stars' => $stars,
                'forks' => $forks,
                'watchers' => $watchers,
                'created_at' => $this->faker->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d\TH:i:s\Z'),
                'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d\TH:i:s\Z'),
            ];
        }

        // Sort by stars descending to simulate "top 100"
        usort($repos, fn ($a, $b) => $b['stars'] - $a['stars']);

        return $repos;
    }
}
