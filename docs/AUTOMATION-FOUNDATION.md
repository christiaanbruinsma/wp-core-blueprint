# Automation Foundation — Core API v1

Core Blueprint Base exposes a small interoperability layer for automation-capable plugins. It lets first-party Core Blueprint extensions and third-party WordPress plugins declare stable **triggers** and **actions** without depending on the optional Core Blueprint Automations product.

Automation Foundation is intentionally not a workflow engine.

## Ownership

- Domain plugins own business semantics and decide when a trigger is true.
- Domain plugins own action implementations and their mutation rules.
- Base owns registration, validation, provider identity, discovery and the thin trigger-delivery boundary.
- Core Blueprint Automations, when installed, owns workflows, conditions, persistence, scheduling, retries, concurrency and run history.
- Base does not persist emitted automation events and does not guarantee retry delivery.

The architectural rule is:

> Extensions own business semantics. Base owns the interoperability contract. Automations owns orchestration.

## Provider identity

Third-party capabilities must belong to a plugin already registered through `CB\Core\ExtensionRegistry`. This prevents anonymous or spoofed capability ownership.

Attach the extension registration callback during normal plugin loading:

```php
use CB\Core\ExtensionRegistry;

add_action( 'cb_core_register_extensions', static function (): void {
    ExtensionRegistry::register( [
        'id'            => 'acme-reservations',
        'plugin_file'   => plugin_basename( __FILE__ ),
        'requires_api'  => '1.0',
        'requires_base' => '1.0.0-rc1',
        'menu_url'      => '',
        'status_id'     => '',
    ] );
} );
```

`core-blueprint` is a reserved provider identity for Base-owned capabilities. Public plugins cannot claim it. Base subsystems use internal `register_base()` paths instead.

## Capability registration lifecycle

Attach one callback during plugin loading. Automation Foundation invokes it once, lazily, after WordPress `init` has completed and after Base's canonical ExtensionRegistry collection.

```php
use CB\Core\Automation\ActionRegistry;
use CB\Core\Automation\TriggerRegistry;

add_action( 'cb_core_register_automation_capabilities', static function (): void {
    TriggerRegistry::register( [
        'provider'       => 'acme-reservations',
        'id'             => 'reservation.confirmed',
        'label'          => __( 'Reservation confirmed', 'acme-reservations' ),
        'description'    => __( 'A reservation reached its confirmed state.', 'acme-reservations' ),
        'schema_version' => '1',
        'payload_schema' => [
            'reservation_id' => [
                'type'     => 'integer',
                'required' => true,
            ],
            'customer_id' => [
                'type' => 'integer',
            ],
        ],
    ] );

    ActionRegistry::register( [
        'provider'            => 'acme-reservations',
        'id'                  => 'reservation.add_note',
        'label'               => __( 'Add reservation note', 'acme-reservations' ),
        'description'         => __( 'Adds a note through the reservations domain service.', 'acme-reservations' ),
        'schema_version'      => '1',
        'input_schema'        => [
            'reservation_id' => [
                'type'     => 'integer',
                'required' => true,
            ],
            'note' => [
                'type'      => 'string',
                'required'  => true,
                'sensitive' => true,
            ],
        ],
        'output_schema'       => [],
        'required_capability' => 'edit_posts',
        'executor'            => static function ( array $input ): array {
            // Call the plugin's own public/domain service here.
            // Do not bypass its authorization or persistence rules.
            return [];
        },
    ] );
} );
```

Malformed, duplicate and unknown-provider definitions fail closed instead of overwriting an existing capability.

## Capability IDs

Trigger and action IDs use dotted lowercase identifiers, for example:

```text
reservation.confirmed
reservation.cancelled
reservation.add_note
```

The provider and capability ID together form the effective identity:

```text
acme-reservations::reservation.confirmed
```

Treat both parts as persistent API identifiers. Labels and descriptions may change; IDs should not be repurposed for new semantics.

## Schema versions

Every trigger and action declares a positive integer schema version as a string:

```text
1
2
3
```

Increase it when the transport contract changes incompatibly. A label or description change alone does not require a schema-version change.

Automation consumers must not silently reinterpret a workflow configured for an incompatible contract version.

## Transport schemas

AF1 deliberately uses a bounded transport vocabulary. Supported field types are:

- `string`
- `integer`
- `number`
- `boolean`
- `array` containing only one declared scalar item type

A field definition may use:

```text
type
required
sensitive
items
```

`items` is required only for `array` fields and may be `string`, `integer`, `number` or `boolean`.

Nested objects, `WP_User`, `WP_Post`, arbitrary domain objects, resources and nested arrays are rejected. Prefer stable IDs and necessary immutable facts over copied domain records.

Unknown runtime payload fields also fail validation. Producers therefore cannot silently expand a payload without updating its declared contract.

### Sensitive fields

`sensitive: true` is **classification metadata**. It tells an orchestration consumer that the value requires careful handling if a run is persisted or logged.

It does not redact, hash or mutate the in-request trigger payload. Domain execution must receive the exact validated value it emitted. Consumers are responsible for applying privacy-safe persistence and logging policies.

## Emitting triggers

Emit only after WordPress `init` has completed:

```php
use CB\Core\Automation\Emitter;

$result = Emitter::emit(
    'acme-reservations',
    'reservation.confirmed',
    [
        'reservation_id' => 481,
        'customer_id'    => 92,
    ],
    'reservation:481:confirmed:1'
);

if ( is_wp_error( $result ) ) {
    // Handle the rejected emission according to the domain workflow.
}
```

The optional event ID should be a stable domain-event identifier when one exists. This gives an orchestration runtime a deterministic deduplication key. When omitted, Base generates a UUID for that emission.

A successful emission dispatches one immutable `CB\Core\Automation\TriggerEvent` on:

```text
cb_core_automation_trigger_emitted
```

The event exposes:

```text
event_id()
provider()
trigger_id()
schema_version()
payload()
occurred_at()
```

This is an in-request delivery boundary only. Base does not store, queue or retry the event.

## Early emission

AF1 intentionally refuses trigger emission before the WordPress `init` lifecycle has completed. It returns:

```text
cb_core_automation_not_ready
```

This preserves Base's existing extension-registration timing and prevents a plugin loaded early in WordPress from freezing the provider/capability inventory before later plugins have registered themselves.

A domain event that happens before this boundary must not be worked around by forcing registry collection early.

## Discovery

Consumers can inspect registered metadata through:

```php
use CB\Core\Automation\ActionRegistry;
use CB\Core\Automation\TriggerRegistry;

$triggers = TriggerRegistry::all();
$actions  = ActionRegistry::all();

$trigger = TriggerRegistry::get( 'acme-reservations', 'reservation.confirmed' );
$action  = ActionRegistry::get( 'acme-reservations', 'reservation.add_note' );
```

Public action discovery deliberately excludes the executable callback. Discovery is metadata, not execution authority.

## Action execution boundary

AF1 accepts and retains an action executor so the capability has an owner-controlled implementation, but it does **not** expose a public action-invocation API yet.

That omission is deliberate. The Automations runtime must first establish and test:

- configuration authority;
- execution authority;
- execution principal semantics;
- input and output validation at invocation time;
- idempotency and correlation identifiers;
- audit and privacy behavior;
- failure and retry semantics.

External code must not use reflection or internal Foundation classes to obtain an executor. Until the orchestration execution contract is finalized, actions are registration/discovery capabilities only.

## No hard dependency on Core Blueprint Automations

A plugin may implement Automation Foundation support with only Core Blueprint Base installed. If the optional Automations plugin is absent, capability registration remains harmless and ordinary plugin behavior must continue normally.

Do not require, import or call classes from `wp-core-blueprint-automations` merely to expose triggers/actions.

## Non-goals of Base

Automation Foundation does not provide:

- workflow definitions or workflow storage;
- a visual workflow builder;
- queues or workers;
- scheduled or delayed jobs;
- retries;
- branching or loops;
- concurrency control;
- run history;
- webhooks or external connectors.

Those concerns belong to the optional orchestration product, not to Base.
