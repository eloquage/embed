<?php

use Eloquage\Embed\Embed;
use Eloquage\Embed\Embedding;

beforeEach(function () {
    $this->embed = new Embed;
});

it('creates an immutable embedding record with float vector values', function () {
    $record = $this->embed->record(
        id: 'manual-42',
        model: 'local/e5-base-v2',
        dimensions: 3,
        vector: [3, 4.5, 0],
        metadata: ['kind' => 'document', 'published' => true, 'note' => null],
    );

    expect($record)
        ->toBeInstanceOf(Embedding::class)
        ->and($record->id)->toBe('manual-42')
        ->and($record->model)->toBe('local/e5-base-v2')
        ->and($record->dimensions)->toBe(3)
        ->and($record->vector)->toBe([3.0, 4.5, 0.0])
        ->and($record->metadata)->toBe(['kind' => 'document', 'published' => true, 'note' => null]);

    $reflection = new ReflectionClass($record);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('rejects invalid embedding records', function (mixed $id, mixed $model, mixed $dimensions, mixed $vector, mixed $metadata) {
    expect(fn () => $this->embed->record($id, $model, $dimensions, $vector, $metadata))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'blank id' => ['', 'model', 1, [1], []],
    'blank model' => ['id', " \t", 1, [1], []],
    'empty vector' => ['id', 'model', 1, [], []],
    'non-positive dimensions' => ['id', 'model', 0, [1], []],
    'non-integer dimensions' => ['id', 'model', 1.0, [1], []],
    'dimension mismatch' => ['id', 'model', 2, [1], []],
    'non-list vector' => ['id', 'model', 2, [1 => 1, 2 => 2], []],
    'non-numeric vector value' => ['id', 'model', 1, ['1'], []],
    'non-finite vector value' => ['id', 'model', 1, [INF], []],
    'non-array metadata' => ['id', 'model', 1, [1], 'metadata'],
    'integer metadata key' => ['id', 'model', 1, [1], [1 => 'value']],
    'array metadata value' => ['id', 'model', 1, [1], ['nested' => []]],
    'non-finite metadata value' => ['id', 'model', 1, [1], ['score' => INF]],
]);

it('normalizes a record without changing the source', function () {
    $record = $this->embed->record('manual-42', 'local/e5-base-v2', 2, [3, 4], ['kind' => 'document']);
    $normalized = $this->embed->normalize($record);

    expect($record->vector)->toBe([3.0, 4.0])
        ->and($normalized->vector[0])->toBe(0.6)
        ->and($normalized->vector[1])->toBe(0.8)
        ->and($normalized->id)->toBe($record->id)
        ->and($normalized->model)->toBe($record->model)
        ->and($normalized->dimensions)->toBe($record->dimensions)
        ->and($normalized->metadata)->toBe($record->metadata)
        ->and(abs(array_sum(array_map(static fn (float $value): float => $value * $value, $normalized->vector)) - 1.0))
        ->toBeLessThan(0.0000000001);
});

it('rejects zero and overflowing norms', function (array $vector) {
    $record = $this->embed->record('id', 'model', count($vector), $vector);

    expect(fn () => $this->embed->normalize($record))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero vector' => [[0, 0]],
    'overflowing vector' => [[PHP_FLOAT_MAX, PHP_FLOAT_MAX]],
]);

it('adds reserved chunk metadata to a copied record', function () {
    $record = $this->embed->record('id', 'model', 2, [3, 4], [
        'kind' => 'document',
        'chunk_source' => 'old.md',
    ]);
    $chunked = $this->embed->withChunkMetadata($record, 'manual.md', 2, 100, 180);

    expect($record->metadata)->toBe(['kind' => 'document', 'chunk_source' => 'old.md'])
        ->and($chunked->metadata)->toBe([
            'kind' => 'document',
            'chunk_source' => 'manual.md',
            'chunk_index' => 2,
            'chunk_start_offset' => 100,
            'chunk_end_offset' => 180,
        ]);
});

it('rejects invalid chunk metadata', function (mixed $source, mixed $index, mixed $start, mixed $end) {
    $record = $this->embed->record('id', 'model', 1, [1]);

    expect(fn () => $this->embed->withChunkMetadata($record, $source, $index, $start, $end))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'blank source' => ['', 0, 0, 0],
    'negative index' => ['source', -1, 0, 0],
    'non-integer index' => ['source', 0.0, 0, 0],
    'negative start' => ['source', 0, -1, 0],
    'negative end' => ['source', 0, 0, -1],
    'end before start' => ['source', 0, 2, 1],
]);

it('batches records in order without requiring matching models or dimensions', function () {
    $first = $this->embed->record('first', 'model-a', 1, [1]);
    $second = $this->embed->record('second', 'model-b', 2, [2, 3]);

    expect($this->embed->batch([$first, $second]))
        ->toBe([$first, $second]);
});

it('rejects empty, associative, and invalid embedding batches', function (mixed $records) {
    expect(fn () => $this->embed->batch($records))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => [[]],
    'associative' => [['first' => new Embedding('id', 'model', 1, [1])]],
    'invalid member' => [[new Embedding('id', 'model', 1, [1]), 'not a record']],
    'non-array' => ['records'],
]);

it('derives opaque deterministic keys for exact text and structured payloads', function () {
    $textKey = $this->embed->cacheKey('model', 'A paragraph.');
    $sameTextKey = $this->embed->cacheKey('model', 'A paragraph.');
    $orderedPayloadKey = $this->embed->cacheKey('model', [
        'input' => 'A paragraph.',
        'dimensions' => 3,
        'options' => ['temperature' => 0.0, 'normalize' => true],
    ]);
    $reorderedPayloadKey = $this->embed->cacheKey('model', [
        'options' => ['normalize' => true, 'temperature' => 0.0],
        'dimensions' => 3,
        'input' => 'A paragraph.',
    ]);

    expect($textKey)->toBe($sameTextKey)
        ->and($textKey)->toStartWith('eloquage-embed:v1:')
        ->and(strlen($textKey))->toBe(strlen('eloquage-embed:v1:') + 64)
        ->and($textKey)->not->toContain('A paragraph.')
        ->and($orderedPayloadKey)->toBe($reorderedPayloadKey)
        ->and($orderedPayloadKey)->not->toBe($textKey);
});

it('keeps cache keys sensitive to model, payload bytes, and list order', function () {
    $key = fn (string $model, mixed $payload): string => $this->embed->cacheKey($model, $payload);

    expect($key('model', 'Text'))
        ->not->toBe($key('other-model', 'Text'))
        ->and($key('model', 'Text'))
        ->not->toBe($key('model', ' text'))
        ->and($key('model', 'Text'))
        ->not->toBe($key('model', 'text'))
        ->and($key('model', [1, 2]))
        ->not->toBe($key('model', [2, 1]))
        ->and($key('model', ['value' => 1]))
        ->not->toBe($key('model', ['value' => 1.0]));
});

it('rejects empty and non-json cache-key inputs', function (mixed $model, mixed $payload) {
    expect(fn () => $this->embed->cacheKey($model, $payload))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'blank model' => [' ', 'text'],
    'blank text' => ['model', " \n"],
    'empty structured payload' => ['model', []],
    'object payload' => ['model', (object) ['input' => 'text']],
    'object key' => ['model', [1 => 'value', 'name' => 'text']],
    'nested object' => ['model', ['options' => [(object) []]]],
    'non-finite number' => ['model', ['score' => INF]],
]);

it('keeps the existing entrypoint name', function () {
    expect($this->embed->name())->toBe('embed');
});
