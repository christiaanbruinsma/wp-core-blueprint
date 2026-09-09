<?php
declare(strict_types=1);

namespace CB\Core\Design;

use CB\Core\Design\Profile\Document\Bootstrap as DocumentBootstrap;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	public static function boot(): void {
		DocumentBootstrap::boot();
	}
}
