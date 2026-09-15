# eloquage/embed

Embedding helpers for PHP apps: normalize, chunk metadata, cache keys, and provider-agnostic embedding DTOs.

- Composer: `eloquage/embed`
- Entrypoints: `Eloquage\Embed\Embed` and immutable `Eloquage\Embed\Embedding`
- This package is framework-agnostic. The Laravel app at the monorepo root is a local test bench only.

## Layout

- `src/` — public PHP API (source of truth)
- `native/` — optional TypePHP AOT sources (empty today)
- `tests/` — Pest 5
- `TYPEPHP.md` — extension build contract
- `project.yml.example` — TypePHP project config (copy to gitignored `project.yml`)

## Commands

```bash
composer test
composer format
vendor/bin/pest --coverage --min=90
```

## Conventions

- No Illuminate / Laravel service providers.
- Always ship a pure-PHP fallback. Never `require` `swoole/typephp`.
- Consumers: PHP 8.3+. Package CI: PHP 8.4. TypePHP compile: PHP 8.5 syntax.

## TypePHP

Extension mode only (`mode: ext`). Build in Docker, not on the host:

```bash
# harness (default: ghcr.io/eloquage/typephp-builder)
docker/typephp/build-package.sh embed

# this repo
docker run --rm -v "$PWD":/src -w /src \
  "${ELOQUAGE_TYPEPHP_IMAGE:-ghcr.io/eloquage/typephp-builder:latest}" \
  sh -c 'test -f project.yml || cp project.yml.example project.yml; tpc.php project.yml'
```

See `TYPEPHP.md`. Linux containers only for the shared builder. Optional native install channels: setup-php, docker-php-ext-install, PECL, Windows DLL.

## Harness demo

Public behavior must be exercisable from the laravel-x welcome page (`/` → `resources/views/welcome.blade.php`) with a Feature test.

## Public API and validation

`Embed` is the operational entrypoint. It preserves `name()` and provides
`record()`, `normalize()`, `withChunkMetadata()`, `batch()`, and `cacheKey()`.
`Embedding` is a `final readonly` record with public read-only `id`, `model`,
`dimensions`, `vector`, and `metadata` fields. Its constructor is the invariant
boundary: identities and models are non-blank, dimensions are positive and
match a non-empty ordered finite vector, vector values are stored as floats,
and metadata is a flat string-keyed scalar-or-null map. Transformations return
new records or copied ordered lists. Chunk offsets are caller-owned; the
package does not split or tokenize text.

The package is provider-neutral and offline. Do not add Laravel, provider SDK,
credential, network, `eloquage/vector`, or `eloquage/tokens` dependencies.

## TypePHP scope for embed-records-and-cache-keys

The current records, normalization, metadata, batch, and cache-key capability
is pure PHP. TypePHP/native work is explicitly skipped for this change: do not
add `native/` sources, change `project.yml`, add a Composer `swoole/typephp`
dependency, pull the builder, run `tpc`, or claim a `.so` build. Keep the pure
PHP fallback complete; future native work must use the Docker workflow in
`TYPEPHP.md` and preserve this public API.

## Humans vs agents

- README — install/usage for humans
- This file — agent context
- TYPEPHP.md — AOT / Docker / release
