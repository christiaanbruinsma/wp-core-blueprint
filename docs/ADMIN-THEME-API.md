# Admin Theme API

Core Blueprint Base owns one theme state for the complete WordPress admin. The built-in themes are **Core Blueprint Light** and **Core Blueprint Dark**. There is no separate Core Admin presentation mode.

The theme engine has three integration layers:

1. **Semantic CSS tokens** for normal UI styling.
2. **PHP API and hooks** for WordPress admin integrations.
3. **Browser API and events** for interfaces that must redraw non-CSS surfaces after a live theme switch.

## CSS is the primary contract

Do not detect a built-in theme slug in extension CSS. Use semantic tokens instead:

```css
.my-extension-card {
    background: var(--cb-surface-1);
    color: var(--cb-text);
    border: 1px solid var(--cb-border);
}

.my-extension-card:focus-within {
    box-shadow: 0 0 0 2px var(--cb-interactive-focus);
}
```

This keeps extensions compatible with Light, Dark, and partner themes registered through `cb_admin_themes`.

## PHP API

```php
use CB\Core\UI\AdminTheme;

$theme = AdminTheme::theme();
$mode  = AdminTheme::mode();
```

`AdminTheme::mode()` is the server-resolved initial mode. When the preference is Auto, the browser can resolve the final built-in Light/Dark mode from `prefers-color-scheme` before paint.

### Declare a compatible screen

A third-party or sibling extension may explicitly declare a screen as compatible after WordPress returns its hook suffix:

```php
$hook = add_menu_page(/* ... */);
AdminTheme::register_screen($hook);
```

Registration is **not** required to receive Light/Dark theme state. The theme engine is global in `wp-admin`. Registration is a compatibility declaration and adds the `cb-admin-theme-compatible` body class on that screen.

## Hooks

### Register partner themes

The existing `cb_admin_themes` filter remains the canonical registry. Partner themes must provide the same semantic color-token contract as Base.

### Safety valve for self-contained admin applications

The theme applies to all normal `wp-admin` screens by default. A developer that owns a self-contained application and knows it is incompatible may opt that screen out:

```php
add_filter('cb_admin_theme_apply', function (bool $apply, $screen): bool {
    if ($screen && 'my_app_page' === $screen->id) {
        return false;
    }

    return $apply;
}, 10, 2);
```

This is a developer compatibility boundary, not a user-facing presentation mode.

### Theme-aware enqueue hook

```php
add_action(
    'cb_admin_theme_enqueue',
    function (string $hookSuffix, string $theme, string $mode, bool $registered): void {
        // Enqueue extension-owned theme-aware assets when needed.
    },
    10,
    4
);
```

Additional hooks:

- `cb_admin_theme_screen_registered`
- `cb_admin_theme_body_classes`

## Browser API

For charts, canvas renderers, code editors, or similar JavaScript interfaces:

```js
window.cbAdminTheme.theme();
window.cbAdminTheme.mode();
window.cbAdminTheme.state();
```

Listen for a live HUD theme change:

```js
document.addEventListener('cb:admin-theme-change', (event) => {
    const { theme, mode } = event.detail;
    // Redraw non-CSS UI only when necessary.
});
```

The runtime also emits `cb:admin-theme-ready` after the browser API becomes available.

## Ownership rule

Base owns:

- theme state and persistence;
- HUD Light/Dark switching;
- semantic tokens;
- WordPress Core admin adaptation;
- shared Foundation component presentation;
- public theme integration contracts.

Extensions own their domain composition and must not implement their own theme state, theme toggle, or duplicated shared presentation layer.

The WordPress editor content canvas is intentionally not forced into Dark or Light. Editor chrome may follow the admin theme, while content continues to represent the site/editor styles.
