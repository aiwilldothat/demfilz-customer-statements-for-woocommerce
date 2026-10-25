<?php
/**
 * Statement builder form.
 *
 * @package DemfilzCustomerStatements
 *
 * @var array $filters Parsed request filters.
 */

defined( 'ABSPATH' ) || exit;

$dcsf_start_month = DCSF_Periods::start_month();
$dcsf_tz          = wp_timezone();

// Build quarter labels relative to the FY start month.
$dcsf_quarters = array();
for ( $dcsf_q = 1; $dcsf_q <= 4; $dcsf_q++ ) {
	$dcsf_qs                  = ( new DateTimeImmutable( 'now', $dcsf_tz ) )->setDate( 2001, $dcsf_start_month, 1 )->modify( '+' . ( ( $dcsf_q - 1 ) * 3 ) . ' months' );
	$dcsf_qe                  = $dcsf_qs->modify( '+2 months' );
	$dcsf_quarters[ $dcsf_q ] = sprintf( 'Q%d (%s–%s)', $dcsf_q, $dcsf_qs->format( 'M' ), $dcsf_qe->format( 'M' ) );
}

$dcsf_this_year = (int) wp_date( 'Y' );
?>

<form method="get" class="dcsf-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
	<input type="hidden" name="page" value="dcsf">
	<input type="hidden" name="dcsf_generate" value="1">
	<?php wp_nonce_field( 'dcsf_generate', 'dcsf_nonce', false ); ?>

	<table class="form-table dcsf-builder" role="presentation">
		<tr>
			<th scope="row"><label for="dcsf-customers"><?php esc_html_e( 'Customers', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<select
					id="dcsf-customers"
					name="customer_ids[]"
					class="wc-customer-search dcsf-customers"
					multiple="multiple"
					style="width:480px;max-width:100%;"
					data-placeholder="<?php esc_attr_e( 'Search customers by name or email…', 'demfilz-customer-statements-for-woocommerce' ); ?>"
					data-action="woocommerce_json_search_customers"
					data-allow_clear="true">
					<?php
					foreach ( (array) $filters['customer_ids'] as $dcsf_cid ) {
						$dcsf_user = get_userdata( (int) $dcsf_cid );
						if ( ! $dcsf_user ) {
							continue;
						}
						$dcsf_name = trim( $dcsf_user->first_name . ' ' . $dcsf_user->last_name );
						if ( '' === $dcsf_name ) {
							$dcsf_name = $dcsf_user->display_name;
						}
						printf(
							'<option value="%d" selected="selected">%s</option>',
							(int) $dcsf_cid,
							esc_html( sprintf( '%s (#%d – %s)', $dcsf_name, (int) $dcsf_cid, $dcsf_user->user_email ) )
						);
					}
					?>
				</select>
				<p class="description"><?php esc_html_e( 'Pick one or more registered customers. Leave blank and use the box below for guest orders.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="dcsf-emails"><?php esc_html_e( 'Guest billing emails', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<textarea id="dcsf-emails" name="emails" rows="2" class="large-text code" placeholder="jane@example.com, john@example.com"><?php echo esc_textarea( implode( ', ', (array) $filters['emails'] ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Optional. Comma- or space-separated billing emails for guest (non-registered) customers.', 'demfilz-customer-statements-for-woocommerce' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Period', 'demfilz-customer-statements-for-woocommerce' ); ?></th>
			<td>
				<fieldset class="dcsf-period-radios">
					<?php
					$dcsf_periods = array(
						'fy'      => __( 'Financial year', 'demfilz-customer-statements-for-woocommerce' ),
						'quarter' => __( 'Quarter', 'demfilz-customer-statements-for-woocommerce' ),
						'month'   => __( 'Month', 'demfilz-customer-statements-for-woocommerce' ),
						'custom'  => __( 'Custom range', 'demfilz-customer-statements-for-woocommerce' ),
					);
					foreach ( $dcsf_periods as $dcsf_key => $dcsf_lbl ) {
						printf(
							'<label class="dcsf-radio"><input type="radio" name="period" value="%s" %s> %s</label> ',
							esc_attr( $dcsf_key ),
							checked( $filters['period'], $dcsf_key, false ),
							esc_html( $dcsf_lbl )
						);
					}
					?>
				</fieldset>
			</td>
		</tr>

		<tr class="dcsf-period-row" data-show="fy quarter">
			<th scope="row"><label for="dcsf-fy"><?php esc_html_e( 'Financial year', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<select id="dcsf-fy" name="fy">
					<?php
					foreach ( DCSF_Periods::fy_options() as $dcsf_y => $dcsf_y_label ) {
						printf(
							'<option value="%d" %s>%s</option>',
							(int) $dcsf_y,
							selected( (int) $filters['fy'], (int) $dcsf_y, false ),
							esc_html( $dcsf_y_label )
						);
					}
					?>
				</select>
			</td>
		</tr>

		<tr class="dcsf-period-row" data-show="quarter">
			<th scope="row"><label for="dcsf-quarter"><?php esc_html_e( 'Quarter', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<select id="dcsf-quarter" name="quarter">
					<?php
					foreach ( $dcsf_quarters as $dcsf_qn => $dcsf_qlabel ) {
						printf(
							'<option value="%d" %s>%s</option>',
							(int) $dcsf_qn,
							selected( (int) $filters['quarter'], (int) $dcsf_qn, false ),
							esc_html( $dcsf_qlabel )
						);
					}
					?>
				</select>
			</td>
		</tr>

		<tr class="dcsf-period-row" data-show="month">
			<th scope="row"><label for="dcsf-month"><?php esc_html_e( 'Month', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<select id="dcsf-month" name="month">
					<?php
					for ( $dcsf_m = 1; $dcsf_m <= 12; $dcsf_m++ ) {
						$dcsf_mname = ( new DateTimeImmutable( 'now', $dcsf_tz ) )->setDate( 2001, $dcsf_m, 1 )->format( 'F' );
						printf(
							'<option value="%d" %s>%s</option>',
							(int) $dcsf_m,
							selected( (int) $filters['month'], (int) $dcsf_m, false ),
							esc_html( $dcsf_mname )
						);
					}
					?>
				</select>
				<select name="cal_year">
					<?php
					for ( $dcsf_yy = $dcsf_this_year; $dcsf_yy >= $dcsf_this_year - 10; $dcsf_yy-- ) {
						printf(
							'<option value="%d" %s>%d</option>',
							(int) $dcsf_yy,
							selected( (int) $filters['cal_year'], (int) $dcsf_yy, false ),
							(int) $dcsf_yy
						);
					}
					?>
				</select>
			</td>
		</tr>

		<tr class="dcsf-period-row" data-show="custom">
			<th scope="row"><label for="dcsf-from"><?php esc_html_e( 'Date range', 'demfilz-customer-statements-for-woocommerce' ); ?></label></th>
			<td>
				<input type="date" id="dcsf-from" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>">
				<span class="dcsf-dash">→</span>
				<input type="date" id="dcsf-to" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>">
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Generate statement', 'demfilz-customer-statements-for-woocommerce' ); ?></button>
	</p>
</form>
