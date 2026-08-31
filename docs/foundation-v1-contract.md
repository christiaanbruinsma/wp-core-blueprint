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

Consumers provide business meaning and exact values. Foundation owns generic behavior, accessibility and presentation adapters.

`Stack` is the shared vertical composition primitive (`.cb-core-stack`, plus compact/loose spacing variants). Child components keep ownership of their internal geometry; Stack owns only spacing between direct siblings. `Form Controls` owns the Core-scoped native file-input and `::file-selector-button` presentation, so modules must not redraw upload controls locally.

## Core Admin component contracts

The frozen component layer includes Button, Badge, StateBadge, Status, Notice, Busy, Field, Form Controls, Stack, CheckRow, ChoiceGroup, ObjectPicker, SelectPicker, Toolbar, Disclosure, MasterSwitch, ChoiceCard/RadioCard, Empty State, Overview cards, KV Table and Scrollbar.

`StatusMenu` is a Base-owned Core Admin composition primitive for compact status + action popovers (currently used by Dashboard cards). It is intentionally not a public extension enqueue contract: sibling extensions contribute declarative Dashboard shortcuts through `CardRegistry`, while Base owns rendering and interaction.

Page CSS may own composition (grid, section rhythm, task-specific preview sizing) but must not visually clone one of these components.

## Compatibility

- Historical Toolbar aliases (`cb-core-filter-bar*`) remain supported for backwards compatibility, but Base itself uses `cb-core-toolbar*`.
- Legacy `Tile::quick` remains available for backwards compatibility; new navigation surfaces should use the formal navigation/status tile variants or Overview navigation cards as appropriate.
- Modal confirm presentation uses only the canonical `confirmVariant` option.

## Release guardrails

A Base release is blocked when any of the following fail:

- plugin ZIP root is not exactly `core-blueprint/`;
- plugin basename changes from `core-blueprint/core-blueprint.php`;
- packaged source differs from the validated worktree;
- PHP/JS/CSS validation fails;
- a public Foundation dependency is referenced without being registered/enqueued on that route;
- a standalone wp-admin consumer receives Core Admin presentation unintentionally.
