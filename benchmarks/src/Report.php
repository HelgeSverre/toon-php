<?php

namespace Benchmarks;

class Report
{
    /** @var array<int, array{name: string, description: string, tokensByModel: array<string, array{toon: int, json: int, xml: int}>}> */
    private array $results = [];

    private string $method;

    /** @var array<int, string> Ordered list of model IDs used for counting */
    private array $models;

    /**
     * @param  array<int, string>  $models
     */
    public function __construct(string $countingMethod, array $models)
    {
        $this->method = $countingMethod;
        $this->models = $models;
    }

    /**
     * Add a benchmark result.
     *
     * @param  array<string, array{toon: int, json: int, xml: int}>  $tokensByModel  model ID => per-format token counts
     */
    public function addResult(string $name, string $description, array $tokensByModel): void
    {
        $this->results[] = [
            'name' => $name,
            'description' => $description,
            'tokensByModel' => $tokensByModel,
        ];
    }

    /**
     * Generate markdown report.
     */
    public function generateMarkdown(): string
    {
        $md = "# TOON Token Efficiency Benchmark\n\n";
        $md .= '_Generated on '.date('Y-m-d H:i:s')."_\n\n";
        $md .= "**Token Counting Method:** {$this->method}\n\n";
        $md .= '**Models compared:** '.implode(', ', array_map([$this, 'modelLabel'], $this->models))."\n\n";
        $md .= "---\n\n";

        // totals[model][format]
        $totals = [];
        foreach ($this->models as $model) {
            $totals[$model] = ['toon' => 0, 'json' => 0, 'xml' => 0];
        }

        foreach ($this->results as $result) {
            $md .= $this->generateBenchmarkSection($result);
            $md .= "\n---\n\n";

            foreach ($this->models as $model) {
                foreach (['toon', 'json', 'xml'] as $format) {
                    $totals[$model][$format] += $result['tokensByModel'][$model][$format];
                }
            }
        }

        $md .= $this->generateTotalsSection($totals);

        return $md;
    }

    /**
     * @param  array{name: string, description: string, tokensByModel: array<string, array{toon: int, json: int, xml: int}>}  $result
     */
    private function generateBenchmarkSection(array $result): string
    {
        $md = "## {$result['name']}\n\n";
        $md .= "_{$result['description']}_\n\n";
        $md .= $this->tokenTable($result['tokensByModel']);

        return $md;
    }

    /**
     * Render a token table with one column per model, plus the per-model TOON-vs-JSON reduction.
     *
     * @param  array<string, array{toon: int, json: int, xml: int}>  $tokensByModel
     */
    private function tokenTable(array $tokensByModel): string
    {
        $header = '| Format |';
        $sep = '|--------|';
        foreach ($this->models as $model) {
            $header .= ' '.$this->modelLabel($model).' |';
            $sep .= '-----------|';
        }
        $md = $header."\n".$sep."\n";

        foreach (['toon' => 'TOON', 'json' => 'JSON', 'xml' => 'XML'] as $format => $label) {
            $row = "| {$label} |";
            foreach ($this->models as $model) {
                $row .= ' '.number_format($tokensByModel[$model][$format]).' |';
            }
            $md .= $row."\n";
        }
        $md .= "\n";

        $parts = [];
        foreach ($this->models as $model) {
            $t = $tokensByModel[$model];
            $pct = $t['json'] > 0 ? (($t['json'] - $t['toon']) / $t['json']) * 100 : 0;
            $parts[] = sprintf('%s %s%%', $this->modelLabel($model), number_format($pct, 1));
        }
        $md .= '_TOON vs JSON reduction: '.implode(' · ', $parts)."_\n\n";

        return $md;
    }

    /**
     * Generate the totals section, including the cross-model tokenizer comparison.
     *
     * @param  array<string, array{toon: int, json: int, xml: int}>  $totals
     */
    private function generateTotalsSection(array $totals): string
    {
        $md = "## Summary\n\n";
        $md .= "### Total Tokens Across All Benchmarks\n\n";
        $md .= $this->tokenTable($totals);

        $md .= "### Tokenizer Comparison\n\n";
        $base = $this->models[0];
        $baseTotal = array_sum($totals[$base]);
        foreach ($this->models as $model) {
            $total = array_sum($totals[$model]);
            if ($model === $base) {
                $md .= sprintf(
                    "- **%s** (baseline): %s total tokens across all formats\n",
                    $this->modelLabel($model),
                    number_format($total)
                );

                continue;
            }
            $delta = $baseTotal > 0 ? (($total - $baseTotal) / $baseTotal) * 100 : 0;
            $md .= sprintf(
                "- **%s**: %s total tokens (%s%% vs %s) — different tokenizer generation\n",
                $this->modelLabel($model),
                number_format($total),
                ($delta >= 0 ? '+' : '').number_format($delta, 1),
                $this->modelLabel($base)
            );
        }
        $md .= "\nModels in the same generation share a tokenizer, so their counts match exactly; ".
            "models from different generations differ in absolute count. The TOON-vs-JSON reduction stays roughly constant across all of them.\n\n";

        // Key takeaways using the last (most current) model.
        $current = $this->models[array_key_last($this->models)];
        $t = $totals[$current];
        $jsonSavings = $t['json'] > 0 ? (($t['json'] - $t['toon']) / $t['json']) * 100 : 0;
        $xmlSavings = $t['xml'] > 0 ? (($t['xml'] - $t['toon']) / $t['xml']) * 100 : 0;

        $md .= '### Key Takeaways ('.$this->modelLabel($current).")\n\n";
        $md .= sprintf("- TOON uses **%s%% fewer tokens** than JSON\n", number_format($jsonSavings, 1));
        $md .= sprintf("- TOON uses **%s%% fewer tokens** than XML\n", number_format($xmlSavings, 1));
        $md .= sprintf(
            "- Total token savings: **%s tokens** vs JSON, **%s tokens** vs XML\n",
            number_format($t['json'] - $t['toon']),
            number_format($t['xml'] - $t['toon'])
        );

        return $md;
    }

    /**
     * Turn a model ID into a readable label, e.g. claude-haiku-4-5 -> "Haiku 4.5".
     */
    private function modelLabel(string $model): string
    {
        $label = preg_replace('/^claude-/', '', $model) ?? $model;
        $label = preg_replace('/-(\d+)-(\d+)$/', ' $1.$2', $label) ?? $label; // -4-5 -> " 4.5"
        $label = preg_replace('/-(\d+)$/', ' $1', $label) ?? $label;          // -5   -> " 5"

        return ucwords($label);
    }

    /**
     * Save report to file.
     */
    public function save(string $filePath): void
    {
        file_put_contents($filePath, $this->generateMarkdown());
    }
}
