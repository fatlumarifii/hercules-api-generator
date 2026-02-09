<?php

declare(strict_types=1);

namespace Hercules\ApiGenerator\Services;

class ResponseParser
{
    /**
     * Parse @response annotations from a controller method's docblock.
     *
     * @return array<int, array{status: int, description: string, content: array}>
     */
    public function parseResponses(string $controllerClass, string $methodName): array
    {
        if (! class_exists($controllerClass)) {
            return [];
        }

        try {
            $reflection = new \ReflectionMethod($controllerClass, $methodName);
        } catch (\ReflectionException $e) {
            return [];
        }

        $docComment = $reflection->getDocComment();

        if (! $docComment) {
            return [];
        }

        $responses = $this->extractResponses($docComment);
        $descriptions = $this->extractResponseDescriptions($docComment);

        // Merge descriptions into responses
        foreach ($responses as &$response) {
            if (isset($descriptions[$response['status']])) {
                $response['description'] = $descriptions[$response['status']];
            }
        }

        return $responses;
    }

    /**
     * Extract @response annotations from a docblock.
     */
    private function extractResponses(string $docComment): array
    {
        $responses = [];
        $lines = explode("\n", $docComment);
        $currentResponse = null;
        $jsonBuffer = '';

        foreach ($lines as $line) {
            $line = preg_replace('/^\s*\*\s?/', '', $line);
            $line = rtrim($line);

            // Check for @response tag
            if (preg_match('/^@response\s+(\d{3})\s+(.*)/s', $line, $matches)) {
                // Save previous response if accumulating
                if ($currentResponse !== null) {
                    $responses[] = $this->finalizeResponse($currentResponse, $jsonBuffer);
                }

                $currentResponse = (int) $matches[1];
                $jsonBuffer = trim($matches[2]);

                continue;
            }

            // If we hit another @ tag or end of docblock, finalize current response
            if ($currentResponse !== null && (str_starts_with(trim($line), '@') || trim($line) === '/')) {
                $responses[] = $this->finalizeResponse($currentResponse, $jsonBuffer);
                $currentResponse = null;
                $jsonBuffer = '';

                continue;
            }

            // Continue accumulating JSON for multiline responses
            if ($currentResponse !== null && trim($line) !== '') {
                $jsonBuffer .= ' '.trim($line);
            }
        }

        // Finalize last response if still accumulating
        if ($currentResponse !== null) {
            $responses[] = $this->finalizeResponse($currentResponse, $jsonBuffer);
        }

        return $responses;
    }

    /**
     * Extract @responseDescription annotations from a docblock.
     *
     * @return array<int, string>
     */
    private function extractResponseDescriptions(string $docComment): array
    {
        $descriptions = [];

        if (preg_match_all('/@responseDescription\s+(\d{3})\s+(.+)/m', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $descriptions[(int) $match[1]] = trim($match[2]);
            }
        }

        return $descriptions;
    }

    /**
     * Finalize a response by decoding the JSON buffer.
     */
    private function finalizeResponse(int $status, string $jsonBuffer): array
    {
        $jsonBuffer = trim($jsonBuffer);
        $content = [];

        if ($jsonBuffer !== '') {
            $decoded = json_decode($jsonBuffer, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $content = $decoded;
            }
        }

        return [
            'status' => $status,
            'description' => '',
            'content' => $content,
        ];
    }
}
