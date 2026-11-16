<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiException;
use App\Api\ApiRequest;
use App\Api\ApiResponse;

/*
 * Picks the response format from the Accept header.
 *
 *   Accept: application/json                    -> JSON  (also the default: no Accept, any type, application/any)
 *   Accept: application/xml  or text/xml        -> XML
 *   Accept: application/yaml, application/x-yaml or text/yaml -> YAML
 *   Accept: text/html (only)                    -> 406 Not Acceptable
 *
 * q-values are respected, e.g. "application/xml;q=0.5, application/yaml" picks YAML.
 * Errors before a format is chosen (such as this 406) are sent as JSON.
 */
class ContentNegotiationMiddleware implements ApiMiddleware
{
    /** media type => format, in the server's order of preference */
    public const MEDIA_TYPES = [
        'application/json'   => 'json',
        'application/xml'    => 'xml',
        'text/xml'           => 'xml',
        'application/yaml'   => 'yaml',
        'application/x-yaml' => 'yaml',
        'text/yaml'          => 'yaml',
    ];

    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        $mediaType = $this->choose($request->header('Accept'));

        if ($mediaType === null) {
            throw new ApiException(
                406,
                'Not acceptable. This API can respond with: application/json, application/xml, application/yaml.'
            );
        }

        $request->mediaType = $mediaType;
        $request->format = self::MEDIA_TYPES[$mediaType];

        return $next($request);
    }

    private function choose(string $accept): ?string
    {
        if (trim($accept) === '') {
            return 'application/json';
        }

        // Parse "type/sub;q=0.8, other/sub" into [range, q, position]
        $ranges = [];
        foreach (explode(',', $accept) as $position => $part) {
            $pieces = array_map('trim', explode(';', $part));
            $range = strtolower(array_shift($pieces));
            if ($range === '') {
                continue;
            }
            $q = 1.0;
            foreach ($pieces as $param) {
                if (preg_match('/\Aq\s*=\s*([01](?:\.\d{0,3})?)\z/i', $param, $m) === 1) {
                    $q = (float) $m[1];
                }
            }
            if ($q > 0) {
                $ranges[] = [$range, $q, $position];
            }
        }

        // Highest q first; equal q keeps the client's order
        usort($ranges, fn($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        foreach ($ranges as [$range]) {
            foreach (array_keys(self::MEDIA_TYPES) as $type) {
                if ($range === $type || $range === '*/*' || $range === strtok($type, '/') . '/*') {
                    return $type;
                }
            }
        }
        return null;
    }
}
