# Core Blueprint changelog

## 1.0.0-rc3.41 — 2026-09-04

### BASE-V1-H — Final Base release candidate

- Mark rc3.41 as the controlled final-RC candidate for the Base v1 release-closure cycle; stable `1.0.0` remains a separate explicit approval gate after staging/manual validation.
- Add an allowlist-based deterministic production ZIP builder that always packages below the canonical `core-blueprint/` root and emits a SHA-256 checksum.
- Exclude repository/test tooling, Composer development metadata, PHPUnit configuration and GitHub workflow files from public release packages by construction rather than by cleanup after archiving.
- Add CI package-boundary validation for required runtime payload, canonical root, version consistency and packaged PHP syntax.
- Add real WordPress 7.0 / PHP 8.4 release-package smoke coverage for both a fresh rc3.41 ZIP install/activation and an update from pinned canonical rc3.40 to rc3.41 while preserving active state.
- Keep Base production domain/security behavior unchanged in H apart from the rc3.41 version marker.

### v1 hardening completed after rc3.28

The rc3.29-rc3.40 development interval was dominated by the structured BASE-V1 release-hardening roadmap rather than feature expansion. Major closure work included:

- reproducible PHPUnit/WordPress CI foundations across WordPress 7.0/7.1 and PHP 8.4/8.5;
- request-boundary lifecycle and data-ownership scenarios plus isolated destructive uninstall coverage;
- canonical Extension Starter source/consumer conformance and the full optional-module enable/disable/re-enable matrix;
- privileged-request, filesystem-recovery, Role Policy/Failsafe, Scanner/provenance and Media Replace durable-state conformance;
- removal of duplicated activation authority and centralization of extension lifecycle ownership;
- centralization of Core Admin asset-hook ownership and request-aware Content Models bootstrap boundaries;
- measured performance attribution, query-source provenance and targeted HUD update-transient cache priming without adding Base-owned cache state;
- public-API freeze/open-source hygiene, including the canonical root GPL license file.

The complete pre-H development history through `1.0.0-rc3.28` is preserved verbatim in [`CHANGELOG-HISTORY.md`](CHANGELOG-HISTORY.md).
