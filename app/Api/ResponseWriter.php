<?php

declare(strict_types=1);

namespace App\Api;

use XMLWriter;

/*
 * Sends the status, headers and body, in the format ContentNegotiationMiddleware chose.
 * The same data gives the same meaning in every format:
 *
 *   JSON  {"id": 1, "show_type": "TV", "end_date": null}
 *   XML   <response><id>1</id><show_type>TV</show_type><end_date nil="true"/></response>
 *   YAML  id: 1
 *         show_type: "TV"
 *         end_date: null
 *
 * Lists become a JSON array, <item> elements in XML, and "- " entries in YAML.
 * A key that isn't a valid XML name (e.g. "a b") becomes <a_b name="a b">.
 */
class ResponseWriter
{
    public function send(ApiRequest $request, ApiResponse $response): void
    {
        http_response_code($response->status);
        foreach ($response->headers as $name => $value) {
            header("$name: $value");
        }

        if ($response->status === 204 || $response->data === null) {
            ini_set('default_mimetype', ''); // no body, so no Content-Type (PHP adds text/html otherwise)
            return;
        }

        header('Content-Type: ' . $request->mediaType . '; charset=UTF-8');
        echo match ($request->format) {
            'xml'  => $this->xml($response->data),
            'yaml' => $this->yaml($response->data),
            default => json_encode($response->data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        };
    }

    // ---------------------------------------------------------------- XML

    public function xml(mixed $data): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $this->xmlElement($xml, 'response', $data);
        $xml->endDocument();
        return $xml->outputMemory();
    }

    private function xmlElement(XMLWriter $xml, string|int $key, mixed $value): void
    {
        $name = $this->xmlName((string) $key);
        $xml->startElement($name);
        if ($name !== (string) $key) {
            $xml->writeAttribute('name', (string) $key); // original key, e.g. "a b"
        }

        if ($value === null) {
            $xml->writeAttribute('nil', 'true');
        } elseif (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $this->xmlElement($xml, array_is_list($value) ? 'item' : $childKey, $child);
            }
        } elseif (is_bool($value)) {
            $xml->text($value ? 'true' : 'false');
        } else {
            $xml->text((string) $value);
        }

        $xml->endElement();
    }

    // XML element names can't contain spaces or start with a digit: "a b" -> a_b
    private function xmlName(string $key): string
    {
        $name = preg_replace('/[^A-Za-z0-9_.-]/', '_', $key) ?? '_';
        if ($name === '' || preg_match('/\A(?:[0-9.-]|xml)/i', $name) === 1) {
            $name = '_' . $name;
        }
        return $name;
    }

    // ---------------------------------------------------------------- YAML

    public function yaml(mixed $data): string
    {
        return "---\n" . $this->yamlBlock($data, 0) . "\n";
    }

    private function yamlBlock(mixed $value, int $indent): string
    {
        if (!is_array($value) || $value === []) {
            return str_repeat(' ', $indent) . $this->yamlScalar($value);
        }

        $pad = str_repeat(' ', $indent);
        $lines = [];

        foreach ($value as $key => $child) {
            if (array_is_list($value)) {
                if (is_array($child) && $child !== []) {
                    // "- " replaces the first two spaces of the nested block
                    $block = $this->yamlBlock($child, $indent + 2);
                    $lines[] = $pad . '- ' . substr($block, $indent + 2);
                } else {
                    $lines[] = $pad . '- ' . $this->yamlScalar($child);
                }
                continue;
            }

            $yamlKey = preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', (string) $key) === 1
                ? (string) $key
                : $this->yamlString((string) $key);

            $lines[] = is_array($child) && $child !== []
                ? $pad . $yamlKey . ":\n" . $this->yamlBlock($child, $indent + 2)
                : $pad . $yamlKey . ': ' . $this->yamlScalar($child);
        }

        return implode("\n", $lines);
    }

    private function yamlScalar(mixed $value): string
    {
        return match (true) {
            $value === null    => 'null',
            $value === []      => '[]',
            is_bool($value)    => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default            => $this->yamlString((string) $value),
        };
    }

    // Strings are always double-quoted (JSON string syntax is valid YAML), so values like
    // "yes", "null" or "1999" keep their meaning as text - the same as in the JSON response.
    private function yamlString(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
