<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	public static function boot(): void {
		// R1 intentionally registers no document types or layout capabilities.
		// Concrete registrations begin only when Fixed/Flow behavior exists.
	}
}
