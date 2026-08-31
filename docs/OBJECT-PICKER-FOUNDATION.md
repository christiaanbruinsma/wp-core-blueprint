# Object Picker Foundation

`CB\Core\UI\ObjectPicker` is the shared Base primitive for asynchronously searching and selecting WordPress-backed or extension-backed objects without loading a complete object catalog into a `<select>`.

Foundation owns:

- progressive enhancement over one real submitted text input;
- single and multiple selection presentation;
- ordered selected-item state;
- debounced async search transport;
- removable selected-item chips;
- Core Admin and WordPress-native presentation adapters;
- the public runtime `window.cbCore.objectPicker.init()`.

Consumers own:

- the AJAX action;
- nonce scope and authorization;
- search query implementation;
- object semantics and labels;
- persistence and validation.

This boundary deliberately prevents Foundation from knowing about Content Models, posts, users, terms, Certificates, Backups, or any other product domain.

## Rendering

```php
use CB\Core\UI\ObjectPicker;

echo ObjectPicker::render( [
    'id'       => 'related-object',
    'name'     => 'related_object',
    'multiple' => true,
    'action'   => 'my_plugin_search_objects',
    'nonce'    => wp_create_nonce( 'my_plugin_search_objects' ),
    'context'  => [ 'scope' => 'example' ],
    'selected' => [
        [ 'id' => 42, 'label' => 'Example', 'meta' => 'Post · #42' ],
    ],
] );
```

The submitted fallback value is one integer ID for single selection or a comma-separated ordered ID list for multiple selection. Consumers normalize that transport representation into their own typed storage contract.

## Search response

The consumer AJAX action should return:

```php
wp_send_json_success( [
    'items' => [
        [ 'id' => 42, 'label' => 'Example', 'meta' => 'Post · #42' ],
    ],
] );
```

Never treat the browser-provided search context as authorization. Resolve trusted configuration again on the server and apply the consumer's capability boundary before returning objects.

## Assets

```php
CB\Core\UI\Assets::enqueue_object_picker();
```

Auto mode selects Core presentation below the Core Blueprint parent menu and the WordPress-native adapter elsewhere. Consumers may explicitly use the documented presentation constants where screen ownership is already known.
