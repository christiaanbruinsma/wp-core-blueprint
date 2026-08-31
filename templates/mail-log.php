<?php
/**
 * Privacy-first Mail Log table.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cb-core-wrap cb-core-mail-wrap">
	<h1 class="cb-core-title"><?php esc_html_e( 'Mail Log', 'core-blueprint' ); ?></h1>
	<p class="cb-core-intro"><?php esc_html_e( 'Delivery metadata for messages sent through Core Blueprint Mail. Message bodies, attachment contents and credentials are intentionally never stored.', 'core-blueprint' ); ?></p>

	<?php if ( is_array( $result_notice ) && ! empty( $result_notice['message'] ) ) : ?>
		<?php
		echo \CB\Core\UI\Notice::render( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notice::render() returns escape-clean HTML.
			'variant' => 'error' === ( $result_notice['type'] ?? '' ) ? \CB\Core\UI\Notice::ERROR : \CB\Core\UI\Notice::SUCCESS,
			'message' => (string) $result_notice['message'],
		] );
		?>
	<?php endif; ?>

	<div class="cb-core-meta">
		<span class="cb-core-meta__item"><?php printf( esc_html( _n( '%s entry', '%s entries', $total, 'core-blueprint' ) ), esc_html( number_format_i18n( $total ) ) ); ?></span>
		<span class="cb-core-meta__item"><?php printf( esc_html( _n( 'Retention: %d day', 'Retention: %d days', $retention, 'core-blueprint' ) ), $retention ); ?></span>
		<span class="cb-core-meta__item"><?php esc_html_e( 'Body logging: Off', 'core-blueprint' ); ?></span>
	</div>

	<section class="cb-core-mail-filter-section">
		<form method="get" class="cb-core-mail-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab_slug ); ?>" />
			<label><span><?php esc_html_e( 'Search', 'core-blueprint' ); ?></span><input type="search" name="search" value="<?php echo esc_attr( $current_search ); ?>" placeholder="<?php esc_attr_e( 'Recipient, subject, error…', 'core-blueprint' ); ?>" /></label>
			<label><span><?php esc_html_e( 'Status', 'core-blueprint' ); ?></span><select name="status"><option value=""><?php esc_html_e( 'Any', 'core-blueprint' ); ?></option><option value="sent" <?php selected( $current_status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'core-blueprint' ); ?></option><option value="failed" <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'core-blueprint' ); ?></option></select></label>
			<label><span><?php esc_html_e( 'Provider', 'core-blueprint' ); ?></span><select name="provider"><option value=""><?php esc_html_e( 'Any', 'core-blueprint' ); ?></option><?php foreach ( $providers as $slug => $label ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_provider, $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Period', 'core-blueprint' ); ?></span><select name="period"><?php foreach ( \CB\Core\Log\TimeFilter::PRESETS as $slug => $_label ) : ?><option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_period, $slug ); ?>><?php echo esc_html( \CB\Core\Log\TimeFilter::label( $slug ) ); ?></option><?php endforeach; ?></select></label>
			<div class="cb-core-mail-filter-actions">
				<button class="button cb-core-button cb-core-button--secondary cb-core-button--compact" type="submit"><?php esc_html_e( 'Apply filters', 'core-blueprint' ); ?></button>
				<a class="button cb-core-button cb-core-button--secondary cb-core-button--compact" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $page_slug . '&tab=' . $tab_slug ) ); ?>"><?php esc_html_e( 'Clear', 'core-blueprint' ); ?></a>
			</div>
		</form>
	</section>

	<section class="cb-core-section cb-core-mail-log-section">
		<?php if ( empty( $rows ) ) : ?>
			<div class="cb-core-empty"><?php esc_html_e( 'No mail delivery records match the current filters.', 'core-blueprint' ); ?></div>
		<?php else : ?>
			<div class="cb-core-mail-log-table-scroll">
				<table class="widefat striped cb-core-mail-log-table">
					<thead>
						<tr>
							<th scope="col" class="cb-core-mail-log-col-time"><?php esc_html_e( 'Time', 'core-blueprint' ); ?></th>
							<th scope="col" class="cb-core-mail-log-col-status"><?php esc_html_e( 'Status', 'core-blueprint' ); ?></th>
							<th scope="col" class="cb-core-mail-log-col-provider"><?php esc_html_e( 'Provider', 'core-blueprint' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Recipient', 'core-blueprint' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Subject', 'core-blueprint' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Details', 'core-blueprint' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) :
						$recipient = \CB\Core\Mail\Admin\LogsTab::format_addresses( $row->recipients_decoded ?? [] );
						$details = 'failed' === $row->status
							? trim( (string) $row->error_code . ( $row->error_message ? ': ' . $row->error_message : '' ) )
							: ( $row->provider_message_id ? 'ID: ' . $row->provider_message_id : sprintf( __( '%d ms', 'core-blueprint' ), (int) $row->duration_ms ) );
					?>
						<tr>
							<td class="cb-core-mail-log-col-time">
								<div class="cb-core-mail-log-time">
									<time datetime="<?php echo esc_attr( $row->created_at ); ?>"><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', $row->created_at ) ); ?></time>
									<?php if ( ! empty( $row->is_test ) ) : ?><span class="cb-core-badge cb-core-badge-tech"><?php esc_html_e( 'Test', 'core-blueprint' ); ?></span><?php endif; ?>
								</div>
							</td>
							<td class="cb-core-mail-log-col-status">
								<?php
								echo \CB\Core\UI\StateBadge::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StateBadge::render() returns escape-clean HTML.
									'failed' === $row->status ? __( 'Failed', 'core-blueprint' ) : __( 'Sent', 'core-blueprint' ),
									[ 'variant' => 'failed' === $row->status ? \CB\Core\UI\StateBadge::ERROR : \CB\Core\UI\StateBadge::SUCCESS ]
								);
								?>
							</td>
							<td class="cb-core-mail-log-col-provider"><?php echo esc_html( $providers[ $row->provider ] ?? $row->provider ); ?><br><span class="cb-core-muted"><?php echo esc_html( strtoupper( (string) $row->transport ) ); ?></span></td>
							<td><span title="<?php echo esc_attr( $recipient ); ?>"><?php echo esc_html( $recipient ?: '-' ); ?></span></td>
							<td><?php echo esc_html( $row->subject ?: '-' ); ?></td>
							<td><span title="<?php echo esc_attr( $details ); ?>"><?php echo esc_html( $details ?: '-' ); ?></span><?php if ( (int) $row->attachment_count > 0 ) : ?><br><span class="cb-core-muted"><?php printf( esc_html( _n( '%d attachment', '%d attachments', (int) $row->attachment_count, 'core-blueprint' ) ), (int) $row->attachment_count ); ?></span><?php endif; ?><?php if ( (int) ( $row->embed_count ?? 0 ) > 0 ) : ?><br><span class="cb-core-muted"><?php printf( esc_html( _n( '%d inline embed', '%d inline embeds', (int) $row->embed_count, 'core-blueprint' ) ), (int) $row->embed_count ); ?></span><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $total_pages > 1 ) :
		$base = admin_url( 'admin.php?page=' . $page_slug . '&tab=' . $tab_slug );
		$filters = array_filter( [ 'status' => $current_status, 'provider' => $current_provider, 'search' => $current_search, 'period' => $current_period ] );
		if ( $filters ) { $base = add_query_arg( $filters, $base ); }
	?>
		<div class="tablenav bottom"><div class="tablenav-pages"><span class="displaying-num"><?php printf( esc_html( _n( '%s item', '%s items', $total, 'core-blueprint' ) ), esc_html( number_format_i18n( $total ) ) ); ?></span><span class="pagination-links"><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', max( 1, $current_page - 1 ), $base ) ); ?>">«</a><span class="paging-input"><?php printf( esc_html__( '%1$s of %2$s', 'core-blueprint' ), esc_html( (string) $current_page ), esc_html( (string) $total_pages ) ); ?></span><a class="button" href="<?php echo esc_url( add_query_arg( 'paged', min( $total_pages, $current_page + 1 ), $base ) ); ?>">»</a></span></div></div>
	<?php endif; ?>

	<?php if ( current_user_can( 'manage_options' ) && $total > 0 ) : ?>
		<section class="cb-core-mail-danger-zone" aria-labelledby="cb-core-mail-clear-title">
			<h2 id="cb-core-mail-clear-title"><?php esc_html_e( 'Clear Mail Log', 'core-blueprint' ); ?></h2>
			<p><?php esc_html_e( 'Permanently delete every stored mail delivery record. This does not change mail settings or provider credentials.', 'core-blueprint' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-core-mail-clear-log-form" data-cb-core-mail-clear-log data-confirm-title="<?php esc_attr_e( 'Clear Mail Log', 'core-blueprint' ); ?>" data-confirm-body="<?php esc_attr_e( 'Permanently delete every stored mail delivery record. This does not change mail settings or provider credentials.', 'core-blueprint' ); ?>" data-confirm-label="<?php esc_attr_e( 'Clear Mail Log', 'core-blueprint' ); ?>">
				<input type="hidden" name="action" value="cb_core_mail_clear_log" />
				<?php wp_nonce_field( 'cb_core_mail_clear_log' ); ?>
				<button type="submit" class="button cb-core-button cb-core-button--danger"><?php esc_html_e( 'Clear Mail Log', 'core-blueprint' ); ?></button>
			</form>
		</section>
	<?php endif; ?>
</div>
