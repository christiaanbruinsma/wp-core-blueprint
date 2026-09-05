<?php
declare(strict_types=1);

final class CB_Base_Modal_Confirm_Check_Contract_Test extends WP_UnitTestCase {

    private function modal_source(): string {
        $source = file_get_contents( CB_CORE_DIR . 'assets/js/core/modal.js' );
        self::assertIsString( $source );
        return $source;
    }

    public function test_confirm_check_is_a_native_required_gate_with_fail_closed_label_validation(): void {
        $source = $this->modal_source();

        self::assertStringContainsString( 'confirmCheck: {', $source );
        self::assertStringContainsString( "confirmCheckInput.type = 'checkbox';", $source );
        self::assertStringContainsString( 'confirmCheckInput.checked = false;', $source );
        self::assertStringContainsString( 'checkLabel.htmlFor = confirmCheckInput.id;', $source );
        self::assertStringContainsString( 'checkLabel.textContent = confirmCheck.label;', $source );
        self::assertStringContainsString(
            'Core Blueprint modal confirmCheck.label must be a non-empty string.',
            $source
        );
    }

    public function test_confirm_button_availability_has_one_owner_and_includes_busy_lock(): void {
        $source = $this->modal_source();

        self::assertSame( 1, substr_count( $source, 'dialog._cbConfirmBtn.disabled =' ) );
        self::assertStringNotContainsString( 'confirmBtn.disabled =', $source );
        self::assertStringNotContainsString( 'button.disabled =', $source );
        self::assertStringContainsString(
            'dialog._cbConfirmBtn.disabled = confirmPending || ! allGatesValid();',
            $source
        );
    }

    public function test_all_active_modal_gates_feed_the_central_evaluator(): void {
        $source = $this->modal_source();

        self::assertStringContainsString( "const typedValid = mode !== 'typed'", $source );
        self::assertStringContainsString( 'const inputValid = ! inputRequired', $source );
        self::assertStringContainsString( 'const checkValid = ! dialog._cbConfirmCheck || dialog._cbConfirmCheck.checked;', $source );
        self::assertStringContainsString( 'return typedValid && inputValid && checkValid;', $source );
        self::assertStringContainsString(
            "dialog._cbConfirmCheck.addEventListener( 'change', updateConfirmAvailability );",
            $source
        );
        self::assertStringContainsString(
            "dialog._cbInput.addEventListener( 'input', updateConfirmAvailability );",
            $source
        );
    }

    public function test_async_completion_releases_busy_lock_then_re_evaluates_current_gates(): void {
        $source = $this->modal_source();
        $finally = strpos( $source, '} finally {' );

        self::assertNotFalse( $finally );
        $finally_block = substr( $source, $finally, 450 );
        self::assertIsString( $finally_block );

        $pending_reset = strpos( $finally_block, 'confirmPending = false;' );
        $reevaluate = strpos( $finally_block, 'updateConfirmAvailability();' );

        self::assertNotFalse( $pending_reset );
        self::assertNotFalse( $reevaluate );
        self::assertLessThan( $reevaluate, $pending_reset );
    }

    public function test_confirm_check_has_both_existing_modal_presentations(): void {
        $core_css = file_get_contents( CB_CORE_DIR . 'assets/css/components/modals.css' );
        $native_css = file_get_contents( CB_CORE_DIR . 'assets/css/components/modals-native.css' );

        self::assertIsString( $core_css );
        self::assertIsString( $native_css );
        self::assertStringContainsString( '.cb-core-modal__confirm-check', $core_css );
        self::assertStringContainsString( '.cb-core-modal__confirm-check', $native_css );
    }
}
