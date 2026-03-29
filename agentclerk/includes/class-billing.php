<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Billing management for AgentClerk.
 *
 * Handles billing status polling (cron), grace period tracking,
 * admin notices for payment failures, lifetime license checkout + activation,
 * card update flow, and fee calculation.
 *
 * Fee schedule:
 *   BYOK:    1% per sale, $1.00 minimum
 *   TurnKey: 1.5% per sale, $1.99 minimum
 *   Lifetime: $0 (no fees)
 *
 * @since 1.0.0
 */
class AgentClerk_Billing {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Billing|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Billing
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Register hooks (no cron scheduling here -- that's in the activator).
	 */
	private function __construct() {
		add_action( 'agentclerk_poll_billing_status', array( $this, 'poll_status' ) );
		add_action( 'admin_notices', array( $this, 'show_billing_notices' ) );
		add_action( 'wp_ajax_agentclerk_lifetime_checkout', array( $this, 'lifetime_checkout' ) );
		add_action( 'wp_ajax_agentclerk_card_update', array( $this, 'card_update' ) );
		add_action( 'admin_init', array( $this, 'handle_license_return' ) );
		add_action( 'admin_init', array( $this, 'maybe_poll_status' ) );
	}

	/**
	 * Poll billing/license status on admin page load (throttled to every 5 min).
	 */
	public function maybe_poll_status() {
		if ( false !== get_transient( 'agentclerk_last_status_poll' ) ) {
			return;
		}
		set_transient( 'agentclerk_last_status_poll', 1, 5 * MINUTE_IN_SECONDS );
		$this->poll_status();
	}

	/**
	 * Poll the backend for current billing status (cron hook).
	 */
	public function poll_status() {
		$response = AgentClerk::backend_request( '/billing/status', array( 'method' => 'GET' ) );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data ) ) {
			return;
		}

		$status = isset( $data['billingStatus'] ) ? sanitize_text_field( $data['billingStatus'] ) : 'active';
		update_option( 'agentclerk_billing_status', $status );

		// License status from backend.
		if ( isset( $data['licenseStatus'] ) ) {
			$license_status = sanitize_text_field( $data['licenseStatus'] );
			update_option( 'agentclerk_license_status', $license_status );

			// Lifetime license holders have zero fees.
			if ( 'active' === $license_status ) {
				update_option( 'agentclerk_accrued_fees', 0 );
			}
		}
		if ( isset( $data['licenseKey'] ) ) {
			update_option( 'agentclerk_license_key', sanitize_text_field( $data['licenseKey'] ) );
		}

		if ( isset( $data['accruedFees'] ) ) {
			update_option( 'agentclerk_accrued_fees', floatval( $data['accruedFees'] ) );
		}
		if ( isset( $data['cardLast4'] ) ) {
			update_option( 'agentclerk_billing_card_last4', sanitize_text_field( $data['cardLast4'] ) );
		}
		if ( isset( $data['stripeCustomerId'] ) ) {
			update_option( 'agentclerk_stripe_customer_id', sanitize_text_field( $data['stripeCustomerId'] ) );
		}

		// Grace period tracking.
		if ( 'grace_period' === $status ) {
			$started        = strtotime( $data['gracePeriodStarted'] ?? '' );
			$days_remaining = $started ? 3 - (int) floor( ( time() - $started ) / 86400 ) : 3;
			update_option( 'agentclerk_grace_days_remaining', max( 0, $days_remaining ) );
		}

		// Suspension handling.
		if ( 'suspended' === $status ) {
			update_option( 'agentclerk_plugin_status', 'suspended' );
		}

		// Re-activate if billing is restored.
		if ( 'active' === $status && 'suspended' === get_option( 'agentclerk_plugin_status' ) ) {
			update_option( 'agentclerk_plugin_status', 'active' );
		}
	}

	/**
	 * Show admin notices for billing issues.
	 */
	public function show_billing_notices() {
		$status = get_option( 'agentclerk_billing_status', 'active' );

		if ( 'grace_period' === $status ) {
			$days = (int) get_option( 'agentclerk_grace_days_remaining', 3 );
			echo '<div class="notice notice-error"><p>';
			echo '<strong>' . esc_html__( 'AgentClerk payment failed.', 'agentclerk' ) . '</strong> ';
			printf(
				/* translators: %d: number of days remaining in grace period */
				esc_html__( 'Update your payment card within %d day(s) to avoid suspension.', 'agentclerk' ),
				$days
			);
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=agentclerk-sales' ) ) . '">';
			echo esc_html__( 'Update card &rarr;', 'agentclerk' );
			echo '</a>';
			echo '</p></div>';
		}

		if ( 'suspended' === $status ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>' . esc_html__( 'AgentClerk account suspended.', 'agentclerk' ) . '</strong> ';
			echo esc_html__( 'Your agent is offline. Please update your payment method to restore service.', 'agentclerk' );
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=agentclerk-sales' ) ) . '">';
			echo esc_html__( 'Resolve &rarr;', 'agentclerk' );
			echo '</a>';
			echo '</p></div>';
		}
	}

	/**
	 * AJAX handler: initiate lifetime license checkout.
	 */
	public function lifetime_checkout() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$response = AgentClerk::backend_request( '/license/checkout', array(
			'method' => 'POST',
			'body'   => array(
				'successUrl' => admin_url( 'admin.php?page=agentclerk-sales&license_success=1&nonce=' . wp_create_nonce( 'agentclerk_license' ) ),
				'cancelUrl'  => admin_url( 'admin.php?page=agentclerk-sales' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( array( 'checkoutUrl' => $data['checkoutUrl'] ?? '' ) );
	}

	/**
	 * AJAX handler: initiate card update flow.
	 */
	public function card_update() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$response = AgentClerk::backend_request( '/billing/card-update', array(
			'method' => 'POST',
			'body'   => array(
				'returnUrl' => admin_url( 'admin.php?page=agentclerk-sales' ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( array( 'portalUrl' => $data['portalUrl'] ?? '' ) );
	}

	/**
	 * Handle the return from Stripe license checkout.
	 */
	public function handle_license_return() {
		if ( ! isset( $_GET['license_success'] ) || '1' !== $_GET['license_success'] ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'agentclerk-sales' !== $page ) {
			return;
		}

		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'agentclerk_license' ) ) {
			return;
		}

		$this->activate_license();
	}

	/**
	 * Activate the lifetime license via the backend.
	 */
	private function activate_license() {
		$response = AgentClerk::backend_request( '/license/activate', array( 'method' => 'POST', 'body' => array() ) );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $data['licenseKey'] ) ) {
			update_option( 'agentclerk_license_status', 'active' );
			update_option( 'agentclerk_license_key', sanitize_text_field( $data['licenseKey'] ) );
			update_option( 'agentclerk_accrued_fees', 0 );

			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html__( 'Lifetime license activated. No more transaction fees!', 'agentclerk' );
				echo '</p></div>';
			} );
		}
	}

	/**
	 * Calculate the AgentClerk fee for a given sale amount.
	 *
	 * Fee schedule:
	 *   Lifetime license: $0
	 *   BYOK:    1% of sale, minimum $1.00
	 *   TurnKey: 1.5% of sale, minimum $1.99
	 *
	 * @param float $sale_amount Order total.
	 * @return float Calculated fee.
	 */
	public static function calculate_fee( $sale_amount ) {
		// No fees for lifetime license holders.
		if ( 'active' === get_option( 'agentclerk_license_status' ) ) {
			return 0.00;
		}

		$tier = get_option( 'agentclerk_tier', 'byok' );

		if ( 'turnkey' === $tier ) {
			return max( $sale_amount * 0.015, 1.99 );
		}

		// BYOK tier.
		return max( $sale_amount * 0.01, 1.00 );
	}
}
