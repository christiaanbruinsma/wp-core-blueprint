<?php
/**
 * Maintenance Report PDF - production template.
 *
 * Renders a persisted immutable data snapshot produced by
 * MaintenanceAggregator::collect(). Branding is resolved separately at
 * render time by ReportBranding::for_pdf().
 *
 * Variables provided by MaintenancePdf::render_html_template():
 *   - $branding      array  Output of ReportBranding::for_pdf()
 *                           keys: logo_url, provider_name, provider_contact,
 *                                 accent_color, is_default
 *   - $period        array  ['start_ts' => int, 'end_ts' => int, 'days' => int]
 *   - $status        array  ['banner' => ok|warn|critical, 'headline', 'subline',
 *                            'detail_headline', 'detail_subline']
 *   - $kpis          array  Up to five entries: updates_performed,
 *                           updates_pending, security_issues (only when a
 *                           security data source is registered),
 *                           backups_created, active_users.
 *                           Each: ['count' => int, 'breakdown' => array<int,string>]
 *   - $security      ?array Either ['detected', 'blocked_attempts',
 *                           'brute_force', 'summary'] or null. Null hides
 *                           the Security Activity block on page 2 and the
 *                           security_issues KPI tile on page 1.
 *   - $site_state    array  Six entries: wp_core, theme, plugins, php, database,
 *                           website. Each: ['label', 'status', 'state', 'detail']
 *   - $notes         array  List of ['type' => ok|info|warn|critical,
 *                                     'title', 'body']
 *   - $sections      array  theme_updates, plugin_updates, plugin_installations,
 *                           plugin_removals, core_updates. Each:
 *                           ['title', 'count', 'columns', 'rows']
 *   - $security      array  ['detected', 'blocked_attempts', 'brute_force',
 *                            'summary']
 *   - $backups       array  ['count', 'last_at', 'providers', 'summary']
 *   - $period_start  string 'Y-m-d'
 *   - $period_end    string 'Y-m-d'
 *   - $site_url      string
 *   - $site_title    string
 *   - $generated_at  string UTC MySQL datetime captured when the snapshot was stored
 *
 * Dompdf constraints respected:
 *   - All CSS inline (Dompdf does not reliably resolve linked stylesheets)
 *   - Layout via tables, not flexbox (CSS3 layout not fully supported)
 *   - Conservative units (no rem, no calc)
 *   - Page numbering via CSS counters; embedded PHP remains disabled.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$accent = isset( $branding['accent_color'] ) ? (string) $branding['accent_color'] : '#1e6fb8';

/**
 * Render one detail-section table (Plugin Updates, Theme Updates, etc).
 * Reads $section['columns'] to render only active columns. Defined inside
 * function_exists guard so the template can be safely included twice in
 * one request (live preview + actual PDF render).
 */
if ( ! function_exists( 'cb_render_activity_section' ) ) {
	function cb_render_activity_section( array $section, string $kind, string $accent ): void {
		if ( ( $section['count'] ?? 0 ) === 0 ) {
			return;
		}

		$columns = $section['columns'] ?? [ 'target_name', 'version_to', 'date', 'actor' ];
		$rows    = $section['rows'] ?? [];

		// Column header labels - vary by section kind for natural reading.
		$target_label = in_array( $kind, [ 'theme', 'core' ], true )
			? __( 'Theme', 'core-blueprint' )
			: __( 'Plugin', 'core-blueprint' );
		if ( 'core' === $kind ) {
			$target_label = __( 'Component', 'core-blueprint' );
		}

		$labels = [
			'target_name'  => $target_label,
			'version_from' => __( 'From', 'core-blueprint' ),
			'version_to'   => __( 'Version', 'core-blueprint' ),
			'date'         => __( 'Date', 'core-blueprint' ),
			'actor'        => __( 'Performed By', 'core-blueprint' ),
			'notes'        => __( 'Notes', 'core-blueprint' ),
		];
		// When both From and To are present, rename version_to to "To".
		if ( in_array( 'version_from', $columns, true ) ) {
			$labels['version_to'] = __( 'To', 'core-blueprint' );
		}

		printf(
			'<h3 class="section-title">%s <span class="section-count">(%d)</span></h3>',
			esc_html( (string) ( $section['title'] ?? '' ) ),
			(int) $section['count']
		);

		// Base width weights - proportionally normalized to 100% so all
		// activity tables align column-by-column regardless of which
		// optional columns are active. Same key set as $columns.
		$weights = [
			'target_name'  => 28,
			'version_from' => 11,
			'version_to'   => 13,
			'date'         => 20,
			'actor'        => 32,
			'notes'        => 16,
		];
		$active_weights = array_intersect_key( $weights, array_flip( $columns ) );
		$total_weight   = array_sum( $active_weights );

		echo '<table class="activity-table"><colgroup>';
		foreach ( $columns as $col ) {
			$w = $total_weight > 0 ? round( ( $weights[ $col ] ?? 1 ) / $total_weight * 100, 2 ) : 0;
			printf( '<col style="width: %s%%;">', esc_attr( (string) $w ) );
		}
		echo '</colgroup><thead><tr>';
		foreach ( $columns as $col ) {
			printf( '<th>%s</th>', esc_html( $labels[ $col ] ?? $col ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $columns as $col ) {
				$value = $row[ $col ] ?? '';
				if ( 'date' === $col && '' !== $value ) {
					$value_ts = strtotime( (string) $value . ' UTC' );
					$value = $value_ts ? wp_date( 'd-m-Y H:i', $value_ts, wp_timezone() ) : (string) $value;
				}
				if ( in_array( $col, [ 'version_from', 'version_to' ], true ) && '' === $value ) {
					$value = '-';
				}
				printf( '<td>%s</td>', esc_html( (string) $value ) );
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( ! empty( $section['truncated'] ) ) {
			printf(
				'<p class="section-intro">%s</p>',
				esc_html( sprintf(
					/* translators: %1$d: rows shown, %2$d: total matching actions. */
					__( 'Showing the newest %1$d of %2$d recorded actions.', 'core-blueprint' ),
					count( $rows ),
					(int) $section['count']
				) )
			);
		}
	}
}

/**
 * Status colour map. Semantic - independent of agency accent colour.
 */
if ( ! function_exists( 'cb_status_colours' ) ) {
	function cb_status_colours( string $level ): array {
		switch ( $level ) {
			case 'critical':
				return [ 'bg' => '#fdecea', 'border' => '#f5c2bd', 'fg' => '#c0392b' ];
			case 'warn':
				return [ 'bg' => '#fef6e7', 'border' => '#f5d99a', 'fg' => '#b8761c' ];
			case 'info':
				return [ 'bg' => '#e8f1fb', 'border' => '#b9d4ee', 'fg' => '#1e6fb8' ];
			case 'ok':
			default:
				return [ 'bg' => '#eaf6ec', 'border' => '#bfdfc4', 'fg' => '#2e7d3a' ];
		}
	}
}

/**
 * Resolve a compact status glyph used as accessible fallback text.
 */
if ( ! function_exists( 'cb_pdf_status_glyph' ) ) {
	function cb_pdf_status_glyph( string $level ): string {
		switch ( $level ) {
			case 'critical': return '×';
			case 'warn':     return '!';
			case 'info':     return 'i';
			case 'ok':
			default:         return '✓';
		}
	}
}

/**
 * Build a self-contained SVG status icon for Dompdf.
 *
 * The icon artwork is path-based instead of font-based so the mark stays
 * optically centred inside its circle regardless of font metrics. The SVG is
 * embedded as a local data URI; no remote resources are enabled or requested.
 */
if ( ! function_exists( 'cb_pdf_status_icon_data_uri' ) ) {
	function cb_pdf_status_icon_data_uri( string $level, string $colour ): string {
		$level = in_array( $level, [ 'ok', 'info', 'warn', 'critical' ], true ) ? $level : 'ok';
		$colour = preg_match( '/^#[0-9a-fA-F]{6}$/', $colour ) ? $colour : '#2e7d3a';

		switch ( $level ) {
			case 'critical':
				$mark = '<path d="M14 14 L30 30 M30 14 L14 30" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/>';
				break;
			case 'warn':
				$mark = '<path d="M22 11 V26" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/><circle cx="22" cy="32" r="2.25" fill="#fff"/>';
				break;
			case 'info':
				$mark = '<circle cx="22" cy="13" r="2.25" fill="#fff"/><path d="M22 20 V32" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/>';
				break;
			case 'ok':
			default:
				$mark = '<path d="M11.5 22.5 L18.5 29.5 L32.5 14.5" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>';
				break;
		}

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44"><circle cx="22" cy="22" r="22" fill="' . $colour . '"/>' . $mark . '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}

/**
 * Build a compact, self-contained metadata icon for Dompdf.
 *
 * Header metadata used to rely on a generic bullet. These path-based SVGs
 * provide semantic calendar, clock, person and contact marks without an icon
 * font or remote asset dependency.
 */
if ( ! function_exists( 'cb_pdf_meta_icon_data_uri' ) ) {
	function cb_pdf_meta_icon_data_uri( string $type, string $colour ): string {
		$type   = in_array( $type, [ 'period', 'generated', 'prepared_by', 'contact' ], true ) ? $type : 'period';
		$colour = preg_match( '/^#[0-9a-fA-F]{6}$/', $colour ) ? $colour : '#1e6fb8';

		switch ( $type ) {
			case 'generated':
				$art = '<circle cx="9" cy="9" r="6.5"/><path d="M9 5.4 V9 L11.8 10.8"/>';
				break;
			case 'prepared_by':
				$art = '<circle cx="9" cy="6.1" r="2.6"/><path d="M3.8 15.1 C4.4 11.9 6.1 10.5 9 10.5 C11.9 10.5 13.6 11.9 14.2 15.1"/>';
				break;
			case 'contact':
				$art = '<rect x="2.4" y="4.1" width="13.2" height="9.8" rx="1.4"/><path d="M3.3 5 L9 9.3 L14.7 5"/>';
				break;
			case 'period':
			default:
				$art = '<rect x="2.6" y="3.6" width="12.8" height="11.8" rx="1.5"/><path d="M5.5 2.4 V5.2 M12.5 2.4 V5.2 M2.8 7 H15.2"/>';
				break;
		}

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="' . $colour . '" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $art . '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}

/**
 * Resolve intrinsic SVG dimensions for PDF logo scaling.
 */
if ( ! function_exists( 'cb_pdf_svg_dimensions' ) ) {
	/** Return intrinsic SVG width/height without loading external resources. */
	function cb_pdf_svg_dimensions( string $svg ): array {
		if ( 1 !== preg_match( '/<svg\b[^>]*>/i', $svg, $match ) ) {
			return [ 0.0, 0.0 ];
		}

		$tag    = $match[0];
		$width  = 0.0;
		$height = 0.0;

		if ( preg_match( '/\bwidth\s*=\s*["\']\s*([0-9]*\.?[0-9]+)(?:px|pt|pc|in|cm|mm)?\s*["\']/i', $tag, $width_match ) ) {
			$width = (float) $width_match[1];
		}
		if ( preg_match( '/\bheight\s*=\s*["\']\s*([0-9]*\.?[0-9]+)(?:px|pt|pc|in|cm|mm)?\s*["\']/i', $tag, $height_match ) ) {
			$height = (float) $height_match[1];
		}

		$view_width = 0.0;
		$view_height = 0.0;
		if ( preg_match( '/\bviewBox\s*=\s*["\']([^"\']+)["\']/i', $tag, $viewbox_match ) ) {
			$parts = preg_split( '/[\s,]+/', trim( $viewbox_match[1] ) );
			if ( is_array( $parts ) && 4 === count( $parts ) ) {
				$view_width  = max( 0.0, (float) $parts[2] );
				$view_height = max( 0.0, (float) $parts[3] );
			}
		}

		if ( $width > 0 && $height > 0 ) {
			return [ $width, $height ];
		}
		if ( $view_width > 0 && $view_height > 0 ) {
			if ( $width > 0 ) {
				return [ $width, $width * ( $view_height / $view_width ) ];
			}
			if ( $height > 0 ) {
				return [ $height * ( $view_width / $view_height ), $height ];
			}
			return [ $view_width, $view_height ];
		}

		return [ 0.0, 0.0 ];
	}
}

/** Resolve a Dompdf-safe note glyph. */
if ( ! function_exists( 'cb_pdf_note_glyph' ) ) {
	function cb_pdf_note_glyph( string $type ): string {
		switch ( $type ) {
			case 'critical': return '×';
			case 'warn':     return '!';
			case 'info':     return 'i';
			case 'ok':
			default:         return '✓';
		}
	}
}

$status_colours = cb_status_colours( (string) ( $status['banner'] ?? 'ok' ) );

// Pre-calculate logo dimensions. Dompdf doesn't reliably honour CSS max-width
// + max-height on <img> tags, so we compute the scaled size in PHP and emit
// explicit width/height attributes. Logo gets a max bounding box of 200x55
// - reads well next to the 22pt title in this two-stripe header.
$logo_w = 0; $logo_h = 0;
if ( ! empty( $branding['logo_url'] ) ) {
	$logo_src = (string) $branding['logo_url'];
	$logo_iw  = 0.0;
	$logo_ih  = 0.0;

	if ( 0 === strpos( $logo_src, 'data:image/svg+xml' ) ) {
		$comma_pos = strpos( $logo_src, ',' );
		if ( false !== $comma_pos ) {
			$svg_bytes = base64_decode( substr( $logo_src, $comma_pos + 1 ), true );
			if ( is_string( $svg_bytes ) ) {
				[ $logo_iw, $logo_ih ] = cb_pdf_svg_dimensions( $svg_bytes );
			}
		}
	} elseif ( 0 === strpos( $logo_src, 'data:image/' ) ) {
		$comma_pos = strpos( $logo_src, ',' );
		if ( false !== $comma_pos ) {
			$bytes = base64_decode( substr( $logo_src, $comma_pos + 1 ), true );
			$info  = is_string( $bytes ) ? @getimagesizefromstring( $bytes ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( is_array( $info ) && isset( $info[0], $info[1] ) ) {
				$logo_iw = (float) $info[0];
				$logo_ih = (float) $info[1];
			}
		}
	} else {
		$info = @getimagesize( $logo_src ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( is_array( $info ) && isset( $info[0], $info[1] ) ) {
			$logo_iw = (float) $info[0];
			$logo_ih = (float) $info[1];
		}
	}

	if ( $logo_iw > 0 && $logo_ih > 0 ) {
		$max_w  = 200.0;
		$max_h  = 55.0;
		$scale  = min( $max_w / $logo_iw, $max_h / $logo_ih, 1.0 );
		$logo_w = max( 1, (int) round( $logo_iw * $scale ) );
		$logo_h = max( 1, (int) round( $logo_ih * $scale ) );
	}
}
?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( sprintf( 'Maintenance Report - %s - %s tot %s', $site_title, $period_start, $period_end ) ); ?></title>
<style>
@page { margin: 45px 40px 55px 40px; }

body {
	font-family: "DejaVu Sans", sans-serif;
	font-size: 9.5pt;
	color: #2c2f33;
	line-height: 1.45;
	margin: 0;
	padding: 0;
}

/* ── Header ─────────────────────────────────────────────────────────── */
.report-header { width: 100%; border-collapse: collapse; }
.report-header td { border: none; padding: 0; vertical-align: middle; }

/* Strook 1: 50/50 - logo links / title rechts, ontmoeten elkaar in het midden */
.header-row1 { width: 100%; border-collapse: collapse; }
.header-row1 td { border: none; padding: 0; vertical-align: middle; height: 50pt; }
.header-logo { width: 50%; text-align: left; }
.header-logo img { vertical-align: middle; }
.header-title { width: 50%; text-align: right; vertical-align: middle; }
.header-title h1 {
	font-size: 22pt; margin: 0; color: <?php echo esc_attr( $accent ); ?>;
	font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt;
	white-space: nowrap; line-height: 1; vertical-align: middle;
}

.header-divider {
	border-bottom: 1px solid #d0d4d9;
	margin: 14px 0 14px 0;
}

/* Strook 2: 50/50 - site-info links / meta-box rechts, ontmoeten elkaar in het midden */
.header-row2 { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
.header-row2 td { border: none; padding: 0; vertical-align: middle; }
.header-site { width: 50%; vertical-align: middle; }
.header-site .site-name {
	font-size: 16pt; font-weight: bold; color: #2c2f33;
	margin: 0 0 4px 0;
}
.header-site .site-url {
	font-size: 9.5pt; color: <?php echo esc_attr( $accent ); ?>;
	margin: 0;
}

/* Meta-box: bordered + rounded right-side block */
.header-meta {
	width: 50%; vertical-align: middle;
	padding-left: 18px;
}
.meta-box {
	border: 1px solid #d0d4d9; border-radius: 6px;
	padding: 10px 14px 14px 14px;
}
.meta-box .meta-table { width: 100%; border-collapse: collapse; }
.meta-box .meta-table td {
	padding: 0; border: none; vertical-align: middle;
	font-size: 9pt; height: 26px; line-height: 1.2;
}
/* Symmetric outer spacing: first row has no top padding, last row has no
 * bottom padding - the meta-box container's own 10px padding produces equal
 * top and bottom whitespace around the rows. */
.meta-box .meta-table tr + tr td { padding-top: 5px; }
.meta-box .meta-icon { width: 32px; text-align: center; font-size: 0; line-height: 0; }
.meta-box .meta-icon .meta-symbol { display: inline-block; position: relative; top: 1px; width: 16px; height: 16px; margin: 0; border: 0; vertical-align: middle; }
.meta-box .meta-key { width: 90px; color: #2c2f33; font-weight: bold; }
.meta-box .meta-val { color: #2c2f33; }

/* ── Status banner ──────────────────────────────────────────────────── */
.status-banner {
	width: 100%; border-collapse: separate; border-spacing: 0;
	background: <?php echo esc_attr( $status_colours['bg'] ); ?>;
	border: 1px solid <?php echo esc_attr( $status_colours['border'] ); ?>;
	border-radius: 6px;
	margin-bottom: 18px;
}
.status-banner > tbody > tr > td.status-half {
	width: 50%; padding: 12px 14px; border: none; vertical-align: middle;
}
.status-banner > tbody > tr > td.status-half-right {
	border-left: 1px solid <?php echo esc_attr( $status_colours['border'] ); ?>;
}
.status-half-inner { width: 100%; border-collapse: collapse; }
.status-half-inner td { padding: 0; border: none; vertical-align: middle; }
.status-half-inner .status-icon-cell { width: 70px; text-align: center; padding: 0; }
.status-half-inner .status-icon-cell .status-icon { display: block; width: 44px; height: 44px; margin: 0 auto; border: 0; }
.status-headline {
	font-size: 12pt; font-weight: bold;
	color: <?php echo esc_attr( $status_colours['fg'] ); ?>;
	margin: 0 0 3px 0;
	line-height: 1.15;
}
.status-subline { font-size: 9pt; color: #2c2f33; margin: 0; line-height: 1.3; }

/* ── Section heading ────────────────────────────────────────────────── */
.section-heading {
	font-size: 9.5pt; font-weight: bold; color: #2c2f33;
	letter-spacing: 0.5pt; margin: 18px 0 8px 0; text-transform: uppercase;
}

/* ── KPI strip ──────────────────────────────────────────────────────── */
.kpi-strip {
	width: 100%; border-collapse: separate; border-spacing: 6px 0;
	margin-bottom: 16px; margin-left: -6px; margin-right: -6px;
}
.kpi-cell {
	border: 1px solid #d0d4d9; border-radius: 6px; padding: 12px 8px;
	text-align: center; vertical-align: top; width: 20%;
}
.kpi-number {
	font-size: 24pt; font-weight: bold;
	color: <?php echo esc_attr( $accent ); ?>;
	line-height: 1; margin-bottom: 4px;
}
.kpi-label { font-size: 9pt; font-weight: bold; color: #2c2f33; margin-bottom: 8px; }
.kpi-breakdown { font-size: 8pt; color: #6a6e73; line-height: 1.35; }

/* ── Two-column row (Current State + Notes / Security + Backups) ────── */
.two-col { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
.two-col > tbody > tr > td { border: none; padding: 0; vertical-align: top; width: 50%; }
.two-col > tbody > tr > td.col-left  { padding-right: 6px; }
.two-col > tbody > tr > td.col-right { padding-left: 6px; }

/* ── Current State table ────────────────────────────────────────────── */
.state-table {
	width: 100%;
	border-collapse: separate; border-spacing: 0;
	border: 1px solid #d0d4d9; border-radius: 6px;
}
.state-table th, .state-table td {
	border: none;
	border-bottom: 1px solid #d0d4d9;
	padding: 7px 9px;
	text-align: left; vertical-align: middle; font-size: 9pt;
}
.state-table th + th, .state-table td + td { border-left: 1px solid #d0d4d9; }
.state-table tr:last-child td { border-bottom: none; }
.state-table .state-label { font-weight: bold; width: 38%; color: #2c2f33; }
.state-table .state-status { width: 30%; }
.state-table .state-status .check { font-weight: bold; }
.state-table .state-detail { color: #555; }

/* ── Notes box ──────────────────────────────────────────────────────── */
.notes-box {
	border: 1px solid #d0d4d9; border-radius: 6px;
	width: 100%;
	border-collapse: separate; border-spacing: 0;
}
.notes-list { width: 100%; border-collapse: collapse; }
.notes-list td { border: none; padding: 8px 9px; vertical-align: top; font-size: 9pt; }
.notes-list .note-icon-cell { width: 24px; padding-right: 0; vertical-align: top; padding-top: 14px; }
.notes-list .note-icon-cell .note-icon { display: block; width: 16px; height: 16px; margin: 0; border: 0; }
.notes-list td.note-text-cell { vertical-align: top; }
.notes-list .note-title { font-weight: bold; margin-bottom: 1px; }
.notes-list .note-body { color: #555; font-size: 8.5pt; }

/* ── Footer (per-page positioning) ──────────────────────────────────── */
/* Pragmatic conservative width - DomPDF's @page margin interpretation is
 * inconsistent across versions, so we use values comfortably inside the
 * content area to guarantee the footer never overflows. */
.page-footer {
	position: fixed;
	bottom: 14pt; left: 50pt;
	width: 485pt;
	border-top: 1px solid #d0d4d9; padding-top: 6px;
	font-size: 8pt; color: #6a6e73;
}
.page-footer-table { width: 100%; border-collapse: collapse; }
.page-footer-table td { border: none; padding: 0; }
.page-footer-table .footer-right { text-align: right; padding-right: 6pt; }
.page-footer a { color: <?php echo esc_attr( $accent ); ?>; text-decoration: none; }

/* Inline page numbering - Dompdf substitutes counter(page)/counter(pages)
 * via CSS pseudo-element content. Lets the page number live on the same line
 * as the rest of the footer. */
.pagenum-current::before { content: counter(page); }
.pagenum-total::before   { content: counter(pages); }

/* ── Detail page (page 2+) ──────────────────────────────────────────── */
.page-break-before { page-break-before: always; }
.section-intro { font-size: 9pt; color: #555; margin: 0 0 14px 0; }
.section-title {
	font-size: 10pt; font-weight: bold; color: #2c2f33;
	margin: 16px 0 6px 0; letter-spacing: 0.4pt; text-transform: uppercase;
}
.section-title .section-count {
	color: #6a6e73; font-weight: normal; letter-spacing: 0; text-transform: none;
}

.activity-table {
	width: 100%; margin-bottom: 4px; table-layout: fixed;
	border-collapse: separate; border-spacing: 0;
	border: 1px solid #d0d4d9; border-radius: 6px;
}
.activity-table th, .activity-table td {
	border: none;
	border-bottom: 1px solid #d0d4d9;
	padding: 6px 8px;
	text-align: left; font-size: 8.5pt; vertical-align: top;
	word-wrap: break-word;
}
.activity-table th + th, .activity-table td + td { border-left: 1px solid #d0d4d9; }
.activity-table tr:last-child td { border-bottom: none; }
.activity-table th { background: #f4f6f8; font-weight: bold; color: #2c2f33; }

/* Per-corner radii so the header-row background respects the outer rounded
 * corners - without these, the grey TH background bleeds into the rounded
 * top-left and top-right corners of the table. */
.activity-table tr:first-child th:first-child { border-top-left-radius: 6px; }
.activity-table tr:first-child th:last-child  { border-top-right-radius: 6px; }
.activity-table tr:last-child td:first-child  { border-bottom-left-radius: 6px; }
.activity-table tr:last-child td:last-child   { border-bottom-right-radius: 6px; }

/* ── Security & Backup boxes ────────────────────────────────────────── */
.summary-box { border: 1px solid #d0d4d9; border-radius: 6px; padding: 12px 14px; min-height: 100px; }
.summary-box .summary-title {
	font-size: 9pt; font-weight: bold;
	color: <?php echo esc_attr( $accent ); ?>;
	letter-spacing: 0.4pt; text-transform: uppercase; margin-bottom: 8px;
}
.summary-box .summary-headline {
	font-size: 9.5pt; font-weight: bold; color: #2e7d3a; margin-bottom: 6px;
}
.summary-box .summary-line { font-size: 8.5pt; color: #2c2f33; margin: 2px 0; }
</style>
</head>
<body>

<!-- ════════════════════════ PAGINA 1 ════════════════════════ -->

<!-- Strook 1: logo links / titel rechts -->
<table class="header-row1">
	<tr>
		<td class="header-logo">
			<?php if ( ! empty( $branding['logo_url'] ) && $logo_w > 0 ) : ?>
				<?php /* esc_attr (not esc_url) - esc_url strips data: URIs since 'data' is not in its protocol allowlist. The branding URL is either an absolute https:// URL or a base64 data: URI from ReportBranding::for_pdf(); both are safe in an attribute. */ ?>
				<img src="<?php echo esc_attr( $branding['logo_url'] ); ?>" width="<?php echo (int) $logo_w; ?>" height="<?php echo (int) $logo_h; ?>" alt="">
			<?php endif; ?>
		</td>
		<td class="header-title">
			<h1><?php esc_html_e( 'Maintenance Report', 'core-blueprint' ); ?></h1>
		</td>
	</tr>
</table>

<!-- Divider tussen strook 1 en 2 -->
<div class="header-divider"></div>

<!-- Strook 2: site info links / meta-box rechts, beide vertical-center -->
<table class="header-row2">
	<tr>
		<td class="header-site">
			<p class="site-name"><?php echo esc_html( $site_title ); ?></p>
			<p class="site-url"><?php echo esc_html( $site_url ); ?></p>
		</td>
		<td class="header-meta">
			<div class="meta-box">
				<table class="meta-table">
					<tr>
						<td class="meta-icon"><img class="meta-symbol" src="<?php echo esc_attr( cb_pdf_meta_icon_data_uri( 'period', $accent ) ); ?>" width="16" height="16" alt=""></td>
						<td class="meta-key"><?php esc_html_e( 'Period', 'core-blueprint' ); ?>:</td>
						<td class="meta-val"><?php echo esc_html( $period_start . ' t/m ' . $period_end ); ?></td>
					</tr>
					<tr>
						<td class="meta-icon"><img class="meta-symbol" src="<?php echo esc_attr( cb_pdf_meta_icon_data_uri( 'generated', $accent ) ); ?>" width="16" height="16" alt=""></td>
						<td class="meta-key"><?php esc_html_e( 'Generated', 'core-blueprint' ); ?>:</td>
						<td class="meta-val"><?php echo esc_html( get_date_from_gmt( $generated_at, 'd-m-Y H:i' ) ); ?></td>
					</tr>
					<?php if ( ! empty( $branding['provider_name'] ) ) : ?>
						<tr>
							<td class="meta-icon"><img class="meta-symbol" src="<?php echo esc_attr( cb_pdf_meta_icon_data_uri( 'prepared_by', $accent ) ); ?>" width="16" height="16" alt=""></td>
							<td class="meta-key"><?php esc_html_e( 'Prepared by', 'core-blueprint' ); ?>:</td>
							<td class="meta-val"><?php echo esc_html( $branding['provider_name'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( ! empty( $branding['provider_contact'] ) ) : ?>
						<tr>
							<td class="meta-icon"><img class="meta-symbol" src="<?php echo esc_attr( cb_pdf_meta_icon_data_uri( 'contact', $accent ) ); ?>" width="16" height="16" alt=""></td>
							<td class="meta-key"><?php esc_html_e( 'Contact', 'core-blueprint' ); ?>:</td>
							<td class="meta-val"><?php echo esc_html( $branding['provider_contact'] ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>
		</td>
	</tr>
</table>

<!-- Status banner ────────────────────────────────────────────────────── -->
<?php
$status_level    = (string) ( $status['banner'] ?? 'ok' );
$status_glyph    = cb_pdf_status_glyph( $status_level );
$status_fg       = $status_colours['fg'];
$status_icon_uri = cb_pdf_status_icon_data_uri( $status_level, $status_fg );
?>
<table class="status-banner">
	<tr>
		<td class="status-half">
			<table class="status-half-inner">
				<tr>
					<td class="status-icon-cell">
						<img class="status-icon" src="<?php echo esc_attr( $status_icon_uri ); ?>" width="44" height="44" alt="<?php echo esc_attr( $status_glyph ); ?>">
					</td>
					<td>
						<p class="status-headline"><?php echo esc_html( (string) $status['headline'] ); ?></p>
						<p class="status-subline"><?php echo esc_html( (string) $status['subline'] ); ?></p>
					</td>
				</tr>
			</table>
		</td>
		<td class="status-half status-half-right">
			<table class="status-half-inner">
				<tr>
					<td class="status-icon-cell">
						<img class="status-icon" src="<?php echo esc_attr( $status_icon_uri ); ?>" width="44" height="44" alt="<?php echo esc_attr( $status_glyph ); ?>">
					</td>
					<td>
						<p class="status-headline"><?php echo esc_html( (string) $status['detail_headline'] ); ?></p>
						<p class="status-subline"><?php echo esc_html( (string) $status['detail_subline'] ); ?></p>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- KPI strip ────────────────────────────────────────────────────────── -->
<p class="section-heading"><?php esc_html_e( 'Maintenance summary', 'core-blueprint' ); ?></p>

<table class="kpi-strip">
	<tr>
		<?php
		// Order: Updates Performed → Updates Pending → Security Issues →
		// Backups Created → Active Users. Pending is shown next to Performed
		// so the reader can compare "what was done this period" against
		// "what's still outstanding" at a glance.
		$kpi_order = [
			'updates_performed' => __( 'Updates Performed', 'core-blueprint' ),
			'updates_pending'   => __( 'Updates Pending',   'core-blueprint' ),
			'security_issues'   => __( 'Security Issues',   'core-blueprint' ),
			'backups_created'   => __( 'Backups Created',   'core-blueprint' ),
			'active_users'      => __( 'Active Users',      'core-blueprint' ),
		];
		foreach ( $kpi_order as $key => $label ) :
			// security_issues is conditional - present only when a security
			// addon supplied data via cb_core_report_security. When CB Base
			// runs alone the key is absent and the strip shows 4 tiles.
			if ( ! isset( $kpis[ $key ] ) ) {
				continue;
			}
			$kpi   = $kpis[ $key ];
			$lines = $kpi['breakdown'] ?? [];
			?>
			<td class="kpi-cell">
				<div class="kpi-number"><?php echo (int) $kpi['count']; ?></div>
				<div class="kpi-label"><?php echo esc_html( $label ); ?></div>
				<div class="kpi-breakdown">
					<?php foreach ( (array) $lines as $line ) : ?>
						<div><?php echo esc_html( (string) $line ); ?></div>
					<?php endforeach; ?>
				</div>
			</td>
		<?php endforeach; ?>
	</tr>
</table>

<!-- Current State + Notes ──────────────────────────────────────────────
     If notes is empty, render the Current State table at full width - no
     redundant "All good!" placeholder note. -->
<?php if ( ! empty( $notes ) ) : ?>
<table class="two-col">
	<tr>
		<td class="col-left">
			<p class="section-heading"><?php esc_html_e( 'Current State', 'core-blueprint' ); ?></p>
			<table class="state-table">
				<?php
				$state_order = [ 'wp_core', 'theme', 'plugins', 'php', 'database', 'website' ];
				foreach ( $state_order as $key ) :
					$item = $site_state[ $key ] ?? null;
					if ( ! $item ) {
						continue;
					}
					$row_colours = cb_status_colours( (string) ( $item['status'] ?? 'ok' ) );
					?>
					<tr>
						<td class="state-label"><?php echo esc_html( (string) $item['label'] ); ?></td>
						<td class="state-status" style="color: <?php echo esc_attr( $row_colours['fg'] ); ?>;">
							<span class="check">✓</span> <?php echo esc_html( (string) $item['state'] ); ?>
						</td>
						<td class="state-detail"><?php echo esc_html( (string) $item['detail'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
		<td class="col-right">
			<p class="section-heading"><?php esc_html_e( 'Notes / Observations', 'core-blueprint' ); ?></p>
			<table class="notes-box">
				<tr>
					<td style="padding: 0;">
						<table class="notes-list">
							<?php foreach ( $notes as $note ) :
								$note_type    = (string) ( $note['type'] ?? 'ok' );
								$nc           = cb_status_colours( $note_type );
								$note_glyph   = cb_pdf_note_glyph( $note_type );
								$note_icon_uri = cb_pdf_status_icon_data_uri( $note_type, $nc['fg'] );
								?>
								<tr>
									<td class="note-icon-cell">
										<img class="note-icon" src="<?php echo esc_attr( $note_icon_uri ); ?>" width="16" height="16" alt="<?php echo esc_attr( $note_glyph ); ?>">
									</td>
									<td class="note-text-cell">
										<div class="note-title" style="color: <?php echo esc_attr( $nc['fg'] ); ?>;"><?php echo esc_html( (string) $note['title'] ); ?></div>
										<div class="note-body"><?php echo esc_html( (string) $note['body'] ); ?></div>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php else : ?>
<p class="section-heading"><?php esc_html_e( 'Current State', 'core-blueprint' ); ?></p>
<table class="state-table">
	<?php
	$state_order = [ 'wp_core', 'theme', 'plugins', 'php', 'database', 'website' ];
	foreach ( $state_order as $key ) :
		$item = $site_state[ $key ] ?? null;
		if ( ! $item ) {
			continue;
		}
		$row_colours = cb_status_colours( (string) ( $item['status'] ?? 'ok' ) );
		?>
		<tr>
			<td class="state-label"><?php echo esc_html( (string) $item['label'] ); ?></td>
			<td class="state-status" style="color: <?php echo esc_attr( $row_colours['fg'] ); ?>;">
				<span class="check">✓</span> <?php echo esc_html( (string) $item['state'] ); ?>
			</td>
			<td class="state-detail"><?php echo esc_html( (string) $item['detail'] ); ?></td>
		</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>

<!-- ════════════════════════ PAGINA 2 ════════════════════════ -->
<div class="page-break-before"></div>

<p class="section-heading"><?php esc_html_e( 'Maintenance Details', 'core-blueprint' ); ?></p>
<p class="section-intro"><?php esc_html_e( 'Overview of all maintenance actions performed in this period.', 'core-blueprint' ); ?></p>

<?php
cb_render_activity_section( $sections['theme_updates']        ?? [], 'theme',  $accent );
cb_render_activity_section( $sections['plugin_updates']       ?? [], 'plugin', $accent );
cb_render_activity_section( $sections['plugin_installations'] ?? [], 'plugin', $accent );
cb_render_activity_section( $sections['plugin_removals']      ?? [], 'plugin', $accent );
cb_render_activity_section( $sections['core_updates']         ?? [], 'core',   $accent );

$any_section = false;
foreach ( $sections as $s ) {
	if ( ( $s['count'] ?? 0 ) > 0 ) { $any_section = true; break; }
}
if ( ! $any_section ) :
	?>
	<p class="section-intro" style="font-style: italic;"><?php esc_html_e( 'No maintenance activity recorded in this period.', 'core-blueprint' ); ?></p>
<?php endif; ?>

<!-- Security + Backups two-column ──────────────────────────────────────
     When CB Base runs without a security data source ($security === null),
     the Security Activity box is omitted and the Backups box spans full
     width. Listeners on cb_core_report_security can populate $security
     to bring the side-by-side layout back. -->
<table class="two-col" style="margin-top: 18px;">
	<tr>
		<?php if ( null !== $security ) : ?>
			<td class="col-left">
				<div class="summary-box">
					<div class="summary-title"><?php esc_html_e( 'Security Activity', 'core-blueprint' ); ?></div>
					<?php if ( (int) ( $security['detected'] ?? 0 ) === 0 ) : ?>
						<div class="summary-headline"><?php echo esc_html( (string) $security['summary'] ); ?></div>
					<?php else : ?>
						<div class="summary-headline" style="color:#c0392b;">
							<?php echo esc_html( sprintf(
								/* translators: %d: number of security issues. */
								_n( '%d security issue detected.', '%d security issues detected.', (int) $security['detected'], 'core-blueprint' ),
								(int) $security['detected']
							) ); ?>
						</div>
					<?php endif; ?>
					<?php if ( null !== ( $security['blocked_attempts'] ?? null ) ) : ?>
						<div class="summary-line">
							<?php echo esc_html( sprintf(
								/* translators: %d: number of blocked login attempts. */
								_n( '%d login attempt was blocked by the firewall.', '%d login attempts were blocked by the firewall.', (int) $security['blocked_attempts'], 'core-blueprint' ),
								(int) $security['blocked_attempts']
							) ); ?>
						</div>
					<?php endif; ?>
					<?php if ( (int) ( $security['brute_force'] ?? 0 ) === 0 ) : ?>
						<div class="summary-line"><?php esc_html_e( 'No successful brute force attacks.', 'core-blueprint' ); ?></div>
					<?php endif; ?>
				</div>
			</td>
			<td class="col-right">
		<?php else : ?>
			<td colspan="2">
		<?php endif; ?>
				<div class="summary-box">
					<div class="summary-title"><?php esc_html_e( 'Backups', 'core-blueprint' ); ?></div>
					<div class="summary-headline" style="color:#2c2f33;">
						<?php echo esc_html( sprintf(
							/* translators: %d: number of backups created. */
							_n( '%d backup was created in this period.', '%d backups were created in this period.', (int) $backups['count'], 'core-blueprint' ),
							(int) $backups['count']
						) ); ?>
					</div>
					<?php
					$backup_last_at = (string) ( $backups['last_at'] ?? '' );
					if ( '' === $backup_last_at ) {
						$backup_last_at = (string) ( $backups['last_at_overall'] ?? '' );
					}
					?>
					<?php if ( '' !== $backup_last_at ) : ?>
						<div class="summary-line">
							<?php
							printf(
								/* translators: %s: timestamp of the last backup. */
								esc_html__( 'Last backup: %s', 'core-blueprint' ),
								esc_html( wp_date( 'd-m-Y H:i', (int) strtotime( $backup_last_at . ' UTC' ), wp_timezone() ) )
							);
							?>
						</div>
					<?php endif; ?>
					<div class="summary-line"><?php echo esc_html( (string) $backups['summary'] ); ?></div>
				</div>
			</td>
	</tr>
</table>

<?php
$site_host = parse_url( $site_url, PHP_URL_HOST );
if ( ! $site_host ) {
	$site_host = preg_replace( '#^https?://#i', '', $site_url );
}
?>
<!-- Single-line footer: short generated-for text on the left, page X of Y on
     the right. Page numbers via Dompdf's CSS counter() - works inline so the
     numbers sit on the same row as the rest of the footer text. -->
<div class="page-footer">
	<table class="page-footer-table">
		<tr>
			<td class="footer-left">
				<?php
				printf(
					/* translators: %s: site host (e.g., example.nl). */
					esc_html__( 'Report generated for %s', 'core-blueprint' ),
					'<span style="color:' . esc_attr( $accent ) . ';">' . esc_html( (string) $site_host ) . '</span>'
				);
				?>
			</td>
			<td class="footer-right">
				<?php esc_html_e( 'Page', 'core-blueprint' ); ?>
				<span class="pagenum-current"></span>
			</td>
		</tr>
	</table>
</div>


</body>
</html>
