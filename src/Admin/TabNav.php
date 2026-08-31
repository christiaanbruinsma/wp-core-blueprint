<?php
declare(strict_types=1);
/**
 * TabNav - static rendering helpers for tab navigation on admin pages.
 *
 * The {@see \CB\Core\Admin\Tabbed} trait provides instance methods for
 * pages that are built as class-instance renderers (Logs, Safeguards).
 * Tab renderers in the Logs registry are static callables though, so
 * they need the same helpers in a form they can actually call. This
 * class provides them. {@see Tabbed} remains in place for the page-class
 * style; new tab renderers prefer {@see TabNav}.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class TabNav {

	/**
	 * Inject a WP-native nav-tab-wrapper right after the first </h1> in
	 * the given HTML blob. Falls back to prepending when no H1 is present.
	 *
	 * @param string              $html       Rendered tab content.
	 * @param string              $page_slug  Admin page slug used in link URLs.
	 * @param string              $active_tab Currently-active tab slug.
	 * @param array<string,string> $tabs      Map of tab-slug → translated label.
	 */
	public static function inject( string $html, string $page_slug, string $active_tab, array $tabs ): string {
		$nav = self::build( $page_slug, $active_tab, $tabs );

		$pos = stripos( $html, '</h1>' );
		if ( false !== $pos ) {
			$insert_at = $pos + strlen( '</h1>' );
			return substr( $html, 0, $insert_at ) . "\n" . $nav . substr( $html, $insert_at );
		}
		return $nav . $html;
	}

	/**
	 * Build the nav-tab-wrapper HTML. Public so renderers can position the
	 * nav explicitly instead of relying on post-hoc injection.
	 */
	public static function build( string $page_slug, string $active_tab, array $tabs ): string {
		$out = '<nav class="nav-tab-wrapper cb-core-tab-wrapper" aria-label="' . esc_attr__( 'Sections', 'core-blueprint' ) . '">';
		foreach ( $tabs as $id => $label ) {
			$url     = admin_url( 'admin.php?page=' . $page_slug . '&tab=' . $id );
			$classes = 'nav-tab' . ( $id === $active_tab ? ' nav-tab-active' : '' );
			$out    .= sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $classes ),
				esc_html( $label )
			);
		}
		$out .= '</nav>';
		return $out;
	}

	/**
	 * Render a standard "subsystem not loaded" placeholder. Used by tab
	 * renderers when a dependency isn't available.
	 */
	public static function render_subsystem_missing( string $message ): void {
		echo '<div class="wrap"><p>' . esc_html( $message ) . '</p></div>';
	}
}
