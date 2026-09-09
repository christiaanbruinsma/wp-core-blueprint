<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	public static function boot(): void {
		// Document contracts are autoloaded on demand.
		// R3 adds Fixed behavior without global registries or consumer-owned design types.
	}
}
