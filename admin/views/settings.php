<?php
/**
 * Settings form.
 *
 * @package DemfilzCustomerStatements
 *
 * @var array $s Current settings.
 */

defined( 'ABSPATH' ) || exit;

$dcsf_tz       = wp_timezone();
$dcsf_paid     = wc_get_is_paid_statuses();
$dcsf_selected = array_filter( (array) $s['statuses'] );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=dcsf-settings' ) ); ?>" class="dcsf-settings-form">
	<?php wp_nonce_field( 'dcsf_save_settings' ); ?>
	<input type="hidden" name="dcsf_action" value="save_settings">

	<h2><?php esc_html_e( 'Reporting', 'demfilz-customer-statements-for-woocommerce' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="dcsf-fy-month"><?php esc_html_e( 'Financial year starts', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<select id="dcsf-fy-month" name="dcsf[fy_start_month]">
					<?php
					for ( $dcsf_m = 1; $dcsf_m <= 12; $dcsf_m++ ) {
						$dcsf_mname = ( new DateTimeImmutable( 'now', $dcsf_tz ) )->setDate( 2001, $dcsf_m, 1 )->format( 'F' );
						printf(
							'<option value="%d" %s>%s 1</option>',
							(int) $dcsf_m,
							selected( (int) $s['fy_start_month'], (int) $dcsf_m, false ),
							esc_html( $dcsf_mname )
						);
					}
					?>
				</select>
				<p class="description"><?php esc_html_e( 'Australia: July 1. United Kingdom: April 1. United States / calendar year: January 1.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Date basis', 'demfilz-customer-statements-for-woocommerce' ); ?></th>
			<td>
				<label><input type="radio" name="dcsf[date_basis]" value="paid" <?php checked( $s['date_basis'], 'paid' ); ?>> <?php esc_html_e( 'Payment date (when the order was paid)', 'demfilz-customer-statements-for-woocommerce' ); ?></label><br>
				<label><input type="radio" name="dcsf[date_basis]" value="created" <?php checked( $s['date_basis'], 'created' ); ?>> <?php esc_html_e( 'Order date (when the order was placed)', 'demfilz-customer-statements-for-woocommerce' ); ?></label>
				<p class="description"><?php esc_html_e( 'Payment date is recommended for financial statements — it reflects when money was actually received.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Paid order statuses', 'demfilz-customer-statements-for-woocommerce' ); ?></th>
			<td>
				<fieldset>
					<?php
					foreach ( wc_get_order_statuses() as $dcsf_key => $dcsf_label ) {
						$dcsf_slug    = preg_replace( '/^wc-/', '', $dcsf_key );
						$dcsf_checked = empty( $dcsf_selected ) ? in_array( $dcsf_slug, $dcsf_paid, true ) : in_array( $dcsf_slug, $dcsf_selected, true );
						printf(
							'<label class="dcsf-status"><input type="checkbox" name="dcsf[statuses][]" value="%s" %s> %s</label> ',
							esc_attr( $dcsf_slug ),
							checked( $dcsf_checked, true, false ),
							esc_html( $dcsf_label )
						);
					}
					?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Only orders with these statuses count as paid invoices. Defaults to Processing and Completed.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="dcsf-tax-label"><?php esc_html_e( 'Tax label', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<input type="text" id="dcsf-tax-label" name="dcsf[tax_label]" value="<?php echo esc_attr( $s['tax_label'] ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Heading used for the tax column, e.g. GST, VAT, Tax.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Statement header (print / PDF)', 'demfilz-customer-statements-for-woocommerce' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="dcsf-company-name"><?php esc_html_e( 'Business name', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<input type="text" id="dcsf-company-name" name="dcsf[company_name]" value="<?php echo esc_attr( $s['company_name'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Leave blank to use your site title.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dcsf-company-abn"><?php esc_html_e( 'ABN / Tax number', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td><input type="text" id="dcsf-company-abn" name="dcsf[company_abn]" value="<?php echo esc_attr( $s['company_abn'] ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="dcsf-company-address"><?php esc_html_e( 'Business address', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<textarea id="dcsf-company-address" name="dcsf[company_address]" rows="3" class="large-text"><?php echo esc_textarea( $s['company_address'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Leave blank to use your WooCommerce store address.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Logo', 'demfilz-customer-statements-for-woocommerce' ); ?></th>
			<td>
				<input type="url" id="dcsf-company-logo" name="dcsf[company_logo]" value="<?php echo esc_attr( $s['company_logo'] ); ?>" class="regular-text dcsf-logo-url">
				<button type="button" class="button dcsf-logo-pick"><?php esc_html_e( 'Select image', 'demfilz-customer-statements-for-woocommerce' ); ?></button>
				<button type="button" class="button dcsf-logo-clear"><?php esc_html_e( 'Clear', 'demfilz-customer-statements-for-woocommerce' ); ?></button>
				<div class="dcsf-logo-preview">
					<?php if ( $s['company_logo'] ) : ?>
						<img src="<?php echo esc_url( $s['company_logo'] ); ?>" alt="">
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="dcsf-notes"><?php esc_html_e( 'Footer notes', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<textarea id="dcsf-notes" name="dcsf[statement_notes]" rows="3" class="large-text"><?php echo esc_textarea( $s['statement_notes'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional note printed at the bottom of every statement (terms, remittance details, etc.).', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Advanced', 'demfilz-customer-statements-for-woocommerce' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Uninstall', 'demfilz-customer-statements-for-woocommerce' ); ?></th>
			<td>
				<label><input type="checkbox" name="dcsf[delete_on_uninstall]" value="1" <?php checked( ! empty( $s['delete_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete plugin settings when the plugin is deleted.', 'demfilz-customer-statements-for-woocommerce' ); ?></label>
				<p class="description"><?php esc_html_e( 'Order data is never touched — only this plugin\'s own settings.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'demfilz-customer-statements-for-woocommerce' ); ?></button></p>
</form>

<?php
/**
 * Fires at the bottom of the Settings page. The Pro add-on renders its
 * automatic-statement (scheduled email) controls here.
 */
do_action( 'dcsf_settings_after' );
?>
