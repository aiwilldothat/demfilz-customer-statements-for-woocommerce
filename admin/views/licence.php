<?php
/**
 * Licence / Pro page.
 *
 * @package DemfilzCustomerStatements
 *
 * @var array $lic     Stored licence state.
 * @var bool  $active  Whether Pro is active.
 * @var bool  $has_pro Whether the Pro add-on plugin is installed.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="dcsf-licence">
	<p>
		<?php esc_html_e( 'Current plan:', 'demfilz-customer-statements-for-woocommerce' ); ?>
		<strong style="color:<?php echo $active ? '#1a7f37' : '#646970'; ?>">
			<?php echo $active ? esc_html__( 'Pro', 'demfilz-customer-statements-for-woocommerce' ) : esc_html__( 'Free', 'demfilz-customer-statements-for-woocommerce' ); ?>
		</strong>
	</p>

	<div class="dcsf-pro-pitch card" style="max-width:640px;padding:16px 20px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Demfilz Customer Statements Pro', 'demfilz-customer-statements-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Automate your customer statements:', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
		<ul style="list-style:disc;margin-left:20px;">
			<li><?php esc_html_e( 'Real, branded PDF statements (download or attach to email).', 'demfilz-customer-statements-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Email a statement straight to the selected customer(s) in one click.', 'demfilz-customer-statements-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Scheduled auto-send: every customer is emailed their monthly, quarterly or end-of-financial-year statement automatically.', 'demfilz-customer-statements-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Bulk run: send this period’s statement to every customer with paid orders.', 'demfilz-customer-statements-for-woocommerce' ); ?></li>
		</ul>

		<?php if ( ! $active ) : ?>
			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( DCSF_Licence::checkout_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to Pro', 'demfilz-customer-statements-for-woocommerce' ); ?>
				</a>
			</p>
			<p class="description"><?php esc_html_e( 'After subscribing you receive a licence key by email. Enter it below to activate Pro on this site.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
		<?php else : ?>
			<p>
				<a class="button" href="<?php echo esc_url( DCSF_Licence::account_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Manage subscription', 'demfilz-customer-statements-for-woocommerce' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( $active && ! $has_pro ) : ?>
		<div class="notice notice-warning inline" style="margin:16px 0;"><p>
			<?php esc_html_e( 'Your licence is active, but the “Demfilz Customer Statements Pro” add-on plugin is not installed. Download it from your account and install it to enable the Pro features.', 'demfilz-customer-statements-for-woocommerce' ); ?>
			<a href="<?php echo esc_url( DCSF_Licence::account_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open my account', 'demfilz-customer-statements-for-woocommerce' ); ?></a>
		</p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Licence key', 'demfilz-customer-statements-for-woocommerce' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=dcsf-licence' ) ); ?>">
		<?php wp_nonce_field( 'dcsf_licence' ); ?>
		<input type="hidden" name="dcsf_action" value="activate_licence">
		<input type="text" class="regular-text" name="dcsf_licence_key" value="<?php echo esc_attr( isset( $lic['key'] ) ? $lic['key'] : '' ); ?>" placeholder="DCSF-XXXXX-XXXXX-XXXXX-XXXXX">
		<button type="submit" class="button button-secondary"><?php esc_html_e( 'Activate licence', 'demfilz-customer-statements-for-woocommerce' ); ?></button>
		<?php if ( ! empty( $lic['message'] ) && ! $active ) : ?>
			<p class="description" style="color:#b32d2e;"><?php echo esc_html( $lic['message'] ); ?></p>
		<?php endif; ?>
	</form>

	<?php if ( ! empty( $lic['key'] ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=dcsf-licence' ) ); ?>" style="margin-top:10px;">
			<?php wp_nonce_field( 'dcsf_licence' ); ?>
			<input type="hidden" name="dcsf_action" value="deactivate_licence">
			<button type="submit" class="button-link" style="color:#b32d2e;"><?php esc_html_e( 'Remove licence from this site', 'demfilz-customer-statements-for-woocommerce' ); ?></button>
		</form>
	<?php endif; ?>

	<p class="description" style="margin-top:16px;color:#8a8f94;">
		<?php esc_html_e( 'Licensing connects to api.demfilz.com to validate your subscription. Billing is handled by Stripe. No customer or order data is sent.', 'demfilz-customer-statements-for-woocommerce' ); ?>
	</p>
</div>
