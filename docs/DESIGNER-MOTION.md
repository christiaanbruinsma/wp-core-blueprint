# Core Blueprint Designer Motion

Status: **public v1 Design Foundation interaction contract**.

Designer Motion is a small profile-neutral primitive for discrete layout changes inside Core Blueprint designers. Base owns the visual transition mechanics; consumers keep ownership of their model, DOM rendering and business meaning.

## Public boundary

Import from the public Design Editor module only:

```js
import { animateLayoutChange } from '@cb-core/design-editor';
```

The same primitive is available to non-module Designer integrations through:

```js
window.cbCore.designEditor.motion.animateLayoutChange
```

Consumers must not import Base private motion source paths.

## Stable identity

Items that may move during a discrete command declare a stable key:

```html
<section data-cb-design-motion-key="action:step_123">...</section>
```

Keys must be unique within the supplied motion root and must represent semantic identity, not the current array position. A key such as `action:1` is therefore only valid when `1` is a durable identifier, not when it means "second item".

## Layout transition

Wrap the synchronous model/DOM mutation that changes item positions:

```js
animateLayoutChange(container, () => {
    reorderConsumerModel();
    renderConsumerLayout();
});
```

Base measures keyed items before and after the callback and animates surviving identities from their previous viewport position to their new position.

The mutation is authoritative and immediate. Motion is presentation only; it never delays persistence, history, selection or domain state.

## Canonical defaults

Base currently owns these defaults:

- duration: `170ms`
- easing: `cubic-bezier(.2, .8, .2, 1)`
- movement: individual CSS `translate`
- item selector: `[data-cb-design-motion-key]`

Using the individual `translate` property keeps a consumer's existing `transform` semantics independent from shared motion.

Consumers may supply selector/duration/easing overrides only when a concrete interaction requires it. First-party designers should normally use the shared defaults so motion remains consistent across the suite.

## Accessibility and rapid commands

When the browser reports `prefers-reduced-motion: reduce`, the consumer mutation still runs immediately and no transition is created.

Before a new layout command is measured, Base cancels any still-active shared transition on the same keyed nodes. Rapid Move Up/Down, keyboard commands and history replay therefore do not accumulate animation queues.

## Ownership boundary

Base owns:

- before/after layout measurement;
- FLIP-style displacement;
- shared duration/easing defaults;
- reduced-motion behavior;
- active-animation cancellation and cleanup.

Consumers own:

- what an item means;
- which command changes its position;
- model mutation and persistence;
- DOM rendering;
- stable semantic motion keys.

Designer Motion must not contain consumer operations such as `moveActionUp`, `moveMailBlock`, `moveCertificateLayer` or equivalent domain knowledge.

## Intended use

Use Designer Motion for discrete state changes such as:

- Move Up / Move Down;
- keyboard reordering;
- layer/order changes;
- Undo / Redo replay that changes layout positions.

Do not apply it to live pointer dragging. During drag, the element must follow input directly; shared motion may be used only for a subsequent discrete state transition when appropriate.
