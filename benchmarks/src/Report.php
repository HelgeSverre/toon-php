<?php

namespace Benchmarks;

class Report
{
    /** @var array<int, array{name: string, description: string, tokens: array<string, array{toon: int, json: int, xml: int}>}> */
    private array $results = [];

    /** @var array<int, string> Ordered counter labels (columns) */
    private array $labels;

    /**
     * @param  array<int, string>  $labels
     */
    public function __construct(array $labels)
    {
        $this->labels = $labels;
    }

    /**
     * Add a benchmark result.
     *
     * @param  array<string, array{toon: int, json: int, xml: int}>  $tokens  counter label => per-format token counts
     */
    public function addResult(string $name, string $description, array $tokens): void
    {
        $this->results[] = [
            'name' => $name,
            'description' => $description,
            'tokens' => $tokens,
        ];
    }

    public function generateMarkdown(): string
    {
        $md = "# TOON Token Efficiency Benchmark\n\n";
        $md .= '_Generated on '.date('Y-m-d H:i:s')."_\n\n";
        $md .= '**Token counters:** '.implode(', ', $this->labels)."\n\n";
        $md .= "Each column is a different tokenizer. Both the absolute counts and the exact TOON-vs-JSON ".
            "reduction vary by tokenizer and data shape. The naive `chars/4` estimate overstates TOON's ".
            "advantage — TOON packs more information per character, so real tokenizers see a smaller ".
            "(but usually still positive) reduction.\n\n";
        $md .= "---\n\n";

        // totals[label][format]
        $totals = [];
        foreach ($this->labels as $label) {
            $totals[$label] = ['toon' => 0, 'json' => 0, 'xml' => 0];
        }

        foreach ($this->results as $result) {
            $md .= "## {$result['name']}\n\n";
            $md .= "_{$result['description']}_\n\n";
            $md .= $this->tokenTable($result['tokens']);
            $md .= "\n---\n\n";

            foreach ($this->labels as $label) {
                foreach (['toon', 'json', 'xml'] as $format) {
                    $totals[$label][$format] += $result['tokens'][$label][$format];
                }
            }
        }

        $md .= $this->generateSummary($totals);

        return $md;
    }

    /**
     * Render a table: one row per format, one column per counter, plus a
     * per-counter "TOON vs JSON" reduction line underneath.
     *
     * @param  array<string, array{toon: int, json: int, xml: int}>  $tokens
     */
    private function tokenTable(array $tokens): string
    {
        $md = '| Format |'.implode('', array_map(fn ($l) => " {$l} |", $this->labels))."\n";
        $md .= '|--------|'.str_repeat('-------------|', count($this->labels))."\n";

        foreach (['toon' => 'TOON', 'json' => 'JSON', 'xml' => 'XML'] as $format => $label) {
            $md .= "| {$label} |";
            foreach ($this->labels as $counter) {
                $md .= ' '.number_format($tokens[$counter][$format]).' |';
            }
            $md .= "\n";
        }
        $md .= "\n";

        $parts = [];
        foreach ($this->labels as $counter) {
            $parts[] = sprintf('%s %s%%', $counter, number_format($this->reduction($tokens[$counter]), 1));
        }
        $md .= '_TOON vs JSON reduction: '.implode(' · ', $parts)."_\n";

        return $md;
    }

    /**
     * @param  array<string, array{toon: int, json: int, xml: int}>  $totals
     */
    private function generateSummary(array $totals): string
    {
        $md = "## Summary\n\n";
        $md .= "### Total Tokens Across All Benchmarks\n\n";
        $md .= $this->tokenTable($totals);
        $md .= "\n";

        $md .= "### TOON vs JSON Reduction by Tokenizer\n\n";
        $md .= "| Tokenizer | TOON | JSON | Reduction |\n";
        $md .= "|-----------|------|------|-----------|\n";
        foreach ($this->labels as $label) {
            $t = $totals[$label];
            $md .= sprintf(
                "| %s | %s | %s | %s%% |\n",
                $label,
                number_format($t['toon']),
                number_format($t['json']),
                number_format($this->reduction($t), 1)
            );
        }
        $md .= "\nTOON uses fewer tokens than JSON on most datasets across real tokenizers, though the margin ".
            "varies by tokenizer and data shape and can approach parity for deeply nested records. Compare the ".
            "`chars/4` estimate against the real tokenizers to see how much a character-based guess overstates the gain.\n";

        return $md;
    }

    /**
     * TOON's percentage reduction vs JSON.
     *
     * @param  array{toon: int, json: int, xml: int}  $tokens
     */
    private function reduction(array $tokens): float
    {
        return $tokens['json'] > 0 ? (($tokens['json'] - $tokens['toon']) / $tokens['json']) * 100 : 0.0;
    }

    public function save(string $filePath): void
    {
        file_put_contents($filePath, $this->generateMarkdown());
    }
}
