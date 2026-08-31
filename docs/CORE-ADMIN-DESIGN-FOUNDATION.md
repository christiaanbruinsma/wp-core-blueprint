# Core Admin Design Foundation

Status: **public v1 Core Admin markup/design contract**.

## Ownership boundary

A `PageRegistry` component requirement means:

```text
semantic component ID
→ documented markup/behavior contract
→ Base-owned presentation/assets
```

It does **not** expose CSS filenames, WordPress handles, enqueue order or bundle boundaries. Base owns the canonical visual language for shared Core Admin primitives, including light/dark presentation, tokens, typography, spacing, surfaces, borders, radii, shadows, hover/focus and semantic states.

First-party extension CSS may position or compose a Base primitive, but must not locally redraw its canonical appearance. Feature-specific product components remain extension-owned and should consume `--cb-*` tokens where practical.

The minimal Core Admin shell does not automatically include every primitive. Registered pages declare only what their markup consumes.

## `nav-tabs`

WordPress-native tab navigation with Core Admin presentation.

```html
<nav class="nav-tab-wrapper cb-core-tab-wrapper" aria-label="Section navigation">
    <a class="nav-tab nav-tab-active" href="...">Overview</a>
    <a class="nav-tab" href="...">Settings</a>
</nav>
```

Contract: keep WordPress `nav-tab` / `nav-tab-active` interaction grammar and add `cb-core-tab-wrapper` to the wrapper. Base owns tab colours, borders, typography, focus and dark/light states.

## `panels`

Shared framed content surface.

```html
<section class="cb-core-panel">
    <h2>Section title</h2>
    <p>Content…</p>
</section>
```

A direct child `table.widefat` is a supported panel composition and inherits Base panel-table presentation. Panel appearance is Base-owned; extension CSS may place the panel in a grid or layout.

## `cards`

Static information/content card; not a navigation tile.

```html
<section class="cb-core-card">
    <header class="cb-core-card__header">
        <h2 class="cb-core-card__title">Title</h2>
    </header>
    <div class="cb-core-card__body">Content…</div>
</section>
```

Supported structural elements include `__header`, `__title`, `__icon`, `__body`, `__body--flush`, `__lead`, `__empty`, `__footer` and `__footer-stripe`; `cb-core-card--spacious` is the shared spacious variant.

## `badges`

Metadata/classification badge.

```html
<span class="cb-core-badge cb-core-badge-tech">PHP 8.5</span>
```

Use `cb-core-badge` plus a Base-defined semantic/classification variant such as `cb-core-badge-tech`, `cb-core-badge-standard`, `cb-core-badge-identity`, `cb-core-badge-cwe`, `cb-core-badge-compliance`, `cb-core-badge-severity` or the documented severity/risk modifiers. Do not invent local colour variants for generic badge states.

## `state-badges`

Compact workflow/state label.

```html
<span class="cb-core-state-badge cb-core-state-badge--success cb-core-state-badge--compact">Active</span>
```

Canonical state modifiers are `--neutral`, `--info`, `--success`, `--warning`, `--danger`/`--error`; size modifiers are `--compact` and `--default`.

## `status`

Informational dot + text status indicator.

```html
<span class="cb-core-status">
    <span class="cb-core-status__dot cb-core-status__dot--success" aria-hidden="true"></span>
    <span class="cb-core-status__label">Healthy</span>
</span>
```

The visible label carries the accessible state meaning; the dot is decorative and should be `aria-hidden`. Canonical dot modifiers include success, warning, danger, info and muted.

## `empty-state`

Standalone empty-result/message surface.

```html
<p class="cb-core-empty">No items yet.</p>
```

For an empty region inside a Card use the Card contract's `cb-core-card__empty` instead.

## `kv-table`

Key/value or diagnostic/current-state table.

```html
<table class="widefat cb-core-kv">
    <tbody>
        <tr>
            <th scope="row">PHP</th>
            <td>8.5</td>
        </tr>
    </tbody>
</table>
```

`th[scope="row"]` is the canonical key column. `cb-core-current` may mark the current row. This semantic is deliberately **not** a generic arbitrary-table contract. Feature-specific tables remain feature-owned until Base defines a true shared table primitive.

## `form-controls`

Core Admin presentation for native WordPress form controls inside the Core Admin surface. Existing WordPress form semantics and accessible labels remain authoritative; Base owns presentation. The minimal Core Admin shell already provides the baseline form-control asset, but the semantic ID remains accepted as an explicit component declaration.

## `description-toggle`

Plain/Technical dual-description block with Base-owned toggle behavior.

Prefer Base's renderer so the accessibility and toggle markup remain canonical:

```php
CB\Core\UI::render_description_block(
    [
        'plain'     => 'Plain explanation…',
        'technical' => 'Technical explanation…',
    ],
    CB\Core\UI::current_mode()
);
```

The resulting contract uses `.cb-core-desc.cb-core-dual[data-active]`, `.cb-core-desc-plain`, `.cb-core-desc-technical` and the `.cb-core-desc-toggle[data-current]` button with its accessible label/title. Base owns the `@cb-core/description-toggle` behavior, current Plain/Technical mode and presentation; consumers should not duplicate the control markup when the renderer is available.

## Not public primitives

Private/page-specific styles such as `table-cols`, `policy-table`, `log-table`, Scanner/Reports-specific table rules and other feature composition CSS are **not** promoted by this contract merely because more than one Base screen uses them. A future public primitive requires a genuinely reusable markup/behavior contract first.
