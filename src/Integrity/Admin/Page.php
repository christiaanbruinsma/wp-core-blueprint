<?php
declare(strict_types=1);
/**
 * Core Scanner - panel renderer for the Safeguards tab.
 *
 * Called from {@see \CB\Core\Admin\Pages\Safeguards::render_core_scanner_tab()}
 * with the current page wrap and tab-nav already in place. This class
 * renders only the inner panel content - header chrome (h1, intro,
 * tabnav) is owned by Safeguards.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Integrity\Admin;

use CB\Core\Integrity\Quarantine\Repository as QuarantineRepository;
use CB\Core\Integrity\Quarantine\Service as QuarantineService;
use CB\Core\Integrity\State;
use CB\Core\Integrity\Support\ResultFormatter;
use CB\Core\Integrity\Storage\BaselineReviewRepository;
use CB\Core\Integrity\Storage\ResultRepository;
use CB\Core\UI\Icon;
use CB\Core\UI\FormStatus;
use CB\Core\UI\Notice;
use CB\Core\UI\StateBadge;
use CB\Core\UI\Status;

use function checked;
use function count;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html__;
use function get_locale;
use function is_array;
use function number_format_i18n;
use function selected;
use function sprintf;
use function strtoupper;
use function ucfirst;

defined( 'ABSPATH' ) || exit;

final class Page {

	/**
	 * Render the Core Scanner panel inside the active Safeguards tab.
	 * Capability is enforced upstream by Safeguards via the cb_view_*
	 * pattern; manage actions inside the panel are gated by the REST
	 * permission_callback (cb_manage_integrity).
	 *
	 * State-based UI (1.3.13-dev): the panel renders one of three
	 * states based on what's happening on the server:
	 *
	 *   - idle      → no active scan, no recent result (or cleared).
	 *                 Shows the Run-CTA, scan history, locale, settings.
	 *                 Hides findings, components, verified-checks, diff.
	 *   - scanning  → an active scan is running (progress transient
	 *                 has status pending/running). Shows the sticky
	 *                 progress block + dimmed underlying content.
	 *   - result    → a stored scan result is present. Shows the full
	 *                 analytical view: summary tiles, components,
	 *                 findings, verified, diff (if has-changes), etc.
	 *
	 * Underlying always-visible blocks (history, distribution-locale,
	 * settings) render in all three states - they are operator-context
	 * regardless of scan state.
	 */
    public static function render_panel(): void {
        $state             = self::resolve_page_state();
        $view              = self::resolve_scanner_view();
        $latest            = ResultRepository::getLatest();
        $settings          = ResultRepository::settings();
        $history           = ResultFormatter::history_summary();
        $current_scan      = is_array( $latest ) ? (string) ( $latest['timestamp'] ?? '' ) : '';
        $baseline_meta     = ResultFormatter::summary( $latest );
        $baseline          = is_array( $baseline_meta['baseline'] ?? null ) ? $baseline_meta['baseline'] : [ 'exists' => false ];
        $is_enabled        = State::is_enabled();
        $can_manage_policy = self::can_manage_policy();
        $finding_total     = is_array( $latest ) ? count( ResultFormatter::review_findings( $latest ) ) : 0;
        $quarantine_open   = QuarantineRepository::open_count();
        ?>
        <div class="cb-core-integrity-wrap cb-core-integrity-state-<?php echo esc_attr( $state ); ?>" data-cb-integrity-state="<?php echo esc_attr( $state ); ?>" data-cb-integrity-view="<?php echo esc_attr( $view ); ?>" data-cb-integrity-enabled="<?php echo $is_enabled ? 'true' : 'false'; ?>" data-cb-integrity-policy-access="<?php echo $can_manage_policy ? 'true' : 'false'; ?>">
            <?php self::render_scanner_nav( $view, $finding_total, $quarantine_open ); ?>

            <?php if ( ! $is_enabled ) : ?>
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notice::render() returns escape-clean HTML.
                echo Notice::render( [
                    'variant' => Notice::INFO,
                    'title'   => __( 'Core Scanner is disabled.', 'core-blueprint' ),
                    'message' => $can_manage_policy
                        ? __( 'Scheduled scans will not run; manual scan is unavailable. Existing scan history, findings, quarantine records, and approved baselines remain available.', 'core-blueprint' )
                        : __( 'Scheduled and manual scans are unavailable while the scanner is disabled. Existing evidence remains available for review; a CB Operator can re-enable the scanner.', 'core-blueprint' ),
                ] );
                ?>
            <?php endif; ?>

            <?php
            switch ( $view ) {
                case 'findings':
                    if ( 'scanning' === $state ) {
                        self::render_scanning_state( $settings );
                    } elseif ( is_array( $latest ) ) {
                        self::render_findings_state( $latest, $settings );
                    } else {
                        self::render_findings_empty_state();
                    }
                    break;
                case 'quarantine':
                    self::render_quarantine_workspace( $can_manage_policy );
                    break;
                case 'history':
                    self::render_history_panel( (array) ( $history['items'] ?? [] ), $current_scan );
                    break;
                case 'settings':
                    self::render_distribution_locale_panel( $settings, $can_manage_policy );
                    self::render_settings_panel( $settings, $can_manage_policy );
                    break;
                default:
                    switch ( $state ) {
                        case 'scanning':
                            self::render_scanning_state( $settings );
                            break;
                        case 'result':
                            self::render_result_state( $latest, $settings, $baseline, $is_enabled );
                            break;
                        default:
                            self::render_idle_state( $baseline, $is_enabled );
                    }
            }
            ?>
        </div>
        <?php
    }

    private static function resolve_scanner_view(): string {
        $view = isset( $_GET['cb_integrity_view'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection.
            ? sanitize_key( wp_unslash( $_GET['cb_integrity_view'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            : 'overview';
        return in_array( $view, [ 'overview', 'findings', 'quarantine', 'history', 'settings' ], true ) ? $view : 'overview';
    }

    private static function scanner_view_url( string $view, array $extra = [] ): string {
        return add_query_arg(
            array_merge(
                [
                    'page'              => 'core-blueprint-safeguards',
                    'tab'               => 'core-scanner',
                    'cb_integrity_view' => $view,
                ],
                $extra
            ),
            admin_url( 'admin.php' )
        );
    }

    private static function render_scanner_nav( string $view, int $finding_total, int $quarantine_open ): void {
        $items = [
            'overview'   => [ __( 'Overview', 'core-blueprint' ), null ],
            'findings'   => [ __( 'Findings', 'core-blueprint' ), $finding_total ],
            'quarantine' => [ __( 'Quarantine', 'core-blueprint' ), $quarantine_open ],
            'history'    => [ __( 'History', 'core-blueprint' ), null ],
            'settings'   => [ __( 'Settings', 'core-blueprint' ), null ],
        ];
        ?>
        <nav class="cb-core-integrity-local-nav" aria-label="<?php echo esc_attr__( 'Core Scanner workspace', 'core-blueprint' ); ?>">
            <?php foreach ( $items as $key => [ $label, $count ] ) : ?>
                <a class="cb-core-integrity-local-nav__item<?php echo $view === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::scanner_view_url( $key ) ); ?>" <?php echo $view === $key ? 'aria-current="page"' : ''; ?>>
                    <span><?php echo esc_html( $label ); ?></span>
                    <?php if ( null !== $count ) : ?><strong><?php echo esc_html( (string) $count ); ?></strong><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }


    private static function render_findings_empty_state(): void {
        ?>
        <section class="cb-core-integrity-panel cb-core-integrity-empty-state">
            <h2 class="cb-core-section-title"><?php echo esc_html__( 'No scan findings yet', 'core-blueprint' ); ?></h2>
            <p><?php echo esc_html__( 'Run Core Scanner from Overview first. Findings that require human review will appear here.', 'core-blueprint' ); ?></p>
        </section>
        <?php
    }

    private static function render_history_panel( array $items, string $current_scan ): void {
        ?>
        <section class="cb-core-integrity-history-section">
            <div class="cb-core-integrity-panel-head">
                <div>
                    <h2 class="cb-core-section-title"><?php echo esc_html__( 'Scan History', 'core-blueprint' ); ?></h2>
                    <p id="cb-core-integrity-history-meta"><?php echo esc_html( sprintf( __( 'Showing the latest %d stored scans.', 'core-blueprint' ), count( $items ) ) ); ?></p>
                </div>
            </div>
            <div class="cb-core-integrity-history" id="cb-core-integrity-history"><?php self::render_history( $items, $current_scan ); ?></div>
        </section>
        <?php
    }

    private static function render_settings_panel( array $settings, bool $can_manage_policy ): void {
        ?>
        <section class="cb-core-integrity-settings-section cb-core-integrity-settings-panel">
            <div class="cb-core-integrity-panel-head">
                <div>
                    <h2 class="cb-core-section-title"><?php echo esc_html__( 'Scanner settings', 'core-blueprint' ); ?></h2>
                    <p><?php echo esc_html( $can_manage_policy ? __( 'These settings are shared by manual, scheduled, and future Hub-triggered scans.', 'core-blueprint' ) : __( 'These settings define scanner scope and are read-only here because only a CB Operator may change scanner policy.', 'core-blueprint' ) ); ?></p>
                </div>
            </div>
            <table class="form-table cb-core-integrity-settings-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="cb-core-integrity-schedule"><?php echo esc_html__( 'Schedule', 'core-blueprint' ); ?></label></th>
                        <td><select id="cb-core-integrity-schedule" <?php disabled( ! $can_manage_policy ); ?>><option value="disabled" <?php selected( $settings['schedule'], 'disabled' ); ?>><?php echo esc_html__( 'Disabled', 'core-blueprint' ); ?></option><option value="daily" <?php selected( $settings['schedule'], 'daily' ); ?>><?php echo esc_html__( 'Daily', 'core-blueprint' ); ?></option><option value="weekly" <?php selected( $settings['schedule'], 'weekly' ); ?>><?php echo esc_html__( 'Weekly', 'core-blueprint' ); ?></option></select></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Plugins', 'core-blueprint' ); ?></th>
                        <td><label><input type="checkbox" id="cb-core-integrity-plugin-checksums" <?php checked( $settings['plugin_checksums'] ); ?> <?php disabled( ! $can_manage_policy ); ?> /><span><?php echo esc_html__( 'Verify WordPress.org plugin checksums where available', 'core-blueprint' ); ?></span></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Themes', 'core-blueprint' ); ?></th>
                        <td><label><input type="checkbox" id="cb-core-integrity-theme-checksums" <?php checked( $settings['theme_checksums'] ); ?> <?php disabled( ! $can_manage_policy ); ?> /><span><?php echo esc_html__( 'Verify WordPress.org theme checksums where available', 'core-blueprint' ); ?></span></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Uploads', 'core-blueprint' ); ?></th>
                        <td><label><input type="checkbox" id="cb-core-integrity-uploads-scan" <?php checked( $settings['uploads_scan'] ); ?> <?php disabled( ! $can_manage_policy ); ?> /><span><?php echo esc_html__( 'Scan uploads for executable files', 'core-blueprint' ); ?></span></label></td>
                    </tr>
                </tbody>
            </table>
            <?php if ( $can_manage_policy ) : ?>
                <div class="cb-core-integrity-settings-actions">
                    <button type="button" class="button cb-core-button cb-core-button--primary" id="cb-core-integrity-save-settings" data-cb-integrity-action="save-settings"><?php echo esc_html__( 'Save Settings', 'core-blueprint' ); ?></button>
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FormStatus::render() returns escape-clean HTML.
                    echo FormStatus::render( [ 'id' => 'cb-core-integrity-settings-status', 'tight' => true ] );
                    ?>
                </div>
            <?php else : ?>
                <?php
                // Keep the canonical live region present for JS/runtime symmetry, even on read-only views.
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FormStatus::render() returns escape-clean HTML.
                echo FormStatus::render( [ 'id' => 'cb-core-integrity-settings-status', 'block' => true ] );
                ?>
            <?php endif; ?>
        </section>
        <?php
    }

	/**
	 * Determine which page-state to render.
	 *
	 *   - 'scanning' if a persisted resumable scan job owns the scan lock
	 *   - 'result'   if a latest scan result exists
	 *   - 'idle'     otherwise (no active scan, no stored result)
	 *
	 * Server-side resolution per CP-9: avoids JS state-switching
	 * over hidden DOM, gives screen readers a clean tree, and keeps
	 * sticky positioning predictable.
	 */
	private static function resolve_page_state(): string {
		$active = \CB\Core\Integrity\Scanner\ScanJobStatus::active_job();
		if ( null !== $active ) {
			return 'scanning';
		}

		$latest = ResultRepository::getLatest();
		if ( is_array( $latest ) ) {
			return 'result';
		}

		return 'idle';
	}

	/**
	 * Render the idle-state UI: focus on the run-CTA.
	 *
	 * Hidden in this state: findings, components, verified-checks,
	 * diff panel, summary tiles. Operator should not be confronted
	 * with empty placeholder blocks ("No previous scan available
	 * for comparison yet") just to be told something is missing.
	 */
	private static function render_idle_state( array $baseline, bool $is_enabled = true ): void {
		?>
		<div class="cb-core-integrity-actions" aria-label="<?php echo esc_attr__( 'Core Scanner actions', 'core-blueprint' ); ?>">
			<?php if ( $is_enabled ) : ?>
				<button type="button" class="button cb-core-button cb-core-button--primary cb-core-integrity-primary-action" id="cb-core-integrity-run-scan" data-cb-integrity-action="run-scan">
					<?php echo esc_html__( 'Run Core Scanner', 'core-blueprint' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( self::can_manage_policy() && ! empty( $baseline['exists'] ) ) : ?>
				<button type="button" class="button cb-core-button cb-core-button--secondary" id="cb-core-integrity-clear-baseline" data-cb-integrity-action="clear-baseline">
					<?php echo esc_html__( 'Clear Approved Baseline', 'core-blueprint' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<section class="cb-core-integrity-panel cb-core-integrity-empty-state">
			<h2 class="cb-core-section-title"><?php echo esc_html__( 'No active scan result', 'core-blueprint' ); ?></h2>
			<p><?php echo esc_html__( 'Run a scan to analyze the current state of this site. Existing scan history and approved baselines are kept separately.', 'core-blueprint' ); ?></p>
		</section>

		<details class="cb-core-integrity-about-scan cb-core-disclosure cb-core-disclosure--section cb-core-disclosure--subtle">
			<summary class="cb-core-disclosure__summary">
				<?php echo Icon::render( 'expand', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-disclosure__icon' ] ); ?>
				<h2 class="cb-core-disclosure__title"><?php echo esc_html__( 'About this scan', 'core-blueprint' ); ?></h2>
			</summary>
			<div class="cb-core-disclosure__body">
				<?php self::render_about_scan_content(); ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Render the scanning-state UI.
	 *
	 * The progress block is server-rendered as the initial frame
	 * (0% on bar, "Starting…" phase label, 0:00 timer). JS takes
	 * over once it polls the transient - by then this server-side
	 * markup already gave the operator immediate visual feedback.
	 */
	private static function render_scanning_state( array $settings ): void {
		?>
		<section class="cb-core-integrity-panel cb-core-integrity-progress-panel" id="cb-core-integrity-progress" aria-label="<?php echo esc_attr__( 'Scan progress', 'core-blueprint' ); ?>" aria-live="polite">
			<div class="cb-core-integrity-progress-head">
				<h2 class="cb-core-integrity-progress-title">
					<span class="cb-core-spinner is-active" aria-hidden="true"><span class="cb-core-spinner__ring"></span></span>
					<?php echo esc_html__( 'Running Core Scanner', 'core-blueprint' ); ?>
				</h2>
				<span class="cb-core-integrity-progress-timer" id="cb-core-integrity-progress-timer" aria-label="<?php echo esc_attr__( 'Elapsed time', 'core-blueprint' ); ?>">0:00.0</span>
			</div>
			<div class="cb-core-integrity-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
				<div class="cb-core-integrity-progress-bar-fill" id="cb-core-integrity-progress-fill" style="width: 0%"></div>
			</div>
			<p class="cb-core-integrity-progress-phase" id="cb-core-integrity-progress-phase">
				<?php echo esc_html__( 'Starting…', 'core-blueprint' ); ?>
			</p>
		</section>
		<?php
	}

	/**
	 * Render the result-state UI: summary tiles, components,
	 * findings, verified (collapsed), diff (only if has-changes).
	 */
	private static function render_result_state( array $latest, array $settings, array $baseline, bool $is_enabled = true ): void {
		$summary    = ResultFormatter::summary( $latest );
		$baseline_eligibility = ResultRepository::baselineApprovalEligibility( $latest );
		$baseline_candidates  = (int) ( $baseline_eligibility['candidates'] ?? 0 );
		$incident_lifecycle = is_array( $latest['incident_lifecycle'] ?? null ) ? $latest['incident_lifecycle'] : [];
		$critical   = (int) ( $summary['summary']['critical'] ?? 0 );
		$warning    = (int) ( $summary['summary']['warning'] ?? 0 );
		$ok         = (int) ( $summary['summary']['ok'] ?? 0 );
		$review_count = count( ResultFormatter::review_findings( $latest ) );
		$status     = (string) ( $summary['status'] ?? 'idle' );
		$completion = (string) ( $summary['completion'] ?? 'incomplete' );
		$coverage   = is_array( $summary['coverage'] ?? null ) ? $summary['coverage'] : [];
		$status_label = 'ok' === $status
			? ( 'complete' === $completion ? __( 'No anomalies detected', 'core-blueprint' ) : __( 'No anomalies detected in checked scope', 'core-blueprint' ) )
			: __( 'Review needed', 'core-blueprint' );
		$status_variant = match ( $status ) {
			'ok'                => 'active',
			'warning', 'notice' => 'warning',
			'critical', 'failed', 'error' => 'error',
			default             => 'idle',
		};
		$last_scan  = (string) ( $summary['last_scan'] ?? '' );
		$duration   = (float) ( $latest['duration_seconds'] ?? 0 );
		$anomaly    = is_array( $latest['anomaly'] ?? null ) ? $latest['anomaly'] : null;

		$lifecycle_counts = is_array( $incident_lifecycle['counts'] ?? null ) ? $incident_lifecycle['counts'] : [];
		$has_changes = ! empty( $incident_lifecycle['has_previous'] ) && (
			(int) ( $lifecycle_counts['new'] ?? 0 ) > 0 ||
			(int) ( $lifecycle_counts['changed'] ?? 0 ) > 0 ||
			(int) ( $lifecycle_counts['resolved'] ?? 0 ) > 0 ||
			(int) ( $lifecycle_counts['unconfirmed'] ?? 0 ) > 0
		);
		?>
		<div class="cb-core-integrity-actions" aria-label="<?php echo esc_attr__( 'Core Scanner actions', 'core-blueprint' ); ?>">
			<?php if ( $is_enabled ) : ?>
				<button type="button" class="button cb-core-button cb-core-button--primary cb-core-integrity-primary-action" id="cb-core-integrity-run-scan" data-cb-integrity-action="run-scan">
					<?php echo esc_html__( 'Run Core Scanner', 'core-blueprint' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $is_enabled && self::can_manage_policy() && $baseline_candidates > 0 ) : ?>
				<a class="button cb-core-button cb-core-button--secondary cb-core-integrity-secondary-action" href="<?php echo esc_url( self::scanner_view_url( 'findings', [ 'cb_integrity_baseline_candidate' => '1' ] ) ); ?>">
					<?php echo esc_html( sprintf( _n( 'Review %d Local Baseline', 'Review %d Local Baselines', $baseline_candidates, 'core-blueprint' ), $baseline_candidates ) ); ?>
				</a>
			<?php endif; ?>
			<?php if ( self::can_manage_policy() ) : ?>
				<details class="cb-core-integrity-maintenance-actions">
					<summary class="button"><?php echo esc_html__( 'Maintenance', 'core-blueprint' ); ?></summary>
					<div class="cb-core-integrity-maintenance-actions__menu">
						<?php if ( ! empty( $baseline['exists'] ) ) : ?>
							<button type="button" class="button cb-core-button cb-core-button--secondary" id="cb-core-integrity-clear-baseline" data-cb-integrity-action="clear-baseline">
								<?php echo esc_html__( 'Clear Approved Baseline', 'core-blueprint' ); ?>
							</button>
						<?php endif; ?>
						<button type="button" class="button cb-core-button cb-core-button--danger" id="cb-core-integrity-clear-results" data-cb-integrity-action="clear-results">
							<?php echo esc_html__( 'Clear Results', 'core-blueprint' ); ?>
						</button>
					</div>
				</details>
			<?php endif; ?>
		</div>

		<section class="cb-core-integrity-overview-panel cb-core-integrity-overview-panel-<?php echo esc_attr( $status ); ?>" aria-live="polite">
			<div class="cb-core-integrity-overview-main">
				<span class="cb-core-integrity-label"><?php echo esc_html__( 'Current scan state', 'core-blueprint' ); ?></span>
				<span id="cb-core-integrity-status" class="cb-core-integrity-current-status"><?php echo Status::render( $status_variant, $status_label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Status::render() returns escape-clean HTML. ?></span>
				<span id="cb-core-integrity-last-scan" class="cb-core-integrity-muted">
					<?php
					echo esc_html( $last_scan );
					if ( $duration > 0 ) {
						echo ' · ' . esc_html( sprintf( __( 'Completed in %ss', 'core-blueprint' ), number_format_i18n( $duration, 1 ) ) );
					}
					?>
				</span>
				<?php if ( $review_count > 0 ) : ?>
					<a class="button cb-core-button cb-core-button--secondary cb-core-integrity-review-findings" href="<?php echo esc_url( self::scanner_view_url( 'findings' ) ); ?>">
						<?php echo esc_html( sprintf( _n( 'Review %d finding', 'Review %d findings', $review_count, 'core-blueprint' ), $review_count ) ); ?>
					</a>
				<?php endif; ?>
			</div>
			<div class="cb-core-integrity-overview-metrics">
				<div><span><?php echo esc_html__( 'Critical', 'core-blueprint' ); ?></span><strong id="cb-core-integrity-count-critical"><?php echo esc_html( (string) $critical ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Warnings', 'core-blueprint' ); ?></span><strong id="cb-core-integrity-count-warning"><?php echo esc_html( (string) $warning ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Verified', 'core-blueprint' ); ?></span><strong id="cb-core-integrity-count-ok"><?php echo esc_html( (string) $ok ); ?></strong></div>
				<div><span><?php echo esc_html__( 'Coverage', 'core-blueprint' ); ?></span><strong><?php echo esc_html( ucfirst( $completion ) ); ?></strong></div>
				<div class="cb-core-integrity-baseline-metric"><span><?php echo esc_html__( 'Baseline', 'core-blueprint' ); ?></span><strong id="cb-core-integrity-baseline-status"><?php echo ! empty( $baseline['exists'] ) ? esc_html__( 'Approved', 'core-blueprint' ) : esc_html__( 'None', 'core-blueprint' ); ?></strong><small id="cb-core-integrity-baseline-meta"><?php echo ! empty( $baseline['exists'] ) ? esc_html( sprintf( __( '%1$d entries, created %2$s', 'core-blueprint' ), (int) $baseline['entry_count'], (string) $baseline['created_at'] ) ) : esc_html__( 'No approved local baseline yet', 'core-blueprint' ); ?></small></div>
			</div>
		</section>

		<?php self::render_coverage_panel( $coverage ); ?>

		<?php if ( null !== $anomaly && 'slower' === ( $anomaly['type'] ?? '' ) ) : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notice::render() returns escape-clean HTML.
			echo Notice::render( [
				'variant' => Notice::WARNING,
				'title'   => __( 'This scan took longer than usual', 'core-blueprint' ),
				'message' => sprintf(
					/* translators: 1: previous scan duration, 2: current scan duration, 3: ratio */
					__( 'Previous scan: %1$ss · this scan: %2$ss · %3$s× slower', 'core-blueprint' ),
					number_format_i18n( (float) $anomaly['previous_seconds'], 1 ),
					number_format_i18n( (float) $anomaly['current_seconds'], 1 ),
					number_format_i18n( (float) $anomaly['ratio'], 1 )
				),
			] );
			?>
		<?php endif; ?>

		<?php if ( $has_changes ) : ?>
			<section class="cb-core-integrity-panel cb-core-integrity-diff-panel">
				<div class="cb-core-integrity-panel-head">
					<div>
						<h2 class="cb-core-section-title"><?php echo esc_html__( 'Anomaly Changes Since Last Scan', 'core-blueprint' ); ?></h2>
						<p id="cb-core-integrity-diff-meta"><?php echo esc_html__( 'This compares detected anomalies between completed scans. A resolved anomaly is only confirmed when the current scan completed coverage for that area.', 'core-blueprint' ); ?></p>
					</div>
				</div>
				<div id="cb-core-integrity-diff">
					<?php self::render_incident_changes( $incident_lifecycle ); ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="cb-core-integrity-panel">
			<div class="cb-core-integrity-panel-head">
				<h2 class="cb-core-section-title"><?php echo esc_html__( 'Components', 'core-blueprint' ); ?></h2>
				<p class="cb-core-integrity-muted"><?php echo esc_html__( 'Open a component directly in the Findings workspace.', 'core-blueprint' ); ?></p>
			</div>
			<div class="cb-core-integrity-component-grid" id="cb-core-integrity-components">
				<?php foreach ( (array) $summary['components'] as $component => $state_value ) : ?>
					<?php
					$component_key = sanitize_key( (string) $component );
					$component_url = self::scanner_view_url( 'findings', [ 'cb_integrity_component' => $component_key ] );
					$is_active_component = false;
					?>
					<a href="<?php echo esc_url( $component_url ); ?>"
						class="cb-core-integrity-component cb-core-integrity-component-filter<?php echo $is_active_component ? ' cb-core-integrity-component-filter-active' : ''; ?>"
						data-cb-integrity-component-filter="<?php echo esc_attr( $component_key ); ?>"
						<?php echo $is_active_component ? 'aria-current="page"' : ''; ?>>
						<span><?php echo esc_html( ucfirst( (string) $component ) ); ?></span>
						<?php echo StateBadge::render( (string) $state_value, [ 'variant' => self::state_badge_variant( (string) $state_value ) ] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<details class="cb-core-integrity-about-scan cb-core-disclosure cb-core-disclosure--section cb-core-disclosure--subtle">
			<summary class="cb-core-disclosure__summary">
				<?php echo Icon::render( 'expand', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-disclosure__icon' ] ); ?>
				<h2 class="cb-core-disclosure__title"><?php echo esc_html__( 'About this scan', 'core-blueprint' ); ?></h2>
			</summary>
			<div class="cb-core-disclosure__body">
				<?php self::render_about_scan_content(); ?>
			</div>
		</details>

		<?php
	}

	private static function render_findings_state( array $latest, array $settings ): void {
		$batch = max( 10, (int) ( $settings['max_visible_findings'] ?? 50 ) );
		$limit = isset( $_GET['cb_integrity_findings'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
			? max( $batch, absint( wp_unslash( $_GET['cb_integrity_findings'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: $batch;
		$component = isset( $_GET['cb_integrity_component'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
			? sanitize_key( wp_unslash( $_GET['cb_integrity_component'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
		if ( ! in_array( $component, [ '', 'core', 'plugins', 'themes', 'uploads' ], true ) ) {
			$component = '';
		}
		$severity = isset( $_GET['cb_integrity_severity'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
			? sanitize_key( wp_unslash( $_GET['cb_integrity_severity'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
		if ( ! in_array( $severity, [ '', 'critical', 'warning' ], true ) ) {
			$severity = '';
		}
		$status = isset( $_GET['cb_integrity_status'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
			? sanitize_key( wp_unslash( $_GET['cb_integrity_status'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
		$search = isset( $_GET['cb_integrity_search'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
			? sanitize_text_field( wp_unslash( $_GET['cb_integrity_search'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
		$actionable_only = isset( $_GET['cb_integrity_actionable'] ) && '1' === (string) wp_unslash( $_GET['cb_integrity_actionable'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$baseline_only = isset( $_GET['cb_integrity_baseline_candidate'] ) && '1' === (string) wp_unslash( $_GET['cb_integrity_baseline_candidate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$baseline_eligibility = ResultRepository::baselineApprovalEligibility( $latest );
		$baseline_candidates = (int) ( $baseline_eligibility['candidates'] ?? 0 );
		$baseline_review = BaselineReviewRepository::progress( $latest );
		$reviewed_candidate_ids = array_fill_keys( BaselineReviewRepository::reviewedIds( $latest ), true );
		$baseline_meta = ResultFormatter::summary( $latest );
		$baseline = is_array( $baseline_meta['baseline'] ?? null ) ? $baseline_meta['baseline'] : [ 'exists' => false ];

		$all_findings = ResultFormatter::review_findings( $latest );
		$status_options = [];
		$filtered = [];
		foreach ( $all_findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$finding_status = sanitize_key( (string) ( $finding['status'] ?? $finding['type'] ?? 'notice' ) );
			$status_options[ $finding_status ] = true;
			$finding_component = sanitize_key( (string) ( $finding['type'] ?? '' ) );
			$finding_component = match ( $finding_component ) {
				'plugin' => 'plugins',
				'theme'  => 'themes',
				default  => $finding_component,
			};
			if ( '' !== $component && $component !== $finding_component ) {
				continue;
			}
			if ( '' !== $severity && $severity !== sanitize_key( (string) ( $finding['severity'] ?? '' ) ) ) {
				continue;
			}
			if ( '' !== $status && $status !== $finding_status ) {
				continue;
			}
			if ( $actionable_only && ! QuarantineService::can_quarantine_finding( $finding ) ) {
				continue;
			}
			if ( $baseline_only && ! ResultRepository::isBaselineCandidateCheck( $finding ) ) {
				continue;
			}
			if ( '' !== $search ) {
				$target = is_array( $finding['target'] ?? null ) ? $finding['target'] : [];
				$haystack = strtolower( implode( ' ', [
					self::finding_target_path( $finding ),
					(string) ( $target['file'] ?? '' ),
					(string) ( $target['slug'] ?? '' ),
					(string) ( $target['label'] ?? '' ),
					(string) ( $finding['message'] ?? '' ),
				] ) );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}
			$filtered[] = $finding;
		}
		ksort( $status_options );
		$total = count( $filtered );
		$visible = array_slice( $filtered, 0, $limit );
		$groups = ResultFormatter::group_review_findings( $visible );
		$passed = ResultFormatter::grouped_passed( $latest, 500 );
		$scan_summary = ResultFormatter::summary( $latest );
		$passed_count = (int) ( $scan_summary['summary']['ok'] ?? 0 );
		$has_more = count( $visible ) < $total;
		$next_limit = $has_more ? min( $total, max( $limit + $batch, $limit * 2 ) ) : $limit;
		$more_args = [ 'cb_integrity_findings' => $next_limit ];
		foreach ( [ 'cb_integrity_component' => $component, 'cb_integrity_severity' => $severity, 'cb_integrity_status' => $status, 'cb_integrity_search' => $search ] as $key => $value ) {
			if ( '' !== $value ) {
				$more_args[ $key ] = $value;
			}
		}
		if ( $actionable_only ) {
			$more_args['cb_integrity_actionable'] = '1';
		}
		if ( $baseline_only ) {
			$more_args['cb_integrity_baseline_candidate'] = '1';
		}
		?>
		<section class="cb-core-integrity-panel cb-core-integrity-findings-workspace">
			<div class="cb-core-integrity-panel-head">
				<div>
					<h2 class="cb-core-section-title"><?php echo esc_html__( 'Findings', 'core-blueprint' ); ?></h2>
					<p class="cb-core-integrity-muted"><?php echo esc_html( $baseline_only ? __( 'Review every local-baseline candidate before trusting its current file state. Approval changes what future scans consider expected.', 'core-blueprint' ) : __( 'Review anomalies, narrow the investigation, and isolate actionable uploads without losing context.', 'core-blueprint' ) ); ?></p>
				</div>
				<div class="cb-core-integrity-findings-head-actions">
					<?php echo StateBadge::render( sprintf( _n( '%d finding', '%d findings', $total, 'core-blueprint' ), $total ), [ 'variant' => $total > 0 ? StateBadge::WARNING : StateBadge::SUCCESS ] ); ?>
					<?php if ( $baseline_only && $baseline_candidates > 0 ) : ?>
						<span class="cb-core-integrity-review-progress"><?php echo esc_html( sprintf( __( 'Reviewed %1$d of %2$d', 'core-blueprint' ), (int) $baseline_review['reviewed'], (int) $baseline_review['total'] ) ); ?></span>
					<?php endif; ?>
					<?php if ( $baseline_only && State::is_enabled() && self::can_manage_policy() && $baseline_candidates > 0 ) : ?>
						<button type="button" class="button cb-core-button cb-core-button--primary cb-core-integrity-secondary-action" id="cb-core-integrity-approve-baseline" data-cb-integrity-action="approve-baseline" data-cb-integrity-baseline-candidates="<?php echo esc_attr( (string) $baseline_candidates ); ?>" <?php disabled( empty( $baseline_review['complete'] ) ); ?> title="<?php echo esc_attr( empty( $baseline_review['complete'] ) ? __( 'Review every baseline candidate before bulk approval.', 'core-blueprint' ) : '' ); ?>">
							<?php
							echo esc_html( sprintf(
								! empty( $baseline['exists'] )
									? _n( 'Update %d Local Baseline', 'Update %d Local Baselines', $baseline_candidates, 'core-blueprint' )
									: _n( 'Approve %d Local Baseline', 'Approve %d Local Baselines', $baseline_candidates, 'core-blueprint' ),
								$baseline_candidates
							) );
							?>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<form class="cb-core-toolbar cb-core-toolbar--compact cb-core-integrity-findings-toolbar" method="get">
				<input type="hidden" name="page" value="core-blueprint-safeguards" />
				<input type="hidden" name="tab" value="core-scanner" />
				<input type="hidden" name="cb_integrity_view" value="findings" />
				<label class="cb-core-toolbar__field cb-core-toolbar__field--grow cb-core-integrity-search-field"><span class="screen-reader-text"><?php echo esc_html__( 'Search findings', 'core-blueprint' ); ?></span><input type="search" name="cb_integrity_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search path, component or message…', 'core-blueprint' ); ?>" /></label>
				<label class="cb-core-toolbar__field"><span class="screen-reader-text"><?php echo esc_html__( 'Component', 'core-blueprint' ); ?></span><select name="cb_integrity_component"><option value=""><?php echo esc_html__( 'All components', 'core-blueprint' ); ?></option><?php foreach ( [ 'core' => __( 'Core', 'core-blueprint' ), 'plugins' => __( 'Plugins', 'core-blueprint' ), 'themes' => __( 'Themes', 'core-blueprint' ), 'uploads' => __( 'Uploads', 'core-blueprint' ) ] as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $component, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
				<label class="cb-core-toolbar__field"><span class="screen-reader-text"><?php echo esc_html__( 'Severity', 'core-blueprint' ); ?></span><select name="cb_integrity_severity"><option value=""><?php echo esc_html__( 'All severities', 'core-blueprint' ); ?></option><option value="critical" <?php selected( $severity, 'critical' ); ?>><?php echo esc_html__( 'Critical', 'core-blueprint' ); ?></option><option value="warning" <?php selected( $severity, 'warning' ); ?>><?php echo esc_html__( 'Warning', 'core-blueprint' ); ?></option></select></label>
				<label class="cb-core-toolbar__field"><span class="screen-reader-text"><?php echo esc_html__( 'Status', 'core-blueprint' ); ?></span><select name="cb_integrity_status"><option value=""><?php echo esc_html__( 'All statuses', 'core-blueprint' ); ?></option><?php foreach ( array_keys( $status_options ) as $key ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( self::finding_status_label( $key ) ); ?></option><?php endforeach; ?></select></label>
				<label class="cb-core-toolbar__toggle"><input type="checkbox" name="cb_integrity_actionable" value="1" <?php checked( $actionable_only ); ?> /><span><?php echo esc_html__( 'Actionable only', 'core-blueprint' ); ?></span></label>
				<label class="cb-core-toolbar__toggle"><input type="checkbox" name="cb_integrity_baseline_candidate" value="1" <?php checked( $baseline_only ); ?> /><span><?php echo esc_html__( 'Baseline candidates only', 'core-blueprint' ); ?></span></label>
				<div class="cb-core-toolbar__actions cb-core-toolbar__actions--inline">
					<div class="cb-core-toolbar__actions-row">
						<a class="cb-core-integrity-reset-filters" href="<?php echo esc_url( self::scanner_view_url( 'findings' ) ); ?>"><?php echo esc_html__( 'Reset filters', 'core-blueprint' ); ?></a>
						<button type="submit" class="button cb-core-button cb-core-button--primary cb-core-button--compact"><?php echo esc_html__( 'Apply filters', 'core-blueprint' ); ?></button>
					</div>
				</div>
			</form>

			<p class="cb-core-integrity-findings-meta"><?php echo esc_html( sprintf( __( 'Showing %1$d of %2$d matching findings. %3$d findings exist in the current scan.', 'core-blueprint' ), count( $visible ), $total, count( $all_findings ) ) ); ?></p>
			<div class="cb-core-integrity-findings" id="cb-core-integrity-findings"><?php self::render_grouped_findings( $groups, false, $reviewed_candidate_ids ); ?></div>
			<?php if ( $has_more ) : ?>
				<p class="cb-core-integrity-findings-more"><a class="button cb-core-button cb-core-button--secondary" href="<?php echo esc_url( self::scanner_view_url( 'findings', $more_args ) ); ?>"><?php echo esc_html( sprintf( __( 'Show more findings (up to %d)', 'core-blueprint' ), $next_limit ) ); ?></a><span class="cb-core-integrity-muted"><?php echo esc_html__( 'Filtering is applied before pagination, so search always covers the complete stored finding set.', 'core-blueprint' ); ?></span></p>
			<?php endif; ?>
		</section>

		<details class="cb-core-integrity-verified-panel cb-core-disclosure cb-core-disclosure--section">
			<summary class="cb-core-disclosure__summary" aria-controls="cb-core-integrity-passed">
				<?php echo Icon::render( 'expand', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-disclosure__icon' ] ); ?>
				<h2 class="cb-core-disclosure__title"><?php echo esc_html__( 'Verified / Passed Checks', 'core-blueprint' ); ?></h2>
				<span class="cb-core-disclosure__meta cb-core-integrity-group-count" aria-label="<?php echo esc_attr( sprintf( _n( '%d passed check', '%d passed checks', $passed_count, 'core-blueprint' ), $passed_count ) ); ?>"><?php echo esc_html( (string) $passed_count ); ?></span>
			</summary>
			<div class="cb-core-disclosure__body">
				<div class="cb-core-integrity-findings cb-core-integrity-passed" id="cb-core-integrity-passed"><?php self::render_grouped_findings( $passed, true ); ?></div>
			</div>
		</details>
		<?php
	}

	/**
	 * Show what the scanner could and could not actually inspect. This is
	 * deliberately separate from finding severity: coverage describes
	 * confidence in the scan scope, not whether the site is "safe".
	 */
	private static function render_coverage_panel( array $coverage ): void {
		$state = (string) ( $coverage['state'] ?? 'incomplete' );
		$incomplete = is_array( $coverage['incomplete_components'] ?? null ) ? $coverage['incomplete_components'] : [];
		?>
		<section class="cb-core-integrity-panel cb-core-integrity-coverage-panel">
			<div class="cb-core-integrity-panel-head">
				<div>
					<h2 class="cb-core-section-title"><?php echo esc_html__( 'Scan coverage', 'core-blueprint' ); ?></h2>
					<p class="cb-core-integrity-muted">
						<?php echo 'complete' === $state
							? esc_html__( 'All enabled scan areas completed their configured checks. This does not guarantee that the site is secure; it means no part of the configured scan was silently skipped.', 'core-blueprint' )
							: esc_html__( 'Coverage is incomplete. Review the affected scan areas below before interpreting the findings; some files or components could not be verified.', 'core-blueprint' ); ?>
					</p>
				</div>
				<?php echo StateBadge::render( strtoupper( $state ), [ 'variant' => 'complete' === $state ? StateBadge::SUCCESS : StateBadge::WARNING ] ); ?>
			</div>
			<div class="cb-core-integrity-component-grid">
				<?php foreach ( [ 'core', 'plugins', 'themes', 'uploads' ] as $component ) : ?>
					<?php
					$component_coverage = is_array( $coverage[ $component ] ?? null ) ? $coverage[ $component ] : [];
					$component_state = (string) ( $component_coverage['state'] ?? 'unknown' );
					$checked = isset( $component_coverage['files_inspected'] )
						? (int) $component_coverage['files_inspected']
						: (int) ( $component_coverage['verified_files'] ?? 0 )
							+ (int) ( $component_coverage['modified_files'] ?? 0 )
							+ (int) ( $component_coverage['unexpected_files'] ?? 0 )
							+ (int) ( $component_coverage['local_baseline_files_checked'] ?? 0 )
							+ (int) ( $component_coverage['snapshot_files_inspected'] ?? 0 );
					$unexpected = (int) ( $component_coverage['unexpected_files'] ?? 0 );
					$missing = (int) ( $component_coverage['missing_files'] ?? 0 );
					$unreadable = (int) ( $component_coverage['unreadable_files'] ?? $component_coverage['unreadable'] ?? 0 );
					?>
					<?php $component_url = self::scanner_view_url( 'findings', [ 'cb_integrity_component' => $component ] ); ?>
					<a class="cb-core-integrity-component cb-core-integrity-component-filter" href="<?php echo esc_url( $component_url ); ?>">
						<span><?php echo esc_html( ucfirst( $component ) ); ?></span>
						<?php echo StateBadge::render( strtoupper( $component_state ), [ 'variant' => self::state_badge_variant( $component_state ) ] ); ?>
						<small class="cb-core-integrity-muted"><?php echo esc_html( sprintf( __( 'Checked %1$d · unexpected %2$d · missing %3$d · unreadable %4$d', 'core-blueprint' ), $checked, $unexpected, $missing, $unreadable ) ); ?></small>
					</a>
				<?php endforeach; ?>
			</div>
			<?php if ( ! empty( $incomplete ) ) : ?>
				<p class="cb-core-integrity-hint"><strong><?php echo esc_html__( 'Needs attention:', 'core-blueprint' ); ?></strong> <?php echo esc_html( implode( ', ', array_map( 'ucfirst', $incomplete ) ) ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * About-scan content fragment - used by both idle and result
	 * states inside the About-this-scan accordion.
	 */
	private static function render_about_scan_content(): void {
		?>
		<div class="cb-core-integrity-about-grid">
			<div>
				<h2 class="cb-core-section-title"><?php echo esc_html__( 'What this scan does', 'core-blueprint' ); ?></h2>
				<p><?php echo esc_html__( 'This scanner is carefully developed to provide a structured and reliable way to monitor the integrity of your website. It compares files against trusted sources such as WordPress.org, approved local baselines, and the current filesystem.', 'core-blueprint' ); ?></p>
				<ul>
					<li><?php echo esc_html__( 'Detects unexpected file changes.', 'core-blueprint' ); ?></li>
					<li><?php echo esc_html__( 'Identifies missing or newly added files.', 'core-blueprint' ); ?></li>
					<li><?php echo esc_html__( 'Compares files against official checksums where available.', 'core-blueprint' ); ?></li>
					<li><?php echo esc_html__( 'Compares files against an approved local baseline.', 'core-blueprint' ); ?></li>
				</ul>
			</div>
			<div>
				<h2 class="cb-core-section-title"><?php echo esc_html__( 'What this scan does not do', 'core-blueprint' ); ?></h2>
				<ul>
					<li><?php echo esc_html__( 'It does not guarantee that your website is fully secure.', 'core-blueprint' ); ?></li>
					<li><?php echo esc_html__( 'It does not detect all types of malware or vulnerabilities.', 'core-blueprint' ); ?></li>
					<li><?php echo esc_html__( 'It cannot verify all premium or custom plugins without a trusted external source.', 'core-blueprint' ); ?></li>
					<li><?php echo esc_html__( 'It does not replace a full security audit, monitoring solution, or firewall.', 'core-blueprint' ); ?></li>
				</ul>
				<p class="cb-core-integrity-muted"><?php echo esc_html__( 'This scan is a best-effort analysis. While carefully designed, it should always be used as part of a broader security and maintenance strategy.', 'core-blueprint' ); ?></p>
			</div>
		</div>
		<?php
	}

	private static function render_incident_changes( array $lifecycle ): void {
		$counts = is_array( $lifecycle['counts'] ?? null ) ? $lifecycle['counts'] : [];

		echo '<div class="cb-core-integrity-diff-summary">';
		echo '<span><strong>+' . esc_html( (string) ( $counts['new'] ?? 0 ) ) . '</strong> ' . esc_html__( 'new', 'core-blueprint' ) . '</span>';
		echo '<span><strong>~' . esc_html( (string) ( $counts['changed'] ?? 0 ) ) . '</strong> ' . esc_html__( 'changed', 'core-blueprint' ) . '</span>';
		echo '<span><strong>✓' . esc_html( (string) ( $counts['resolved'] ?? 0 ) ) . '</strong> ' . esc_html__( 'resolved', 'core-blueprint' ) . '</span>';
		echo '<span><strong>?' . esc_html( (string) ( $counts['unconfirmed'] ?? 0 ) ) . '</strong> ' . esc_html__( 'resolution unconfirmed', 'core-blueprint' ) . '</span>';
		echo '</div>';

		$groups = [
			'new'         => __( 'New anomalies', 'core-blueprint' ),
			'changed'     => __( 'Changed anomalies', 'core-blueprint' ),
			'resolved'    => __( 'Resolved anomalies', 'core-blueprint' ),
			'unconfirmed' => __( 'Resolution not confirmed', 'core-blueprint' ),
		];

		echo '<div class="cb-core-integrity-diff-components">';
		foreach ( $groups as $key => $label ) {
			$items = is_array( $lifecycle[ $key ] ?? null ) ? $lifecycle[ $key ] : [];
			if ( [] === $items ) {
				continue;
			}

			echo '<details class="cb-core-integrity-diff-component">';
			echo '<summary><strong>' . esc_html( $label ) . '</strong><span>' . esc_html( (string) count( $items ) ) . '</span></summary>';
			echo '<ul>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$target = is_array( $item['target'] ?? null ) ? $item['target'] : [];
				$component = (string) ( $target['label'] ?? $target['slug'] ?? $item['type'] ?? '' );
				$path = self::finding_target_path( $item );
				echo '<li>';
				if ( '' !== $component ) {
					echo '<strong>' . esc_html( $component ) . '</strong>';
				}
				if ( '' !== $path ) {
					echo ( '' !== $component ? ' · ' : '' ) . '<code>' . esc_html( $path ) . '</code>';
				}
				echo '</li>';
			}
			echo '</ul></details>';
		}
		echo '</div>';
	}

	private static function render_history( array $items, string $current_scan = '' ): void {
		if ( empty( $items ) ) {
			echo '<p class="cb-core-integrity-muted">' . esc_html__( 'No previous scans stored yet.', 'core-blueprint' ) . '</p>';
			return;
		}

		echo '<div class="cb-core-integrity-history-table-wrap">';
		echo '<table class="widefat striped cb-core-integrity-history-table">';
		echo '<caption class="screen-reader-text">' . esc_html__( 'Scan History', 'core-blueprint' ) . '</caption>';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Status', 'core-blueprint' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Date', 'core-blueprint' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Result', 'core-blueprint' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Source', 'core-blueprint' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $items as $item ) {
			$status      = (string) ( $item['status'] ?? 'idle' );
			$summary     = is_array( $item['summary'] ?? null ) ? $item['summary'] : [];
			$time        = (string) ( $item['timestamp'] ?? '' );
			$source      = (string) ( $item['source'] ?? '' );
			$is_current  = '' !== $current_scan && $time === $current_scan;
			$is_baseline = 'baseline' === $source || 'component_baseline' === $source;

			echo '<tr class="' . esc_attr( ( $is_current ? 'is-current ' : '' ) . ( $is_baseline ? 'is-baseline' : '' ) ) . '">';
			echo '<td>' . StateBadge::render( strtoupper( $status ), [ 'variant' => self::state_badge_variant( $status ) ] ) . '</td>';
			echo '<td><strong>' . esc_html( $time ) . '</strong></td>';
			echo '<td>' . esc_html( sprintf( __( '%1$d OK, %2$d warnings, %3$d critical', 'core-blueprint' ), (int) ( $summary['ok'] ?? 0 ), (int) ( $summary['warning'] ?? 0 ), (int) ( $summary['critical'] ?? 0 ) ) ) . '</td>';
			echo '<td><div class="cb-core-integrity-history-tags">';
			if ( $is_current ) {
				echo '<span class="cb-core-badge">' . esc_html__( 'Current', 'core-blueprint' ) . '</span>';
			}
			if ( $is_baseline ) {
				echo '<span class="cb-core-badge">' . esc_html__( 'Baseline', 'core-blueprint' ) . '</span>';
			} elseif ( '' !== $source ) {
				echo '<span class="cb-core-badge">' . esc_html( $source ) . '</span>';
			}
			echo '</div></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function finding_status_label( string $status ): string {
		return match ( $status ) {
			'baseline_required'    => __( 'Baseline required', 'core-blueprint' ),
			'unsupported'          => __( 'Unsupported', 'core-blueprint' ),
			'verification_failed'  => __( 'Verification failed', 'core-blueprint' ),
			'scan_incomplete'      => __( 'Scan incomplete', 'core-blueprint' ),
			'unreadable'           => __( 'Unreadable', 'core-blueprint' ),
			'unverifiable'         => __( 'Unverifiable', 'core-blueprint' ),
			'symlink_skipped'      => __( 'Symlink skipped', 'core-blueprint' ),
			'changed'              => __( 'Changed', 'core-blueprint' ),
			'new'                  => __( 'New', 'core-blueprint' ),
			'missing'              => __( 'Missing', 'core-blueprint' ),
			'critical'             => __( 'Critical', 'core-blueprint' ),
			'warning'              => __( 'Warning', 'core-blueprint' ),
			'notice'               => __( 'Notice', 'core-blueprint' ),
			default                => ucwords( str_replace( '_', ' ', $status ) ),
		};
	}

	private static function cluster_upload_groups( array $component_groups ): array {
		$clusters = [];
		foreach ( $component_groups as $group ) {
			foreach ( (array) ( $group['findings'] ?? [] ) as $finding ) {
				if ( ! is_array( $finding ) ) {
					continue;
				}
				$path = self::finding_target_path( $finding );
				$relative = preg_replace( '#^/?wp-content/uploads/#', '', str_replace( '\\', '/', $path ) );
				$relative = is_string( $relative ) ? ltrim( $relative, '/' ) : '';
				$parts = '' !== $relative ? explode( '/', $relative ) : [];
				$top = count( $parts ) > 1 ? (string) $parts[0] : '';
				$key = '' !== $top ? $top : '__uploads_root__';
				$severity = sanitize_key( (string) ( $finding['severity'] ?? 'warning' ) );
				if ( ! isset( $clusters[ $key ] ) ) {
					$clusters[ $key ] = [
						'component' => 'uploads',
						'slug'      => '' !== $top ? $top . '/' : __( 'Uploads root', 'core-blueprint' ),
						'path'      => 'wp-content/uploads/' . ( '' !== $top ? $top . '/' : '' ),
						'status'    => 'critical' === $severity ? 'critical' : 'warning',
						'severity'  => 'critical' === $severity ? 'critical' : 'warning',
						'message'   => '',
						'count'     => 0,
						'findings'  => [],
						'can_approve_baseline' => false,
						'baseline'  => [ 'exists' => false ],
					];
				}
				$clusters[ $key ]['count']++;
				$clusters[ $key ]['findings'][] = $finding;
				if ( 'critical' === $severity ) {
					$clusters[ $key ]['severity'] = 'critical';
					$clusters[ $key ]['status'] = 'critical';
				}
			}
		}
		foreach ( $clusters as &$cluster ) {
			$count = (int) $cluster['count'];
			$cluster['message'] = sprintf(
				_n( '%d scanner finding is grouped under this uploads location.', '%d scanner findings are grouped under this uploads location.', $count, 'core-blueprint' ),
				$count
			);
		}
		unset( $cluster );
		return array_values( $clusters );
	}

	private static function render_grouped_findings( array $groups, bool $passed = false, array $reviewed_candidate_ids = [] ): void {
		if ( empty( $groups ) ) {
			echo '<p class="cb-core-integrity-muted">' . ( $passed ? esc_html__( 'No verified checks to show yet.', 'core-blueprint' ) : esc_html__( 'No findings to show.', 'core-blueprint' ) ) . '</p>';
			return;
		}

		$quarantined_findings = [];
		if ( ! $passed ) {
			foreach ( QuarantineRepository::all() as $quarantine_item ) {
				if ( ! is_array( $quarantine_item ) ) {
					continue;
				}
				$status = (string) ( $quarantine_item['status'] ?? '' );
				if ( in_array( $status, [ 'restored', 'deleted' ], true ) ) {
					continue;
				}
				$finding_id = (string) ( $quarantine_item['finding_id'] ?? '' );
				if ( '' !== $finding_id ) {
					$quarantined_findings[ $finding_id ] = $quarantine_item;
				}
			}
		}

		foreach ( $groups as $component => $component_groups ) {
			if ( ! $passed && 'uploads' === (string) $component ) {
				$component_groups = self::cluster_upload_groups( (array) $component_groups );
			}
			$component_label = ucfirst( (string) $component );
			$group_count     = count( (array) $component_groups );
			if ( ! $passed ) {
				$group_count = 0;
				foreach ( (array) $component_groups as $issue_group ) {
					$group_count += max( 0, (int) ( $issue_group['count'] ?? 0 ) );
				}
			}

			// Filter-buttons in the Components panel use plural keys
			// (core / plugins / themes / uploads) - those come from
			// the scan summary's components map. Finding groups here
			// use the singular `component` field on the finding itself
			// (plugin / theme / core / uploads). Map singular → plural
			// for the data attribute so the filter button finds its
			// target group on click. Without this remap "Plugins"
			// filter button → no match, click does nothing.
			$filter_key = match ( (string) $component ) {
				'plugin' => 'plugins',
				'theme'  => 'themes',
				default  => (string) $component,
			};
			?>
			<section class="cb-core-integrity-finding-group" data-cb-integrity-component-group="<?php echo esc_attr( $filter_key ); ?>">
				<h3>
					<span><?php echo esc_html( $component_label ); ?></span>
					<span class="cb-core-integrity-group-count" aria-label="<?php echo esc_attr( sprintf( $passed ? _n( '%d verified', '%d verified', $group_count, 'core-blueprint' ) : _n( '%d issue', '%d issues', $group_count, 'core-blueprint' ), $group_count ) ); ?>"><?php echo esc_html( (string) $group_count ); ?></span>
				</h3>
				<?php foreach ( $component_groups as $group ) : ?>
					<?php
					$status     = (string) ( $group['status'] ?? 'notice' );
					$severity   = (string) ( $group['severity'] ?? 'ok' );
					$slug       = (string) ( $group['slug'] ?? '' );
					$message    = (string) ( $group['message'] ?? '' );
					$count      = (int) ( $group['count'] ?? 0 );
					$group_path = (string) ( $group['path'] ?? '' );
					$first      = (array) ( (array) ( $group['findings'] ?? [] )[0] ?? [] );
					$type       = (string) ( $first['type'] ?? '' );
					$target     = is_array( $first['target'] ?? null ) ? $first['target'] : [];
					$base_slug  = (string) ( $target['slug'] ?? '' );
					$can_approve = ! empty( $group['can_approve_baseline'] );
					$baseline = is_array( $group['baseline'] ?? null ) ? $group['baseline'] : [ 'exists' => false ];
					$baseline_exists = ! empty( $baseline['exists'] );
					$baseline_label = $baseline_exists ? __( 'Update Approved Baseline', 'core-blueprint' ) : __( 'Approve Baseline', 'core-blueprint' );
					$candidate_id = ResultRepository::baselineCandidateId( $first );
					$candidate_reviewed = '' !== $candidate_id && isset( $reviewed_candidate_ids[ $candidate_id ] );
					$directory_recommendation = null;
					if ( ! $passed && 'uploads' === (string) $component && self::can_manage_policy() ) {
						foreach ( (array) ( $group['findings'] ?? [] ) as $candidate ) {
							$candidate_id = (string) ( $candidate['id'] ?? '' );
							if ( '' !== $candidate_id && ! isset( $quarantined_findings[ $candidate_id ] ) && QuarantineService::can_quarantine_finding( $candidate ) && QuarantineService::directory_action_available( $candidate ) ) {
								$directory_recommendation = $candidate;
								break;
							}
						}
					}
					?>
					<details class="cb-core-integrity-component-result cb-core-integrity-component-result-<?php echo esc_attr( $severity ); ?> cb-core-interactive-surface cb-core-interactive-row cb-core-interactive-row--<?php echo esc_attr( $severity ); ?>">
						<summary class="cb-core-interactive-row__summary">
							<?php echo Icon::render( 'expand', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-interactive-row__icon' ] ); ?>
							<span class="cb-core-integrity-component-title"><?php echo esc_html( $slug ); ?></span>
							<?php echo StateBadge::render( str_replace( '_', ' ', $status ), [ 'variant' => self::state_badge_variant( $status ) ] ); ?>
							<span class="cb-core-integrity-muted"><?php echo esc_html( $passed ? sprintf( _n( '%d passed check', '%d passed checks', $count, 'core-blueprint' ), $count ) : sprintf( _n( '%d finding', '%d findings', $count, 'core-blueprint' ), $count ) ); ?></span>
							<?php if ( is_array( $directory_recommendation ) ) : ?>
								<button type="button" class="button cb-core-button cb-core-button--remediation cb-core-integrity-summary-action cb-core-integrity-quarantine-primary" data-cb-integrity-action="quarantine-finding" data-cb-integrity-finding-id="<?php echo esc_attr( (string) ( $directory_recommendation['id'] ?? '' ) ); ?>" data-cb-integrity-scope="directory"><?php echo Icon::render( 'quarantine', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-button__icon' ] ); ?><span class="cb-core-button__label"><?php echo esc_html__( 'Quarantine folder', 'core-blueprint' ); ?></span></button>
							<?php endif; ?>
							<?php if ( self::can_manage_policy() && $can_approve && '' !== $type && '' !== $base_slug && '' !== $candidate_id ) : ?>
								<?php if ( $candidate_reviewed ) : ?>
									<?php echo StateBadge::render( __( 'Reviewed', 'core-blueprint' ), [ 'variant' => StateBadge::SUCCESS, 'class' => 'cb-core-integrity-reviewed-pill' ] ); ?>
									<button type="button" class="button cb-core-button cb-core-button--primary cb-core-button--compact cb-core-integrity-summary-action" data-cb-integrity-action="approve-component-baseline" data-cb-integrity-type="<?php echo esc_attr( $type ); ?>" data-cb-integrity-slug="<?php echo esc_attr( $base_slug ); ?>" data-cb-integrity-baseline-exists="<?php echo $baseline_exists ? '1' : '0'; ?>">
										<?php echo esc_html( $baseline_label ); ?>
									</button>
								<?php else : ?>
									<button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact cb-core-integrity-summary-action cb-core-integrity-review-action" data-cb-integrity-action="open-baseline-review"><?php echo Icon::render( 'review', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-button__icon' ] ); ?><span class="cb-core-button__label"><?php echo esc_html__( 'Review', 'core-blueprint' ); ?></span></button>
								<?php endif; ?>
							<?php endif; ?>
							<?php
							// Remove-from-baseline action: only meaningful for the
							// `missing` status (component approved earlier, no longer
							// present on disk) AND when there is something to remove
							// (an existing baseline entry for this type+slug).
							if ( self::can_manage_policy() && 'missing' === $status && $baseline_exists && '' !== $type && '' !== $base_slug ) :
							?>
								<button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact cb-core-integrity-summary-action" data-cb-integrity-action="remove-component-baseline" data-cb-integrity-type="<?php echo esc_attr( $type ); ?>" data-cb-integrity-slug="<?php echo esc_attr( $base_slug ); ?>">
									<?php echo esc_html__( 'Remove from baseline', 'core-blueprint' ); ?>
								</button>
							<?php endif; ?>
						</summary>
						<p><?php echo esc_html( $message ); ?></p>
						<?php if ( 'uploads' !== (string) $component ) : ?>
						<p class="cb-core-integrity-baseline-indicator <?php echo $baseline_exists ? 'is-approved' : 'is-missing'; ?>">
							<span><?php echo esc_html__( 'Baseline:', 'core-blueprint' ); ?></span>
							<?php if ( $baseline_exists ) : ?>
								<?php echo esc_html( sprintf( __( 'Approved on %s', 'core-blueprint' ), (string) ( $baseline['approved_at'] ?? $baseline['updated_at'] ?? $baseline['created_at'] ?? '' ) ) ); ?>
							<?php else : ?>
								<?php echo esc_html__( 'Not set', 'core-blueprint' ); ?>
							<?php endif; ?>
						</p>
						<?php endif; ?>
						<?php if ( 'uploads' !== (string) $component && in_array( $status, [ 'changed', 'new', 'needs_baseline', 'verification_failed' ], true ) ) : ?>
							<p class="cb-core-integrity-hint"><?php echo esc_html__( 'This change may be expected after a plugin or theme update. Only update the baseline after confirming the current state is trusted.', 'core-blueprint' ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $group_path ) : ?>
							<p class="cb-core-integrity-path"><span><?php echo esc_html__( 'Path:', 'core-blueprint' ); ?></span> <code><?php echo esc_html( $group_path ); ?></code></p>
						<?php endif; ?>
						<div class="cb-core-integrity-component-details"<?php echo '' !== $candidate_id ? ' data-cb-integrity-candidate-id="' . esc_attr( $candidate_id ) . '"' : ''; ?>>
							<?php foreach ( (array) ( $group['findings'] ?? [] ) as $finding ) : ?>
								<?php
								$finding_severity = (string) ( $finding['severity'] ?? 'ok' );
								$type             = (string) ( $finding['type'] ?? '' );
								$finding_target   = is_array( $finding['target'] ?? null ) ? $finding['target'] : [];
								$file             = (string) ( $finding_target['file'] ?? '' );
								$finding_path     = self::finding_target_path( $finding );
								$finding_message  = (string) ( $finding['message'] ?? '' );
								?>
								<article class="cb-core-integrity-finding cb-core-integrity-finding-<?php echo esc_attr( $finding_severity ); ?>">
									<div>
										<strong><?php echo esc_html( strtoupper( $finding_severity ) ); ?></strong>
										<span><?php echo esc_html( $finding_message ); ?></span>
									</div>
									<?php if ( '' !== $finding_path && $finding_path !== $group_path ) : ?>
										<div class="cb-core-integrity-finding-path"><span><?php echo esc_html__( 'Path:', 'core-blueprint' ); ?></span> <code><?php echo esc_html( $finding_path ); ?></code></div>
									<?php endif; ?>
									<?php
									$verification       = is_array( $finding['verification'] ?? null ) ? $finding['verification'] : [];
									$investigation_meta = is_array( $finding['meta'] ?? null ) ? $finding['meta'] : [];
									$finding_id         = (string) ( $finding['id'] ?? '' );
									$quarantine_item    = '' !== $finding_id ? ( $quarantined_findings[ $finding_id ] ?? null ) : null;
									$children           = is_array( $finding['children'] ?? null ) ? $finding['children'] : [];
									$finding_meta       = is_array( $finding['meta'] ?? null ) ? $finding['meta'] : [];
									$verified_total     = (int) ( $finding_meta['verified_files'] ?? count( $children ) );
									$lifecycle          = is_array( $finding['lifecycle'] ?? null ) ? $finding['lifecycle'] : [];
									$first_detected     = self::format_finding_timestamp( (string) ( $lifecycle['first_detected_at'] ?? '' ) );
									$last_detected      = self::format_finding_timestamp( (string) ( $lifecycle['last_detected_at'] ?? '' ) );
									$last_changed       = self::format_finding_timestamp( (string) ( $lifecycle['last_changed_at'] ?? '' ) );
									$observations       = max( 0, (int) ( $lifecycle['observations'] ?? 0 ) );
									$state              = sanitize_key( (string) ( $lifecycle['state'] ?? '' ) );
									?>
									<?php if ( is_array( $quarantine_item ) ) : ?>
										<div class="cb-core-integrity-remediation-actions">
											<a class="button cb-core-button cb-core-button--secondary cb-core-button--compact" href="<?php echo esc_url( self::scanner_view_url( 'quarantine' ) ); ?>"><?php echo esc_html__( 'Open Quarantine Workspace', 'core-blueprint' ); ?></a>
											<span class="cb-core-integrity-muted"><?php echo esc_html__( 'This finding is already isolated in the Quarantine Workspace. Run a new scan to confirm the original anomaly is resolved.', 'core-blueprint' ); ?></span>
										</div>
									<?php elseif ( ! $passed && self::can_manage_policy() && QuarantineService::can_quarantine_finding( $finding ) ) : ?>
										<div class="cb-core-integrity-remediation-actions">
											<button type="button" class="button cb-core-button cb-core-button--remediation cb-core-button--compact cb-core-integrity-quarantine-file" data-cb-integrity-action="quarantine-finding" data-cb-integrity-finding-id="<?php echo esc_attr( $finding_id ); ?>" data-cb-integrity-scope="file"><?php echo Icon::render( 'quarantine', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-button__icon' ] ); ?><span class="cb-core-button__label"><?php echo esc_html__( 'Quarantine file', 'core-blueprint' ); ?></span></button>
											<?php if ( null === $directory_recommendation && QuarantineService::directory_action_available( $finding ) ) : ?>
												<button type="button" class="button cb-core-button cb-core-button--remediation cb-core-integrity-quarantine-primary" data-cb-integrity-action="quarantine-finding" data-cb-integrity-finding-id="<?php echo esc_attr( $finding_id ); ?>" data-cb-integrity-scope="directory"><?php echo Icon::render( 'quarantine', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-button__icon' ] ); ?><span class="cb-core-button__label"><?php echo esc_html__( 'Quarantine folder', 'core-blueprint' ); ?></span></button>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									<details class="cb-core-disclosure cb-core-disclosure--compact cb-core-integrity-finding-technical">
										<summary class="cb-core-disclosure__summary">
											<?php echo Icon::render( 'expand', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-disclosure__icon' ] ); ?>
											<span class="cb-core-disclosure__title"><?php echo esc_html__( 'Technical details', 'core-blueprint' ); ?></span>
										</summary>
										<div class="cb-core-disclosure__body">
											<?php if ( ! empty( $verification ) ) : ?>
												<div class="cb-core-integrity-verification"><span><?php echo esc_html__( 'Verification:', 'core-blueprint' ); ?></span> <?php echo esc_html( (string) ( $verification['label'] ?? $verification['method'] ?? '' ) ); ?> <?php if ( ! empty( $verification['confidence'] ) ) : ?><em><?php echo esc_html( '(' . ucfirst( (string) $verification['confidence'] ) . ' confidence)' ); ?></em><?php endif; ?></div>
											<?php endif; ?>
											<?php if ( ! empty( $investigation_meta['filesystem_path'] ) ) : ?>
												<?php $filesystem_path = (string) $investigation_meta['filesystem_path']; ?>
												<div class="cb-core-integrity-finding-path cb-core-integrity-finding-path-technical">
													<span><?php echo esc_html__( 'Filesystem location:', 'core-blueprint' ); ?></span>
													<code><?php echo esc_html( $filesystem_path ); ?></code>
													<button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact cb-core-integrity-copy-path" data-cb-integrity-action="copy-path" data-cb-integrity-copy-value="<?php echo esc_attr( $filesystem_path ); ?>"><?php echo esc_html__( 'Copy path', 'core-blueprint' ); ?></button>
												</div>
											<?php endif; ?>
											<?php if ( ! empty( $investigation_meta['expected_hash'] ) ) : ?>
												<div class="cb-core-integrity-verification"><span><?php echo esc_html__( 'Expected hash:', 'core-blueprint' ); ?></span> <code><?php echo esc_html( (string) $investigation_meta['expected_hash'] ); ?></code></div>
											<?php endif; ?>
											<?php if ( ! empty( $investigation_meta['actual_hash'] ) ) : ?>
												<div class="cb-core-integrity-verification"><span><?php echo esc_html__( 'Current hash:', 'core-blueprint' ); ?></span> <code><?php echo esc_html( (string) $investigation_meta['actual_hash'] ); ?></code></div>
											<?php elseif ( ! empty( $investigation_meta['sha256'] ) ) : ?>
												<div class="cb-core-integrity-verification"><span><?php echo esc_html__( 'SHA-256:', 'core-blueprint' ); ?></span> <code><?php echo esc_html( (string) $investigation_meta['sha256'] ); ?></code></div>
											<?php endif; ?>
											<?php if ( isset( $investigation_meta['size'] ) || isset( $investigation_meta['mtime'] ) ) : ?>
												<div class="cb-core-integrity-verification"><span><?php echo esc_html__( 'File context:', 'core-blueprint' ); ?></span> <?php echo isset( $investigation_meta['size'] ) ? esc_html( size_format( (int) $investigation_meta['size'] ) ) : ''; ?><?php if ( isset( $investigation_meta['mtime'] ) ) : ?> · <?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $investigation_meta['mtime'] ) ); ?><?php endif; ?></div>
											<?php endif; ?>
											<?php if ( '' !== $first_detected || '' !== $last_detected ) : ?>
												<div class="cb-core-integrity-verification">
													<span><?php echo esc_html__( 'Detection history:', 'core-blueprint' ); ?></span>
													<?php if ( '' !== $first_detected ) : ?><?php echo esc_html( sprintf( __( 'First detected %s', 'core-blueprint' ), $first_detected ) ); ?><?php endif; ?>
													<?php if ( '' !== $last_detected && $last_detected !== $first_detected ) : ?> · <?php echo esc_html( sprintf( __( 'last seen %s', 'core-blueprint' ), $last_detected ) ); ?><?php endif; ?>
													<?php if ( 'changed' === $state && '' !== $last_changed ) : ?> · <?php echo esc_html( sprintf( __( 'changed %s', 'core-blueprint' ), $last_changed ) ); ?><?php endif; ?>
													<?php if ( $observations > 1 ) : ?> · <?php echo esc_html( sprintf( _n( '%d observation', '%d observations', $observations, 'core-blueprint' ), $observations ) ); ?><?php endif; ?>
												</div>
											<?php endif; ?>
											<code class="cb-core-integrity-technical"><?php echo esc_html( trim( $type . ' ' . $file ) ); ?></code>
											<?php if ( ! empty( $children ) ) : ?>
												<details class="cb-core-integrity-audit-children">
													<summary><?php echo esc_html( sprintf( _n( '%d verified file', '%d verified files', $verified_total, 'core-blueprint' ), $verified_total ) ); ?></summary>
													<?php if ( $verified_total > count( $children ) ) : ?>
														<p class="cb-core-integrity-muted"><?php echo esc_html( sprintf( __( 'Showing %1$d of %2$d verified file paths to keep this result compact.', 'core-blueprint' ), count( $children ), $verified_total ) ); ?></p>
													<?php endif; ?>
													<ul>
														<?php foreach ( $children as $child ) : ?>
															<li><code><?php echo esc_html( is_array( $child ) ? self::finding_target_path( $child ) : '' ); ?></code> <span><?php echo esc_html( (string) ( $child['status'] ?? 'ok' ) ); ?></span></li>
														<?php endforeach; ?>
													</ul>
												</details>
											<?php endif; ?>
										</div>
									</details>
								</article>
							<?php endforeach; ?>
						</div>
						<?php if ( ! $passed && self::can_manage_policy() && $can_approve && '' !== $candidate_id && ! $candidate_reviewed ) : ?>
							<div class="cb-core-integrity-baseline-review-footer">
								<div><strong><?php echo esc_html__( 'Review required', 'core-blueprint' ); ?></strong><span class="cb-core-integrity-muted"><?php echo esc_html__( "Confirm that this component's current files are expected before baseline approval becomes available.", 'core-blueprint' ); ?></span></div>
								<button type="button" class="button cb-core-button cb-core-button--primary" data-cb-integrity-action="mark-baseline-reviewed" data-cb-integrity-candidate-id="<?php echo esc_attr( $candidate_id ); ?>"><?php echo esc_html__( 'Mark reviewed', 'core-blueprint' ); ?></button>
							</div>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>
			</section>
			<?php
		}
	}


	private static function render_quarantine_workspace( bool $can_manage_policy ): void {
		$items = QuarantineRepository::items();
		$open  = QuarantineRepository::open_count();
		?>
		<section class="cb-core-integrity-panel cb-core-quarantine-workspace" id="cb-core-quarantine-workspace" aria-labelledby="cb-core-quarantine-heading">
			<div class="cb-core-integrity-panel-head">
				<div>
					<h2 id="cb-core-quarantine-heading"><?php echo esc_html__( 'Quarantine Workspace', 'core-blueprint' ); ?></h2>
					<p><?php echo esc_html__( 'Isolate reviewed Scanner findings from the active site, inspect them later, and restore or permanently remove them with a full audit trail.', 'core-blueprint' ); ?></p>
				</div>
				<?php echo StateBadge::render( sprintf( _n( '%d open item', '%d open items', $open, 'core-blueprint' ), $open ), [ 'variant' => $open > 0 ? StateBadge::WARNING : StateBadge::SUCCESS ] ); ?>
			</div>
			<?php if ( empty( $items ) ) : ?>
				<p class="cb-core-integrity-muted"><?php echo esc_html__( 'Nothing is quarantined. Quarantine actions appear on actionable Uploads findings after a scan.', 'core-blueprint' ); ?></p>
			<?php else : ?>
				<div class="cb-core-quarantine-list">
				<?php foreach ( $items as $item ) :
					$status = sanitize_key( (string) ( $item['status'] ?? 'awaiting_review' ) );
					$is_closed = in_array( $status, [ 'restored', 'deleted' ], true );
					$is_transition_attention = in_array( $status, [ 'restoring', 'deleting' ], true );
					$id = (string) ( $item['id'] ?? '' );
				?>
					<article class="cb-core-quarantine-item <?php echo $is_closed ? 'is-closed' : 'is-open'; ?>" data-cb-quarantine-id="<?php echo esc_attr( $id ); ?>">
						<div class="cb-core-quarantine-item__main">
							<div><strong><?php echo esc_html( (string) ( $item['relative_path'] ?? '' ) ); ?></strong><span class="cb-core-integrity-muted"><?php echo esc_html( sprintf( __( '%1$s · %2$d files · %3$s', 'core-blueprint' ), self::quarantine_kind_label( (string) ( $item['kind'] ?? 'file' ) ), (int) ( $item['file_count'] ?? 0 ), size_format( (int) ( $item['total_bytes'] ?? 0 ) ) ) ); ?></span></div>
							<?php echo StateBadge::render( self::quarantine_status_label( $status ), [ 'variant' => self::state_badge_variant( $status ) ] ); ?>
						</div>
						<div class="cb-core-quarantine-item__meta"><span><?php echo esc_html( sprintf( __( 'Quarantined %s', 'core-blueprint' ), (string) ( $item['quarantined_at'] ?? '' ) ) ); ?></span><?php if ( ! empty( $item['evidence_sha256'] ) ) : ?><code><?php echo esc_html( (string) $item['evidence_sha256'] ); ?></code><?php endif; ?></div>
						<div class="cb-core-quarantine-item__actions">
							<button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact" data-cb-integrity-action="quarantine-inspect" data-cb-quarantine-id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html__( 'Inspect', 'core-blueprint' ); ?></button>
							<?php if ( $can_manage_policy && ! $is_closed && ! $is_transition_attention ) : ?>
								<button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact" data-cb-integrity-action="quarantine-note" data-cb-quarantine-id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html__( 'Add note', 'core-blueprint' ); ?></button>
								<button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact" data-cb-integrity-action="quarantine-state" data-cb-quarantine-id="<?php echo esc_attr( $id ); ?>" data-cb-quarantine-state="reviewed"><?php echo esc_html__( 'Mark reviewed', 'core-blueprint' ); ?></button>
								<button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact" data-cb-integrity-action="quarantine-restore" data-cb-quarantine-id="<?php echo esc_attr( $id ); ?>"><?php echo Icon::render( 'restore', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-button__icon' ] ); ?><span class="cb-core-button__label"><?php echo esc_html__( 'Restore', 'core-blueprint' ); ?></span></button>
								<button type="button" class="button cb-core-button cb-core-button--danger cb-core-button--compact" data-cb-integrity-action="quarantine-delete" data-cb-quarantine-id="<?php echo esc_attr( $id ); ?>"><?php echo Icon::render( 'delete', [ 'size' => Icon::SIZE_COMPACT, 'class' => 'cb-core-button__icon' ] ); ?><span class="cb-core-button__label"><?php echo esc_html__( 'Permanently delete', 'core-blueprint' ); ?></span></button>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<p class="cb-core-integrity-muted"><?php echo esc_html__( 'Quarantine is intentionally finding-driven; Core Scanner does not expose a general-purpose web file manager.', 'core-blueprint' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Map Scanner workflow/severity state to the shared StateBadge semantic palette.
	 */
	private static function state_badge_variant( string $status ): string {
		return match ( sanitize_key( $status ) ) {
			'ok', 'complete', 'completed', 'done', 'passed', 'reviewed', 'restored', 'deleted' => StateBadge::SUCCESS,
			'notice', 'info', 'pending', 'running' => StateBadge::INFO,
			'warning', 'needs_baseline', 'baseline_required', 'awaiting_review', 'keep_quarantined', 'marked_for_deletion',
			'incomplete', 'scan_incomplete', 'distribution_drift', 'modified', 'missing', 'changed', 'new', 'unexpected',
			'unexpected_unverified', 'unexpected_root_executable', 'unreadable', 'unreadable_unexpected', 'symlink_skipped',
			'verification_failed', 'unsupported', 'executable_upload' => StateBadge::WARNING,
			'critical', 'restoring', 'deleting' => StateBadge::DANGER,
			'failed', 'error', 'restore_failed', 'delete_failed' => StateBadge::ERROR,
			default => StateBadge::NEUTRAL,
		};
	}

	private static function quarantine_status_label( string $status ): string {
		return match ( $status ) {
			'awaiting_review'      => __( 'Awaiting review', 'core-blueprint' ),
			'reviewed'             => __( 'Reviewed', 'core-blueprint' ),
			'keep_quarantined'     => __( 'Keep quarantined', 'core-blueprint' ),
			'marked_for_deletion'  => __( 'Marked for deletion', 'core-blueprint' ),
			'restoring'            => __( 'Restore needs attention', 'core-blueprint' ),
			'restore_failed'       => __( 'Restore failed', 'core-blueprint' ),
			'restored'             => __( 'Restored', 'core-blueprint' ),
			'deleting'             => __( 'Deletion needs attention', 'core-blueprint' ),
			'delete_failed'        => __( 'Deletion failed', 'core-blueprint' ),
			'deleted'              => __( 'Deleted', 'core-blueprint' ),
			default                => ucwords( str_replace( '_', ' ', $status ) ),
		};
	}

	private static function quarantine_kind_label( string $kind ): string {
		return 'directory' === $kind ? __( 'Directory', 'core-blueprint' ) : __( 'File', 'core-blueprint' );
	}

	/**
	 * Format an ISO/UTC finding timestamp in the WordPress site timezone.
	 */
	private static function format_finding_timestamp( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return $value;
		}

		$format = trim( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) );
		return wp_date( '' !== $format ? $format : 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Render the Distribution Locale panel.
	 *
	 * Always visible (per CB transparency principle) and intentionally
	 * compact: a status line, a UI-locale comparison line, and an
	 * inline Re-detect button. Operator-controls (mode + override) are
	 * collapsed under a details/summary so the typical scanner page
	 * doesn't surface knobs the operator only touches in edge cases.
	 *
	 * The "Tried locales" entry is a separate inline collapsible: most
	 * operators don't need to see five locale strings, but when
	 * something looks off (detection picked the wrong distribution,
	 * cross-check failed) it's an essential diagnostic.
	 */
	private static function render_distribution_locale_panel( array $settings, bool $can_manage_policy ): void {
		$mode      = (string) ( $settings['distribution_locale_mode']     ?? 'fallback' );
		$detected  = (string) ( $settings['distribution_locale_detected'] ?? '' );
		$override  = (string) ( $settings['distribution_locale_override'] ?? '' );
		$meta      = is_array( $settings['distribution_locale_meta'] ?? null ) ? $settings['distribution_locale_meta'] : [];
		$tried     = is_array( $meta['tried'] ?? null ) ? $meta['tried'] : [];
		$last      = (string) ( $meta['last_detected_at'] ?? '' );
		$matched   = (string) ( $meta['matched_file']     ?? '' );
		$cross     = (string) ( $meta['cross_check']      ?? '' );
		$ui_locale = (string) get_locale();

		// Compute the explicit values per CP-10:
		//   - UI locale       → what get_locale() returns (always)
		//   - Distribution    → which distribution the scanner uses
		//                       (override → detected → fallback to UI)
		//   - Detection status → human-readable mode label
		$distribution_used = ( 'override' === $mode && '' !== $override )
			? $override
			: ( ( 'auto' === $mode && '' !== $detected ) ? $detected : $ui_locale );

		$distribution_suffix = '';
		switch ( $mode ) {
			case 'override':
				$distribution_suffix = '' !== $override ? sprintf( ' (%s)', __( 'manual override', 'core-blueprint' ) ) : '';
				break;
			case 'auto':
				$distribution_suffix = '' !== $detected ? sprintf( ' (%s)', __( 'auto-detected', 'core-blueprint' ) ) : '';
				break;
			default:
				$distribution_suffix = sprintf( ' (%s)', __( 'fallback to UI locale', 'core-blueprint' ) );
		}

		switch ( $mode ) {
			case 'auto':
				$status_label = '' !== $detected
					? esc_html__( 'Auto', 'core-blueprint' )
					: esc_html__( 'Auto (not yet detected)', 'core-blueprint' );
				break;
			case 'override':
				$status_label = '' !== $override
					? esc_html__( 'Manual override', 'core-blueprint' )
					: esc_html__( 'Manual override (not set)', 'core-blueprint' );
				break;
			default:
				$status_label = esc_html__( 'Not detected yet - runs automatically on first checksum mismatch', 'core-blueprint' );
		}

		?>
		<section class="cb-core-integrity-settings-section cb-core-integrity-locale-panel" aria-labelledby="cb-core-integrity-locale-heading">
			<div class="cb-core-integrity-panel-head">
				<h2 id="cb-core-integrity-locale-heading"><?php echo esc_html__( 'Distribution locale', 'core-blueprint' ); ?></h2>
				<?php if ( $can_manage_policy ) : ?>
					<button type="button" class="button cb-core-button cb-core-button--secondary" data-cb-integrity-action="redetect-locale">
						<?php echo esc_html__( 'Re-detect', 'core-blueprint' ); ?>
					</button>
				<?php endif; ?>
			</div>
			<p class="cb-core-integrity-muted">
				<?php echo esc_html__( 'Which official WordPress distribution is on disk. Used for checksum verification. Normally auto-detected - override only when you know what you are doing (e.g. after manual core re-install).', 'core-blueprint' ); ?>
			</p>

			<dl class="cb-core-integrity-locale-status">
				<dt><?php echo esc_html__( 'UI locale', 'core-blueprint' ); ?></dt>
				<dd><code><?php echo esc_html( $ui_locale ); ?></code></dd>

				<dt><?php echo esc_html__( 'Distribution', 'core-blueprint' ); ?></dt>
				<dd>
					<code><?php echo esc_html( $distribution_used ); ?></code>
					<?php if ( '' !== $distribution_suffix ) : ?>
						<span class="cb-core-integrity-muted"><?php echo esc_html( $distribution_suffix ); ?></span>
					<?php endif; ?>
				</dd>

				<dt><?php echo esc_html__( 'Detection status', 'core-blueprint' ); ?></dt>
				<dd><?php echo $status_label; // already escaped above ?></dd>

				<?php if ( '' !== $last ) : ?>
				<dt><?php echo esc_html__( 'Last detection', 'core-blueprint' ); ?></dt>
				<dd><?php echo esc_html( $last ); ?></dd>
				<?php endif; ?>

				<?php if ( '' !== $matched ) : ?>
				<dt><?php echo esc_html__( 'Matched file', 'core-blueprint' ); ?></dt>
				<dd><code><?php echo esc_html( $matched ); ?></code></dd>
				<?php endif; ?>

				<?php if ( '' !== $cross ) : ?>
				<dt><?php echo esc_html__( 'Cross-check', 'core-blueprint' ); ?></dt>
				<dd>
					<?php
					switch ( $cross ) {
						case 'ok':       echo esc_html__( 'Passed (locale-agnostic core files match)',                'core-blueprint' ); break;
						case 'failed':   echo esc_html__( 'Failed - possible tampering, detection not pinned',         'core-blueprint' ); break;
						case 'skipped':  echo esc_html__( 'Skipped (cross-check files unavailable)',                   'core-blueprint' ); break;
						default:         echo esc_html( $cross );
					}
					?>
				</dd>
				<?php endif; ?>
			</dl>

			<?php if ( ! empty( $tried ) ) : ?>
			<details class="cb-core-integrity-locale-tried">
				<summary><?php echo esc_html__( 'Tried locales', 'core-blueprint' ); ?> (<?php echo count( $tried ); ?>)</summary>
				<ul>
					<?php foreach ( $tried as $locale_string ) : ?>
						<li><code><?php echo esc_html( (string) $locale_string ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</details>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Whether the current user may change Core Scanner trust/policy state.
	 *
	 * cb_manage_integrity can be granted dynamically to ordinary WordPress
	 * administrators so they can run and review scans. This stronger capability
	 * remains CB Operator-only and protects anything that changes what the
	 * scanner trusts, retains, or checks.
	 */
	/**
	 * Build the canonical display path for a Scanner Finding target.
	 */
	private static function finding_target_path( array $finding ): string {
		$target = is_array( $finding['target'] ?? null ) ? $finding['target'] : [];
		$path   = trim( str_replace( '\\', '/', (string) ( $target['path'] ?? '' ) ) );
		$file   = ltrim( str_replace( '\\', '/', (string) ( $target['file'] ?? '' ) ), '/' );

		if ( '' === $file ) {
			return $path;
		}

		if ( '' === $path || '.' === $path || './' === $path ) {
			return $file;
		}

		return rtrim( $path, '/' ) . '/' . $file;
	}

	private static function can_manage_policy(): bool {
		return current_user_can( 'cb_manage_integrity_policy' );
	}
}
