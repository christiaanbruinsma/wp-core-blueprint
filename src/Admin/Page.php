<?php
declare(strict_types=1);
/**
 * Page - interface for pages under the Core Blueprint admin menu.
 *
 * This interface plus PageRegistry is the normative public v1 contract for
 * pages contributed under the Core Blueprint admin menu. Registration owns
 * WordPress menu wiring and the Core Admin shell boundary.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Admin;
defined( 'ABSPATH' ) || exit;

interface Page {

	/**
	 * Globally unique lower-case kebab-case WordPress admin page slug.
	 * Base-owned slugs are reserved and cannot be claimed by extensions.
	 */
	public function slug(): string;

	/**
	 * Page title - translated. Shown in the browser tab and as the
	 * submenu label. Keep it short (1-3 words).
	 */
	public function title(): string;

	/**
	 * Menu title - translated. Defaults to title() when not overridden.
	 * Separate method so the submenu can show a shorter form while the
	 * page itself has a more descriptive heading.
	 */
	public function menu_title(): string;

	/**
	 * Required WordPress capability. Typically 'manage_options'.
	 */
	public function capability(): string;

	/**
	 * Submenu ordering position. Lower = earlier in the menu.
	 * Return null to append at the end (standard third-party behaviour).
	 *
	 * Public extension pages return null or a position >= 100. Positions 1-99
	 * are enforced as Base-owned. Null lets WordPress/Base append the page.
	 *
	 * Current Core Blueprint base positions:
	 *   10  Dashboard
	 *   20  Logs
	 *   22  Notes
	 *   25  Reports
	 *   30  Safeguards
	 *   90  Preferences
	 *   99  Extensions
	 */
	public function position(): ?int;

	/**
	 * Render the page. Called by WordPress when the admin_menu item
	 * is clicked. Should output directly (echo); return value unused.
	 */
	public function render(): void;
}
