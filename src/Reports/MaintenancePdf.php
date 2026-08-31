<?php
declare(strict_types=1);
/**
 * Maintenance report PDF presenter.
 *
 * Turns a persisted immutable report snapshot into a PDF binary on demand.
 * No report PDF is written to permanent storage.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Reports;

use CB\Core\PDF\Renderer;
use CB\Core\PDF\RendererException;

defined( 'ABSPATH' ) || exit;

final class MaintenancePdf {

	private Renderer $renderer;

	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Render one stored report row to a PDF binary.
	 *
	 * @param array<string,mixed> $report Stored row from Storage::find().
	 * @throws RendererException When the snapshot/template cannot be rendered.
	 */
	public function render( array $report ): string {
		$data = $report['report_data'] ?? null;
		if ( ! is_array( $data ) ) {
			throw new RendererException( 'Maintenance report snapshot is missing or invalid.' );
		}

		$snapshot_version = (int) ( $data['snapshot_version'] ?? 0 );
		if ( MaintenanceAggregator::SNAPSHOT_VERSION !== $snapshot_version ) {
			throw new RendererException( 'Maintenance report snapshot version is unsupported.' );
		}

		$period_start = (string) ( $report['period_start'] ?? '' );
		$period_end   = (string) ( $report['period_end'] ?? '' );
		$branding     = ReportBranding::for_pdf();
		$html         = $this->render_html_template( $report, $data, $branding, $period_start, $period_end );

		return $this->renderer->render( $html );
	}

	/**
	 * @param array<string,mixed> $report   Stored report row.
	 * @param array<string,mixed> $data     Immutable report snapshot.
	 * @param array<string,mixed> $branding Current PDF branding.
	 */
	private function render_html_template(
		array $report,
		array $data,
		array $branding,
		string $period_start,
		string $period_end
	): string {
		$template = CB_CORE_DIR . 'templates/pdf/maintenance-report.php';
		if ( ! is_file( $template ) ) {
			throw new RendererException( 'PDF template missing: ' . $template );
		}

		$period     = $data['period'] ?? [];
		$status     = $data['status'] ?? [];
		$kpis       = $data['kpis'] ?? [];
		$site_state = $data['site_state'] ?? [];
		$notes      = $data['notes'] ?? [];
		$sections   = $data['sections'] ?? [];
		$security   = $data['security'] ?? null;
		$backups      = $data['backups'] ?? [];
		$site         = is_array( $data['site'] ?? null ) ? $data['site'] : [];
		$site_url     = (string) ( $site['url'] ?? '' );
		$site_title   = (string) ( $site['title'] ?? '' );
		$generated_at = (string) ( $report['generated_at'] ?? '' );

		if ( '' === $site_url || '' === $generated_at ) {
			throw new RendererException( 'Maintenance report snapshot metadata is incomplete.' );
		}

		ob_start();
		require $template;
		return (string) ob_get_clean();
	}
}
