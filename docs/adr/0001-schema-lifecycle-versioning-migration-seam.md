# 0001 — Schema lifecycle: versioning, fingerprinting, and the migration seam

- Status: Accepted
- Date: 2026-06-23

## Context

`laravel-data-schemas` projects JSON Schema documents from Spatie `Data`
classes. Until now every schema was anonymous: the document `$id` was the
class's short name and nested `Data` classes were inlined under `$defs` keyed by
short name (`#/$defs/Short`). That is fine for a single point-in-time render, but
it gives schemas no identity over time — we cannot say "this is article v3",
cannot detect when a shape drifts, cannot store frozen artifacts, and cannot feed
a migration ladder (the next slice) a stable old-vs-new comparison.

The package is symlinked into a large Laravel app that already generates schemas
from many `Data` classes that declare no version. Any lifecycle machinery MUST
NOT change the output of those classes.

## Decision

### 1. Versioning is opt-in, carried natively in `$id`

A `Data` class opts into the lifecycle by implementing
`Rushing\LaravelDataSchemas\Contracts\SchemaIdentity`:

```php
interface SchemaIdentity {
    public static function schemaName(): string;   // e.g. "content/article"
    public static function schemaVersion(): int;    // orderable, author-declared
}
```

When a class implements it, `generateId()` emits an absolute versioned URI
`<base_uri>/<schemaName>/<schemaVersion>` (e.g.
`https://schemas.splicewire.app/content/article/3`). The version is carried
natively in `$id` — NOT in a bespoke `#[SchemaVersion]` attribute and NOT in the
`$schema` dialect field. `base_uri` is configurable
(`config('data-schemas.base_uri')`).

A class that does NOT implement `SchemaIdentity` is unchanged: short-name `$id`
and `#/$defs/Short` inlining. This is the entire backward-compatibility seam —
detection is a single `implementsInterface(SchemaIdentity::class)` check.

### 2. Uniform absolute-URI nested addressing (opt-in per node)

A nested `Data` class that ALSO implements `SchemaIdentity` projects its OWN
absolute versioned `$id` and is referenced via `$ref: <absolute-$id>` — a JSON
Schema 2020-12 bundled resource embedded under `$defs` keyed by `$id`, retaining
its `$id`. A nested class that does not opt in keeps `#/$defs/Short` inlining. A
tree therefore freely mixes addressable and inlined nodes.

### 3. Structural fingerprint (drift guard)

`SchemaFingerprint::of()` produces a stable sha256 over a canonicalized schema:
object keys are sorted; volatile/descriptive keys (`examples`, `description`,
`title`, `$id`, `$schema`, `$comment`) are excluded; lists keep order
(`required`, `enum`, `type` unions are structural). Same structure → same hash;
a structural change → a different hash.

### 4. Structural diff

`SchemaDiff::between($old, $new)` returns `{added, dropped, widened, changed,
breaking}`. "Widened" is a strict type superset honouring the numeric tower
(`integer ⊆ number`); a drop or any non-widening change sets `breaking: true`.
This is the explicit data structure the migration rungs will consume.

### 5. Bundling and `forLlmStrict`

`SchemaBundler::bundle()` produces a self-contained 2020-12 compound document:
absolute refs are embedded as bundled resources under `$defs` keyed by `$id`,
each retaining its `$id` (offline-portable yet re-resolvable). `dereference()`
fully inlines every ref (dropping `$id`/`$defs`) into a complete schema for
strict-LLM consumption — combined with the existing `forLlmStrict` mode (which
already strips `x-*` and root metadata) the model receives one complete, x-free
schema. `assertResolvable()` uses `opis/json-schema` to prove the result parses
and every ref resolves.

### 6. Immutable, `$id`-keyed registry

`SchemaRegistry` (contract) + `FilesystemSchemaRegistry` (impl) store frozen,
committed JSON artifacts keyed by `$id`, one file per `$id`, resolvable at any
depth. Versions are write-once: re-registering an `$id` with the same
fingerprint is an idempotent no-op; an in-place overwrite of a frozen `$id` whose
fingerprint differs throws `SchemaRegistryConflict`. A changed shape requires a
new `$id`/version.

## Consequences

- The consuming app's existing schema generation is byte-for-byte unchanged: no
  `Data` class there implements `SchemaIdentity`, so all keep short-name `$id`
  and `#/$defs/Short` inlining. (Verified by a backward-compat test.)
- New verticals can adopt versioned, addressable, registry-backed schemas
  incrementally — one class at a time — by implementing the interface.
- `opis/json-schema` is now a runtime dependency (ref-resolution / validation).
- `laravel-popcorn` (migration ladder execution) is deferred to slice 13; it is
  not needed for identity/fingerprint/diff/registry and would only add a VCS dep
  with nothing yet to wire.

## Alternatives considered

- **A `#[SchemaVersion]` attribute** — rejected: the version belongs in `$id`
  (the spec's identity field), keeping it orderable and resolvable without
  out-of-band lookup.
- **Version in the `$schema` dialect field** — rejected: `$schema` declares the
  meta-schema dialect, not document identity; overloading it breaks validators.
- **Always-on absolute `$id`s** — rejected: it would change every existing
  schema in the app, violating the hard backward-compat constraint.
- **Mutable registry with history table** — rejected for now: write-once frozen
  artifacts are simpler, diffable in git, and force a new `$id` on shape change,
  which is exactly the invariant the migration ladder depends on.
