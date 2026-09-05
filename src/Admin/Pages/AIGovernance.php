<?php
declare(strict_types=1);
/**
 * Canonical AI Governance admin surface.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */
namespace CB\Core\Admin\Pages;

use CB\Core\Admin\PageBase;
use CB\Core\AIGovernance\Activity;
use CB\Core\AIGovernance\Admin\Actions;
use CB\Core\AIGovernance\Repository;
use CB\Core\AIGovernance\Settings;

defined( 'ABSPATH' ) || exit;

final class AIGovernance extends PageBase {
	public const SLUG = 'core-blueprint-ai-governance';

	public function slug(): string {
		return self::SLUG;
	}

	public function title(): string {
		return __( 'AI Governance', 'core-blueprint' );
	}

	public function menu_title(): string {
		return __( 'AI Governance', 'core-blueprint' );
	}

	public function position(): ?int {
		return 18;
	}

	public function render(): void {
		$this->guard();
		$activity_id = isset( $_GET['activity'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['activity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $activity_id ) {
			$this->render_detail( $activity_id );
			return;
		}
		$this->render_list();
	}

	private function render_list(): void {
		$filters = $this->filters_from_request();
		$query_args = $filters;
		$query_args['page'] = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query_args['per_page'] = 50;
		$result = Repository::query( $query_args );
		$rows = $result['rows'];
		$total = $result['total'];
		$current_page = $result['page'];
		$total_pages = $result['total_pages'];
		$raw = $this->raw_filter_values();
		?>
		<div class="wrap cb-core-wrap cb-core-ai-governance-page">
			<h1 class="cb-core-title"><?php esc_html_e( 'AI Governance', 'core-blueprint' ); ?></h1>
			<p class="cb-core-intro"><?php esc_html_e( 'Evidence-based activity records for WordPress abilities, agents and machine integrations. Unknown attribution stays unknown; raw prompts and responses are not captured by default.', 'core-blueprint' ); ?></p>

			<ul class="cb-core-meta">
				<li class="cb-core-meta__item">
					<?php printf( esc_html__( 'Recorded activity: %s', 'core-blueprint' ), '<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>' ); ?>
				</li>
				<li class="cb-core-meta__item"><?php esc_html_e( 'Evidence model: metadata-first', 'core-blueprint' ); ?></li>
				<li class="cb-core-meta__item"><?php esc_html_e( 'Visible to administrators only', 'core-blueprint' ); ?></li>
			</ul>

			<?php if ( isset( $_GET['retention'] ) && 'updated' === sanitize_key( (string) $_GET['retention'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'AI activity retention updated.', 'core-blueprint' ); ?></p></div>
			<?php endif; ?>

			<section class="cb-core-section">
				<form method="get" class="cb-core-toolbar">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
					<label class="cb-core-toolbar__field">
						<span class="cb-core-toolbar__label"><?php esc_html_e( 'From', 'core-blueprint' ); ?></span>
						<input type="date" name="from" value="<?php echo esc_attr( $raw['from'] ); ?>" />
					</label>
					<label class="cb-core-toolbar__field">
						<span class="cb-core-toolbar__label"><?php esc_html_e( 'To', 'core-blueprint' ); ?></span>
						<input type="date" name="to" value="<?php echo esc_attr( $raw['to'] ); ?>" />
					</label>
					<label class="cb-core-toolbar__field">
						<span class="cb-core-toolbar__label"><?php esc_html_e( 'Actor user ID', 'core-blueprint' ); ?></span>
						<input type="number" min="1" name="actor" value="<?php echo esc_attr( $raw['actor'] ); ?>" />
					</label>
					<label class="cb-core-toolbar__field">
						<span class="cb-core-toolbar__label"><?php esc_html_e( 'Source ID', 'core-blueprint' ); ?></span>
						<input type="text" name="source" value="<?php echo esc_attr( $raw['source'] ); ?>" placeholder="wordpress-mcp-adapter" />
					</label>
					<label class="cb-core-toolbar__field">
						<span class="cb-core-toolbar__label"><?php esc_html_e( 'Operation contains', 'core-blueprint' ); ?></span>
						<input type="text" name="operation" value="<?php echo esc_attr( $raw['operation'] ); ?>" placeholder="plugin/ability" />
					</label>
					<label class="cb-core-toolbar__field">
						<span class="cb-core-toolbar__label"><?php esc_html_e( 'Outcome', 'core-blueprint' ); ?></span>
						<select name="outcome">
							<option value=""><?php esc_html_e( 'Any', 'core-blueprint' ); ?></option>
							<?php foreach ( Activity::OUTCOMES as $outcome ) : ?>
								<option value="<?php echo esc_attr( $outcome ); ?>" <?php selected( $raw['outcome'], $outcome ); ?>><?php echo esc_html( $outcome ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="cb-core-toolbar__actions">
						<span class="cb-core-toolbar__label"><?php esc_html_e( 'Actions', 'core-blueprint' ); ?></span>
						<div class="cb-core-toolbar__actions-row">
							<button type="submit" class="button"><?php esc_html_e( 'Apply filters', 'core-blueprint' ); ?></button>
							<?php if ( $this->has_filters( $raw ) ) : ?>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>"><?php esc_html_e( 'Clear', 'core-blueprint' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				</form>
			</section>

			<section class="cb-core-section">
				<?php if ( [] === $rows ) : ?>
					<div class="cb-core-empty">
						<h2><?php esc_html_e( 'No AI or agent activity recorded yet', 'core-blueprint' ); ?></h2>
						<p><?php esc_html_e( 'Records will appear when WordPress abilities are observed or a supported integration reports governance activity. No AI provider needs to be configured in Core Blueprint.', 'core-blueprint' ); ?></p>
					</div>
				<?php else : ?>
					<div class="cb-core-log-table-scroll">
						<table class="widefat striped cb-core-log-table">
							<thead><tr>
								<th scope="col"><?php esc_html_e( 'Time', 'core-blueprint' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actor', 'core-blueprint' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Source', 'core-blueprint' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Operation', 'core-blueprint' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Outcome', 'core-blueprint' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Target', 'core-blueprint' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Evidence', 'core-blueprint' ); ?></th>
							</tr></thead>
							<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&activity=' . rawurlencode( (string) $row->activity_id ) ) ); ?>"><?php echo esc_html( (string) $row->created_at ); ?></a></td>
									<td><?php echo esc_html( $this->actor_label( $row ) ); ?></td>
									<td><?php echo esc_html( $this->source_label( $row ) ); ?><br><code><?php echo esc_html( (string) $row->transport ); ?></code></td>
									<td><code><?php echo esc_html( (string) $row->operation ); ?></code></td>
									<td><code><?php echo esc_html( (string) $row->outcome ); ?></code></td>
									<td><?php echo esc_html( $this->target_label( $row ) ); ?></td>
									<td><code><?php echo esc_html( (string) $row->capture_state ); ?></code></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php $this->render_pagination( $current_page, $total_pages, $raw ); ?>
				<?php endif; ?>
			</section>

			<section class="cb-core-section">
				<h2><?php esc_html_e( 'Audit export', 'core-blueprint' ); ?></h2>
				<p><?php esc_html_e( 'Export the selected audit period and active filters. JSON preserves structured evidence; CSV serializes structured fields as JSON cells.', 'core-blueprint' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Actions::EXPORT_ACTION ); ?>" />
					<?php wp_nonce_field( Actions::EXPORT_ACTION ); ?>
					<?php foreach ( $raw as $key => $value ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
					<?php endforeach; ?>
					<label for="cb-ai-export-format" class="screen-reader-text"><?php esc_html_e( 'Export format', 'core-blueprint' ); ?></label>
					<select id="cb-ai-export-format" name="format"><option value="csv">CSV</option><option value="json">JSON</option></select>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Export activity', 'core-blueprint' ); ?></button>
				</form>
			</section>

			<section id="retention" class="cb-core-section">
				<h2><?php esc_html_e( 'Retention', 'core-blueprint' ); ?></h2>
				<p><?php esc_html_e( 'AI Activity is a dedicated governance datastore and is pruned by Core Blueprint’s daily retention runner. Use 0 to retain records indefinitely.', 'core-blueprint' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Actions::RETENTION_ACTION ); ?>" />
					<?php wp_nonce_field( Actions::RETENTION_ACTION ); ?>
					<label for="cb-ai-retention-days"><?php esc_html_e( 'Retention days', 'core-blueprint' ); ?></label>
					<input id="cb-ai-retention-days" type="number" name="retention_days" min="0" max="<?php echo esc_attr( (string) Settings::MAX_RETENTION_DAYS ); ?>" value="<?php echo esc_attr( (string) Settings::retention_days() ); ?>" />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save retention', 'core-blueprint' ); ?></button>
				</form>
			</section>
		</div>
		<?php
	}

	private function render_detail( string $activity_id ): void {
		$row = Repository::get( $activity_id );
		?>
		<div class="wrap cb-core-wrap cb-core-ai-governance-page">
			<h1 class="cb-core-title"><?php esc_html_e( 'AI activity detail', 'core-blueprint' ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ); ?>">&larr; <?php esc_html_e( 'Back to AI Governance', 'core-blueprint' ); ?></a></p>
			<?php if ( null === $row ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'The requested AI activity record was not found.', 'core-blueprint' ); ?></p></div>
			</div>
			<?php return; endif; ?>
			<section class="cb-core-section">
				<table class="widefat striped"><tbody>
				<?php
				$details = [
					__( 'Activity ID', 'core-blueprint' ) => $row->activity_id,
					__( 'Observed at', 'core-blueprint' ) => $row->created_at,
					__( 'Completed at', 'core-blueprint' ) => $row->completed_at ?: '—',
					__( 'Actor', 'core-blueprint' ) => $this->actor_label( $row ),
					__( 'Operation type', 'core-blueprint' ) => $row->operation_type,
					__( 'Operation', 'core-blueprint' ) => $row->operation,
					__( 'Transport', 'core-blueprint' ) => $row->transport,
					__( 'Source', 'core-blueprint' ) => $this->source_label( $row ),
					__( 'Outcome', 'core-blueprint' ) => $row->outcome,
					__( 'Capture state', 'core-blueprint' ) => $row->capture_state,
					__( 'Target', 'core-blueprint' ) => $this->target_label( $row ),
					__( 'Duration (ms)', 'core-blueprint' ) => null !== $row->duration_ms ? (string) $row->duration_ms : '—',
					__( 'Error code', 'core-blueprint' ) => $row->error_code ?: '—',
				];
				foreach ( $details as $label => $value ) : ?>
					<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( (string) $value ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</section>
			<section class="cb-core-section">
				<h2><?php esc_html_e( 'Evidence', 'core-blueprint' ); ?></h2>
				<pre><?php echo esc_html( wp_json_encode( $row->evidence_decoded ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ?: '{}' ); ?></pre>
				<h2><?php esc_html_e( 'Context', 'core-blueprint' ); ?></h2>
				<pre><?php echo esc_html( wp_json_encode( $row->context_decoded ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ?: '{}' ); ?></pre>
			</section>
		</div>
		<?php
	}

	/** @return array<string,mixed> */
	private function filters_from_request(): array {
		$raw = $this->raw_filter_values();
		$filters = [];
		if ( $this->valid_date( $raw['from'] ) ) {
			$filters['since'] = $raw['from'] . ' 00:00:00';
		}
		if ( $this->valid_date( $raw['to'] ) ) {
			$filters['until'] = $raw['to'] . ' 23:59:59';
		}
		if ( '' !== $raw['actor'] ) {
			$filters['actor'] = max( 0, (int) $raw['actor'] );
		}
		foreach ( [ 'source', 'operation', 'outcome' ] as $key ) {
			if ( '' !== $raw[ $key ] ) {
				$filters[ $key ] = $raw[ $key ];
			}
		}
		return $filters;
	}

	/** @return array{from:string,to:string,actor:string,source:string,operation:string,outcome:string} */
	private function raw_filter_values(): array {
		$outcome = isset( $_GET['outcome'] ) ? sanitize_key( (string) wp_unslash( $_GET['outcome'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return [
			'from' => isset( $_GET['from'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'to' => isset( $_GET['to'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'actor' => isset( $_GET['actor'] ) ? (string) max( 0, (int) $_GET['actor'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'source' => isset( $_GET['source'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['source'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'operation' => isset( $_GET['operation'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['operation'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'outcome' => in_array( $outcome, Activity::OUTCOMES, true ) ? $outcome : '',
		];
	}

	private function actor_label( object $row ): string {
		if ( ! empty( $row->actor_user_login ) ) {
			return (string) $row->actor_user_login . ( ! empty( $row->actor_user_id ) ? ' (#' . (int) $row->actor_user_id . ')' : '' );
		}
		return __( 'Unknown actor', 'core-blueprint' );
	}

	private function source_label( object $row ): string {
		if ( ! empty( $row->source_label ) ) {
			return (string) $row->source_label;
		}
		if ( ! empty( $row->source_id ) ) {
			return (string) $row->source_id;
		}
		return __( 'Unknown source', 'core-blueprint' );
	}

	private function target_label( object $row ): string {
		if ( ! empty( $row->target_label ) ) {
			return (string) $row->target_label;
		}
		if ( ! empty( $row->target_type ) || ! empty( $row->target_id ) ) {
			return trim( (string) $row->target_type . ( ! empty( $row->target_id ) ? ':' . (string) $row->target_id : '' ), ':' );
		}
		return '—';
	}

	/** @param array<string,string> $raw */
	private function has_filters( array $raw ): bool {
		return [] !== array_filter( $raw, static fn( string $value ): bool => '' !== $value );
	}

	/** @param array<string,string> $raw */
	private function render_pagination( int $current, int $total, array $raw ): void {
		if ( $total <= 1 ) {
			return;
		}
		$base_args = array_filter( array_merge( [ 'page' => self::SLUG ], $raw ), static fn( $value ): bool => '' !== $value );
		echo '<p class="tablenav-pages">';
		if ( $current > 1 ) {
			$prev = add_query_arg( array_merge( $base_args, [ 'paged' => $current - 1 ] ), admin_url( 'admin.php' ) );
			echo '<a class="button" href="' . esc_url( $prev ) . '">&larr; ' . esc_html__( 'Previous', 'core-blueprint' ) . '</a> ';
		}
		printf( esc_html__( 'Page %1$d of %2$d', 'core-blueprint' ), $current, $total );
		if ( $current < $total ) {
			$next = add_query_arg( array_merge( $base_args, [ 'paged' => $current + 1 ] ), admin_url( 'admin.php' ) );
			echo ' <a class="button" href="' . esc_url( $next ) . '">' . esc_html__( 'Next', 'core-blueprint' ) . ' &rarr;</a>';
		}
		echo '</p>';
	}

	private function valid_date( string $value ): bool {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) );
		return $date instanceof \DateTimeImmutable && $date->format( 'Y-m-d' ) === $value;
	}
}
