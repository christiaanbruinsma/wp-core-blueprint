<?php
/**
 * Preferences → Reports tab
 *
 * Variables provided by Preferences::render_reports_tab():
 *   - $branding             array  current reports.branding values
 *   - $fallback             array  raw defaults from ReportBranding::settings_defaults()
 *   - $logo_attachment_id   int    current logo attachment ID (0 = none)
 *   - $logo_url             string resolved attachment URL (empty if no logo)
 *   - $logo_alt             string attachment alt text
 *   - $nonce                string cb_core_admin nonce
 *
 * Layout: report preferences only. Module activation is managed from the Dashboard.
 * When Reports is disabled, a small informational banner is shown while appearance/provider
 * details and Save / Reset actions at the bottom.
 * The previous live-preview column was removed in 1.3.34-dev because the
 * HTML mock did not faithfully match the Dompdf-rendered PDF - operators
 * now generate an actual report on the Maintenance Report tab to verify
 * branding changes.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$provider_name    = (string) ( $branding['provider_name'] ?? '' );
$provider_contact = (string) ( $branding['provider_contact'] ?? '' );
$accent_color = (string) ( $branding['accent_color'] ?? $fallback['accent_color'] );
$is_enabled   = class_exists( '\CB\Core\Reports\State' ) ? \CB\Core\Reports\State::is_enabled() : true;
?>
<div class="wrap cb-core-wrap cb-core-reports-preferences">

	<h1 class="cb-core-title"><?php esc_html_e( 'Reports', 'core-blueprint' ); ?></h1>

	<p class="cb-core-intro">
		<?php esc_html_e( 'Configure report appearance and optional provider details. Report content is stored as an immutable snapshot; presentation settings can be updated independently.', 'core-blueprint' ); ?>
	</p>

	<?php if ( ! $is_enabled ) : ?>
		<?php
		echo \CB\Core\UI\Notice::render( [
			'variant' => \CB\Core\UI\Notice::INFO,
			'title'   => __( 'Reports is disabled.', 'core-blueprint' ),
			'message' => __( 'Branding below stays editable. Enable Reports from the Dashboard when you want to generate reports again.', 'core-blueprint' ),
			'class'   => 'cb-core-reports-disabled-notice',
		] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped - helper escapes own output
		?>
	<?php endif; ?>

	<form
		id="cb-core-branding-form"
		class="cb-core-form cb-core-branding-form"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
	>
		<section class="cb-core-preferences-section cb-core-branding-panel">
			<h2><?php esc_html_e( 'Report appearance', 'core-blueprint' ); ?></h2>

			<p class="description cb-core-panel-intro">
				<?php esc_html_e( 'Logo and accent colour are applied when a report PDF is viewed or downloaded. Changing them does not change the stored report content.', 'core-blueprint' ); ?>
			</p>

			<?php // Logo sub-section. ?>
			<section class="cb-core-branding-section">
				<h3 class="cb-core-branding-subhead"><?php esc_html_e( 'Logo', 'core-blueprint' ); ?></h3>

				<p class="description">
					<?php esc_html_e( 'Supported: SVG, PNG or JPEG up to 2 MB. For raster logos, use a clear square or landscape image up to 4096 x 4096 px. Shown in the top-left of every report.', 'core-blueprint' ); ?>
				</p>

				<div class="cb-core-logo-picker">
					<input
						type="hidden"
						name="logo_attachment_id"
						id="cb-core-logo-id"
						value="<?php echo (int) $logo_attachment_id; ?>"
					>
					<div class="cb-core-logo-preview" id="cb-core-logo-preview"
						<?php if ( '' !== $logo_url ) : ?>
							data-has-logo="yes"
						<?php else : ?>
							data-has-logo="no"
						<?php endif; ?>>
						<?php if ( '' !== $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>">
						<?php else : ?>
							<span class="cb-core-logo-placeholder">
								<?php esc_html_e( 'No logo set', 'core-blueprint' ); ?>
							</span>
						<?php endif; ?>
					</div>
					<p class="cb-core-logo-actions">
						<button
							type="button"
							class="button cb-core-button cb-core-button--secondary"
							id="cb-core-logo-pick"
						>
							<?php
							echo $logo_attachment_id > 0
								? esc_html__( 'Change logo', 'core-blueprint' )
								: esc_html__( 'Select logo', 'core-blueprint' );
							?>
						</button>
						<button
							type="button"
							class="button-link cb-core-logo-remove"
							id="cb-core-logo-remove"
							<?php echo $logo_attachment_id > 0 ? '' : 'hidden'; ?>
						>
							<?php esc_html_e( 'Remove', 'core-blueprint' ); ?>
						</button>
					</p>
				</div>
			</section>

			<?php // Accent colour - part of report appearance. ?>
			<section class="cb-core-branding-section">
				<div class="cb-core-field cb-core-branding-field">
					<label for="cb-core-accent-color" class="cb-core-field__label">
						<?php esc_html_e( 'Accent colour', 'core-blueprint' ); ?>
					</label>
					<div class="cb-core-accent-row">
						<input
							type="color"
							id="cb-core-accent-color"
							name="accent_color"
							value="<?php echo esc_attr( $accent_color ); ?>"
						>
						<input
							type="text"
							id="cb-core-accent-hex"
							class="cb-core-input cb-core-accent-hex"
							value="<?php echo esc_attr( $accent_color ); ?>"
							maxlength="7"
							pattern="^#[0-9a-fA-F]{6}$"
							aria-label="<?php esc_attr_e( 'Accent colour hex value', 'core-blueprint' ); ?>"
						>
					</div>
					<p class="description cb-core-field__hint">
						<?php
						printf(
							/* translators: %s: default colour hex code */
							esc_html__( 'Used for headings, accents, and the rule under the report header. Default: %s.', 'core-blueprint' ),
							'<code>' . esc_html( $fallback['accent_color'] ) . '</code>'
						);
						?>
					</p>
				</div>
			</section>

			<?php // Optional report provider. ?>
			<section class="cb-core-branding-section">
				<h3 class="cb-core-branding-subhead"><?php esc_html_e( 'Report provider', 'core-blueprint' ); ?></h3>

				<p class="description">
					<?php esc_html_e( 'Optional details about the person or organisation preparing the report. Leave both fields empty when the site is self-managed.', 'core-blueprint' ); ?>
				</p>

				<div class="cb-core-field cb-core-branding-field">
					<label for="cb-core-provider-name" class="cb-core-field__label">
						<?php esc_html_e( 'Name', 'core-blueprint' ); ?>
					</label>
					<input
						type="text"
						id="cb-core-provider-name"
						name="provider_name"
						class="cb-core-input"
						value="<?php echo esc_attr( $provider_name ); ?>"
						maxlength="120"
						placeholder="<?php esc_attr_e( 'e.g. Infused', 'core-blueprint' ); ?>"
					>
					<p class="description cb-core-field__hint">
						<?php esc_html_e( 'Shown as “Prepared by” in the report header when filled in.', 'core-blueprint' ); ?>
					</p>
				</div>

				<div class="cb-core-field cb-core-branding-field">
					<label for="cb-core-provider-contact" class="cb-core-field__label">
						<?php esc_html_e( 'Contact', 'core-blueprint' ); ?>
					</label>
					<input
						type="text"
						id="cb-core-provider-contact"
						name="provider_contact"
						class="cb-core-input"
						value="<?php echo esc_attr( $provider_contact ); ?>"
						maxlength="200"
						placeholder="<?php esc_attr_e( 'e.g. support@yourwebsite.com', 'core-blueprint' ); ?>"
					>
					<p class="description cb-core-field__hint">
						<?php esc_html_e( 'Optional single-line contact information for the report provider, such as email, phone, or address.', 'core-blueprint' ); ?>
					</p>
				</div>
			</section>

			<?php // Actions. ?>
			<div class="cb-core-actions cb-core-branding-actions">
				<button
					type="button"
					class="button button-primary cb-core-button cb-core-button--primary"
					id="cb-core-save-branding"
				>
					<?php esc_html_e( 'Save report settings', 'core-blueprint' ); ?>
				</button>
				<button
					type="button"
					class="button cb-core-button cb-core-button--secondary"
					id="cb-core-reset-branding"
				>
					<?php esc_html_e( 'Reset to defaults', 'core-blueprint' ); ?>
				</button>
				<?php echo \CB\Core\UI\FormStatus::render( [ 'target' => 'branding' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>
	</form>

</div>
