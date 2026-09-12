# Core Blueprint Designer Mode

Status: **public v1 Designer launch contract**.

Base owns the Designer Shell chrome, viewport lifecycle and launch transition. Consumers own their editor semantics, canvas content, persistence and business behaviour.

## Manual mode

Manual mode remains the default. Use it when a normal WordPress admin page should remain visible until the user explicitly chooses to enter the Designer.

```html
<div data-cb-design-launch-root>
    <div data-cb-design-launch-context>…normal admin context…</div>
    <div class="cb-core-design-shell" data-cb-design-shell>…</div>
</div>
```

After `CB\Core\Design\Editor\Assets::enqueue_designer_mode()` Base adds the canonical **Design with Core Blueprint** launch control, keeps the shell hidden until launch and returns to the normal admin context when fullscreen closes.

## Direct mode

Direct mode is for routes that already represent an editor session, for example a workflow opened from an Automations library. The route must not visually pass through a normal admin page or the manual launch control.

Declare the mode and a same-origin exit URL in the server-rendered markup:

```html
<div
    data-cb-design-launch-root
    data-cb-design-launch-mode="direct"
    data-cb-design-exit-url="https://example.test/wp-admin/admin.php?page=consumer-library"
>
    <div class="cb-core-design-shell" data-cb-design-shell>…</div>
</div>
```

Direct mode rules:

- `data-cb-design-launch-mode="direct"` and a non-empty `data-cb-design-exit-url` are both required.
- The exit URL must resolve to the current origin. Invalid or cross-origin exit URLs fail back to manual mode when a manual launch context is available.
- Base gives the server-rendered shell fullscreen viewport composition from first paint; consumers must not add overlays, body masks, programmatic launch-button clicks or their own fullscreen geometry.
- Base does not create the **Design with Core Blueprint** manual launch control in direct mode.
- Base hydrates the existing Designer Shell fullscreen controller and keeps the direct route visually fullscreen throughout entry and exit.
- Closing fullscreen, including Escape, navigates directly to the declared exit URL. The underlying WordPress admin page is not an intermediate visual state.
- Consumer Designer Shell roots remain geometrically neutral. Outer margins, fixed positioning, viewport height and fullscreen transitions belong to Base.

Direct mode is transient UI state. It does not change the consumer's document/workflow model and must not be persisted as domain data.

## Ownership boundary

Base owns:

- launch mode interpretation;
- first-paint viewport composition for direct mode;
- canonical Designer header composition;
- fullscreen/focus lifecycle;
- manual launch control;
- direct-mode exit navigation.

Consumers own:

- deciding which route should request manual or direct mode;
- rendering the shared shell markup and their domain-specific slots;
- supplying the same-origin exit URL for direct mode;
- save, validation and editor-domain behaviour.

A consumer must not reproduce Base Designer transitions locally. If a launch behaviour is broadly required by multiple designers, it belongs in this foundation contract.
