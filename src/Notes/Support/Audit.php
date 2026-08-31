<?php
declare(strict_types=1);
namespace CB\Core\Notes\Support;

use CB\Core\Log\AuditLog;

defined( 'ABSPATH' ) || exit;

final class Audit {
    public static function log( string $event, array $context = [] ): void {
        AuditLog::log(
            $event,
            'info',
            $context
        );
    }
}
