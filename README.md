# Core Blueprint

The foundation plugin for every Core Blueprint site. Provides the security baseline, audit logging, failsafe lockout prevention, admin theming, site-wide locale preference, shared governance, and the public Foundation boundaries consumed by Core Blueprint extensions.

Remote site management is deliberately not part of Base. Install the optional **Core Blueprint Beacon** extension on a managed site when that site needs to connect to **Core Blueprint Hub**.

---

## Requirements

- WordPress 7.0 or newer
- PHP 8.4 or newer (PHP 8.5 recommended)
- PHP Sodium (libsodium) extension

If either PHP or Sodium is missing, Core Blueprint refuses to activate and shows a specific error on the plugins screen.

---

## Architecture

### Foundation layer (`CB\Core\*`)

Always active. Contains everything a Core Blueprint site needs as baseline infrastructure.

- **Security module registry** - one subsystem per module (headers, fingerprinting, failsafe, access mode)
- **Audit log** - durable record of security-relevant events with retention management and CSV export
- **Failsafe** - three-layer lockout-prevention mechanism so admin-only mode can never trap the site owner out
- **Access mode** - public / members / admin-only / maintenance states
- **Theme system** - Core Blueprint Dark and Core Blueprint Light, pluggable partner themes via `cb_admin_themes` filter
- **Locale filter** - site-wide language preference
- **Permissions + User Roles** - native WordPress role/capability management with Core Blueprint safety policy, audit logging, a suite-wide capability catalog, and additive user assignments (one base role plus additional roles)
- **Media Replace** - transactionally replace a Media Library file while preserving attachment ID, filename and URL; regenerates WordPress media metadata/sub-sizes and records replacements in the audit log
- **Media Formats** - optional upload/output policy for secure sanitized SVG, WebP, AVIF, experimental JPEG XL and HEIC/HEIF imports, with WordPress-native processing detection and generated-image format control
- **Package Downloads** - download installed plugins and themes as installable ZIP archives from native WordPress admin screens without writing temporary archives into package source directories
- **Content Models** - optional governed WordPress-native schema layer for post types, taxonomies, Option Pages, post/term/user fields, Relations, Group/Repeater and Conditional Logic, with JSON schema transfer, a public plugin API, an isolated Bricks adapter and canonical JSON schema portability
- **Shared admin chrome** - parent menu (`core-blueprint`), shared About page, declarative Dashboard Card API, privacy page

#### UI Foundation

Core Blueprint exposes shared UI semantics without forcing one visual skin onto every WordPress admin surface.

- **Core Blueprint admin surfaces** - pages and submenus below the Core Blueprint parent menu use the Core Admin Theme, shared tokens, components, and dark/light identity.
- **WordPress Pro geometry** - Core Admin follows WordPress admin interaction/layout grammar for page headings, tabs, spacing, forms, tables, and information density wherever WordPress already has a strong convention. Core Blueprint identity comes from colour, dark/light theming, semantic states, Lucide icons, and interaction polish rather than from oversized custom dashboard geometry.
- **Use custom geometry only when the workflow requires it** - workspaces such as Scanner Findings, User Roles, remediation flows, and consequence-aware switches may use richer Core Blueprint components where WordPress has no suitable native pattern. Ordinary settings, tables, navigation, and reference content should stay visually close to WordPress admin.
- **Consequence selector geometry** - `MasterSwitch` owns the binary two-card/toggle geometry. Access Mode is a four-state policy picker and therefore uses a 2×2 tile grid plus an explicit apply action; it must not be forced back into a binary toggle model.
- **Access Mode semantics** - Public is the normal green/live state. Coming Soon, Maintenance and Admin-Only are intentional amber/restricted states with distinct HTTP/SEO behaviour; UI presentation must not collapse them into a generic on/off switch.
- **MasterSwitch grouping** - the two consequence cards and central toggle already form the visual group. Core Admin consumers must not wrap a MasterSwitch in `cb-core-panel`; use the transparent `cb-core-master-switch-shell` only when a layout/rhythm hook is required.
- **Dashboard card semantics** - dashboard-style tiles are not interchangeable decoration. Use `navigation` for ordinary destinations, `status-nav` when that destination also communicates an operational state, `status` for richer live-status objects with actions, and `metric` for KPI/value cards. The legacy `quick` variant remains backwards compatible but should not be used by new code.
- **Standalone WordPress admin surfaces** - sibling-plugin content/operations screens should follow native WordPress admin conventions wherever possible. They may reuse Foundation semantics, accessibility helpers, icons, and interaction behaviour without inheriting Core Blueprint's themed presentation.
- **Buttons** - new Core Blueprint-admin actions use `cb-core-button` plus one semantic variant (`--primary`, `--secondary`, `--remediation`, `--danger`) and an independent density (`--compact` or the default density). Semantic meaning, density, and state must not be encoded into one another.
- **Specialized caution** - the existing `--warning` variant is retained for explicit consequential-caution flows such as Failsafe; it is not the general-purpose replacement for `--remediation` or `--danger`.
- **Button states** - hover, focus, disabled, busy/loading, and `aria-disabled` are states, not variants. Layout margins belong to the parent layout rather than the button component.
- **Button icons** - use the `cb-core-button__icon` slot (or a direct SVG child) and `cb-core-button__label`; sizing and spacing are owned by the Button primitive. A shared Lucide-backed icon registry is the intended icon source so sibling extensions do not ship competing icon systems.
- **Interactive surfaces** - Core Admin disclosures use `cb-core-disclosure`; actionable expandable rows use `cb-core-interactive-row` (optionally with `cb-core-interactive-surface`). Both consume the same interaction-state tokens for hover, focus, and open states.
- **Native disclosure behaviour** - shared disclosures and expandable rows use semantic `<details>/<summary>` markup. The browser owns open/closed state and accessibility semantics; do not add module-specific JavaScript merely to mirror `aria-expanded`.
- **Description density** - when a page already exposes the global Plain/Technical description mode, compact rows and nested progressive-disclosure content should use `UI::render_description_text()` so both variants remain mode-aware without adding a local TECH/PLAIN toggle at every information level.
- **Interactive variants vs states** - disclosure variants (`--section`, `--subtle`, `--compact`) describe structure/density. Interactive-row variants (`--warning`, `--critical`, `--failed`, `--ok`) describe semantic emphasis. `:hover`, `:focus-visible`, and `[open]` are states and must remain shared.
- **Presentation boundary** - Interactive Surface components belong to the Core Admin Theme. Standalone WordPress admin screens should keep WordPress-native presentation; `Assets::enqueue_icons()` remains the narrow opt-in path for shared icons without Core Admin styling.
- **Presentation adapters** - shared component semantics may later expose a Core Admin renderer and a WordPress-native adapter. The semantic action remains the same even when presentation differs by admin surface.
- **State badges** - compact live workflow/security state uses `CB\Core\UI\StateBadge` / `cb-core-state-badge`, with independent semantic variants (`neutral`, `info`, `success`, `warning`, `danger`, `error`) and density (`compact` or `default`). Use `danger` for a critical/risk state and `error` for a manifest failure; do not use state badges as action buttons.
- **Badge vs status** - `cb-core-badge` remains metadata/taxonomy/compliance; `StateBadge` is a compact state label; `CB\Core\UI\Status` is the dot + human-readable statement pattern for service/config state. These patterns are intentionally separate.
- **Risk metadata** - labels such as `high risk`, `medium risk`, and `restrictive` describe configuration metadata, not live state. Render them with shared `cb-core-badge-*` risk variants rather than module-specific chips or `StateBadge`.
- **Module racks** - the shared rack primitive may use density modifiers such as `cb-core-module-rack--compact`; page modules should opt into a shared modifier rather than copy rack/toggle/dot geometry into page-specific CSS.
- **Feedback hierarchy** - `Notice` is persistent in-page feedback, `cbCore.toast` is transient feedback, and `FormStatus` is inline save feedback. Their shared feedback semantics are `info`, `success`, `warning`, and `error`; destructive intent belongs to actions (`--danger`), not error feedback. `Notice` may include optional plain-text bullet items when consequences or prerequisites need a short scannable list; modules should not build a parallel warning-card component for that case.
- **Busy/loading** - async busy state uses `window.cbCore.busy` / `@cb-core/busy` and the shared Spinner primitive. Button busy state preserves/restores original markup and uses `aria-busy`; region busy state must be explicitly marked and must never apply a global `[aria-busy]` interaction lock to unrelated WordPress admin UI. Busy/loading is a state, never a semantic variant.
- **Fields** - `CB\Core\UI\Field` / `cb-core-field` is the shared Core Admin form-field composition. Label, control, error, help, and metadata are separate slots. The control remains caller-owned: callers wire `aria-invalid` and `aria-describedby` to the actual input/select/fieldset using the optional field message IDs rather than relying on DOM guessing.
- **Toolbars / filter bars** - `cb-core-toolbar*` is the canonical Core Admin composition for search/filter/action rows. `--compact` is the dense workspace variant. Historical `cb-core-filter-bar*` selectors remain supported as backwards-compatible aliases for Logs/Reports; do not create page-specific toolbar surfaces when this composition fits.
- **Modal** - `window.cbCore.modal` remains the shared Core Admin `<dialog>` primitive. Confirm-action semantics come from the Button Foundation; modal form controls use the same Form Control Foundation; title/body/input/status relationships are labelled with ARIA; focus starts on input or Cancel and returns to the opener after close. Input mode supports an explicit `input.label` and falls back to its placeholder for backwards-compatible accessible naming.
- **Form presentation boundary** - these Field/Toolbar/Modal presentation components belong to the Core Admin Theme. Standalone WordPress admin screens should prefer native WordPress form/table/filter patterns; shared semantics or accessibility helpers may be adapted without importing the Core Admin skin.

#### Icons / Lucide

Lucide is the canonical icon source for the Core Blueprint suite. Base owns a curated icon registry through `CB\Core\UI\Icon`; extensions must not bundle their own Lucide copy when Base is available. Prefer semantic aliases such as `quarantine`, `restore`, `delete`, `review`, `public-site`, `locked-site`, and `settings` so a glyph can be changed centrally without changing downstream plugins.

PHP surfaces use `Icon::render()`. Dynamic admin UI uses `window.cbCore.icon` / `@cb-core/icon`. Standalone WordPress admin pages that need dynamic icons opt in with `CB\Core\UI\Assets::enqueue_icons()`; this intentionally does not load the Core Admin Theme. Icons inherit `currentColor`, are decorative (`aria-hidden`) by default, and require an accessible label when the icon itself is the only carrier of meaning. The registry is deliberately curated rather than exposing the full Lucide catalog. Vendor attribution lives in `licenses/LUCIDE.md`.

### Beacon extension (`Core Blueprint Beacon`)

The optional satellite plugin owns `CB\Core\Beacon\*` and all remote-management runtime. Base only exposes the generic scanner, audit, DB, UI, status, CLI and extension boundaries it consumes.

When Beacon is installed and enabled it can pair this site with Core Blueprint Hub using the `core-blueprint/v1` REST namespace, encrypted Bearer authentication, governed backup/update routes, connection logging, and signed redirects.

### Sibling plugins

Core Blueprint is the foundation. Feature plugins hang off it:

- **Core Blueprint Beacon** - optional satellite extension installed on managed sites; connects Base to a Hub without putting remote runtime in Base.
- **Core Blueprint Hub** - central monitoring and management application for a fleet of Beacon-enabled Core Blueprint sites.
- Additional plugins are developed separately and integrate through the documented Core Blueprint extension, Dashboard and module-status contracts.

---

## Pairing with a Hub

1. Install and activate Core Blueprint Base on the target site
2. Install and activate **Core Blueprint Beacon**
3. Go to **Core Blueprint → Beacon**
4. Click **Generate new key** - this activates the paired Beacon runtime, installs the Connection Log table, and schedules the daily prune cron
5. On your Hub, add a new site and paste:
   - **Site URL** - the site's home URL
   - **Secret Key** - the plaintext key shown once on Beacon (copy it before navigating away)

After the first successful poll from the Hub, the Core Blueprint dashboard shows a "Connected" status tile.

---

## Uninstalling

Deleting Core Blueprint via the Plugins screen runs `uninstall.php`, which removes:

- Core Blueprint runtime/configuration options in the normal cleanup namespaces (including per-run update-state options matched by wildcard)
- User-meta keys `cb_core_theme`, `cb_core_description_mode`, `cb_core_base_role`, and the shared `cb_locale`
- Transients and scheduled events owned by Core Blueprint
- The `cb_operator` role and Core Blueprint-owned `cb_*` capabilities from WordPress roles
- Base-owned database tables such as `{prefix}cb_core_audit_log`

Beacon-owned backup archives and Beacon-owned tables/options are handled by the **Core Blueprint Beacon** plugin and are not removed by uninstalling Base. Core Scanner quarantine evidence is also deliberately preserved across uninstall: the private per-installation vault and its `cb_core_quarantine_workspace` index are retained so uninstall cannot silently destroy quarantined security evidence.

---

## Development

- Source: every class is in `src/`, one class per file, PSR-4 autoloaded.
- No Composer. The plugin bootstrap registers its own autoloader.
- No build step. Code ships as-is to WordPress.
- Text domain: `core-blueprint`. Translations in `languages/`.

**Invariants that production installs depend on** (change with care):

- Plugin folder name: `core-blueprint`
- Parent menu slug: `core-blueprint`
- REST namespace: `core-blueprint/v1`
- DB tables: `cb_core_audit_log`, `cb_core_beacon_connection_log`
- Backup error code strings (`CB_BACKUP_ERR_*`) - read by the Hub over REST

See `CHANGELOG.md` for version history.
