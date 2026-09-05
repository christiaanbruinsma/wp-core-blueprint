# Core Blueprint Foundation v1 Contract

Status: **public v1 freeze candidate**.

## Presentation boundary

- Pages below the Core Blueprint admin menu use Core Admin presentation and `--cb-*` tokens.
- Standalone WordPress admin pages remain WordPress-native and may opt into Foundation behavior through the public enqueue helpers.
- Loading Core tokens alone must never be used to infer that a standalone screen is a Core Admin screen.

## Public behavior/runtime primitives

| Primitive | PHP enqueue | Script-module/runtime |
| --- | --- | --- |
| Icons | `CB\Core\UI\Assets::enqueue_icons()` | `@cb-core/icon`, `window.cbCore.icon` |
| Toast | `CB\Core\UI\Assets::enqueue_toasts()` | `@cb-core/toast`, `window.cbCore.toast` |
| Modal | `CB\Core\UI\Assets::enqueue_modals()` | `@cb-core/modal`, `window.cbCore.modal` |
| Clipboard | `CB\Core\UI\Assets::enqueue_clipboard()` | `@cb-core/clipboard`, `window.cbCore.clipboard` |
| Token Input | `CB\Core\UI\Assets::enqueue_token_inputs()` | `@cb-core/token-input`, `window.cbCore.tokenInput` |
| Time Picker | `CB\Core\UI\Assets::enqueue_time_picker()` | `@cb-core/time-picker`, `window.cbCore.timePicker` |
| Icon Picker | `CB\Core\UI\Assets::enqueue_icon_picker()` | `@cb-core/icon-picker`, `window.cbCore.iconPicker` |
| Capability Picker | `CB\Core\UI\Assets::enqueue_capability_picker()` | `@cb-core/capability-picker`, `window.cbCore.capabilityPicker` |
| Choice Group | `CB\Core\UI\Assets::enqueue_choice_group()` | PHP/CSS primitive; no JavaScript runtime required |
| Object Picker | `CB\Core\UI\Assets::enqueue_object_picker()` | `@cb-core/object-picker`, `window.cbCore.objectPicker` |
| Select Picker | `CB\Core\UI\Assets::enqueue_select_picker()` | `@cb-core/select-picker`, `window.cbCore.selectPicker` |
| Form Composition | `CB\Core\UI\FormComposition::enqueue()` | PHP/CSS primitive; no JavaScript runtime required |

Consumers provide business meaning and exact values. Foundation owns generic behavior, accessibility and presentation adapters.

The Modal Foundation includes the additive public `confirmCheck: { label }` option. Presence means a required, initially unchecked native acknowledgement checkbox. `confirmCheck` is orthogonal to the existing confirm/typed/input modes, never changes their resolved value, and composes with other gates so Confirm is available only when every active gate is valid. Invalid or empty labels fail closed; see `MODAL-FOUNDATION.md`.

`Stack` is the shared vertical composition primitive (`.cb-core-stack`, plus compact/loose spacing variants). `Field` and Stack share one semantic markup contract across Core Admin and standalone WordPress admin screens. Standalone consumers use the narrow WordPress-native Form Composition adapter so WordPress keeps ownership of native controls, colours and chrome while Base owns only field grouping and vertical rhythm; see `FORM-COMPOSITION-FOUNDATION.md`. Child components keep ownership of their internal geometry. `Form Controls` owns the Core-scoped native file-input and `::file-selector-button` presentation, so modules must not redraw upload controls locally.

## Core Admin component contracts

The frozen component layer includes Button, Badge, StateBadge, Status, Notice, Busy, Field, Form Controls, Stack, CheckRow, ChoiceGroup, ObjectPicker, SelectPicker, Toolbar, Disclosure, MasterSwitch, ChoiceCard/RadioCard, Empty State, Overview cards, metric tiles, Integration Grid, Detail Rows, KV Table and Scrollbar.

`IntegrationGrid` is the Base-owned presentation primitive for integration/readiness cards on registered Core Admin pages. Consumers own detection/readiness semantics, labels and action destinations; Base owns card/grid presentation and maps the public `ready|needs-setup|optional|unavailable` states to the existing Status primitive. The normative contract is `INTEGRATION-GRID-FOUNDATION.md`.

`DetailRows` is the Base-owned presentation primitive for compact object/target/resource rows composed inside a consumer-owned card or section. Consumers own row inventory, domain meaning, readiness logic, labels, destinations and surrounding guidance; Base owns row anatomy, responsive presentation and composition with the existing Status/Button primitives. Detail Rows uses the existing generic Status semantics directly and does not extend Integration Grid with nested target semantics. The normative contract is `DETAIL-ROWS-FOUNDATION.md`.

`StatusMenu` is a Base-owned Core Admin composition primitive for compact status + action popovers (currently used by Dashboard cards). It is intentionally not a public extension enqueue contract: sibling extensions contribute declarative Dashboard shortcuts through `CardRegistry`, while Base owns rendering and interaction.

Page CSS may own composition (grid, section rhythm, task-specific preview sizing) but must not visually clone one of these components.

### Public Core Admin Design Foundation semantics

Registered Core Admin extension pages consume shared visual primitives through `PageRegistry` component IDs, not asset handles. The public v1 semantic set is `nav-tabs`, `panels`, `cards`, `metric-tiles`, `integration-grid`, `detail-rows`, `notices`, `fields`, `radio-cards`, `master-switch`, `disclosure`, `badges`, `state-badges`, `status`, `empty-state`, `kv-table`, `form-controls` and `description-toggle`. The Integration Grid contract is normative in `INTEGRATION-GRID-FOUNDATION.md`; the Detail Rows contract is normative in `DETAIL-ROWS-FOUNDATION.md`; the remaining minimal markup/behavior contracts are normative in `CORE-ADMIN-DESIGN-FOUNDATION.md`. Base remains free to change internal CSS filenames, handles, bundle boundaries and implementation details.

Base owns canonical appearance for these primitives. First-party extension styles may position or compose them, but may not duplicate their generic surfaces, colour, typography, borders, radii, spacing, shadows, focus, hover or state styling. A deliberately different product-specific component or variant remains extension-owned and should use Base tokens where practical.

Design ownership does not imply universal loading. The minimal Core Admin shell remains narrow; a page receives an optional primitive only when it declares that semantic requirement (or when a Base-owned screen manifest selects it).

## Compatibility

- Historical Toolbar aliases (`cb-core-filter-bar*`) remain supported for backwards compatibility, but Base itself uses `cb-core-toolbar*`.
- Legacy `Tile::quick` remains available for backwards compatibility; new navigation surfaces should use the formal navigation/status tile variants or Overview navigation cards as appropriate. The public `metric-tiles` semantic exposes only the current generic metric/KPI tile contract and does not promote those legacy/navigation variants.
- Modal confirm presentation uses only the canonical `confirmVariant` option.

## Release guardrails

A Base release is blocked when any of the following fail:

- plugin ZIP root is not exactly `core-blueprint/`;
- plugin basename changes from `core-blueprint/core-blueprint.php`;
- packaged source differs from the validated worktree;
- PHP/JS/CSS validation fails;
- a public Foundation dependency is referenced without being registered/enqueued on that route;
- a standalone wp-admin consumer receives Core Admin presentation unintentionally.
