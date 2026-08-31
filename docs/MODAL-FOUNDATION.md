# Modal Foundation — Public Extension Contract

Core Blueprint Base owns the shared modal runtime. Extensions must not ship a
second modal engine for normal admin confirmation, input, typed-confirm, or
reference/help dialogs.

## Public runtime API

```js
window.cbCore.modal.show( options )
```

The official script-module dependency identifier is:

```text
@cb-core/modal
```

The implementation file path is private and may change. Extensions should depend
on the module identifier and public `window.cbCore.modal` API only.

## Standalone WordPress admin screens

On a standalone extension-owned wp-admin screen, enqueue the narrow Foundation
primitive instead of the full Core Admin Theme:

```php
\CB\Core\UI\Assets::enqueue_modals();
```

The default presentation is `wp-native`. It loads the modal runtime, shared
Lucide icon primitive, and `modals-native.css`. It does **not** load Core Admin
tokens, dark mode, cards, layouts, or the Core Admin Theme.

Pages under the Core Blueprint admin menu already receive the `core`
presentation from Base and normally should not call the helper themselves.

## Modes

### Confirmation

```js
const confirmed = await window.cbCore.modal.show( {
    title: 'Delete certificate?',
    body: 'This action cannot be undone.',
    confirmLabel: 'Delete',
    confirmVariant: 'danger',
} );
```

Resolves to `true` when confirmed and `false` when dismissed.

### Dismiss-only / informational

Use `dismissOnly: true` for help and reference content. It renders one quiet
Close action; it does not render a redundant Cancel button.

```js
const body = document.createElement( 'div' );
body.textContent = 'Reference content';

await window.cbCore.modal.show( {
    title: 'Keyboard shortcuts',
    body,
    dismissOnly: true,
    confirmLabel: 'Close',
} );
```

### Typed confirmation

```js
const confirmed = await window.cbCore.modal.show( {
    title: 'Delete all records',
    body: 'This cannot be undone.',
    confirmLabel: 'Delete all',
    confirmVariant: 'danger',
    typedConfirm: 'DELETE ALL',
} );
```

### Input

```js
const value = await window.cbCore.modal.show( {
    title: 'Reason',
    confirmLabel: 'Continue',
    input: {
        type: 'text',
        label: 'Reason',
        required: true,
    },
} );
```

Resolves to the entered string when confirmed and `null` when dismissed.

## Supported options

- `title` — modal heading; required by the public contract.
- `body` — string or `HTMLElement`.
- `confirmLabel` / `cancelLabel` — action labels.
- `confirmVariant` — `primary`, `secondary`, `remediation`, or `danger`.
- `confirmIcon` — semantic/canonical shared Lucide icon name.
- `dismissOnly` — informational/reference modal with one Close action.
- `typedConfirm` — exact phrase gate.
- `typedConfirmHint` / `typedConfirmMismatch` — optional copy overrides.
- `input` — `{ type, label, placeholder, maxLength, required }`.

`danger` remains destructive intent. It must not be used merely because a modal
contains an error message.

## Presentation boundary

```text
Shared Modal runtime / semantics
│
├── Core Admin presentation
│   └── Core Blueprint tokens + dark/light mode
│
└── WP Native presentation
    └── normal WordPress wp-admin geometry and controls
```

An extension must not enqueue `modals.css`, `tokens.css`, or private modal files
directly. Use `UI\Assets::enqueue_modals()`.

## Certificates example

Certificates can replace the long inline keyboard-shortcut sentence with a
normal WordPress button. Its screen enqueue callback calls:

```php
\CB\Core\UI\Assets::enqueue_modals();
```

Its Designer module declares `@cb-core/modal` as a dependency and then opens:

```js
await window.cbCore.modal.show( {
    title: 'Keyboard shortcuts',
    body: shortcutsTableElement,
    dismissOnly: true,
    confirmLabel: 'Close',
} );
```

The resulting dialog automatically uses the WP Native adapter on the standalone
Certificates admin screen.
