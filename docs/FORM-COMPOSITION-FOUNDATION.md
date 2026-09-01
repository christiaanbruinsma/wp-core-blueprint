# Form Composition Foundation

Status: **public v1 UI Foundation contract**.

Form Composition gives Core Blueprint screens one semantic Field + Stack markup contract across both presentation boundaries:

- Core Admin keeps the token-based Design Foundation presentation.
- Standalone WordPress admin screens keep native WordPress controls, colours and chrome and receive only structural field grouping / vertical rhythm.

## Enqueue boundary

Consumers enqueue the narrow adapter through:

```php
use CB\Core\UI\FormComposition;

FormComposition::enqueue();
```

With no explicit presentation, Base resolves from the actual admin screen: Core Blueprint menu screens receive the Core presentation and standalone WordPress admin screens receive the `wp-native` adapter. This resolution is based on screen identity, never on whether Core token CSS happens to be loaded. Consumers may explicitly request `FormComposition::PRESENTATION_CORE` or `FormComposition::PRESENTATION_WP_NATIVE` only when they genuinely own that presentation context.

Normal pages registered under the Core Blueprint menu should still prefer PageRegistry's `fields` semantic requirement; Stack is already part of the minimal Core Admin shell.

Consumers must not depend on the internal stylesheet handles or filenames used by this helper.

## Stack contract

```html
<div class="cb-core-stack cb-core-stack--form">
    <div class="cb-core-field">...</div>
    <fieldset class="cb-core-field">...</fieldset>
    <table class="widefat">...</table>
</div>
```

Variants:

- `cb-core-stack` — normal component rhythm.
- `cb-core-stack--compact` — tighter related-control rhythm.
- `cb-core-stack--form` — top-level form/field rhythm. Use this when adjacent direct children are logical fields, fieldsets, summary tables or other peer sections in one form surface.
- `cb-core-stack--loose` — larger workflow/section rhythm.

Stack owns only spacing between its direct children. Child components keep ownership of their own internal geometry. Do not compensate for a missing form rhythm by adding local margins to field labels, descriptions or neighboring tables.

## Field contract

Use `CB\Core\UI\Field::render()` when one normal wrapper + label/control/hint shape fits the field. Native semantic containers such as `<fieldset>` may use the same classes directly when their HTML semantics require a `<legend>`.

```html
<div class="cb-core-field">
    <label class="cb-core-field__label" for="example">Example</label>
    <input id="example" type="text" class="regular-text">
    <p class="description">Helpful context.</p>
</div>
```

Field owns the compact internal rhythm between label, control and help/error/meta text. In the WP-native adapter, these child relationships use the field gap as the spacing authority; consumers should not add extra top margins to `.description` merely to separate one field from the next.

### Simple choices

Use `cb-core-field__choices` for a small inline/wrapping set of native radio or checkbox labels that belong to one field. WordPress continues to own the controls themselves; Form Composition owns only the spacing between peer choices.

```html
<fieldset class="cb-core-field">
    <legend class="cb-core-field__label">Visibility</legend>
    <div class="cb-core-field__choices">
        <label><input type="radio" name="visibility" value="private"> Private</label>
        <label><input type="radio" name="visibility" value="public"> Public</label>
    </div>
    <p class="description">Controls package access.</p>
</fieldset>
```

`cb-core-field__choices` is intentionally not a substitute for Choice Group. Use the dedicated Choice Group Foundation when the options need cards, a managed grid, longer descriptions or richer selection presentation.

Supported structural classes include `cb-core-field`, `cb-core-field--inline`, `cb-core-field--separated`, `cb-core-field--enable`, `cb-core-field__label`, `cb-core-field__control`, `cb-core-field__choices`, `cb-core-field__hint`, `cb-core-field__error` and `cb-core-field__meta`.

## Ownership rules

On standalone WordPress admin screens:

- WordPress owns native input/select/textarea/radio/checkbox/button presentation.
- Form Composition owns only grouping, spacing and field-level structural states.
- `cb-core-stack--form` owns the spacing between peer fields/sections; `cb-core-field` owns the spacing inside one field; `cb-core-field__choices` owns spacing between simple peer choices.
- Do not load Core Admin tokens/theme merely to obtain spacing.
- Do not add local `field + field { margin-top: ... }` chains when Stack expresses the composition.
- Feature-specific grids, previews and task-specific sizing may remain extension-owned.

This adapter intentionally does not make a standalone screen a Core Admin screen and does not add dark mode, Core surfaces, Core buttons or Core form-control skinning.
