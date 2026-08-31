# Core Blueprint Base release process

Base has a stable WordPress plugin identity:

- directory: `core-blueprint/`
- main file: `core-blueprint/core-blueprint.php`
- basename: `core-blueprint/core-blueprint.php`

Do not create distributable archives with a working-directory name such as `cb_rcXXX_work/`.

## Build

From a repository checkout, use:

```text
python tools/package-release.py /path/to/plugin/source /path/to/core-blueprint-<version>.zip
```

The tool deliberately ignores the source directory name and always writes the archive under the canonical `core-blueprint/` root. It refuses to create a filename that does not match the plugin header/`CB_CORE_VERSION`.

## Blocking checks

The package command fails when any of these contracts are broken:

- archive root is not exactly `core-blueprint/`;
- `core-blueprint/core-blueprint.php` is missing;
- plugin header and `CB_CORE_VERSION` differ;
- ZIP filename and packaged version differ;
- symlinks or common build/editor junk are present;
- packaged PHP fails `php -l`;
- packaged JS fails `node --check`;
- packaged CSS has unbalanced structural braces/comments.

## Runtime defence

The Base entrypoint also contains a duplicate-load guard. If a packaging/activation mistake causes WordPress to include a second Core Blueprint entrypoint in the same request, the first loaded copy owns the runtime and the duplicate exits before redefining constants. The skipped path is written to the PHP error log for diagnosis.
