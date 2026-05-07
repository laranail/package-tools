# ADR-0006 — Laravel `.env` is append-only

- **Status:** Accepted (2026-05)
- **Scope:** `package-tools` (`EnvFileService`); applied conceptually across the suite

## Context

When a package built on `laranail/package-tools` is installed in a
host Laravel app, the install command may need to inject keys into the
host's `.env`. The host's `.env` is hand-tuned — it contains real
secrets, deliberate orderings, and end-user comments. Trashing it on
install would be a hostile failure mode.

## Decision

`Services\Environment\EnvFileService` is the **only** sanctioned writer
to a host's `.env`. Discovery walks `Application::environmentFilePath()`
first, then walks up from `cwd`. If no `.env` is found, every read
returns `null` and every write throws — we **never** auto-create.

Write contract:

- `appendIfMissing(key, value, comment)` — no-op if key exists.
- `appendBlock(entries, sectionTitle)` — adds a labelled `# === ===`
  block at EOF; skips already-present keys.
- `forceSet(key, value, acknowledgeDestructive: true)` — only
  destructive method. Requires explicit acknowledgement; emits
  `EnvFileMutated` event for audit trails.

Atomicity:

1. `backup()` → `.env.bak.<timestamp>`.
2. Write tmp → `.env.tmp.<pid>.<rand>`.
3. `rename()` — atomic on POSIX.
4. Dispatch `EnvFileMutated` event after success.

Newline handling: detects LF vs CRLF; matches the host file's
convention. Detects missing trailing newline; injects one before
appending so lines never fuse.

Quote-on-write: values containing whitespace, `#`, `=`, `"`, `'`, or
`\` are double-quoted with `addcslashes` for `"` and `\`. Round-trips
through the parser.

Tool config (PHPStan/Pint/Rector) lives in **native config files**
(`phpstan.neon`, `pint.json`, `rector.php`); runtime config lives in
standard Laravel `config/<package>.php` files. No JSON shape file.

## Consequences

- Consumers can `package:install` a Laranail package without losing
  their `.env` state.
- Audit log via `EnvFileMutated` — subscribers can record every write.
- Cross-platform safe (macOS/Linux/Windows newline conventions handled).
