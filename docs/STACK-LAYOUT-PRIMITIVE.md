# Stack Layout Primitive

`cb-core-stack` is the shared Core Blueprint vertical composition primitive. It exists to keep spacing **between** sibling components token-based and consistent without teaching those components about their neighbors.

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

A direct `.cb-core-section` child has its normal flow margin reset because Stack owns the inter-section gap.

## Ownership rule

- Child Foundations own their border, padding, internal grid and interaction.
- Stack owns only sibling spacing.
- Do not add `Component + Component { margin-top: ... }` rules to solve composition problems.
- Do not create module-specific spacing clones when Stack expresses the layout.

## File inputs

Native Core-admin `<input type="file">` controls are owned by the shared Form Controls Foundation, including `::file-selector-button`. Consumers may still set local width/layout constraints, but must not duplicate the visual control styling.
