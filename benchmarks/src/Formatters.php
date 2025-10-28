<?php

namespace Benchmarks;

use HelgeSverre\Toon\Toon;

class Formatters
{
    /**
     * Format data as JSON
     */
    public static function toJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Format data as compact JSON (no pretty print)
     */
    public static function toJsonCompact(array $data): string
    {
        return json_encode($data);
    }

    /**
     * Format data as XML
     */
    public static function toXml(array $data, string $rootElement = 'data'): string
    {
        $xml = new \SimpleXMLElement("<{$rootElement}/>");
        self::arrayToXml($data, $xml);
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    /**
     * Format data as TOON
     */
    public static function toToon(array $data): string
    {
        return Toon::encode($data);
    }

    /**
     * Recursively convert array to XML
     */
    private static function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            // Handle numeric keys (e.g., array indices)
            if (is_numeric($key)) {
                $key = 'item';
            }

            // Sanitize key names for XML
            $key = preg_replace('/[^a-z0-9_]/i', '_', $key);

            if (is_array($value)) {
                // Check if it's a list (sequential array)
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Sequential array - create multiple child elements
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $child = $xml->addChild($key);
                            self::arrayToXml($item, $child);
                        } else {
                            $xml->addChild($key, htmlspecialchars((string) $item));
                        }
                    }
                } else {
                    // Associative array - create single child element
                    $child = $xml->addChild($key);
                    self::arrayToXml($value, $child);
                }
            } else {
                // Scalar value
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    /**
     * Get all available format names
     */
    public static function getFormats(): array
    {
        return ['toon', 'json', 'xml'];
    }

    /**
     * Format data using the specified format
     */
    public static function format(array $data, string $format): string
    {
        return match ($format) {
            'toon' => self::toToon($data),
            'json' => self::toJsonCompact($data),
            'xml' => self::toXml($data),
            default => throw new \InvalidArgumentException("Unknown format: {$format}"),
        };
    }
}
