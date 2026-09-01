# Stack Layout Primitive

`cb-core-stack` is the shared Core Blueprint vertical composition primitive. It exists to keep spacing **between** sibling components consistent without teaching those components about their neighbors.

Core Admin uses the token-based Stack presentation from the minimal shell. Standalone WordPress admin screens may reuse the same markup through the narrow WordPress-native Form Composition adapter documented in `FORM-COMPOSITION-FOUNDATION.md`; that adapter keeps WordPress ownership of controls, colours and chrome.

## Contract

```html
<div class="cb-core-stack">
    <div class="cb-core-choice-group">...</div>
    <div class="cb-core-choice-group">...</div>
</div>
```

Variants:

- `cb-core-stack` — default component spacing.
- `cb-core-stack--compact` — tighter related-control spacing.
- `cb-core-stack--loose` — larger section/workflow spacing.

A direct `.cb-core-section` child has its normal flow margin reset in Core Admin because Stack owns the inter-section gap.

## Ownership rule

- Child Foundations own their border, padding, internal grid and interaction.
- Stack owns only sibling spacing.
- Do not add `Component + Component { margin-top: ... }` rules to solve composition problems.
- Do not create module-specific spacing clones when Stack expresses the layout.
- Standalone consumers must not load the Core Admin theme merely to obtain Stack spacing.

## File inputs

Native Core-admin `<input type="file">` controls are owned by the shared Form Controls Foundation, including `::file-selector-button`. Consumers may still set local width/layout constraints, but must not duplicate the visual control styling.
