<?php

declare(strict_types=1);

namespace Eloquage\Embed;

use InvalidArgumentException;

/**
 * Primary entrypoint for eloquage/embed.
 *
 * Pure-PHP implementation lives here. Optional TypePHP/native acceleration
 * can be added under native/ later without changing this public API.
 */
final class Embed
{
    public function name(): string
    {
        return 'embed';
    }

    public function record(
        mixed $id,
        mixed $model,
        mixed $dimensions,
        mixed $vector,
        mixed $metadata = [],
    ): Embedding {
        return new Embedding($id, $model, $dimensions, $vector, $metadata);
    }

    public function normalize(Embedding $record): Embedding
    {
        $normSquared = 0.0;

        foreach ($record->vector as $value) {
            $normSquared += $value * $value;
        }

        $norm = sqrt($normSquared);

        if ($norm <= 0.0 || ! is_finite($norm)) {
            throw new InvalidArgumentException('Embedding vector must have a finite, non-zero L2 norm.');
        }

        $vector = [];

        foreach ($record->vector as $value) {
            $vector[] = $value / $norm;
        }

        return new Embedding(
            $record->id,
            $record->model,
            $record->dimensions,
            $vector,
            $record->metadata,
        );
    }

    public function withChunkMetadata(
        Embedding $record,
        mixed $source,
        mixed $index,
        mixed $startOffset,
        mixed $endOffset,
    ): Embedding {
        if (! is_string($source) || trim($source) === '') {
            throw new InvalidArgumentException('Chunk source must be a non-blank string.');
        }

        if (! is_int($index) || $index < 0) {
            throw new InvalidArgumentException('Chunk index must be a non-negative integer.');
        }

        if (! is_int($startOffset) || $startOffset < 0) {
            throw new InvalidArgumentException('Chunk start offset must be a non-negative integer.');
        }

        if (! is_int($endOffset) || $endOffset < $startOffset) {
            throw new InvalidArgumentException('Chunk end offset must be at least the start offset.');
        }

        $metadata = $record->metadata;
        $metadata['chunk_source'] = $source;
        $metadata['chunk_index'] = $index;
        $metadata['chunk_start_offset'] = $startOffset;
        $metadata['chunk_end_offset'] = $endOffset;

        return new Embedding(
            $record->id,
            $record->model,
            $record->dimensions,
            $record->vector,
            $metadata,
        );
    }

    /**
     * @return list<Embedding>
     */
    public function batch(mixed $records): array
    {
        if (! is_array($records) || $records === [] || ! array_is_list($records)) {
            throw new InvalidArgumentException('Embedding batch must be a non-empty ordered list.');
        }

        foreach ($records as $record) {
            if (! $record instanceof Embedding) {
                throw new InvalidArgumentException('Embedding batch members must be Embedding records.');
            }
        }

        return $records;
    }

    public function cacheKey(mixed $model, mixed $payload): string
    {
        if (! is_string($model) || trim($model) === '') {
            throw new InvalidArgumentException('Cache-key model must be a non-blank string.');
        }

        if (is_string($payload)) {
            if (trim($payload) === '') {
                throw new InvalidArgumentException('Cache-key text payload must be non-blank.');
            }

            $canonicalPayload = $payload;
        } elseif (is_array($payload)) {
            $canonicalPayload = $this->canonicalize($payload, true);
        } else {
            throw new InvalidArgumentException('Cache-key payload must be a string or JSON-compatible array.');
        }

        try {
            $canonical = json_encode(
                ['model' => $model, 'payload' => $canonicalPayload],
                JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('Cache-key payload could not be encoded as JSON.', previous: $exception);
        }

        return 'eloquage-embed:v1:'.hash('sha256', $canonical);
    }

    private function canonicalize(array $value, bool $topLevel): array
    {
        if ($topLevel && $value === []) {
            throw new InvalidArgumentException('Cache-key structured payload must not be empty.');
        }

        if (array_is_list($value)) {
            $canonical = [];

            foreach ($value as $item) {
                $canonical[] = $this->canonicalValue($item);
            }

            return $canonical;
        }

        $keys = array_keys($value);

        foreach ($keys as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Cache-key object keys must be strings.');
            }
        }

        sort($keys, SORT_STRING);
        $canonical = [];

        foreach ($keys as $key) {
            $canonical[$key] = $this->canonicalValue($value[$key]);
        }

        return $canonical;
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->canonicalize($value, false);
        }

        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Cache-key payload must contain only JSON-compatible values.');
    }
}
