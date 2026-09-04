# Core Blueprint release tooling

`tools/build-release` is the canonical Base release-package builder.

It intentionally uses an **allowlist** rather than archiving the repository wholesale. Public release ZIPs contain only the plugin runtime, public documentation that belongs in the package, translations, and license material. Development-only files such as `.github/`, `tests/`, `tools/`, `vendor/`, Composer metadata, PHPUnit configuration, and Git metadata are not package inputs.

## Setup

Requirements:

- Python 3.10 or newer;
- a complete Core Blueprint Base source checkout;
- no Composer, Node, or build-tool dependency is required for packaging.

The plugin header version and `CB_CORE_VERSION` must match. The builder fails closed when required runtime files/directories are missing, version metadata disagrees, or an allowlisted source path contains a symlink.

## Usage

From the repository root:

```bash
python3 tools/build-release
```

Choose a different output directory:

```bash
python3 tools/build-release --output-dir /tmp/core-blueprint-release
```

Build a different source checkout, for example a pinned previous RC used by CI update testing:

```bash
python3 tools/build-release --source /path/to/source --output-dir /tmp/core-blueprint-release
```

## Outputs

For version `1.0.0-rc3.41` the builder creates:

```text
dist/core-blueprint-1.0.0-rc3.41.zip
dist/core-blueprint-1.0.0-rc3.41.zip.sha256
```

Every ZIP entry lives below the canonical plugin root:

```text
core-blueprint/
```

The archive is deterministic for identical source bytes: paths are sorted, ZIP timestamps are fixed, permissions are normalized, and compression settings are stable.

## Failure behaviour

Packaging stops with a non-zero exit code when:

- `core-blueprint.php`, `uninstall.php`, `README.md`, `CHANGELOG.md`, or `LICENSE` is missing;
- an expected runtime directory is missing;
- plugin-header `Version` differs from `CB_CORE_VERSION`;
- a release input is a symlink;
- the allowlist unexpectedly resolves to no files.

A failed build must never be treated as a releasable artifact.

## Maintenance

When Base gains or removes a **runtime-owned top-level path**, update the allowlist in `tools/build-release` and the release-package CI assertions in the same change.

Do not add repository-wide directories merely because a new development tool needs them. The packaging boundary should answer one question only:

> Does WordPress or a Base user need this file in the installed production plugin?

When the answer is no, the path stays outside the release ZIP.

Before changing the canonical root name, version-parsing rules, or previous-RC pin used by CI, review the Base packaging/update contract first. The production plugin folder remains `core-blueprint/`.
