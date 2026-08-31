<?php
declare(strict_types=1);
/**
 * Content Models admin page.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\ContentModels\Admin;

use CB\Core\Admin\PageBase;
use CB\Core\Admin\TabNav;
use CB\Core\ContentModels\FieldTypes;
use CB\Core\ContentModels\Repository;
use CB\Core\ContentModels\Runtime;
use CB\Core\ContentModels\State;
use CB\Core\ContentModels\Importers\NativeWordPress\Bootstrap as NativeImporter;
use CB\Core\UI\ChoiceGroup;
use CB\Core\UI\Icon;
use CB\Core\UI\Status as StatusUi;

defined( 'ABSPATH' ) || exit;

final class Page extends PageBase {
	public const SLUG = 'core-blueprint-content-models';

	public function slug(): string { return self::SLUG; }
	public function title(): string { return __( 'Content Models', 'core-blueprint' ); }
	public function position(): ?int { return 25; }
	public function capability(): string { return 'cb_manage_content_models'; }

	public function render(): void {
		$this->guard();

		$tabs = [
			'post-types'   => __( 'Post Types', 'core-blueprint' ),
			'taxonomies'   => __( 'Taxonomies', 'core-blueprint' ),
			'option-pages' => __( 'Options Pages', 'core-blueprint' ),
			'field-groups' => __( 'Field Groups', 'core-blueprint' ),
			'tools'        => __( 'Tools', 'core-blueprint' ),
		];
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'post-types'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $tabs[ $requested ] ) ? $requested : 'post-types';

		ob_start();
		if ( 'tools' === $tab ) {
			$this->render_tools();
		} elseif ( 'field-groups' === $tab ) {
			$this->render_field_groups();
		} elseif ( 'option-pages' === $tab ) {
			$this->render_option_pages();
		} elseif ( 'taxonomies' === $tab ) {
			$this->render_taxonomies();
		} else {
			$this->render_post_types();
		}
		$html = (string) ob_get_clean();

		echo TabNav::inject( $html, self::SLUG, $tab, $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function render_header( string $intro ): void {
		?>
		<div class="wrap cb-core-wrap cb-content-models-wrap">
			<h1 class="cb-core-title"><?php esc_html_e( 'Content Models', 'core-blueprint' ); ?></h1>
			<p class="cb-core-intro"><?php echo esc_html( $intro ); ?></p>
			<?php $this->render_notice(); ?>
			<?php $this->render_runtime_errors(); ?>
		<?php
	}

	private function render_tools(): void {
		$this->render_header( __( 'Export or import Content Models schema definitions. Customer content values are never included in schema transfers.', 'core-blueprint' ) );
		if ( ! State::is_enabled() ) {
			$this->render_disabled_panel();
			echo '</div>';
			return;
		}
		$preview = Transfer::current_preview();
		$error = isset( $_GET['cb_cm_import_error'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_GET['cb_cm_import_error'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $error ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $error ) . '</p></div>';
		}
		if ( ! empty( $_GET['cb_cm_imported'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Content Models schema imported successfully. Existing content values were not changed.', 'core-blueprint' ) . '</p></div>';
		}
		?>
		<div class="cb-core-stack cb-core-stack--loose cb-content-models-tools-stack">
		<div class="cb-content-models-transfer-grid">
			<section class="cb-core-section">
				<h2><?php esc_html_e( 'Export Schema', 'core-blueprint' ); ?></h2>
				<p><?php esc_html_e( 'Download user-managed post types, taxonomies, Option Pages, Field Groups and field definitions as JSON. Stored posts, terms, users, metadata and option values are excluded.', 'core-blueprint' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_core_content_models_export_schema" />
					<?php wp_nonce_field( 'cb_core_content_models_export_schema' ); ?>
					<button class="button cb-core-button cb-core-button--secondary" type="submit"><?php esc_html_e( 'Download JSON', 'core-blueprint' ); ?></button>
				</form>
			</section>
			<section class="cb-core-section">
				<h2><?php esc_html_e( 'Import Schema', 'core-blueprint' ); ?></h2>
				<p><?php esc_html_e( 'Upload a Core Blueprint Content Models JSON document for validation and conflict preview. Import is merge-based and never deletes definitions that are absent from the file.', 'core-blueprint' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_core_content_models_preview_import" />
					<?php wp_nonce_field( 'cb_core_content_models_preview_import' ); ?>
					<input type="file" name="schema_file" accept="application/json,.json" required />
					<button class="button cb-core-button cb-core-button--secondary" type="submit"><?php esc_html_e( 'Analyse Import', 'core-blueprint' ); ?></button>
				</form>
			</section>
		</div>
		<?php if ( is_array( $preview ) && is_array( $preview['analysis'] ?? null ) ) :
			$analysis = $preview['analysis'];
			$counts = is_array( $analysis['counts'] ?? null ) ? $analysis['counts'] : [];
			$conflicts = is_array( $analysis['conflicts'] ?? null ) ? $analysis['conflicts'] : [];
			$locked = is_array( $analysis['locked'] ?? null ) ? $analysis['locked'] : [];
		?>
			<section class="cb-core-section cb-content-models-import-preview">
				<h2><?php esc_html_e( 'Import Preview', 'core-blueprint' ); ?></h2>
				<p><?php echo esc_html( sprintf( __( '%1$d post types, %2$d taxonomies, %3$d Option Pages and %4$d Field Groups passed schema validation.', 'core-blueprint' ), (int) ( $counts['post_types'] ?? 0 ), (int) ( $counts['taxonomies'] ?? 0 ), (int) ( $counts['option_pages'] ?? 0 ), (int) ( $counts['field_groups'] ?? 0 ) ) ); ?></p>
				<?php if ( ! empty( $locked ) ) : ?>
					<div class="notice notice-error inline"><p><?php esc_html_e( 'Import is blocked because one or more matching definitions are owned and locked by another plugin.', 'core-blueprint' ); ?></p></div>
					<ul><?php foreach ( $locked as $item ) : ?><li><code><?php echo esc_html( (string) ( $item['section'] ?? '' ) . ':' . (string) ( $item['key'] ?? '' ) ); ?></code> — <?php echo esc_html( (string) ( $item['owner'] ?? '' ) ); ?></li><?php endforeach; ?></ul>
				<?php else : ?>
					<?php if ( ! empty( $conflicts ) ) : ?><div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( _n( '%d existing user-managed definition has the same key.', '%d existing user-managed definitions have the same key.', count( $conflicts ), 'core-blueprint' ), count( $conflicts ) ) ); ?></p></div><?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cb_core_content_models_apply_import" />
						<?php wp_nonce_field( 'cb_core_content_models_apply_import' ); ?>
						<?php if ( ! empty( $conflicts ) ) : ?><label class="cb-content-models-import-overwrite"><input type="checkbox" name="overwrite" value="1" /> <?php esc_html_e( 'Replace matching user-managed definitions with the imported definitions', 'core-blueprint' ); ?></label><?php endif; ?>
						<button class="button cb-core-button cb-core-button--primary" type="submit"<?php echo ! empty( $conflicts ) ? ' data-requires-overwrite' : ''; ?>><?php esc_html_e( 'Import Schema', 'core-blueprint' ); ?></button>
					</form>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<?php NativeImporter::render(); ?>
		</div>
		</div>
		<?php
	}

	private function render_post_types(): void {
		$this->render_header( __( 'Define WordPress-native content types without a separate custom post type plugin. Definitions are governed by Core Blueprint; content remains ordinary WordPress posts.', 'core-blueprint' ) );
		if ( ! State::is_enabled() ) {
			$this->render_disabled_panel();
			echo '</div>';
			return;
		}

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$model = isset( $_GET['model'] ) ? sanitize_key( wp_unslash( $_GET['model'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' === $view ) {
			$this->render_post_type_editor( $model );
		} elseif ( 'duplicate' === $view ) {
			$this->render_post_type_editor( $model, true );
		} elseif ( 'delete' === $view ) {
			$this->render_post_type_delete( $model );
		} else {
			$this->render_post_type_list();
		}
		echo '</div>';
	}

	private function render_taxonomies(): void {
		$this->render_header( __( 'Create classification structures and attach them to standard or Core Blueprint-managed post types.', 'core-blueprint' ) );
		if ( ! State::is_enabled() ) {
			$this->render_disabled_panel();
			echo '</div>';
			return;
		}

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$model = isset( $_GET['model'] ) ? sanitize_key( wp_unslash( $_GET['model'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' === $view ) {
			$this->render_taxonomy_editor( $model );
		} elseif ( 'duplicate' === $view ) {
			$this->render_taxonomy_editor( $model, true );
		} elseif ( 'delete' === $view ) {
			$this->render_taxonomy_delete( $model );
		} else {
			$this->render_taxonomy_list();
		}
		echo '</div>';
	}


	private function render_option_pages(): void {
		$this->render_header( __( 'Create WordPress admin Option Pages for site-wide settings. Field values use the native WordPress Options API and remain separate from their Content Model definitions.', 'core-blueprint' ) );
		if ( ! State::is_enabled() ) {
			$this->render_disabled_panel();
			echo '</div>';
			return;
		}

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$model = isset( $_GET['model'] ) ? sanitize_key( wp_unslash( $_GET['model'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' === $view ) {
			$this->render_option_page_editor( $model );
		} elseif ( 'duplicate' === $view ) {
			$this->render_option_page_editor( $model, true );
		} elseif ( 'delete' === $view ) {
			$this->render_option_page_delete( $model );
		} else {
			$this->render_option_page_list();
		}
		echo '</div>';
	}


	private function render_field_groups(): void {
		$this->render_header( __( 'Define native WordPress fields and choose which post types or Option Pages should display them. Field definitions are governed by Core Blueprint; values remain native WordPress post meta or options.', 'core-blueprint' ) );
		if ( ! State::is_enabled() ) {
			$this->render_disabled_panel();
			echo '</div>';
			return;
		}

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$model = isset( $_GET['model'] ) ? sanitize_key( wp_unslash( $_GET['model'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$field = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'edit' === $view ) {
			$this->render_field_group_editor( $model );
		} elseif ( 'duplicate' === $view ) {
			$this->render_field_group_editor( $model, true );
		} elseif ( 'delete' === $view ) {
			$this->render_field_group_delete( $model );
		} elseif ( 'field' === $view ) {
			$this->render_field_editor( $model, $field );
		} elseif ( 'duplicate-field' === $view ) {
			$this->render_field_editor( $model, $field, true );
		} elseif ( 'delete-field' === $view ) {
			$this->render_field_delete( $model, $field );
		} else {
			$this->render_field_group_list();
		}
		echo '</div>';
	}

	private function render_post_type_list(): void {
		$models = Repository::post_types();
		$add_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'post-types', 'view' => 'edit' ], admin_url( 'admin.php' ) );
		?>
		<div class="cb-content-models-toolbar">
			<a class="button cb-core-button cb-core-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Post Type', 'core-blueprint' ); ?></a>
		</div>
		<?php if ( empty( $models ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No Core Blueprint post types have been created yet.', 'core-blueprint' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Post Type', 'core-blueprint' ); ?></th>
					<th><?php esc_html_e( 'Key', 'core-blueprint' ); ?></th>
					<th><?php esc_html_e( 'Visibility', 'core-blueprint' ); ?></th>
					<th><?php esc_html_e( 'REST API', 'core-blueprint' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'core-blueprint' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $models as $key => $definition ) :
					$edit_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'post-types', 'view' => 'edit', 'model' => $key ], admin_url( 'admin.php' ) );
					$duplicate_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'post-types', 'view' => 'duplicate', 'model' => $key ], admin_url( 'admin.php' ) );
					$delete_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'post-types', 'view' => 'delete', 'model' => $key ], admin_url( 'admin.php' ) );
				?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) ( $definition['plural_label'] ?? $key ) ); ?></a></strong><br><span class="description"><?php echo esc_html( (string) ( $definition['singular_label'] ?? '' ) ); ?></span></td>
						<td><code><?php echo esc_html( (string) $key ); ?></code></td>
						<td><?php echo StatusUi::render( ! empty( $definition['public'] ) ? 'active' : 'idle', ! empty( $definition['public'] ) ? __( 'Public', 'core-blueprint' ) : __( 'Admin only', 'core-blueprint' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo ! empty( $definition['show_in_rest'] ) ? esc_html__( 'Enabled', 'core-blueprint' ) : esc_html__( 'Disabled', 'core-blueprint' ); ?></td>
						<td class="cb-content-models-col-actions"><div class="cb-content-models-actions"><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $duplicate_url ); ?>"><?php esc_html_e( 'Duplicate', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--danger" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'core-blueprint' ); ?></a></div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function render_post_type_editor( string $key, bool $duplicate = false ): void {
		$model = '' !== $key ? Repository::post_type( $key ) : null;
		if ( '' !== $key && null === $model ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Post type definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		$duplicate_source = $duplicate ? $key : '';
		$is_edit = ! $duplicate && null !== $model;
		$model = array_replace( [
			'key'            => '',
			'singular_label' => '',
			'plural_label'   => '',
			'description'    => '',
			'public'         => true,
			'show_in_rest'   => true,
			'has_archive'    => true,
			'hierarchical'   => false,
			'rewrite_slug'   => '',
			'icon'           => 'dashicons-admin-post',
			'supports'       => [ 'title', 'editor', 'thumbnail', 'revisions' ],
		], is_array( $model ) ? $model : [] );
		if ( $duplicate ) {
			$model['key'] = $this->suggest_unique_model_key( (string) $model['key'], 'post_type', 20 );
			$model['singular_label'] = $this->copy_label( (string) $model['singular_label'] );
			$model['plural_label'] = $this->copy_label( (string) $model['plural_label'] );
			$model['rewrite_slug'] = (string) $model['key'];
		}
		?>
		<h2><?php echo esc_html( $duplicate ? __( 'Duplicate Post Type', 'core-blueprint' ) : ( $is_edit ? __( 'Edit Post Type', 'core-blueprint' ) : __( 'Add Post Type', 'core-blueprint' ) ) ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-core-form-scope">
			<input type="hidden" name="action" value="cb_core_content_models_save_post_type" />
			<input type="hidden" name="original_key" value="<?php echo esc_attr( $is_edit ? (string) $model['key'] : '' ); ?>" />
			<input type="hidden" name="duplicate_source" value="<?php echo esc_attr( $duplicate_source ); ?>" />
			<?php wp_nonce_field( 'cb_core_content_models_save_post_type' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
				<tr><th scope="row"><label for="cb-cm-singular"><?php esc_html_e( 'Singular label', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-singular" name="singular_label" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $model['singular_label'] ); ?>" /><p class="description"><?php esc_html_e( 'Example: Project', 'core-blueprint' ); ?></p></td></tr>
				<tr><th scope="row"><label for="cb-cm-plural"><?php esc_html_e( 'Plural label', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-plural" name="plural_label" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $model['plural_label'] ); ?>" /><p class="description"><?php esc_html_e( 'Example: Projects', 'core-blueprint' ); ?></p></td></tr>
				<tr><th scope="row"><label for="cb-cm-key"><?php esc_html_e( 'Post type key', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-key" name="key" type="text" class="regular-text code" maxlength="20" required <?php echo $is_edit ? 'readonly' : ''; ?> value="<?php echo esc_attr( (string) $model['key'] ); ?>" /><p class="description"><?php esc_html_e( 'Stored as the WordPress post type identifier. This key becomes immutable after creation.', 'core-blueprint' ); ?></p></td></tr>
				<tr><th scope="row"><label for="cb-cm-description"><?php esc_html_e( 'Description', 'core-blueprint' ); ?></label></th><td><textarea id="cb-cm-description" name="description" class="large-text" rows="3"><?php echo esc_textarea( (string) $model['description'] ); ?></textarea></td></tr>
				<tr><th scope="row"><label for="cb-cm-rewrite"><?php esc_html_e( 'URL slug', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-rewrite" name="rewrite_slug" type="text" class="regular-text code" value="<?php echo esc_attr( (string) $model['rewrite_slug'] ); ?>" /><p class="description"><?php esc_html_e( 'Used for public URLs. Leave empty to use the post type key.', 'core-blueprint' ); ?></p></td></tr>
				<tr><th scope="row"><label for="cb-cm-post-type-icon"><?php esc_html_e( 'Menu icon', 'core-blueprint' ); ?></label></th><td><?php $this->render_icon_picker( 'cb-cm-post-type-icon', 'icon', (string) $model['icon'], __( 'Choose a Dashicon or Core Blueprint Lucide icon for this post type’s admin menu item.', 'core-blueprint' ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Behaviour', 'core-blueprint' ); ?></th><td>
					<?php
					echo ChoiceGroup::render( [
						'aria_label' => __( 'Post type behaviour', 'core-blueprint' ),
						'options' => [
							[ 'name' => 'public', 'label' => __( 'Publicly queryable', 'core-blueprint' ), 'checked' => ! empty( $model['public'] ) ],
							[ 'name' => 'show_in_rest', 'label' => __( 'Expose through the WordPress REST API', 'core-blueprint' ), 'checked' => ! empty( $model['show_in_rest'] ) ],
							[ 'name' => 'has_archive', 'label' => __( 'Enable archive page', 'core-blueprint' ), 'checked' => ! empty( $model['has_archive'] ) ],
							[ 'name' => 'hierarchical', 'label' => __( 'Hierarchical (page-like parent/child structure)', 'core-blueprint' ), 'checked' => ! empty( $model['hierarchical'] ) ],
						],
					] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output.
					?>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Editor features', 'core-blueprint' ); ?></th><td>
					<?php
					$support_labels = [
						'title'           => __( 'Title', 'core-blueprint' ),
						'editor'          => __( 'Content editor', 'core-blueprint' ),
						'thumbnail'       => __( 'Featured image', 'core-blueprint' ),
						'excerpt'         => __( 'Excerpt', 'core-blueprint' ),
						'author'          => __( 'Author', 'core-blueprint' ),
						'revisions'       => __( 'Revisions', 'core-blueprint' ),
						'page-attributes' => __( 'Page attributes', 'core-blueprint' ),
						'custom-fields'   => __( 'WordPress native Custom Fields panel', 'core-blueprint' ),
						'comments'        => __( 'Comments', 'core-blueprint' ),
					];
					$support_options = [];
					foreach ( $support_labels as $support => $label ) {
						$support_options[] = [
							'name' => 'supports[]',
							'value' => $support,
							'label' => $label,
							'checked' => in_array( $support, (array) $model['supports'], true ),
						];
					}
					echo ChoiceGroup::render( [
						'aria_label' => __( 'Editor features', 'core-blueprint' ),
						'options' => $support_options,
					] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output.
					?>
				</td></tr>
				</tbody>
			</table>
			<?php submit_button( $duplicate ? __( 'Create Duplicate Post Type', 'core-blueprint' ) : ( $is_edit ? __( 'Save Post Type', 'core-blueprint' ) : __( 'Create Post Type', 'core-blueprint' ) ) ); ?>
		</form>
		<?php
	}

	private function render_post_type_delete( string $key ): void {
		$model = Repository::post_type( $key );
		if ( null === $model ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Post type definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}

		$used_by = [];
		foreach ( Repository::taxonomies() as $taxonomy ) {
			if ( in_array( $key, (array) ( $taxonomy['object_types'] ?? [] ), true ) ) {
				$used_by[] = (string) ( $taxonomy['plural_label'] ?? $taxonomy['key'] ?? '' );
			}
		}
		foreach ( Repository::field_groups() as $group ) {
			if ( in_array( $key, (array) ( $group['post_types'] ?? [] ), true ) ) {
				$used_by[] = (string) ( $group['title'] ?? $group['id'] ?? '' );
			}
		}
		?>
		<h2><?php esc_html_e( 'Delete Post Type Definition', 'core-blueprint' ); ?></h2>
		<div class="notice notice-warning inline"><p><strong><?php echo esc_html( (string) $model['plural_label'] ); ?></strong> — <?php esc_html_e( 'This removes only the Core Blueprint definition. Existing database content is not deleted, but WordPress will stop recognising it as this post type until an equivalent definition is restored.', 'core-blueprint' ); ?></p></div>
		<?php if ( ! empty( $used_by ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( sprintf( __( 'This post type is still used by: %s. Update or remove those taxonomies or field groups first.', 'core-blueprint' ), implode( ', ', $used_by ) ) ); ?></p></div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cb_core_content_models_delete_post_type" />
				<input type="hidden" name="model" value="<?php echo esc_attr( $key ); ?>" />
				<?php wp_nonce_field( 'cb_core_content_models_delete_post_type' ); ?>
				<button type="submit" class="button cb-core-button cb-core-button--danger"><?php esc_html_e( 'Delete Definition', 'core-blueprint' ); ?></button>
			</form>
		<?php endif; ?>
		<?php
	}

	private function render_taxonomy_list(): void {
		$models = Repository::taxonomies();
		$add_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'taxonomies', 'view' => 'edit' ], admin_url( 'admin.php' ) );
		?>
		<div class="cb-content-models-toolbar">
			<a class="button cb-core-button cb-core-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Taxonomy', 'core-blueprint' ); ?></a>
		</div>
		<?php if ( empty( $models ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No Core Blueprint taxonomies have been created yet.', 'core-blueprint' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Taxonomy', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Key', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Assigned to', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Structure', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Actions', 'core-blueprint' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $models as $key => $definition ) :
					$edit_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'taxonomies', 'view' => 'edit', 'model' => $key ], admin_url( 'admin.php' ) );
					$duplicate_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'taxonomies', 'view' => 'duplicate', 'model' => $key ], admin_url( 'admin.php' ) );
					$delete_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'taxonomies', 'view' => 'delete', 'model' => $key ], admin_url( 'admin.php' ) );
				?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) ( $definition['plural_label'] ?? $key ) ); ?></a></strong><br><span class="description"><?php echo esc_html( (string) ( $definition['singular_label'] ?? '' ) ); ?></span></td>
						<td><code><?php echo esc_html( (string) $key ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', (array) ( $definition['object_types'] ?? [] ) ) ); ?></td>
						<td><?php echo ! empty( $definition['hierarchical'] ) ? esc_html__( 'Hierarchical', 'core-blueprint' ) : esc_html__( 'Flat', 'core-blueprint' ); ?></td>
						<td class="cb-content-models-col-actions"><div class="cb-content-models-actions"><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $duplicate_url ); ?>"><?php esc_html_e( 'Duplicate', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--danger" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'core-blueprint' ); ?></a></div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function render_taxonomy_editor( string $key, bool $duplicate = false ): void {
		$model = '' !== $key ? Repository::taxonomy( $key ) : null;
		if ( '' !== $key && null === $model ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Taxonomy definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		$duplicate_source = $duplicate ? $key : '';
		$is_edit = ! $duplicate && null !== $model;
		$model = array_replace( [
			'key'               => '',
			'singular_label'    => '',
			'plural_label'      => '',
			'description'       => '',
			'object_types'      => [ 'post' ],
			'public'            => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite_slug'      => '',
		], is_array( $model ) ? $model : [] );
		if ( $duplicate ) {
			$model['key'] = $this->suggest_unique_model_key( (string) $model['key'], 'taxonomy', 32 );
			$model['singular_label'] = $this->copy_label( (string) $model['singular_label'] );
			$model['plural_label'] = $this->copy_label( (string) $model['plural_label'] );
			$model['rewrite_slug'] = (string) $model['key'];
		}

		$post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
		uasort( $post_types, static fn( $a, $b ): int => strnatcasecmp( (string) $a->labels->name, (string) $b->labels->name ) );
		?>
		<h2><?php echo esc_html( $duplicate ? __( 'Duplicate Taxonomy', 'core-blueprint' ) : ( $is_edit ? __( 'Edit Taxonomy', 'core-blueprint' ) : __( 'Add Taxonomy', 'core-blueprint' ) ) ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-core-form-scope">
			<input type="hidden" name="action" value="cb_core_content_models_save_taxonomy" />
			<input type="hidden" name="original_key" value="<?php echo esc_attr( $is_edit ? (string) $model['key'] : '' ); ?>" />
			<input type="hidden" name="duplicate_source" value="<?php echo esc_attr( $duplicate_source ); ?>" />
			<?php wp_nonce_field( 'cb_core_content_models_save_taxonomy' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row"><label for="cb-cm-tax-singular"><?php esc_html_e( 'Singular label', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-tax-singular" name="singular_label" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $model['singular_label'] ); ?>" /><p class="description"><?php esc_html_e( 'Example: Project Category', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-tax-plural"><?php esc_html_e( 'Plural label', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-tax-plural" name="plural_label" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $model['plural_label'] ); ?>" /><p class="description"><?php esc_html_e( 'Example: Project Categories', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-tax-key"><?php esc_html_e( 'Taxonomy key', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-tax-key" name="key" type="text" class="regular-text code" maxlength="32" required <?php echo $is_edit ? 'readonly' : ''; ?> value="<?php echo esc_attr( (string) $model['key'] ); ?>" /><p class="description"><?php esc_html_e( 'Stored as the WordPress taxonomy identifier. This key becomes immutable after creation.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-tax-description"><?php esc_html_e( 'Description', 'core-blueprint' ); ?></label></th><td><textarea id="cb-cm-tax-description" name="description" class="large-text" rows="3"><?php echo esc_textarea( (string) $model['description'] ); ?></textarea></td></tr>
			<tr><th scope="row"><label for="cb-cm-tax-rewrite"><?php esc_html_e( 'URL slug', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-tax-rewrite" name="rewrite_slug" type="text" class="regular-text code" value="<?php echo esc_attr( (string) $model['rewrite_slug'] ); ?>" /><p class="description"><?php esc_html_e( 'Used for public taxonomy archives. Leave empty to use the taxonomy key.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Assigned post types', 'core-blueprint' ); ?></th><td>
				<?php
				$object_type_options = [];
				foreach ( $post_types as $post_type ) {
					$object_type_options[] = [
						'name' => 'object_types[]',
						'value' => $post_type->name,
						'label' => (string) $post_type->labels->name . ' (' . $post_type->name . ')',
						'checked' => in_array( $post_type->name, (array) $model['object_types'], true ),
					];
				}
				echo ChoiceGroup::render( [
					'aria_label' => __( 'Assigned post types', 'core-blueprint' ),
					'options' => $object_type_options,
					'scrollable' => true,
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output.
				?>
			</td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Behaviour', 'core-blueprint' ); ?></th><td>
				<?php
				echo ChoiceGroup::render( [
					'aria_label' => __( 'Taxonomy behaviour', 'core-blueprint' ),
					'options' => [
						[ 'name' => 'public', 'label' => __( 'Publicly queryable', 'core-blueprint' ), 'checked' => ! empty( $model['public'] ) ],
						[ 'name' => 'show_in_rest', 'label' => __( 'Expose through the WordPress REST API', 'core-blueprint' ), 'checked' => ! empty( $model['show_in_rest'] ) ],
						[ 'name' => 'hierarchical', 'label' => __( 'Hierarchical (category-like)', 'core-blueprint' ), 'checked' => ! empty( $model['hierarchical'] ) ],
						[ 'name' => 'show_admin_column', 'label' => __( 'Show taxonomy column in post lists', 'core-blueprint' ), 'checked' => ! empty( $model['show_admin_column'] ) ],
					],
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output.
				?>
			</td></tr>
			</tbody></table>
			<?php submit_button( $duplicate ? __( 'Create Duplicate Taxonomy', 'core-blueprint' ) : ( $is_edit ? __( 'Save Taxonomy', 'core-blueprint' ) : __( 'Create Taxonomy', 'core-blueprint' ) ) ); ?>
		</form>
		<?php
	}

	private function render_taxonomy_delete( string $key ): void {
		$model = Repository::taxonomy( $key );
		if ( null === $model ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Taxonomy definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		?>
		<h2><?php esc_html_e( 'Delete Taxonomy Definition', 'core-blueprint' ); ?></h2>
		<div class="notice notice-warning inline"><p><strong><?php echo esc_html( (string) $model['plural_label'] ); ?></strong> — <?php esc_html_e( 'This removes only the Core Blueprint definition. Existing terms and relationships stay in the WordPress database, but the taxonomy is no longer registered until an equivalent definition is restored.', 'core-blueprint' ); ?></p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cb_core_content_models_delete_taxonomy" />
			<input type="hidden" name="model" value="<?php echo esc_attr( $key ); ?>" />
			<?php wp_nonce_field( 'cb_core_content_models_delete_taxonomy' ); ?>
			<button type="submit" class="button cb-core-button cb-core-button--danger"><?php esc_html_e( 'Delete Definition', 'core-blueprint' ); ?></button>
		</form>
		<?php
	}


	private function render_option_page_list(): void {
		$pages = Repository::option_pages();
		$add_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'option-pages', 'view' => 'edit' ], admin_url( 'admin.php' ) );
		?>
		<div class="cb-content-models-toolbar"><a class="button cb-core-button cb-core-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Option Page', 'core-blueprint' ); ?></a></div>
		<?php if ( empty( $pages ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No Core Blueprint Option Pages have been created yet.', 'core-blueprint' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped cb-content-models-table">
				<thead><tr><th><?php esc_html_e( 'Option Page', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Slug', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Parent', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Capability', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Actions', 'core-blueprint' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $pages as $slug => $definition ) :
					$edit_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'option-pages', 'view' => 'edit', 'model' => $slug ], admin_url( 'admin.php' ) );
					$duplicate_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'option-pages', 'view' => 'duplicate', 'model' => $slug ], admin_url( 'admin.php' ) );
					$delete_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'option-pages', 'view' => 'delete', 'model' => $slug ], admin_url( 'admin.php' ) );
				?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) ( $definition['title'] ?? $slug ) ); ?></a></strong><br><span class="description"><?php echo esc_html( (string) ( $definition['menu_label'] ?? '' ) ); ?></span></td>
						<td><code><?php echo esc_html( (string) $slug ); ?></code></td>
						<td><?php echo esc_html( '' !== (string) ( $definition['parent_slug'] ?? '' ) ? (string) $definition['parent_slug'] : __( 'Top-level', 'core-blueprint' ) ); ?></td>
						<td><code><?php echo esc_html( (string) ( $definition['capability'] ?? 'manage_options' ) ); ?></code></td>
						<td class="cb-content-models-col-actions"><div class="cb-content-models-actions"><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $duplicate_url ); ?>"><?php esc_html_e( 'Duplicate', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--danger" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'core-blueprint' ); ?></a></div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function render_option_page_editor( string $slug, bool $duplicate = false ): void {
		$model = '' !== $slug ? Repository::option_page( $slug ) : null;
		if ( '' !== $slug && null === $model ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Option Page definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		$duplicate_source = $duplicate ? $slug : '';
		$is_edit = ! $duplicate && null !== $model;
		$model = array_replace( [
			'slug' => '', 'title' => '', 'menu_label' => '', 'description' => '', 'parent_slug' => '',
			'capability' => 'manage_options', 'position' => null, 'icon' => 'dashicons-admin-generic',
		], is_array( $model ) ? $model : [] );
		if ( $duplicate ) {
			$model['slug'] = $this->suggest_unique_option_page_slug( (string) $model['slug'] );
			$model['title'] = $this->copy_label( (string) $model['title'] );
			$model['menu_label'] = $this->copy_label( (string) $model['menu_label'] );
		}
		$parent_choices = $this->option_page_parent_choices( $is_edit ? $slug : '' );
		?>
		<h2><?php echo esc_html( $duplicate ? __( 'Duplicate Option Page', 'core-blueprint' ) : ( $is_edit ? __( 'Edit Option Page', 'core-blueprint' ) : __( 'Add Option Page', 'core-blueprint' ) ) ); ?></h2>
		<?php if ( $is_edit ) : ?><div class="notice notice-info inline"><p><?php esc_html_e( 'The Option Page slug is protected after creation because it is part of the option-value storage namespace.', 'core-blueprint' ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-core-form-scope">
			<input type="hidden" name="action" value="cb_core_content_models_save_option_page" />
			<input type="hidden" name="original_slug" value="<?php echo esc_attr( $is_edit ? (string) $model['slug'] : '' ); ?>" />
			<input type="hidden" name="duplicate_source" value="<?php echo esc_attr( $duplicate_source ); ?>" />
			<?php wp_nonce_field( 'cb_core_content_models_save_option_page' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row"><label for="cb-cm-op-title"><?php esc_html_e( 'Page title', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-op-title" name="title" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $model['title'] ); ?>" /></td></tr>
			<tr><th scope="row"><label for="cb-cm-op-menu"><?php esc_html_e( 'Menu label', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-op-menu" name="menu_label" type="text" class="regular-text" value="<?php echo esc_attr( (string) $model['menu_label'] ); ?>" /><p class="description"><?php esc_html_e( 'Leave empty to use the page title.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-op-slug"><?php esc_html_e( 'Page slug', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-op-slug" name="slug" type="text" class="regular-text code" required <?php echo $is_edit ? 'readonly' : ''; ?> value="<?php echo esc_attr( (string) $model['slug'] ); ?>" /><p class="description"><?php esc_html_e( 'Stable identifier used by WordPress admin routing and this page’s option storage namespace.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-op-description"><?php esc_html_e( 'Description', 'core-blueprint' ); ?></label></th><td><textarea id="cb-cm-op-description" name="description" class="large-text" rows="3"><?php echo esc_textarea( (string) $model['description'] ); ?></textarea></td></tr>
			<tr><th scope="row"><label for="cb-cm-op-parent"><?php esc_html_e( 'Parent page', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-op-parent" name="parent_slug" type="text" class="regular-text code" list="cb-cm-op-parent-choices" value="<?php echo esc_attr( (string) $model['parent_slug'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty for top-level', 'core-blueprint' ); ?>" /><datalist id="cb-cm-op-parent-choices"><?php foreach ( $parent_choices as $parent_slug => $label ) : ?><option value="<?php echo esc_attr( $parent_slug ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></datalist><p class="description"><?php esc_html_e( 'Use a WordPress admin menu slug such as options-general.php, tools.php or core-blueprint. Existing Core Blueprint Option Pages are suggested too.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-op-cap"><?php esc_html_e( 'Required capability', 'core-blueprint' ); ?></label></th><td><?php $this->render_capability_picker( 'cb-cm-op-cap', 'capability', (string) $model['capability'], __( 'Controls who may view and save values on the generated Option Page.', 'core-blueprint' ) ); ?></td></tr>
			<tr><th scope="row"><label for="cb-cm-op-position"><?php esc_html_e( 'Menu position', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-op-position" name="position" type="number" min="1" max="999" value="<?php echo esc_attr( null !== $model['position'] ? (string) $model['position'] : '' ); ?>" /><p class="description"><?php esc_html_e( 'Optional. Leave empty to let WordPress place the item naturally.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-op-icon"><?php esc_html_e( 'Menu icon', 'core-blueprint' ); ?></label></th><td><?php $this->render_icon_picker( 'cb-cm-op-icon', 'icon', (string) $model['icon'], __( 'Choose a Dashicon or Core Blueprint Lucide icon for top-level pages. Submenu pages inherit their parent menu.', 'core-blueprint' ) ); ?></td></tr>
			</tbody></table>
			<?php submit_button( $duplicate ? __( 'Create Duplicate Option Page', 'core-blueprint' ) : ( $is_edit ? __( 'Save Option Page', 'core-blueprint' ) : __( 'Create Option Page', 'core-blueprint' ) ) ); ?>
		</form>
		<?php
	}

	private function render_option_page_delete( string $slug ): void {
		$page = Repository::option_page( $slug );
		if ( null === $page ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Option Page definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		?>
		<h2><?php esc_html_e( 'Delete Option Page Definition', 'core-blueprint' ); ?></h2>
		<div class="notice notice-warning inline"><p><strong><?php echo esc_html( (string) $page['title'] ); ?></strong> — <?php esc_html_e( 'This removes only the generated admin page definition. Stored WordPress option values are deliberately preserved. Remove any Field Group assignments or child Option Pages first.', 'core-blueprint' ); ?></p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="cb_core_content_models_delete_option_page" /><input type="hidden" name="model" value="<?php echo esc_attr( $slug ); ?>" /><?php wp_nonce_field( 'cb_core_content_models_delete_option_page' ); ?><button type="submit" class="button cb-core-button cb-core-button--danger"><?php esc_html_e( 'Delete Definition', 'core-blueprint' ); ?></button></form>
		<?php
	}


	private function render_field_group_list(): void {
		$groups = Repository::field_groups();
		$add_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'edit' ], admin_url( 'admin.php' ) );
		?>
		<div class="cb-content-models-toolbar"><a class="button cb-core-button cb-core-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Field Group', 'core-blueprint' ); ?></a></div>
		<?php if ( empty( $groups ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No Core Blueprint field groups have been created yet.', 'core-blueprint' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped cb-content-models-table">
				<thead><tr><th><?php esc_html_e( 'Field Group', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Location', 'core-blueprint' ); ?></th><th class="cb-content-models-col-fields"><?php esc_html_e( 'Fields', 'core-blueprint' ); ?></th><th class="cb-content-models-col-actions"><?php esc_html_e( 'Actions', 'core-blueprint' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $groups as $id => $group ) :
					$edit_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'edit', 'model' => $id ], admin_url( 'admin.php' ) );
					$duplicate_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'duplicate', 'model' => $id ], admin_url( 'admin.php' ) );
					$delete_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'delete', 'model' => $id ], admin_url( 'admin.php' ) );
					$post_targets = array_map( [ $this, 'post_type_label' ], (array) ( $group['post_types'] ?? [] ) );
					$option_targets = array_map( [ $this, 'option_page_label' ], (array) ( $group['option_pages'] ?? [] ) );
					$term_targets = array_map( static function ( string $taxonomy ): string {
						$object = get_taxonomy( $taxonomy );
						return $object ? (string) $object->labels->singular_name : $taxonomy;
					}, (array) ( $group['term_taxonomies'] ?? [] ) );
					$user_target = ! empty( $group['user_enabled'] ) ? __( 'User profiles', 'core-blueprint' ) : '';
				?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) ( $group['title'] ?? $id ) ); ?></a></strong><br><code><?php echo esc_html( (string) $id ); ?></code></td>
						<td><?php $locations = []; if ( ! empty( $post_targets ) ) { $locations[] = __( 'Posts:', 'core-blueprint' ) . ' ' . implode( ', ', $post_targets ); } if ( ! empty( $option_targets ) ) { $locations[] = __( 'Options:', 'core-blueprint' ) . ' ' . implode( ', ', $option_targets ); } if ( ! empty( $term_targets ) ) { $locations[] = __( 'Terms:', 'core-blueprint' ) . ' ' . implode( ', ', $term_targets ); } if ( '' !== $user_target ) { $locations[] = __( 'Users:', 'core-blueprint' ) . ' ' . $user_target; } echo esc_html( implode( ' · ', $locations ) ); ?></td>
						<td class="cb-content-models-col-fields"><?php echo esc_html( (string) count( (array) ( $group['fields'] ?? [] ) ) ); ?></td>
						<td class="cb-content-models-col-actions"><div class="cb-content-models-actions"><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $duplicate_url ); ?>"><?php esc_html_e( 'Duplicate', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--danger" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'core-blueprint' ); ?></a></div></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function render_field_group_editor( string $id, bool $duplicate = false ): void {
		$group = '' !== $id ? Repository::field_group( $id ) : null;
		if ( '' !== $id && null === $group ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Field group definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		$duplicate_source = $duplicate ? $id : '';
		$is_edit = ! $duplicate && null !== $group;
		$group = array_replace( [
			'id'          => '',
			'title'       => '',
			'description' => '',
			'post_types'   => [],
			'option_pages'    => [],
			'term_taxonomies' => [],
			'user_enabled'    => false,
			'user_roles'      => [],
			'context'         => 'normal',
			'priority'    => 'default',
			'fields'      => [],
		], is_array( $group ) ? $group : [] );
		$duplicate_field_count = $duplicate ? count( (array) $group['fields'] ) : 0;
		if ( $duplicate ) {
			$group['id'] = '';
			$group['title'] = $this->copy_label( (string) $group['title'] );
			$group['post_types'] = [];
			$group['option_pages'] = [];
			$group['term_taxonomies'] = [];
			$group['user_enabled'] = false;
			$group['user_roles'] = [];
		}

		$post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
		uasort( $post_types, static fn( $a, $b ): int => strnatcasecmp( (string) $a->labels->name, (string) $b->labels->name ) );
		$option_pages = Repository::option_pages();
		$term_taxonomies = get_taxonomies( [ 'show_ui' => true ], 'objects' );
		uasort( $term_taxonomies, static fn( $a, $b ): int => strnatcasecmp( (string) $a->labels->name, (string) $b->labels->name ) );
		$roles = wp_roles();
		$user_roles = $roles ? $roles->roles : [];
		?>
		<section class="cb-content-models-section cb-content-models-section--settings">
			<h2><?php echo esc_html( $duplicate ? __( 'Duplicate Field Group', 'core-blueprint' ) : ( $is_edit ? __( 'Edit Field Group', 'core-blueprint' ) : __( 'Add Field Group', 'core-blueprint' ) ) ); ?></h2>
			<?php if ( $duplicate ) : ?><div class="notice notice-info inline"><p><?php echo esc_html( sprintf( _n( '%d field will be copied with a new internal ID. Choose a location before creating the duplicate; overlapping locations with the same meta keys are blocked.', '%d fields will be copied with new internal IDs. Choose a location before creating the duplicate; overlapping locations with the same meta keys are blocked.', $duplicate_field_count, 'core-blueprint' ), $duplicate_field_count ) ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-core-form-scope cb-content-models-group-form">
				<input type="hidden" name="action" value="cb_core_content_models_save_field_group" />
				<input type="hidden" name="original_id" value="<?php echo esc_attr( $is_edit ? (string) $group['id'] : '' ); ?>" />
				<input type="hidden" name="duplicate_source" value="<?php echo esc_attr( $duplicate_source ); ?>" />
				<?php wp_nonce_field( 'cb_core_content_models_save_field_group' ); ?>
				<table class="form-table cb-content-models-form-table" role="presentation"><tbody>
				<tr><th scope="row"><label for="cb-cm-group-title"><?php esc_html_e( 'Title', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-group-title" name="title" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $group['title'] ); ?>" /></td></tr>
				<tr><th scope="row"><label for="cb-cm-group-description"><?php esc_html_e( 'Description', 'core-blueprint' ); ?></label></th><td><textarea id="cb-cm-group-description" name="description" class="large-text" rows="3"><?php echo esc_textarea( (string) $group['description'] ); ?></textarea></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Show this field group on', 'core-blueprint' ); ?></th><td>
					<strong class="cb-content-models-location-title"><?php esc_html_e( 'Post Types', 'core-blueprint' ); ?></strong>
					<?php
					$location_post_type_options = [];
					foreach ( $post_types as $post_type ) {
						$location_post_type_options[] = [
							'name' => 'post_types[]',
							'value' => $post_type->name,
							'label' => (string) $post_type->labels->name . ' (' . $post_type->name . ')',
							'checked' => in_array( $post_type->name, (array) $group['post_types'], true ),
						];
					}
					echo ChoiceGroup::render( [
						'aria_label' => __( 'Post Types', 'core-blueprint' ),
						'options' => $location_post_type_options,
						'scrollable' => true,
					] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output.
					?>
					<strong class="cb-content-models-location-title cb-content-models-location-title--secondary"><?php esc_html_e( 'Options Pages', 'core-blueprint' ); ?></strong>
					<?php if ( empty( $option_pages ) ) : ?>
						<p class="description"><?php esc_html_e( 'No Option Pages exist yet. Create one in the Options Pages tab to use it as a field-group location.', 'core-blueprint' ); ?></p>
					<?php else : ?>
						<?php
						$location_option_page_options = [];
						foreach ( $option_pages as $page_slug => $option_page ) {
							$location_option_page_options[] = [
								'name' => 'option_pages[]',
								'value' => (string) $page_slug,
								'label' => (string) ( $option_page['title'] ?? $page_slug ) . ' (' . $page_slug . ')',
								'checked' => in_array( (string) $page_slug, (array) $group['option_pages'], true ),
							];
						}
						echo ChoiceGroup::render( [
							'aria_label' => __( 'Options Pages', 'core-blueprint' ),
							'options' => $location_option_page_options,
						] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output.
						?>
					<?php endif; ?>
					<strong class="cb-content-models-location-title cb-content-models-location-title--secondary"><?php esc_html_e( 'Taxonomy Terms', 'core-blueprint' ); ?></strong>
					<?php
					$location_taxonomy_options = [];
					foreach ( $term_taxonomies as $taxonomy ) {
						$location_taxonomy_options[] = [
							'name' => 'term_taxonomies[]',
							'value' => $taxonomy->name,
							'label' => (string) $taxonomy->labels->name . ' (' . $taxonomy->name . ')',
							'checked' => in_array( $taxonomy->name, (array) $group['term_taxonomies'], true ),
						];
					}
					echo ChoiceGroup::render( [ 'aria_label' => __( 'Taxonomy Terms', 'core-blueprint' ), 'options' => $location_taxonomy_options, 'scrollable' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
					<strong class="cb-content-models-location-title cb-content-models-location-title--secondary"><?php esc_html_e( 'User Profiles', 'core-blueprint' ); ?></strong>
					<div class="cb-core-stack cb-core-stack--compact">
						<?php echo ChoiceGroup::render( [ 'aria_label' => __( 'User profile location', 'core-blueprint' ), 'options' => [ [ 'name' => 'user_enabled', 'label' => __( 'Show this field group on user profile/edit screens', 'core-blueprint' ), 'checked' => ! empty( $group['user_enabled'] ) ] ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php
						$location_user_role_options = [];
						foreach ( $user_roles as $role_key => $role_data ) {
							$location_user_role_options[] = [ 'name' => 'user_roles[]', 'value' => (string) $role_key, 'label' => (string) ( $role_data['name'] ?? $role_key ) . ' (' . $role_key . ')', 'checked' => in_array( (string) $role_key, (array) $group['user_roles'], true ) ];
						}
						echo ChoiceGroup::render( [ 'aria_label' => __( 'Limit user profiles by role', 'core-blueprint' ), 'options' => $location_user_role_options, 'compact' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
					<p class="description"><?php esc_html_e( 'A group may target Post Meta, Options API, Term Meta, User Meta, or any combination. Leaving all user roles unchecked means all user profiles.', 'core-blueprint' ); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="cb-cm-group-context"><?php esc_html_e( 'Post editor position', 'core-blueprint' ); ?></label></th><td><select id="cb-cm-group-context" name="context"><option value="normal" <?php selected( $group['context'], 'normal' ); ?>><?php esc_html_e( 'Main column', 'core-blueprint' ); ?></option><option value="side" <?php selected( $group['context'], 'side' ); ?>><?php esc_html_e( 'Sidebar', 'core-blueprint' ); ?></option></select></td></tr>
				<tr><th scope="row"><label for="cb-cm-group-priority"><?php esc_html_e( 'Priority', 'core-blueprint' ); ?></label></th><td><select id="cb-cm-group-priority" name="priority"><option value="high" <?php selected( $group['priority'], 'high' ); ?>><?php esc_html_e( 'High', 'core-blueprint' ); ?></option><option value="default" <?php selected( $group['priority'], 'default' ); ?>><?php esc_html_e( 'Default', 'core-blueprint' ); ?></option><option value="low" <?php selected( $group['priority'], 'low' ); ?>><?php esc_html_e( 'Low', 'core-blueprint' ); ?></option></select></td></tr>
				</tbody></table>
				<?php submit_button( $duplicate ? __( 'Create Duplicate Field Group', 'core-blueprint' ) : ( $is_edit ? __( 'Save Field Group', 'core-blueprint' ) : __( 'Create Field Group', 'core-blueprint' ) ) ); ?>
			</form>
		</section>
		<?php
		if ( $is_edit ) {
			$this->render_fields_table( (string) $group['id'], (array) $group['fields'] );
		}
	}

	/** @param array<string,array<string,mixed>> $fields */
	private function render_fields_table( string $group_id, array $fields ): void {
		$add_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'field', 'model' => $group_id ], admin_url( 'admin.php' ) );
		$field_order = implode( ',', array_map( 'strval', array_keys( $fields ) ) );
		$order_form_id = 'cb-cm-field-order-' . sanitize_html_class( $group_id );
		?>
		<div class="cb-content-models-divider" aria-hidden="true"></div>
		<section class="cb-content-models-section cb-content-models-section--fields">
			<div class="cb-content-models-section-header">
				<div>
					<h2><?php esc_html_e( 'Fields', 'core-blueprint' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Expand a field for quick editing without leaving this page, or open the full editor in a separate tab.', 'core-blueprint' ); ?></p>
				</div>
				<div class="cb-content-models-section-actions">
					<a class="button cb-core-button cb-core-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Field', 'core-blueprint' ); ?></a>
				</div>
			</div>
			<?php if ( empty( $fields ) ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'This group does not contain fields yet.', 'core-blueprint' ); ?></p></div>
			<?php else : ?>
				<form id="<?php echo esc_attr( $order_form_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_core_content_models_sort_fields" />
					<input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" />
					<input type="hidden" name="field_order" value="<?php echo esc_attr( $field_order ); ?>" data-cb-cm-order-input />
					<?php wp_nonce_field( 'cb_core_content_models_sort_fields' ); ?>
				</form>
				<div class="cb-content-models-sort-toolbar">
					<p class="description"><?php esc_html_e( 'Drag fields by the handle to change their display order in the editor, then save the new order.', 'core-blueprint' ); ?></p>
					<button type="submit" class="button cb-core-button cb-core-button--secondary" form="<?php echo esc_attr( $order_form_id ); ?>" data-cb-cm-order-save disabled><?php esc_html_e( 'Save Field Order', 'core-blueprint' ); ?></button>
				</div>
				<table class="widefat striped cb-content-models-fields-table"><thead><tr><th class="cb-content-models-fields-table__order"><?php esc_html_e( 'Order', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Field', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Meta key', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Type', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'REST API', 'core-blueprint' ); ?></th><th><?php esc_html_e( 'Actions', 'core-blueprint' ); ?></th></tr></thead><tbody data-cb-cm-sortable data-order-form="<?php echo esc_attr( $order_form_id ); ?>">
				<?php foreach ( $fields as $field_id => $field ) :
					$edit_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'field', 'model' => $group_id, 'field' => $field_id ], admin_url( 'admin.php' ) );
					$duplicate_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'duplicate-field', 'model' => $group_id, 'field' => $field_id ], admin_url( 'admin.php' ) );
					$delete_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'delete-field', 'model' => $group_id, 'field' => $field_id ], admin_url( 'admin.php' ) );
					$type = (string) ( $field['type'] ?? 'text' );
					$panel_id = 'cb-cm-quick-' . sanitize_html_class( (string) $field_id );
					$default = $field['default_value'] ?? '';
					if ( is_array( $default ) ) {
						$default = implode( ', ', array_map( 'strval', $default ) );
					} elseif ( is_bool( $default ) ) {
						$default = $default ? '1' : '0';
					}
				?>
				<tr class="cb-content-models-field-summary" data-field-id="<?php echo esc_attr( $field_id ); ?>">
					<td class="cb-content-models-drag-cell"><span class="cb-content-models-drag-handle dashicons dashicons-menu" draggable="true" data-cb-cm-drag-handle aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'core-blueprint' ); ?></span></td>
					<td><button type="button" class="button-link cb-content-models-field-toggle" data-cb-cm-quick-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><strong data-cb-cm-summary-label><?php echo esc_html( (string) ( $field['label'] ?? $field_id ) ); ?></strong></button><?php echo ! empty( $field['required'] ) ? ' <span class="description" data-cb-cm-summary-required>(' . esc_html__( 'required', 'core-blueprint' ) . ')</span>' : '<span class="description" data-cb-cm-summary-required hidden></span>'; ?></td>
					<td><code><?php echo esc_html( (string) ( $field['name'] ?? '' ) ); ?></code></td>
					<td data-cb-cm-summary-type><?php echo esc_html( FieldTypes::labels()[ $type ] ?? $type ); ?></td>
					<td data-cb-cm-summary-rest><?php echo ! empty( $field['show_in_rest'] ) ? esc_html__( 'Enabled', 'core-blueprint' ) : esc_html__( 'Disabled', 'core-blueprint' ); ?></td>
					<td class="cb-content-models-col-actions"><div class="cb-content-models-actions"><button type="button" class="button cb-core-button cb-core-button--secondary" data-cb-cm-quick-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>"><?php esc_html_e( 'Quick Edit', 'core-blueprint' ); ?></button><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Full Editor ↗', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( $duplicate_url ); ?>"><?php esc_html_e( 'Duplicate', 'core-blueprint' ); ?></a><a class="button cb-core-button cb-core-button--danger" href="<?php echo esc_url( $delete_url ); ?>"><?php esc_html_e( 'Delete', 'core-blueprint' ); ?></a></div></td>
				</tr>
				<tr id="<?php echo esc_attr( $panel_id ); ?>" class="cb-content-models-quick-row" data-cb-cm-quick-row data-field-id="<?php echo esc_attr( $field_id ); ?>" hidden>
					<td colspan="6">
						<div class="cb-content-models-quick-panel">
							<div class="cb-content-models-quick-header"><div><strong><?php esc_html_e( 'Quick Edit', 'core-blueprint' ); ?></strong><p class="description"><?php esc_html_e( 'Common field settings can be changed here without leaving the field group.', 'core-blueprint' ); ?></p></div><a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open full editor ↗', 'core-blueprint' ); ?></a></div>
							<form class="cb-content-models-quick-form" data-cb-cm-quick-form>
								<input type="hidden" name="action" value="cb_core_content_models_quick_save_field" />
								<input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" />
								<input type="hidden" name="original_field_id" value="<?php echo esc_attr( $field_id ); ?>" />
								<input type="hidden" name="name" value="<?php echo esc_attr( (string) ( $field['name'] ?? '' ) ); ?>" />
								<input type="hidden" name="confirm_type_change" value="0" data-cb-cm-confirm-type-change />
								<?php wp_nonce_field( 'cb_core_content_models_quick_save_field', '_ajax_nonce' ); ?>
								<div class="cb-content-models-quick-grid">
									<label><span><?php esc_html_e( 'Field label', 'core-blueprint' ); ?></span><input name="label" type="text" required value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>" /></label>
									<label><span><?php esc_html_e( 'Meta key', 'core-blueprint' ); ?></span><input type="text" class="code" readonly value="<?php echo esc_attr( (string) ( $field['name'] ?? '' ) ); ?>" /></label>
									<div class="cb-content-models-quick-field"><label for="cb-cm-field-type-<?php echo esc_attr( $field_id ); ?>"><span><?php esc_html_e( 'Field type', 'core-blueprint' ); ?></span></label><select id="cb-cm-field-type-<?php echo esc_attr( $field_id ); ?>" name="type" data-cb-cm-field-type data-cb-core-select-picker data-cb-core-select-picker-search="true" data-original-type="<?php echo esc_attr( $type ); ?>"><?php foreach ( FieldTypes::grouped_labels() as $group_label => $group_types ) : ?><optgroup label="<?php echo esc_attr( $group_label ); ?>"><?php foreach ( $group_types as $option_type => $option_label ) : ?><option value="<?php echo esc_attr( $option_type ); ?>" <?php selected( $type, $option_type ); ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
									<label><span><?php esc_html_e( 'Placeholder', 'core-blueprint' ); ?></span><input name="placeholder" type="text" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>" /></label>
								</div>
								<?php $this->render_relation_settings( $field, true ); ?>
								<label class="cb-content-models-quick-wide"><span><?php esc_html_e( 'Instructions', 'core-blueprint' ); ?></span><textarea name="instructions" rows="2"><?php echo esc_textarea( (string) ( $field['instructions'] ?? '' ) ); ?></textarea></label>
								<div class="cb-content-models-quick-flags"><?php echo ChoiceGroup::render( [ 'compact' => true, 'aria_label' => __( 'Field behaviour', 'core-blueprint' ), 'options' => [ [ 'name' => 'required', 'label' => __( 'Required', 'core-blueprint' ), 'checked' => ! empty( $field['required'] ) ], [ 'name' => 'show_in_rest', 'label' => __( 'Expose through REST API', 'core-blueprint' ), 'checked' => ! empty( $field['show_in_rest'] ) ] ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output. ?></div>
								<div class="cb-content-models-quick-grid cb-content-models-quick-grid--advanced">
									<label><span><?php esc_html_e( 'Default value', 'core-blueprint' ); ?></span><input name="default_value" type="text" value="<?php echo esc_attr( (string) $default ); ?>" /></label>
									<label><span><?php esc_html_e( 'Editor rows', 'core-blueprint' ); ?></span><input name="rows" type="number" min="2" max="20" value="<?php echo esc_attr( (string) ( $field['rows'] ?? 5 ) ); ?>" /></label>
									<label><span><?php esc_html_e( 'Min', 'core-blueprint' ); ?></span><input name="min" type="number" step="any" value="<?php echo esc_attr( (string) ( $field['min'] ?? '' ) ); ?>" /></label>
									<label><span><?php esc_html_e( 'Max', 'core-blueprint' ); ?></span><input name="max" type="number" step="any" value="<?php echo esc_attr( (string) ( $field['max'] ?? '' ) ); ?>" /></label>
									<label><span><?php esc_html_e( 'Step', 'core-blueprint' ); ?></span><input name="step" type="number" step="any" value="<?php echo esc_attr( (string) ( $field['step'] ?? '' ) ); ?>" /></label>
								</div>
								<label class="cb-content-models-quick-wide"><span><?php esc_html_e( 'Choices', 'core-blueprint' ); ?></span><textarea name="choices_text" class="code" rows="4"><?php echo esc_textarea( FieldTypes::choices_to_text( (array) ( $field['choices'] ?? [] ) ) ); ?></textarea><span class="description"><?php esc_html_e( 'Used by Select, Radio and Checkbox. One choice per line: value : Label.', 'core-blueprint' ); ?></span></label>
								<div class="cb-content-models-quick-footer"><button type="submit" class="button cb-core-button cb-core-button--primary"><?php esc_html_e( 'Save Field', 'core-blueprint' ); ?></button><span class="cb-content-models-quick-status" role="status" aria-live="polite" data-cb-cm-quick-status></span></div>
							</form>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_field_editor( string $group_id, string $field_id, bool $duplicate = false ): void {
		$group = Repository::field_group( $group_id );
		if ( null === $group ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Field group definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		$field = '' !== $field_id ? Repository::field( $group_id, $field_id ) : null;
		if ( '' !== $field_id && null === $field ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Field definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		$duplicate_source = $duplicate ? $field_id : '';
		$is_edit = ! $duplicate && null !== $field;
		$field = array_replace( [
			'id'            => '',
			'name'          => '',
			'label'         => '',
			'type'          => 'text',
			'instructions'  => '',
			'required'      => false,
			'show_in_rest'  => false,
			'placeholder'   => '',
			'default_value' => '',
			'choices'       => [],
			'min'           => '',
			'max'           => '',
			'step'          => '',
			'rows'                => 5,
			'relation_multiple'   => false,
			'relation_post_types' => [],
			'relation_roles'      => [],
			'relation_taxonomies' => [],
			'sub_fields'           => [],
			'repeater_min'         => 0,
			'repeater_max'         => 0,
			'conditional_logic'    => [],
		], is_array( $field ) ? $field : [] );
		if ( $duplicate ) {
			$field['id'] = '';
			$field['label'] = $this->copy_label( (string) $field['label'] );
			$field['name'] = Repository::suggest_field_copy_name( $group_id, (string) $field['name'] );
		}
		$back_url = add_query_arg( [ 'page' => self::SLUG, 'tab' => 'field-groups', 'view' => 'edit', 'model' => $group_id ], admin_url( 'admin.php' ) );
		$default = $field['default_value'];
		if ( is_array( $default ) ) {
			$default = implode( ', ', array_map( 'strval', $default ) );
		} elseif ( is_bool( $default ) ) {
			$default = $default ? '1' : '0';
		}
		?>
		<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html( (string) $group['title'] ); ?></a></p>
		<h2><?php echo esc_html( $duplicate ? __( 'Duplicate Field', 'core-blueprint' ) : ( $is_edit ? __( 'Edit Field', 'core-blueprint' ) : __( 'Add Field', 'core-blueprint' ) ) ); ?></h2>
		<?php if ( $is_edit ) : ?><div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Field storage is protected.', 'core-blueprint' ); ?></strong> <?php esc_html_e( 'The meta key cannot be changed after creation. Changing the field type is allowed after confirmation because existing WordPress metadata may be interpreted differently. All other field settings remain editable.', 'core-blueprint' ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-core-form-scope">
			<input type="hidden" name="action" value="cb_core_content_models_save_field" /><input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" /><input type="hidden" name="original_field_id" value="<?php echo esc_attr( $is_edit ? (string) $field['id'] : '' ); ?>" /><input type="hidden" name="duplicate_source_field" value="<?php echo esc_attr( $duplicate_source ); ?>" /><input type="hidden" name="confirm_type_change" value="0" data-cb-cm-confirm-type-change />
			<?php wp_nonce_field( 'cb_core_content_models_save_field' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row"><label for="cb-cm-field-label"><?php esc_html_e( 'Field label', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-field-label" name="label" type="text" class="regular-text" required value="<?php echo esc_attr( (string) $field['label'] ); ?>" /></td></tr>
			<tr><th scope="row"><label for="cb-cm-field-name"><?php esc_html_e( 'Field name / meta key', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-field-name" name="name" type="text" class="regular-text code" required <?php echo $is_edit ? 'readonly' : ''; ?> value="<?php echo esc_attr( (string) $field['name'] ); ?>" /><p class="description"><?php esc_html_e( 'Stable field key used by native WordPress storage. Post locations use post meta; Option Pages use the Options API; Term and User locations use their native metadata APIs.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-field-type"><?php esc_html_e( 'Field type', 'core-blueprint' ); ?></label></th><td><select id="cb-cm-field-type" name="type" data-cb-cm-field-type data-cb-core-select-picker data-cb-core-select-picker-search="true" data-original-type="<?php echo esc_attr( (string) $field['type'] ); ?>"><?php foreach ( FieldTypes::grouped_labels() as $group_label => $group_types ) : ?><optgroup label="<?php echo esc_attr( $group_label ); ?>"><?php foreach ( $group_types as $type => $label ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><?php if ( $is_edit ) : ?><p class="description"><?php esc_html_e( 'Changing the type requires confirmation. Existing field values are preserved and are not automatically migrated.', 'core-blueprint' ); ?></p><?php endif; ?></td></tr>
			<tr data-cb-cm-relation-settings-row<?php echo FieldTypes::is_relation_type( (string) $field['type'] ) ? '' : ' hidden'; ?>><th scope="row"><?php esc_html_e( 'Relation settings', 'core-blueprint' ); ?></th><td><?php $this->render_relation_settings( $field, false ); ?></td></tr>
			<tr data-cb-cm-structured-settings-row<?php echo FieldTypes::is_structured_type( (string) $field['type'] ) ? '' : ' hidden'; ?>><th scope="row"><?php esc_html_e( 'Structured field schema', 'core-blueprint' ); ?></th><td><?php $this->render_structured_settings( $field ); ?></td></tr>
			<tr><th scope="row"><label for="cb-cm-field-instructions"><?php esc_html_e( 'Instructions', 'core-blueprint' ); ?></label></th><td><textarea id="cb-cm-field-instructions" name="instructions" class="large-text" rows="3"><?php echo esc_textarea( (string) $field['instructions'] ); ?></textarea></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Behaviour', 'core-blueprint' ); ?></th><td><?php echo ChoiceGroup::render( [ 'aria_label' => __( 'Field behaviour', 'core-blueprint' ), 'options' => [ [ 'name' => 'required', 'label' => __( 'Required', 'core-blueprint' ), 'checked' => ! empty( $field['required'] ) ], [ 'name' => 'show_in_rest', 'label' => __( 'Expose this field through the WordPress REST API', 'core-blueprint' ), 'checked' => ! empty( $field['show_in_rest'] ) ] ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output. ?><p class="description"><?php esc_html_e( 'REST exposure is opt-in. Storage remains native WordPress data regardless of this setting.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-field-placeholder"><?php esc_html_e( 'Placeholder', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-field-placeholder" name="placeholder" type="text" class="regular-text" value="<?php echo esc_attr( (string) $field['placeholder'] ); ?>" /></td></tr>
			<tr><th scope="row"><label for="cb-cm-field-default"><?php esc_html_e( 'Default value', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-field-default" name="default_value" type="text" class="regular-text" value="<?php echo esc_attr( (string) $default ); ?>" /><p class="description"><?php esc_html_e( 'For Checkbox fields, separate multiple default choice values with commas. For True / False use 1 or 0. Media and Relation fields use an empty default and ignore this setting.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><label for="cb-cm-field-choices"><?php esc_html_e( 'Choices', 'core-blueprint' ); ?></label></th><td><textarea id="cb-cm-field-choices" name="choices_text" class="large-text code" rows="6"><?php echo esc_textarea( FieldTypes::choices_to_text( (array) $field['choices'] ) ); ?></textarea><p class="description"><?php esc_html_e( 'Used by Select, Radio and Checkbox. One choice per line: value : Label. A line containing only a value uses that value as the label.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Number constraints', 'core-blueprint' ); ?></th><td><label><?php esc_html_e( 'Min', 'core-blueprint' ); ?> <input name="min" type="number" step="any" value="<?php echo esc_attr( (string) $field['min'] ); ?>" /></label> &nbsp; <label><?php esc_html_e( 'Max', 'core-blueprint' ); ?> <input name="max" type="number" step="any" value="<?php echo esc_attr( (string) $field['max'] ); ?>" /></label> &nbsp; <label><?php esc_html_e( 'Step', 'core-blueprint' ); ?> <input name="step" type="number" step="any" value="<?php echo esc_attr( (string) $field['step'] ); ?>" /></label></td></tr>
			<tr><th scope="row"><label for="cb-cm-field-rows"><?php esc_html_e( 'Editor rows', 'core-blueprint' ); ?></label></th><td><input id="cb-cm-field-rows" name="rows" type="number" min="2" max="20" value="<?php echo esc_attr( (string) $field['rows'] ); ?>" /><p class="description"><?php esc_html_e( 'Used by Textarea and WYSIWYG.', 'core-blueprint' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Conditional Logic', 'core-blueprint' ); ?></th><td><?php $this->render_conditional_logic( $group, $field ); ?></td></tr>
			</tbody></table>
			<?php submit_button( $duplicate ? __( 'Create Duplicate Field', 'core-blueprint' ) : ( $is_edit ? __( 'Save Field', 'core-blueprint' ) : __( 'Create Field', 'core-blueprint' ) ) ); ?>
		</form>
		<?php
	}


	/** @param array<string,mixed> $field */
	private function render_structured_settings( array $field ): void {
		$sub_fields = FieldTypes::sub_fields( $field );
		$type = (string) ( $field['type'] ?? '' );
		?>
		<div class="cb-content-models-structured" data-cb-cm-structured-settings data-empty-subfield-label="<?php echo esc_attr__( 'New subfield', 'core-blueprint' ); ?>">
			<p class="description"><?php esc_html_e( 'Group stores one associative array. Repeater stores an ordered array of rows. Subfield names are stable schema keys; existing structured values are never automatically migrated when a subfield type changes.', 'core-blueprint' ); ?></p>
			<div class="cb-content-models-repeater-limits" data-cb-cm-repeater-limits <?php echo 'repeater' === $type ? '' : 'hidden'; ?>>
				<label><?php esc_html_e( 'Minimum rows', 'core-blueprint' ); ?> <input type="number" min="0" max="100" name="repeater_min" value="<?php echo esc_attr( (string) ( $field['repeater_min'] ?? 0 ) ); ?>" /></label>
				<label><?php esc_html_e( 'Maximum rows', 'core-blueprint' ); ?> <input type="number" min="0" max="500" name="repeater_max" value="<?php echo esc_attr( (string) ( $field['repeater_max'] ?? 0 ) ); ?>" /></label>
				<p class="description"><?php esc_html_e( 'Use 0 for no explicit limit. A non-zero maximum may not be lower than the minimum.', 'core-blueprint' ); ?></p>
			</div>
			<div class="cb-content-models-subfields" data-cb-cm-subfields>
				<?php $index = 0; foreach ( $sub_fields as $sub_field ) : $this->render_subfield_row( $sub_field, $index++ ); endforeach; ?>
			</div>
			<button type="button" class="button cb-core-button cb-core-button--secondary" data-cb-cm-add-subfield><?php esc_html_e( 'Add Subfield', 'core-blueprint' ); ?></button>
			<template data-cb-cm-subfield-template><?php $this->render_subfield_row( [], '__INDEX__' ); ?></template>
		</div>
		<?php
	}

	/** @param array<string,mixed> $sub_field @param int|string $index */
	private function render_subfield_row( array $sub_field, $index ): void {
		$sub_field = array_replace( [
			'id' => '', 'label' => '', 'name' => '', 'type' => 'text', 'instructions' => '', 'required' => false,
			'placeholder' => '', 'default_value' => '', 'choices' => [], 'min' => '', 'max' => '', 'step' => '', 'rows' => 5,
			'relation_multiple' => false, 'relation_post_types' => [], 'relation_roles' => [], 'relation_taxonomies' => [],
		], $sub_field );
		$prefix = 'sub_fields[' . $index . ']';
		$type = (string) $sub_field['type'];
		$atomic_groups = FieldTypes::grouped_labels();
		unset( $atomic_groups[ __( 'Structured', 'core-blueprint' ) ] );
		?>
		<div class="cb-content-models-subfield" data-cb-cm-subfield draggable="true">
			<div class="cb-content-models-subfield__header">
				<span class="dashicons dashicons-move" aria-hidden="true" data-cb-cm-subfield-handle></span>
				<strong data-cb-cm-subfield-title><?php echo esc_html( '' !== (string) $sub_field['label'] ? (string) $sub_field['label'] : __( 'New subfield', 'core-blueprint' ) ); ?></strong>
				<button type="button" class="button-link-delete" data-cb-cm-remove-subfield><?php esc_html_e( 'Remove', 'core-blueprint' ); ?></button>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( (string) $sub_field['id'] ); ?>" />
			<div class="cb-content-models-subfield__grid">
				<label><span><?php esc_html_e( 'Label', 'core-blueprint' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>[label]" value="<?php echo esc_attr( (string) $sub_field['label'] ); ?>" data-cb-cm-subfield-label /></label>
				<label><span><?php esc_html_e( 'Field name', 'core-blueprint' ); ?></span><input type="text" class="code" name="<?php echo esc_attr( $prefix ); ?>[name]" value="<?php echo esc_attr( (string) $sub_field['name'] ); ?>" <?php echo '' !== (string) $sub_field['id'] ? 'readonly' : ''; ?> /></label>
				<label><span><?php esc_html_e( 'Type', 'core-blueprint' ); ?></span><select name="<?php echo esc_attr( $prefix ); ?>[type]" data-cb-cm-subfield-type><?php foreach ( $atomic_groups as $group_label => $types ) : ?><optgroup label="<?php echo esc_attr( $group_label ); ?>"><?php foreach ( $types as $option_type => $label ) : ?><option value="<?php echo esc_attr( $option_type ); ?>" <?php selected( $type, $option_type ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
				<label class="cb-content-models-subfield__check"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[required]" value="1" <?php checked( ! empty( $sub_field['required'] ) ); ?> /> <?php esc_html_e( 'Required', 'core-blueprint' ); ?></label>
				<label><span><?php esc_html_e( 'Placeholder', 'core-blueprint' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>[placeholder]" value="<?php echo esc_attr( (string) $sub_field['placeholder'] ); ?>" /></label>
				<label><span><?php esc_html_e( 'Default', 'core-blueprint' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>[default_value]" value="<?php echo esc_attr( is_scalar( $sub_field['default_value'] ) ? (string) $sub_field['default_value'] : '' ); ?>" /></label>
			</div>
			<label class="cb-content-models-subfield__wide"><span><?php esc_html_e( 'Instructions', 'core-blueprint' ); ?></span><textarea name="<?php echo esc_attr( $prefix ); ?>[instructions]" rows="2"><?php echo esc_textarea( (string) $sub_field['instructions'] ); ?></textarea></label>
			<div data-cb-cm-subfield-choice-settings <?php echo in_array( $type, [ 'select', 'radio', 'checkbox' ], true ) ? '' : 'hidden'; ?>>
				<label class="cb-content-models-subfield__wide"><span><?php esc_html_e( 'Choices', 'core-blueprint' ); ?></span><textarea class="code" name="<?php echo esc_attr( $prefix ); ?>[choices_text]" rows="4"><?php echo esc_textarea( FieldTypes::choices_to_text( (array) $sub_field['choices'] ) ); ?></textarea></label>
			</div>
			<div class="cb-content-models-subfield__grid" data-cb-cm-subfield-number-settings <?php echo 'number' === $type ? '' : 'hidden'; ?>>
				<label><span><?php esc_html_e( 'Min', 'core-blueprint' ); ?></span><input type="number" step="any" name="<?php echo esc_attr( $prefix ); ?>[min]" value="<?php echo esc_attr( (string) $sub_field['min'] ); ?>" /></label>
				<label><span><?php esc_html_e( 'Max', 'core-blueprint' ); ?></span><input type="number" step="any" name="<?php echo esc_attr( $prefix ); ?>[max]" value="<?php echo esc_attr( (string) $sub_field['max'] ); ?>" /></label>
				<label><span><?php esc_html_e( 'Step', 'core-blueprint' ); ?></span><input type="number" step="any" name="<?php echo esc_attr( $prefix ); ?>[step]" value="<?php echo esc_attr( (string) $sub_field['step'] ); ?>" /></label>
			</div>
			<div data-cb-cm-subfield-relation-settings <?php echo FieldTypes::is_relation_type( $type ) ? '' : 'hidden'; ?>>
				<label class="cb-content-models-subfield__check"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[relation_multiple]" value="1" <?php checked( ! empty( $sub_field['relation_multiple'] ) ); ?> /> <?php esc_html_e( 'Allow multiple selections', 'core-blueprint' ); ?></label>
				<div data-cb-cm-subfield-relation-target="post_relation" <?php echo 'post_relation' === $type ? '' : 'hidden'; ?>><?php echo ChoiceGroup::render( [ 'compact' => true, 'scrollable' => true, 'aria_label' => __( 'Allowed post types', 'core-blueprint' ), 'options' => $this->prefixed_choice_options( $this->relation_post_type_choices( (array) $sub_field['relation_post_types'] ), $prefix ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div data-cb-cm-subfield-relation-target="user_relation" <?php echo 'user_relation' === $type ? '' : 'hidden'; ?>><?php echo ChoiceGroup::render( [ 'compact' => true, 'scrollable' => true, 'aria_label' => __( 'Allowed user roles', 'core-blueprint' ), 'options' => $this->prefixed_choice_options( $this->relation_role_choices( (array) $sub_field['relation_roles'] ), $prefix ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div data-cb-cm-subfield-relation-target="term_relation" <?php echo 'term_relation' === $type ? '' : 'hidden'; ?>><?php echo ChoiceGroup::render( [ 'compact' => true, 'scrollable' => true, 'aria_label' => __( 'Allowed taxonomies', 'core-blueprint' ), 'options' => $this->prefixed_choice_options( $this->relation_taxonomy_choices( (array) $sub_field['relation_taxonomies'] ), $prefix ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>
		</div>
		<?php
	}

	/** @param array<int,array<string,mixed>> $options @return array<int,array<string,mixed>> */
	private function prefixed_choice_options( array $options, string $prefix ): array {
		foreach ( $options as &$option ) {
			$name = (string) ( $option['name'] ?? '' );
			$option['name'] = $prefix . '[' . str_replace( '[]', '', $name ) . '][]';
		}
		unset( $option );
		return $options;
	}

	/** @param array<string,mixed> $group @param array<string,mixed> $field */
	private function render_conditional_logic( array $group, array $field ): void {
		$available = [];
		foreach ( (array) ( $group['fields'] ?? [] ) as $candidate_id => $candidate ) {
			if ( is_array( $candidate ) && (string) $candidate_id !== (string) ( $field['id'] ?? '' ) ) {
				$available[ (string) $candidate_id ] = (string) ( $candidate['label'] ?? $candidate_id );
			}
		}
		$logic = is_array( $field['conditional_logic'] ?? null ) ? $field['conditional_logic'] : [];
		?>
		<div class="cb-content-models-conditions" data-cb-cm-conditions>
			<p class="description"><?php esc_html_e( 'Rules inside a group use AND. Separate groups use OR. Conditional Logic controls editor visibility only; stored data is preserved when a field is hidden.', 'core-blueprint' ); ?></p>
			<div data-cb-cm-condition-groups>
				<?php foreach ( $logic as $group_index => $rules ) : if ( is_array( $rules ) ) : ?>
					<div class="cb-content-models-condition-group" data-cb-cm-condition-group>
						<div class="cb-content-models-condition-group__header"><strong><?php esc_html_e( 'Condition group', 'core-blueprint' ); ?></strong><button type="button" class="button-link-delete" data-cb-cm-remove-condition-group><?php esc_html_e( 'Remove group', 'core-blueprint' ); ?></button></div>
						<div data-cb-cm-condition-rules><?php foreach ( $rules as $rule_index => $rule ) : if ( is_array( $rule ) ) : $this->render_condition_rule( $available, $rule, $group_index, $rule_index ); endif; endforeach; ?></div>
						<button type="button" class="button" data-cb-cm-add-condition><?php esc_html_e( 'Add AND rule', 'core-blueprint' ); ?></button>
					</div>
				<?php endif; endforeach; ?>
			</div>
			<button type="button" class="button cb-core-button cb-core-button--secondary" data-cb-cm-add-condition-group <?php echo empty( $available ) ? 'disabled' : ''; ?>><?php esc_html_e( 'Add condition group', 'core-blueprint' ); ?></button>
			<?php if ( empty( $available ) ) : ?><p class="description"><?php esc_html_e( 'Add another field to this Field Group before defining Conditional Logic.', 'core-blueprint' ); ?></p><?php endif; ?>
			<template data-cb-cm-condition-group-template><div class="cb-content-models-condition-group" data-cb-cm-condition-group><div class="cb-content-models-condition-group__header"><strong><?php esc_html_e( 'Condition group', 'core-blueprint' ); ?></strong><button type="button" class="button-link-delete" data-cb-cm-remove-condition-group><?php esc_html_e( 'Remove group', 'core-blueprint' ); ?></button></div><div data-cb-cm-condition-rules><?php $this->render_condition_rule( $available, [], '__GROUP__', '__RULE__' ); ?></div><button type="button" class="button" data-cb-cm-add-condition><?php esc_html_e( 'Add AND rule', 'core-blueprint' ); ?></button></div></template>
			<template data-cb-cm-condition-rule-template><?php $this->render_condition_rule( $available, [], '__GROUP__', '__RULE__' ); ?></template>
		</div>
		<?php
	}

	/** @param array<string,string> $available @param array<string,mixed> $rule @param int|string $group_index @param int|string $rule_index */
	private function render_condition_rule( array $available, array $rule, $group_index, $rule_index ): void {
		$prefix = 'conditional_logic[' . $group_index . '][' . $rule_index . ']';
		?>
		<div class="cb-content-models-condition-rule" data-cb-cm-condition-rule>
			<select name="<?php echo esc_attr( $prefix ); ?>[field]" aria-label="<?php esc_attr_e( 'Field', 'core-blueprint' ); ?>"><?php foreach ( $available as $candidate_id => $label ) : ?><option value="<?php echo esc_attr( $candidate_id ); ?>" <?php selected( (string) ( $rule['field'] ?? '' ), $candidate_id ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			<select name="<?php echo esc_attr( $prefix ); ?>[operator]" data-cb-cm-condition-operator aria-label="<?php esc_attr_e( 'Operator', 'core-blueprint' ); ?>"><option value="equals" <?php selected( (string) ( $rule['operator'] ?? 'equals' ), 'equals' ); ?>><?php esc_html_e( 'equals', 'core-blueprint' ); ?></option><option value="not_equals" <?php selected( (string) ( $rule['operator'] ?? '' ), 'not_equals' ); ?>><?php esc_html_e( 'does not equal', 'core-blueprint' ); ?></option><option value="empty" <?php selected( (string) ( $rule['operator'] ?? '' ), 'empty' ); ?>><?php esc_html_e( 'is empty', 'core-blueprint' ); ?></option><option value="not_empty" <?php selected( (string) ( $rule['operator'] ?? '' ), 'not_empty' ); ?>><?php esc_html_e( 'is not empty', 'core-blueprint' ); ?></option></select>
			<input type="text" name="<?php echo esc_attr( $prefix ); ?>[value]" value="<?php echo esc_attr( (string) ( $rule['value'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Value', 'core-blueprint' ); ?>" data-cb-cm-condition-value />
			<button type="button" class="button-link-delete" data-cb-cm-remove-condition aria-label="<?php esc_attr_e( 'Remove condition', 'core-blueprint' ); ?>">&times;</button>
		</div>
		<?php
	}


	/** @param array<string,mixed> $field */
	private function render_relation_settings( array $field, bool $quick ): void {
		$type = (string) ( $field['type'] ?? 'text' );
		$is_relation = FieldTypes::is_relation_type( $type );
		$classes = 'cb-content-models-relation-settings' . ( $quick ? ' cb-content-models-relation-settings--quick' : '' );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-cb-cm-relation-settings <?php echo $is_relation ? '' : 'hidden'; ?>>
			<div class="cb-content-models-relation-card">
				<strong><?php esc_html_e( 'Cardinality', 'core-blueprint' ); ?></strong>
				<?php echo ChoiceGroup::render( [
					'type'       => ChoiceGroup::TYPE_RADIO,
					'compact'    => $quick,
					'aria_label' => __( 'Relation cardinality', 'core-blueprint' ),
					'options'    => [
						[ 'name' => 'relation_multiple', 'value' => '0', 'label' => __( 'Single selection', 'core-blueprint' ), 'checked' => empty( $field['relation_multiple'] ) ],
						[ 'name' => 'relation_multiple', 'value' => '1', 'label' => __( 'Multiple selection', 'core-blueprint' ), 'checked' => ! empty( $field['relation_multiple'] ) ],
					],
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output. ?>
			</div>

			<div class="cb-content-models-relation-card" data-cb-cm-relation-target="post_relation" <?php echo 'post_relation' === $type ? '' : 'hidden'; ?>>
				<strong><?php esc_html_e( 'Allowed post types', 'core-blueprint' ); ?></strong>
				<?php echo ChoiceGroup::render( [
					'compact'    => $quick,
					'scrollable' => true,
					'aria_label' => __( 'Allowed post types', 'core-blueprint' ),
					'options'    => $this->relation_post_type_choices( (array) ( $field['relation_post_types'] ?? [] ) ),
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output. ?>
				<p class="description"><?php esc_html_e( 'Search results are restricted to these WordPress post types. Values are stored as post IDs.', 'core-blueprint' ); ?></p>
			</div>

			<div class="cb-content-models-relation-card" data-cb-cm-relation-target="user_relation" <?php echo 'user_relation' === $type ? '' : 'hidden'; ?>>
				<strong><?php esc_html_e( 'Allowed user roles', 'core-blueprint' ); ?></strong>
				<?php echo ChoiceGroup::render( [
					'compact'    => $quick,
					'scrollable' => true,
					'aria_label' => __( 'Allowed user roles', 'core-blueprint' ),
					'options'    => $this->relation_role_choices( (array) ( $field['relation_roles'] ?? [] ) ),
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output. ?>
				<p class="description"><?php esc_html_e( 'Leave all roles unchecked to allow any WordPress user. Values are stored as user IDs.', 'core-blueprint' ); ?></p>
			</div>

			<div class="cb-content-models-relation-card" data-cb-cm-relation-target="term_relation" <?php echo 'term_relation' === $type ? '' : 'hidden'; ?>>
				<strong><?php esc_html_e( 'Allowed taxonomies', 'core-blueprint' ); ?></strong>
				<?php echo ChoiceGroup::render( [
					'compact'    => $quick,
					'scrollable' => true,
					'aria_label' => __( 'Allowed taxonomies', 'core-blueprint' ),
					'options'    => $this->relation_taxonomy_choices( (array) ( $field['relation_taxonomies'] ?? [] ) ),
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Foundation renderer escapes its own output. ?>
				<p class="description"><?php esc_html_e( 'Search results are restricted to these taxonomies. Values are stored as term IDs.', 'core-blueprint' ); ?></p>
			</div>
		</div>
		<?php
	}

	/** @param string[] $selected @return array<int,array<string,mixed>> */
	private function relation_post_type_choices( array $selected ): array {
		$objects = get_post_types( [ 'show_ui' => true ], 'objects' );
		uasort( $objects, static fn( $a, $b ): int => strnatcasecmp( (string) $a->labels->singular_name, (string) $b->labels->singular_name ) );
		$options = [];
		foreach ( $objects as $key => $object ) {
			$options[] = [
				'name'    => 'relation_post_types[]',
				'value'   => (string) $key,
				'label'   => sprintf( '%s (%s)', (string) $object->labels->singular_name, (string) $key ),
				'checked' => in_array( (string) $key, $selected, true ),
			];
		}
		foreach ( array_diff( $selected, array_map( 'strval', array_keys( $objects ) ) ) as $missing ) {
			$options[] = [ 'name' => 'relation_post_types[]', 'value' => (string) $missing, 'label' => sprintf( __( 'Unavailable post type (%s)', 'core-blueprint' ), (string) $missing ), 'checked' => true ];
		}
		return $options;
	}

	/** @param string[] $selected @return array<int,array<string,mixed>> */
	private function relation_taxonomy_choices( array $selected ): array {
		$objects = get_taxonomies( [ 'show_ui' => true ], 'objects' );
		uasort( $objects, static fn( $a, $b ): int => strnatcasecmp( (string) $a->labels->singular_name, (string) $b->labels->singular_name ) );
		$options = [];
		foreach ( $objects as $key => $object ) {
			$options[] = [
				'name'    => 'relation_taxonomies[]',
				'value'   => (string) $key,
				'label'   => sprintf( '%s (%s)', (string) $object->labels->singular_name, (string) $key ),
				'checked' => in_array( (string) $key, $selected, true ),
			];
		}
		foreach ( array_diff( $selected, array_map( 'strval', array_keys( $objects ) ) ) as $missing ) {
			$options[] = [ 'name' => 'relation_taxonomies[]', 'value' => (string) $missing, 'label' => sprintf( __( 'Unavailable taxonomy (%s)', 'core-blueprint' ), (string) $missing ), 'checked' => true ];
		}
		return $options;
	}

	/** @param string[] $selected @return array<int,array<string,mixed>> */
	private function relation_role_choices( array $selected ): array {
		$wp_roles = wp_roles();
		$roles = is_object( $wp_roles ) ? (array) $wp_roles->roles : [];
		uasort( $roles, static fn( array $a, array $b ): int => strnatcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) ) );
		$options = [];
		foreach ( $roles as $key => $role ) {
			$options[] = [
				'name'    => 'relation_roles[]',
				'value'   => (string) $key,
				'label'   => sprintf( '%s (%s)', translate_user_role( (string) ( $role['name'] ?? $key ) ), (string) $key ),
				'checked' => in_array( (string) $key, $selected, true ),
			];
		}
		foreach ( array_diff( $selected, array_map( 'strval', array_keys( $roles ) ) ) as $missing ) {
			$options[] = [ 'name' => 'relation_roles[]', 'value' => (string) $missing, 'label' => sprintf( __( 'Unavailable role (%s)', 'core-blueprint' ), (string) $missing ), 'checked' => true ];
		}
		return $options;
	}

	private function render_field_group_delete( string $id ): void {
		$group = Repository::field_group( $id );
		if ( null === $group ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Field group definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		?>
		<h2><?php esc_html_e( 'Delete Field Group Definition', 'core-blueprint' ); ?></h2>
		<div class="notice notice-warning inline"><p><strong><?php echo esc_html( (string) $group['title'] ); ?></strong> — <?php esc_html_e( 'This removes the field definitions and editor UI only. Existing post-meta and Option Page values remain stored in WordPress.', 'core-blueprint' ); ?></p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="cb_core_content_models_delete_field_group" /><input type="hidden" name="model" value="<?php echo esc_attr( $id ); ?>" /><?php wp_nonce_field( 'cb_core_content_models_delete_field_group' ); ?><button type="submit" class="button cb-core-button cb-core-button--danger"><?php esc_html_e( 'Delete Definition', 'core-blueprint' ); ?></button></form>
		<?php
	}

	private function render_field_delete( string $group_id, string $field_id ): void {
		$group = Repository::field_group( $group_id );
		$field = Repository::field( $group_id, $field_id );
		if ( null === $group || null === $field ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Field definition not found.', 'core-blueprint' ) . '</p></div>';
			return;
		}
		?>
		<h2><?php esc_html_e( 'Delete Field Definition', 'core-blueprint' ); ?></h2>
		<div class="notice notice-warning inline"><p><strong><?php echo esc_html( (string) $field['label'] ); ?></strong> (<code><?php echo esc_html( (string) $field['name'] ); ?></code>) — <?php esc_html_e( 'The editor field will disappear, but existing metadata values are deliberately preserved.', 'core-blueprint' ); ?></p></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="cb_core_content_models_delete_field" /><input type="hidden" name="group_id" value="<?php echo esc_attr( $group_id ); ?>" /><input type="hidden" name="field_id" value="<?php echo esc_attr( $field_id ); ?>" /><?php wp_nonce_field( 'cb_core_content_models_delete_field' ); ?><button type="submit" class="button cb-core-button cb-core-button--danger"><?php esc_html_e( 'Delete Definition', 'core-blueprint' ); ?></button></form>
		<?php
	}

	private function option_page_label( string $slug ): string {
		$page = Repository::option_page( $slug );
		return null !== $page ? (string) ( $page['title'] ?? $slug ) : $slug;
	}

	/** @return array<string,string> */
	private function option_page_parent_choices( string $current_slug = '' ): array {
		$choices = [
			'index.php' => __( 'Dashboard', 'core-blueprint' ),
			'edit.php' => __( 'Posts', 'core-blueprint' ),
			'upload.php' => __( 'Media', 'core-blueprint' ),
			'edit.php?post_type=page' => __( 'Pages', 'core-blueprint' ),
			'edit-comments.php' => __( 'Comments', 'core-blueprint' ),
			'themes.php' => __( 'Appearance', 'core-blueprint' ),
			'plugins.php' => __( 'Plugins', 'core-blueprint' ),
			'users.php' => __( 'Users', 'core-blueprint' ),
			'tools.php' => __( 'Tools', 'core-blueprint' ),
			'options-general.php' => __( 'Settings', 'core-blueprint' ),
			'core-blueprint' => __( 'Core Blueprint', 'core-blueprint' ),
		];
		foreach ( Repository::option_pages() as $slug => $page ) {
			if ( $slug !== $current_slug && '' === (string) ( $page['parent_slug'] ?? '' ) ) {
				$choices[ (string) $slug ] = sprintf( __( 'Option Page: %s', 'core-blueprint' ), (string) ( $page['title'] ?? $slug ) );
			}
		}
		return $choices;
	}

	private function suggest_unique_option_page_slug( string $source_slug ): string {
		$base = sanitize_key( $source_slug );
		if ( '' === $base ) { $base = 'options'; }
		$suffix = '-copy';
		$i = 2;
		$candidate = substr( $base, 0, 80 - strlen( $suffix ) ) . $suffix;
		while ( null !== Repository::option_page( $candidate ) ) {
			$suffix = '-copy-' . $i++;
			$candidate = substr( $base, 0, 80 - strlen( $suffix ) ) . $suffix;
		}
		return $candidate;
	}

	private function post_type_label( string $post_type ): string {
		$object = get_post_type_object( $post_type );
		return null !== $object ? (string) $object->labels->name : $post_type;
	}

	private function render_disabled_panel(): void {
		$url = add_query_arg( 'tab', 'content-models', admin_url( 'admin.php?page=core-blueprint-preferences' ) );
		?>
		<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Content Models is disabled.', 'core-blueprint' ); ?></strong> <?php esc_html_e( 'Saved definitions and content are preserved, but Core Blueprint is not registering custom post types, taxonomies, Option Pages or custom fields.', 'core-blueprint' ); ?> <a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open Dashboard', 'core-blueprint' ); ?></a></p></div>
		<?php
	}

	private function render_runtime_errors(): void {
		foreach ( Runtime::errors() as $error ) {
			printf(
				'<div class="notice notice-error inline"><p><strong>%s:</strong> %s</p></div>',
				esc_html( (string) ( $error['key'] ?? '' ) ),
				esc_html( (string) ( $error['message'] ?? '' ) )
			);
		}
	}

	private function render_notice(): void {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $status ) {
			return;
		}

		$type = 'success';
		$message = match ( $status ) {
			'saved'     => __( 'Content model saved.', 'core-blueprint' ),
			'deleted'       => __( 'Content model definition deleted. Stored content was left untouched.', 'core-blueprint' ),
			'field-saved'   => __( 'Field definition saved. Values use native WordPress post meta.', 'core-blueprint' ),
			'field-deleted' => __( 'Field definition deleted. Existing post meta values were preserved.', 'core-blueprint' ),
			'field-order-saved' => __( 'Field order saved. The new sequence is now used when rendering this field group.', 'core-blueprint' ),
			'not-found' => __( 'The requested content model could not be found.', 'core-blueprint' ),
			'in-use'    => __( 'The post type cannot be deleted while a Core Blueprint taxonomy or field group still depends on it.', 'core-blueprint' ),
			'option-page-in-use' => __( 'The Option Page cannot be deleted while a Field Group or child Option Page still depends on it.', 'core-blueprint' ),
			'disabled'  => __( 'Enable Content Models before changing model definitions.', 'core-blueprint' ),
			'error'     => isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : __( 'The content model could not be saved.', 'core-blueprint' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			default     => '',
		};
		if ( in_array( $status, [ 'error', 'not-found', 'in-use', 'option-page-in-use', 'disabled' ], true ) ) {
			$type = 'error';
		}
		if ( '' !== $message ) {
			printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}
	}

	private function copy_label( string $label ): string {
		return sprintf( __( '%s Copy', 'core-blueprint' ), $label );
	}

	private function suggest_unique_model_key( string $source_key, string $kind, int $max_length ): string {
		$base = sanitize_key( $source_key );
		if ( '' === $base ) {
			$base = 'model';
		}
		$suffix_number = 1;
		do {
			$suffix = 1 === $suffix_number ? '-copy' : '-copy-' . $suffix_number;
			$candidate = substr( $base, 0, max( 1, $max_length - strlen( $suffix ) ) ) . $suffix;
			$in_use = 'taxonomy' === $kind
				? ( null !== Repository::taxonomy( $candidate ) || taxonomy_exists( $candidate ) )
				: ( null !== Repository::post_type( $candidate ) || post_type_exists( $candidate ) );
			++$suffix_number;
		} while ( $in_use );
		return $candidate;
	}


	private function render_icon_picker( string $id, string $name, string $value, string $description = '' ): void {
		$value = Icon::normalize_menu_icon( $value, 'dashicons-admin-generic' );
		?>
		<div class="cb-core-picker cb-core-icon-picker" data-cb-core-icon-picker>
			<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="text" class="regular-text code cb-core-picker__input" value="<?php echo esc_attr( $value ); ?>" data-cb-core-icon-picker-input />
			<div class="cb-core-picker__enhanced" data-cb-core-icon-picker-enhanced hidden>
				<button type="button" class="button cb-core-picker__toggle" data-cb-core-icon-picker-toggle aria-expanded="false" aria-haspopup="dialog">
					<span class="cb-core-picker__toggle-main">
						<span class="cb-core-picker__toggle-icon" data-cb-core-icon-picker-preview aria-hidden="true"></span>
						<span class="cb-core-picker__toggle-copy">
							<span class="cb-core-picker__toggle-text" data-cb-core-icon-picker-label></span>
							<span class="cb-core-picker__toggle-meta" data-cb-core-icon-picker-meta></span>
						</span>
					</span>
					<?php echo Icon::render( 'chevron-down', [ 'class' => 'cb-core-picker__toggle-chevron' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted Icon Foundation renderer. ?>
				</button>
				<div class="cb-core-picker__panel" data-cb-core-icon-picker-panel hidden>
					<div class="cb-core-picker__toolbar"><input type="search" class="regular-text cb-core-picker__search" data-cb-core-icon-picker-search placeholder="<?php esc_attr_e( 'Search icons…', 'core-blueprint' ); ?>" /></div>
					<div class="cb-core-picker__families">
						<button type="button" class="button cb-core-picker__family" data-cb-core-icon-picker-family="dashicons"><?php esc_html_e( 'Dashicons', 'core-blueprint' ); ?></button>
						<button type="button" class="button cb-core-picker__family" data-cb-core-icon-picker-family="lucide"><?php esc_html_e( 'Lucide', 'core-blueprint' ); ?></button>
					</div>
					<div class="cb-core-picker__results cb-core-icon-picker__results" data-cb-core-icon-picker-results></div>
				</div>
			</div>
			<?php if ( '' !== $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
		</div>
		<?php
	}

	private function render_capability_picker( string $id, string $name, string $value, string $description = '' ): void {
		$value = sanitize_key( $value );
		if ( '' === $value ) {
			$value = 'manage_options';
		}
		?>
		<div class="cb-core-picker cb-core-capability-picker" data-cb-core-capability-picker>
			<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="text" class="regular-text code cb-core-picker__input" value="<?php echo esc_attr( $value ); ?>" data-cb-core-capability-picker-input />
			<div class="cb-core-picker__enhanced" data-cb-core-capability-picker-enhanced hidden>
				<button type="button" class="button cb-core-picker__toggle" data-cb-core-capability-picker-toggle aria-expanded="false" aria-haspopup="dialog">
					<span class="cb-core-picker__toggle-main">
						<span class="cb-core-picker__toggle-copy">
							<span class="cb-core-picker__toggle-text" data-cb-core-capability-picker-label></span>
							<span class="cb-core-picker__toggle-meta"><?php esc_html_e( 'Capability', 'core-blueprint' ); ?></span>
						</span>
					</span>
					<?php echo Icon::render( 'chevron-down', [ 'class' => 'cb-core-picker__toggle-chevron' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted Icon Foundation renderer. ?>
				</button>
				<div class="cb-core-picker__panel" data-cb-core-capability-picker-panel hidden>
					<div class="cb-core-picker__toolbar"><input type="search" class="regular-text cb-core-picker__search" data-cb-core-capability-picker-search placeholder="<?php esc_attr_e( 'Search capabilities…', 'core-blueprint' ); ?>" /></div>
					<div class="cb-core-picker__results cb-core-capability-picker__results" data-cb-core-capability-picker-results></div>
				</div>
			</div>
			<?php if ( '' !== $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
		</div>
		<?php
	}

}
