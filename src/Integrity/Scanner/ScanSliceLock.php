<?php
declare(strict_types=1);

namespace CB\Core\Integrity\Scanner;

use function add_option;
use function bin2hex;
use function delete_option;
use function get_option;
use function is_array;
use function random_bytes;
use function sanitize_key;
use function time;

/**
 * Short execution lease for one Scanner slice.
 *
 * ScannerLock protects the whole logical job from other jobs. This lease solves
 * a different problem: duplicate/overlapping cron workers for the same job must
 * never process the same persisted cursor concurrently. A crashed PHP request
 * leaves the lease behind; it becomes reclaimable after a conservative TTL.
 */
final class ScanSliceLock {
	private const OPTION    = 'cb_core_integrity_scan_slice_lock';
	private const STALE_TTL = 300;

	/** Return a lease token, or null when another slice is still executing. */
	public static function acquire( string $job_id ): ?string {
		$job_id = sanitize_key( $job_id );
		if ( '' === $job_id ) {
			return null;
		}

		$token = bin2hex( random_bytes( 16 ) );
		$data  = [
			'token'       => $token,
			'job_id'      => $job_id,
			'acquired_at' => time(),
		];

		if ( add_option( self::OPTION, $data, '', false ) ) {
			return $token;
		}

		$current = self::current();

		// A previous job may have been cancelled/recovered while its PHP worker
		// was still unwinding. If the global long-lived lock now belongs to this
		// requested job, a slice lease from another job can no longer publish
		// state and is safe to replace immediately.
		$global = ScannerLock::current();
		if (
			$job_id === (string) ( $global['job_id'] ?? '' )
			&& $job_id !== (string) ( $current['job_id'] ?? '' )
		) {
			delete_option( self::OPTION );
			if ( add_option( self::OPTION, $data, '', false ) ) {
				return $token;
			}
			$current = self::current();
		}

		$age = time() - (int) ( $current['acquired_at'] ?? 0 );
		if ( $age > self::STALE_TTL ) {
			delete_option( self::OPTION );
			if ( add_option( self::OPTION, $data, '', false ) ) {
				return $token;
			}
		}

		return null;
	}

	public static function release( string $token ): void {
		$current = self::current();
		if ( '' !== $token && $token === (string) ( $current['token'] ?? '' ) ) {
			delete_option( self::OPTION );
		}
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}

	public static function current(): array {
		$value = get_option( self::OPTION, [] );
		return is_array( $value ) ? $value : [];
	}
}
