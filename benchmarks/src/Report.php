<?php

namespace Benchmarks;

class Report
{
    private array $results = [];

    private string $method;

    public function __construct(string $countingMethod)
    {
        $this->method = $countingMethod;
    }

    /**
     * Add a benchmark result
     */
    public function addResult(string $name, string $description, array $tokens): void
    {
        $this->results[] = [
            'name' => $name,
            'description' => $description,
            'tokens' => $tokens,
        ];
    }

    /**
     * Generate markdown report
     */
    public function generateMarkdown(): string
    {
        $md = "# TOON Token Efficiency Benchmark\n\n";
        $md .= '_Generated on '.date('Y-m-d H:i:s')."_\n\n";
        $md .= "**Token Counting Method:** {$this->method}\n\n";
        $md .= "---\n\n";

        $totalTokens = ['toon' => 0, 'json' => 0, 'xml' => 0];

        foreach ($this->results as $result) {
            $md .= $this->generateBenchmarkSection($result);
            $md .= "\n---\n\n";

            // Accumulate totals
            foreach ($result['tokens'] as $format => $count) {
                $totalTokens[$format] += $count;
            }
        }

        $md .= $this->generateTotalsSection($totalTokens);

        return $md;
    }

    /**
     * Generate a section for a single benchmark
     */
    private function generateBenchmarkSection(array $result): string
    {
        $md = "## {$result['name']}\n\n";
        $md .= "_{$result['description']}_\n\n";

        $tokens = $result['tokens'];
        $toonTokens = $tokens['toon'];
        $jsonTokens = $tokens['json'];
        $xmlTokens = $tokens['xml'];

        $md .= "| Format | Tokens | vs TOON | Savings |\n";
        $md .= "|--------|--------|---------|----------|\n";
        $md .= sprintf(
            "| TOON | %s | - | baseline |\n",
            number_format($toonTokens)
        );
        $md .= sprintf(
            "| JSON | %s | %s | %s%% |\n",
            number_format($jsonTokens),
            $this->formatDiff($jsonTokens - $toonTokens),
            $this->formatPercentage($toonTokens, $jsonTokens)
        );
        $md .= sprintf(
            "| XML | %s | %s | %s%% |\n",
            number_format($xmlTokens),
            $this->formatDiff($xmlTokens - $toonTokens),
            $this->formatPercentage($toonTokens, $xmlTokens)
        );

        $md .= "\n";
        $md .= $this->generateProgressBar('JSON', $jsonTokens, $xmlTokens);
        $md .= $this->generateProgressBar('TOON', $toonTokens, $xmlTokens);
        $md .= $this->generateProgressBar('XML', $xmlTokens, $xmlTokens);
        $md .= "\n";

        return $md;
    }

    /**
     * Generate the totals section
     */
    private function generateTotalsSection(array $totals): string
    {
        $md = "## Summary\n\n";
        $md .= "### Total Tokens Across All Benchmarks\n\n";

        $toonTotal = $totals['toon'];
        $jsonTotal = $totals['json'];
        $xmlTotal = $totals['xml'];

        $md .= "| Format | Total Tokens | vs TOON | Savings |\n";
        $md .= "|--------|--------------|---------|----------|\n";
        $md .= sprintf(
            "| TOON | %s | - | baseline |\n",
            number_format($toonTotal)
        );
        $md .= sprintf(
            "| JSON | %s | %s | %s%% |\n",
            number_format($jsonTotal),
            $this->formatDiff($jsonTotal - $toonTotal),
            $this->formatPercentage($toonTotal, $jsonTotal)
        );
        $md .= sprintf(
            "| XML | %s | %s | %s%% |\n",
            number_format($xmlTotal),
            $this->formatDiff($xmlTotal - $toonTotal),
            $this->formatPercentage($toonTotal, $xmlTotal)
        );

        $md .= "\n";
        $md .= "### Key Takeaways\n\n";

        // Calculate savings: how much smaller TOON is compared to others
        $jsonSavings = (($jsonTotal - $toonTotal) / $jsonTotal) * 100;
        $xmlSavings = (($xmlTotal - $toonTotal) / $xmlTotal) * 100;

        $md .= sprintf(
            "- TOON uses **%s%% fewer tokens** than JSON\n",
            number_format($jsonSavings, 1)
        );
        $md .= sprintf(
            "- TOON uses **%s%% fewer tokens** than XML\n",
            number_format($xmlSavings, 1)
        );
        $md .= sprintf(
            "- Total token savings: **%s tokens** vs JSON, **%s tokens** vs XML\n",
            number_format($jsonTotal - $toonTotal),
            number_format($xmlTotal - $toonTotal)
        );

        return $md;
    }

    /**
     * Format difference with + or - sign
     */
    private function formatDiff(int $diff): string
    {
        if ($diff > 0) {
            return '+'.number_format($diff);
        }

        return number_format($diff);
    }

    /**
     * Calculate percentage difference
     */
    private function formatPercentage(int $baseline, int $comparison): string
    {
        if ($baseline === 0) {
            return '0.0';
        }

        $percentage = (($comparison - $baseline) / $baseline) * 100;

        return number_format($percentage, 1);
    }

    /**
     * Generate a visual progress bar
     */
    private function generateProgressBar(string $label, int $value, int $max): string
    {
        $percentage = $max > 0 ? ($value / $max) * 100 : 0;
        $barLength = 40;
        $filled = (int) (($percentage / 100) * $barLength);
        $empty = $barLength - $filled;

        $bar = str_repeat('█', $filled).str_repeat('░', $empty);

        return sprintf(
            "**%s** `%s` %s tokens\n",
            str_pad($label, 4),
            $bar,
            number_format($value)
        );
    }

    /**
     * Save report to file
     */
    public function save(string $filePath): void
    {
        $content = $this->generateMarkdown();
        file_put_contents($filePath, $content);
    }
}
