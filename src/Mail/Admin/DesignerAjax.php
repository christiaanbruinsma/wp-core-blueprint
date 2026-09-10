<?php
declare(strict_types=1);
/**
 * Live Mail Designer preview endpoint.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Mail\Admin;

use CB\Core\Mail\Designer\Renderer;
use CB\Core\Mail\Designer\TemplateRegistry;
use CB\Core\Mail\DesignerState;

defined( 'ABSPATH' ) || exit;

final class DesignerAjax {
	private const JSON_DEPTH = 128;

	public static function boot(): void {
		add_action( 'wp_ajax_cb_core_mail_designer_preview', [ __CLASS__, 'preview' ] );
	}

	public static function preview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to preview mail templates.', 'core-blueprint' ) ], 403 );
		}
		check_ajax_referer( 'cb_core_mail_designer_preview', 'nonce' );

		if ( ! DesignerState::is_enabled() ) {
			wp_send_json_error( [ 'message' => __( 'Mail Designer is disabled.', 'core-blueprint' ) ], 409 );
		}

		$template_id = isset( $_POST['template_id'] ) ? (string) wp_unslash( $_POST['template_id'] ) : '';
		if ( null === TemplateRegistry::get( $template_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Unknown mail template.', 'core-blueprint' ) ], 404 );
		}

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$json = isset( $_POST['project_json'] ) ? (string) wp_unslash( $_POST['project_json'] ) : '';
		try {
			$project = json_decode( $json, true, self::JSON_DEPTH, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			$project = null;
		}
		if ( ! is_array( $project ) || array_is_list( $project ) ) {
			wp_send_json_error( [ 'message' => __( 'The mail design is invalid.', 'core-blueprint' ) ], 422 );
		}

		$preview = Renderer::preview( $template_id, $project, $subject );
		if ( null === $preview ) {
			wp_send_json_error( [ 'message' => __( 'The mail preview could not be rendered.', 'core-blueprint' ) ], 422 );
		}

		wp_send_json_success(
			[
				'subject' => $preview['subject'],
				'html'    => $preview['html'],
			],
			200,
			JSON_INVALID_UTF8_SUBSTITUTE
		);
	}

	private function __construct() {}
}
