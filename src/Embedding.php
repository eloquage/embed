<?php

declare(strict_types=1);

namespace Eloquage\Embed;

use InvalidArgumentException;

final readonly class Embedding
{
    /**
     * @param  list<float>  $vector
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        mixed $id,
        mixed $model,
        mixed $dimensions,
        mixed $vector,
        mixed $metadata = [],
    ) {
        if (! is_string($id) || trim($id) === '') {
            throw new InvalidArgumentException('Embedding id must be a non-blank string.');
        }

        if (! is_string($model) || trim($model) === '') {
            throw new InvalidArgumentException('Embedding model must be a non-blank string.');
        }

        if (! is_int($dimensions) || $dimensions < 1) {
            throw new InvalidArgumentException('Embedding dimensions must be a positive integer.');
        }

        if (! is_array($vector) || $vector === [] || ! array_is_list($vector)) {
            throw new InvalidArgumentException('Embedding vector must be a non-empty ordered list.');
        }

        $normalizedVector = [];

        foreach ($vector as $value) {
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException('Embedding vector values must be integers or floats.');
            }

            $float = (float) $value;

            if (! is_finite($float)) {
                throw new InvalidArgumentException('Embedding vector values must be finite.');
            }

            $normalizedVector[] = $float;
        }

        if (count($normalizedVector) !== $dimensions) {
            throw new InvalidArgumentException('Embedding dimensions must equal the vector length.');
        }

        $normalizedMetadata = self::validateMetadata($metadata);

        $this->id = $id;
        $this->model = $model;
        $this->dimensions = $dimensions;
        $this->vector = $normalizedVector;
        $this->metadata = $normalizedMetadata;
    }

    public readonly string $id;

    public readonly string $model;

    public readonly int $dimensions;

    /** @var list<float> */
    public readonly array $vector;

    /** @var array<string, scalar|null> */
    public readonly array $metadata;

    /**
     * @return array<string, scalar|null>
     */
    private static function validateMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Embedding metadata must be an array.');
        }

        $validated = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Embedding metadata keys must be strings.');
            }

            if ($value !== null && ! is_scalar($value)) {
                throw new InvalidArgumentException('Embedding metadata values must be scalar or null.');
            }

            if (is_float($value) && ! is_finite($value)) {
                throw new InvalidArgumentException('Embedding metadata floats must be finite.');
            }

            $validated[$key] = $value;
        }

        return $validated;
    }
}
