# Core Blueprint Platform Principles

Core Blueprint exists to strengthen digital autonomy in WordPress: organisations should retain meaningful control over their data, infrastructure, suppliers and operational decisions.

## Principles

1. **Local-first and sovereign by default** — functionality runs in the WordPress environment where practical. No hidden telemetry, mandatory SaaS account or silent cloud dependency.
2. **Privacy by architecture** — collect and retain only what the feature needs; keep secrets out of logs; prefer explicit opt-in integrations and safe defaults.
3. **Governance by design** — sensitive mutations have explicit capabilities and intent boundaries, and important changes are auditable.
4. **Exit freedom** — use understandable WordPress-native data structures and portable formats. Core Blueprint must not make leaving a provider or the plugin artificially difficult.
5. **Fail closed for trust boundaries, fail recoverably for operations** — remote, filesystem and privilege boundaries refuse ambiguous input; recovery paths remain available and documented.
6. **Extensions through contracts** — third-party code integrates through documented hooks/APIs rather than implementation details. Base owns validation and presentation boundaries where shared trust is involved.
7. **No security theatre** — documentation describes what a control actually protects. Intent confirmation is not authentication; encryption is not access control; logs are not prevention.
8. **European operational reality** — designs should work on ordinary European managed WordPress hosting, avoid unnecessary hyperscaler dependencies and support privacy/governance-sensitive sectors.

These principles are product constraints, not optional branding guidance.
