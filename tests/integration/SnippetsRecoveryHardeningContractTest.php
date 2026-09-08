<?php
declare(strict_types=1);

final class CB_Base_Snippets_Recovery_Hardening_Contract_Test extends WP_UnitTestCase {

	public function test_failed_save_recovery_does_not_persist_raw_code_server_side(): void {
		$actions = (string) file_get_contents( CB_CORE_DIR . 'src/Snippets/Admin/Actions.php' );
		$page    = (string) file_get_contents( CB_CORE_DIR . 'src/Snippets/Admin/Page.php' );

		self::assertStringNotContainsString( 'DRAFT_PREFIX', $actions );
		self::assertStringNotContainsString( 'set_draft(', $actions );
		self::assertStringNotContainsString( 'pull_draft(', $actions );
		self::assertStringNotContainsString( 'Actions::pull_draft(', $page );
	}

	public function test_editor_recovery_is_tab_local_bounded_and_one_shot(): void {
		$script = (string) file_get_contents( CB_CORE_DIR . 'assets/js/features/snippets.js' );

		self::assertStringContainsString( 'window.sessionStorage.setItem', $script );
		self::assertStringContainsString( 'window.sessionStorage.removeItem', $script );
		self::assertStringContainsString( 'const draftMaxAge = 5 * 60 * 1000;', $script );
		self::assertStringContainsString( 'clearRecoveryDraft();', $script );
		self::assertStringContainsString( "editor?.codemirror?.save?.();", $script );
	}
}
