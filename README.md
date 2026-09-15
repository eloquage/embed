# Embedding helpers for PHP apps: normalize, chunk metadata, cache keys, and provider-agnostic embedding DTOs for RAG pipelines.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eloquage/embed.svg?style=flat-square)](https://packagist.org/packages/eloquage/embed)
[![Tests](https://github.com/eloquage/embed/actions/workflows/run-tests.yml/badge.svg)](https://github.com/eloquage/embed/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/eloquage/embed.svg?style=flat-square)](https://packagist.org/packages/eloquage/embed)

## Installation

Install via Composer (pure PHP; always works without a native extension):

```bash
composer require eloquage/embed
```

### Optional native acceleration

When a release includes a TypePHP-built extension (`eloquage_embed`), you can load it for faster paths. The public PHP API is unchanged.

#### shivammathur/setup-php (GitHub Actions)

Once the extension is on PECL:

```yaml
- uses: shivammathur/setup-php@v2
  with:
    php-version: '8.4'
    extensions: eloquage_embed
```

Until then, install from a GitHub Release phpize/PECL tarball or from source (see [setup-php wiki: Add extension from source](https://github.com/shivammathur/setup-php/wiki/Add-extension-from-source)).

#### docker-php-ext-install

Extract the Release phpize tree to an absolute path, then:

```dockerfile
RUN docker-php-ext-configure /tmp/eloquage_embed \
 && docker-php-ext-install /tmp/eloquage_embed \
 && docker-php-ext-enable eloquage_embed
```

#### PECL

```bash
# from a GitHub Release asset URL (canonical until pecl.php.net listing exists)
pecl install https://github.com/eloquage/embed/releases/download/vX.Y.Z/eloquage_embed-X.Y.Z.tgz
# after channel registration:
# pecl install eloquage_embed
```

#### Windows

Download the Release `eloquage_embed.dll`, place it in your PHP extension directory, and enable:

```ini
extension=eloquage_embed
```

On `windows-latest` with setup-php, the same PECL/DLL path applies once a Windows binary is published.

See [TYPEPHP.md](TYPEPHP.md) for building the extension yourself with the shared builder image.

## Usage

```php
use Eloquage\Embed\Embed;

$embed = new Embed();

echo $embed->name(); // embed
```

Create immutable, provider-neutral records from vectors returned by any SDK or
local model:

```php
$record = $embed->record(
    id: 'manual-42',
    model: 'local/e5-base-v2',
    dimensions: 3,
    vector: [3, 4, 0],
    metadata: ['kind' => 'document'],
);

$normalized = $embed->normalize($record);
$chunked = $embed->withChunkMetadata($normalized, 'manual.md', 2, 100, 180);
$batch = $embed->batch([$chunked]);
$key = $embed->cacheKey('local/e5-base-v2', [
    'input' => 'A paragraph from the manual.',
    'dimensions' => 3,
]);
```

`Embedding` records expose `id`, `model`, `dimensions`, `vector`, and scalar
`metadata` fields as public read-only values. Record creation requires a
non-blank identity and model, a positive dimension count exactly matching a
non-empty finite numeric vector, and string metadata keys. Normalization
returns a new unit-length record and rejects zero-norm vectors. Chunk metadata
uses the reserved `chunk_source`, `chunk_index`, `chunk_start_offset`, and
`chunk_end_offset` fields; it annotates without splitting text or calculating
offsets. Batches are non-empty ordered lists and may contain mixed models and
dimensions.

Cache keys are opaque, versioned `eloquage-embed:v1:<sha256>` values. Text is
hashed byte-for-byte after blank-input validation. Structured JSON-compatible
payloads canonicalize associative-key order while preserving list order,
string contents, and numeric distinctions. Key derivation is deterministic,
in-process, and offline: this package is not a provider SDK and does not make
HTTP requests, read credentials, tokenize text, store cache entries, or depend
on Laravel, `eloquage/vector`, or `eloquage/tokens`.

## Testing

```bash
composer test
vendor/bin/pest --coverage --min=90
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Pull requests and issues are welcome on [GitHub](https://github.com/eloquage/embed).

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Miguel Enes](https://github.com/eloquage)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Development

See [AGENTS.md](AGENTS.md) for agent context, tests, and TypePHP Docker builds.
