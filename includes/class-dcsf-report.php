<?php
/**
 * Statement data builder and renderers.
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the statement dataset and renders it as HTML / a print document / CSV.
 */
class DCSF_Report {

	/**
	 * Parse a raw request array into a normalised, sanitised filter set.
	 *
	 * @param array $src Unslashed request data ($_GET or $_POST).
	 * @return array
	 */
	public static function parse_request( $src ) {
		$src = is_array( $src ) ? $src : array();

		$period = isset( $src['period'] ) ? sanitize_key( $src['period'] ) : 'fy';
		if ( ! in_array( $period, array( 'fy', 'quarter', 'month', 'custom' ), true ) ) {
			$period = 'fy';
		}

		$ids = array();
		if ( isset( $src['customer_ids'] ) ) {
			foreach ( (array) $src['customer_ids'] as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$ids[] = $id;
				}
			}
		}

		return array(
			'customer_ids' => array_values( array_unique( $ids ) ),
			'emails'       => self::split_emails( isset( $src['emails'] ) ? $src['emails'] : '' ),
			'period'       => $period,
			'fy'           => isset( $src['fy'] ) && absint( $src['fy'] ) ? absint( $src['fy'] ) : DCSF_Periods::current_fy_start_year(),
			'quarter'      => isset( $src['quarter'] ) ? max( 1, min( 4, absint( $src['quarter'] ) ) ) : 1,
			'month'        => isset( $src['month'] ) ? max( 1, min( 12, absint( $src['month'] ) ) ) : (int) wp_date( 'n' ),
			'cal_year'     => isset( $src['cal_year'] ) && absint( $src['cal_year'] ) ? absint( $src['cal_year'] ) : (int) wp_date( 'Y' ),
			'from'         => isset( $src['from'] ) ? sanitize_text_field( $src['from'] ) : '',
			'to'           => isset( $src['to'] ) ? sanitize_text_field( $src['to'] ) : '',
		);
	}

	/**
	 * Split a free-text list of emails into a clean array.
	 *
	 * @param string $raw Raw textarea value.
	 * @return array
	 */
	private static function split_emails( $raw ) {
		$out   = array();
		$parts = preg_split( '/[\s,;]+/', (string) $raw );
		foreach ( (array) $parts as $part ) {
			$email = sanitize_email( trim( $part ) );
			if ( $email && is_email( $email ) ) {
				$out[] = strtolower( $email );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether the filters name at least one customer / email to report on.
	 *
	 * @param array $filters Parsed filters.
	 * @return bool
	 */
	public static function has_targets( $filters ) {
		return ! empty( $filters['customer_ids'] ) || ! empty( $filters['emails'] );
	}

	/**
	 * Build the full statement dataset.
	 *
	 * @param array $filters Parsed filters.
	 * @return array
	 */
	public static function build( $filters ) {
		$range = DCSF_Periods::resolve( $filters );
		$paid  = DCSF_Settings::effective_statuses();

		/**
		 * Statement mode: 'paid' (default) or 'account'. The Pro add-on returns
		 * 'account' when its outstanding-balance mode is enabled and licensed.
		 *
		 * @param string $mode    Statement mode.
		 * @param array  $filters Parsed filters.
		 */
		$mode = apply_filters( 'dcsf_statement_mode', 'paid', $filters );
		$mode = ( 'account' === $mode ) ? 'account' : 'paid';

		$owing = array();
		if ( 'account' === $mode ) {
			/**
			 * Extra order statuses to include as UNPAID invoices (outstanding),
			 * alongside the paid statuses. Supplied by the Pro add-on.
			 *
			 * @param array $owing   Status slugs (no wc- prefix).
			 * @param array $filters Parsed filters.
			 */
			$owing = (array) apply_filters( 'dcsf_owing_statuses', array(), $filters );
			$owing = array_map(
				static function ( $s ) {
					return preg_replace( '/^wc-/', '', (string) $s );
				},
				$owing
			);
			$owing = array_values( array_diff( array_unique( $owing ), $paid ) );
		}

		// Account mode measures by order date so unpaid orders (no paid date) are
		// captured; paid mode honours the configured basis.
		$basis    = ( 'account' === $mode ) ? 'created' : DCSF_Settings::get( 'date_basis', 'paid' );
		$statuses = ( 'account' === $mode ) ? array_values( array_merge( $paid, $owing ) ) : $paid;

		$targets  = self::targets( $filters );
		$seen     = array();
		$sections = array();
		$grand    = self::zero_totals();

		foreach ( $targets as $target ) {
			$orders = self::query_orders( $target, $statuses, $basis, $range );
			$rows   = array();
			$totals = self::zero_totals();
			$cum    = 0.0;

			foreach ( $orders as $order ) {
				$oid = $order->get_id();
				if ( isset( $seen[ $oid ] ) ) {
					continue;
				}
				$seen[ $oid ] = true;

				$row               = self::order_row( $order, $basis, $paid );
				$cum              += $row['net_paid'];
				$row['cumulative'] = $cum;
				$rows[]            = $row;

				++$totals['count'];
				$totals['net']      += $row['net'];
				$totals['tax']      += $row['tax'];
				$totals['total']    += $row['total'];
				$totals['refunded'] += $row['refunded'];
				$totals['net_paid'] += $row['net_paid'];
				$totals['invoiced'] += $row['invoiced'];
				$totals['paid']     += $row['paid_amt'];
				$totals['balance']  += $row['balance'];
			}

			foreach ( array_keys( $grand ) as $k ) {
				$grand[ $k ] += $totals[ $k ];
			}

			$sections[] = array(
				'target' => $target,
				'rows'   => $rows,
				'totals' => $totals,
			);
		}

		return array(
			'range'    => $range,
			'basis'    => $basis,
			'mode'     => $mode,
			'statuses' => $statuses,
			'paid'     => $paid,
			'owing'    => $owing,
			'sections' => $sections,
			'grand'    => $grand,
			'multi'    => count( $sections ) > 1,
			'filters'  => $filters,
		);
	}

	/**
	 * A zeroed totals row.
	 *
	 * @return array
	 */
	private static function zero_totals() {
		return array(
			'count'    => 0,
			'net'      => 0.0,
			'tax'      => 0.0,
			'total'    => 0.0,
			'refunded' => 0.0,
			'net_paid' => 0.0,
			'invoiced' => 0.0,
			'paid'     => 0.0,
			'balance'  => 0.0,
		);
	}

	/**
	 * Resolve the list of report targets (registered customers + guest emails).
	 *
	 * @param array $filters Parsed filters.
	 * @return array
	 */
	private static function targets( $filters ) {
		$out = array();

		foreach ( (array) $filters['customer_ids'] as $id ) {
			$user = get_userdata( (int) $id );
			if ( ! $user ) {
				continue;
			}
			$name = trim( $user->first_name . ' ' . $user->last_name );
			if ( '' === $name ) {
				$name = $user->display_name;
			}
			$out[] = array(
				'type'  => 'id',
				'value' => (int) $id,
				'name'  => $name,
				'email' => $user->user_email,
			);
		}

		foreach ( (array) $filters['emails'] as $email ) {
			$out[] = array(
				'type'  => 'email',
				'value' => $email,
				'name'  => $email,
				'email' => $email,
			);
		}

		return $out;
	}

	/**
	 * Query the paid orders for a single target within the window.
	 *
	 * @param array  $target   Target descriptor.
	 * @param array  $statuses Paid statuses.
	 * @param string $basis    'paid' or 'created'.
	 * @param array  $range    Resolved range.
	 * @return WC_Order[]
	 */
	private static function query_orders( $target, $statuses, $basis, $range ) {
		$date_key = ( 'created' === $basis ) ? 'date_created' : 'date_paid';

		$args = array(
			'status'  => $statuses,
			'limit'   => -1,
			'orderby' => 'date',
			'order'   => 'ASC',
			'type'    => 'shop_order',
			'return'  => 'objects',
			$date_key => $range['start_ts'] . '...' . $range['end_ts'],
		);

		if ( 'id' === $target['type'] ) {
			$args['customer_id'] = (int) $target['value'];
		} else {
			$args['customer'] = $target['value'];
		}

		$orders = wc_get_orders( $args );
		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Build a single statement row from an order.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $basis 'paid' or 'created'.
	 * @param array    $paid_statuses Statuses treated as paid (no wc- prefix).
	 * @return array
	 */
	private static function order_row( $order, $basis, $paid_statuses = array() ) {
		$total    = (float) $order->get_total();
		$tax      = (float) $order->get_total_tax();
		$refunded = (float) $order->get_total_refunded();
		$is_paid  = in_array( $order->get_status(), (array) $paid_statuses, true );

		if ( 'created' === $basis ) {
			$date = $order->get_date_created();
		} else {
			$date = $order->get_date_paid();
			if ( ! $date ) {
				$date = $order->get_date_created();
			}
		}

		// Account view: invoiced = order total; paid = received (nil on an unpaid
		// invoice); balance = the amount still owing (nil once paid).
		$paid_amt = $is_paid ? ( $total - $refunded ) : 0.0;
		$balance  = $is_paid ? 0.0 : $total;

		return array(
			'id'         => $order->get_id(),
			'number'     => $order->get_order_number(),
			'date_ts'    => $date ? $date->getTimestamp() : 0,
			'status'     => wc_get_order_status_name( $order->get_status() ),
			'payment'    => $order->get_payment_method_title(),
			'net'        => $total - $tax,
			'tax'        => $tax,
			'total'      => $total,
			'refunded'   => $refunded,
			'net_paid'   => $total - $refunded,
			'invoiced'   => $total,
			'is_paid'    => $is_paid,
			'paid_amt'   => $paid_amt,
			'balance'    => $balance,
			'cumulative' => 0.0,
			'edit_url'   => $order->get_edit_order_url(),
		);
	}

	// ----- Rendering -----

	/**
	 * Render the on-screen results (inside the admin wrap).
	 *
	 * @param array  $data       Built dataset.
	 * @param string $export_url Base admin-post URL with the current filters.
	 * @return void
	 */
	public static function render_results( $data, $export_url = '' ) {
		$tax_label = DCSF_Settings::get( 'tax_label', 'GST' );

		echo '<div class="dcsf-results">';

		echo '<div class="dcsf-results-head">';
		echo '<h2>' . esc_html__( 'Statement', 'demfilz-customer-statements-for-woocommerce' ) . ' — ' . esc_html( $data['range']['label'] ) . '</h2>';
		if ( $export_url ) {
			$csv   = wp_nonce_url( $export_url . '&action=dcsf_export_csv', 'dcsf_export' );
			$print = wp_nonce_url( $export_url . '&action=dcsf_print', 'dcsf_export' );
			echo '<p class="dcsf-actions">';
			echo '<a class="button button-primary" href="' . esc_url( $csv ) . '">' . esc_html__( 'Export CSV', 'demfilz-customer-statements-for-woocommerce' ) . '</a> ';
			echo '<a class="button" href="' . esc_url( $print ) . '" target="_blank" rel="noopener">' . esc_html__( 'Print / Save as PDF', 'demfilz-customer-statements-for-woocommerce' ) . '</a>';

			/**
			 * Fires inside the results action bar, after the built-in buttons.
			 * The Pro add-on uses this to add "Download PDF" and "Email to
			 * customer" actions. $export_url carries the current filters.
			 *
			 * @param array  $data       The built statement dataset.
			 * @param string $export_url Base admin-post URL with the current filters.
			 */
			do_action( 'dcsf_results_actions', $data, $export_url );

			echo '</p>';
		}
		echo '</div>';

		$mode = isset( $data['mode'] ) ? $data['mode'] : 'paid';

		echo '<p class="dcsf-meta description">';
		if ( 'account' === $mode ) {
			printf(
				/* translators: 1: paid statuses, 2: unpaid/owing statuses. */
				esc_html__( 'Account statement by order date. Paid: %1$s. Outstanding: %2$s.', 'demfilz-customer-statements-for-woocommerce' ),
				esc_html( self::status_names( $data['paid'] ) ),
				esc_html( $data['owing'] ? self::status_names( $data['owing'] ) : __( 'none selected', 'demfilz-customer-statements-for-woocommerce' ) )
			);
		} else {
			printf(
				/* translators: 1: date basis, 2: list of order statuses. */
				esc_html__( 'Based on %1$s. Counting orders with status: %2$s.', 'demfilz-customer-statements-for-woocommerce' ),
				esc_html( 'created' === $data['basis'] ? __( 'order date', 'demfilz-customer-statements-for-woocommerce' ) : __( 'payment date', 'demfilz-customer-statements-for-woocommerce' ) ),
				esc_html( self::status_names( $data['statuses'] ) )
			);
		}
		echo '</p>';

		if ( empty( $data['sections'] ) ) {
			echo '<p>' . esc_html__( 'No customers selected.', 'demfilz-customer-statements-for-woocommerce' ) . '</p>';
			echo '</div>';
			return;
		}

		foreach ( $data['sections'] as $section ) {
			self::render_section( $section, $tax_label, true, $mode );
		}

		if ( $data['multi'] ) {
			self::render_grand( $data['grand'], $tax_label, true, $mode );
		}

		echo '</div>';
	}

	/**
	 * Render one customer's statement block.
	 *
	 * @param array  $section   Section data.
	 * @param string $tax_label Tax label.
	 * @param bool   $links     Whether to render order edit links.
	 * @param string $mode      'paid' or 'account'.
	 * @return void
	 */
	private static function render_section( $section, $tax_label, $links, $mode = 'paid' ) {
		$t      = $section['target'];
		$totals = $section['totals'];

		echo '<div class="dcsf-section">';
		echo '<h3 class="dcsf-customer">' . esc_html( $t['name'] );
		if ( $t['email'] && $t['email'] !== $t['name'] ) {
			echo ' <span class="dcsf-email">&lt;' . esc_html( $t['email'] ) . '&gt;</span>';
		}
		echo '</h3>';

		if ( empty( $section['rows'] ) ) {
			echo '<p class="dcsf-empty">' . esc_html__( 'No orders in this period.', 'demfilz-customer-statements-for-woocommerce' ) . '</p>';
			echo '</div>';
			return;
		}

		if ( 'account' === $mode ) {
			self::render_section_account( $section, $links );
			echo '</div>';
			return;
		}

		$has_ref = $totals['refunded'] > 0;

		echo '<table class="widefat striped dcsf-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Payment', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="dcsf-num">' . esc_html__( 'Net', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="dcsf-num">' . esc_html( $tax_label ) . '</th>';
		echo '<th class="dcsf-num">' . esc_html__( 'Total', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		if ( $has_ref ) {
			echo '<th class="dcsf-num">' . esc_html__( 'Refunded', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		}
		echo '<th class="dcsf-num">' . esc_html__( 'Cumulative', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $section['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['date_ts'] ? wp_date( get_option( 'date_format' ), $row['date_ts'] ) : '—' ) . '</td>';
			if ( $links && $row['edit_url'] ) {
				echo '<td><a href="' . esc_url( $row['edit_url'] ) . '">#' . esc_html( $row['number'] ) . '</a></td>';
			} else {
				echo '<td>#' . esc_html( $row['number'] ) . '</td>';
			}
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( $row['payment'] ) . '</td>';
			echo '<td class="dcsf-num">' . wp_kses_post( wc_price( $row['net'] ) ) . '</td>';
			echo '<td class="dcsf-num">' . wp_kses_post( wc_price( $row['tax'] ) ) . '</td>';
			echo '<td class="dcsf-num">' . wp_kses_post( wc_price( $row['total'] ) ) . '</td>';
			if ( $has_ref ) {
				echo '<td class="dcsf-num">' . ( $row['refunded'] > 0 ? '-' . wp_kses_post( wc_price( $row['refunded'] ) ) : '—' ) . '</td>';
			}
			echo '<td class="dcsf-num">' . wp_kses_post( wc_price( $row['cumulative'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody><tfoot><tr class="dcsf-totals">';
		echo '<th colspan="4">' . sprintf(
			/* translators: %d: number of paid orders. */
			esc_html( _n( '%d paid order', '%d paid orders', $totals['count'], 'demfilz-customer-statements-for-woocommerce' ) ),
			(int) $totals['count']
		) . '</th>';
		echo '<th class="dcsf-num">' . wp_kses_post( wc_price( $totals['net'] ) ) . '</th>';
		echo '<th class="dcsf-num">' . wp_kses_post( wc_price( $totals['tax'] ) ) . '</th>';
		echo '<th class="dcsf-num">' . wp_kses_post( wc_price( $totals['total'] ) ) . '</th>';
		if ( $has_ref ) {
			echo '<th class="dcsf-num">-' . wp_kses_post( wc_price( $totals['refunded'] ) ) . '</th>';
		}
		echo '<th class="dcsf-num">' . wp_kses_post( wc_price( $totals['net_paid'] ) ) . '</th>';
		echo '</tr></tfoot></table>';
		echo '</div>';
	}

	/**
	 * Render one customer's ACCOUNT statement (invoiced / paid / balance).
	 *
	 * @param array $section Section data.
	 * @param bool  $links   Whether to render order edit links.
	 * @return void
	 */
	private static function render_section_account( $section, $links ) {
		$totals = $section['totals'];

		echo '<table class="widefat striped dcsf-table dcsf-account"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Payment', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="dcsf-num">' . esc_html__( 'Invoiced', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="dcsf-num">' . esc_html__( 'Paid', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="dcsf-num">' . esc_html__( 'Balance', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $section['rows'] as $row ) {
			echo '<tr class="' . ( $row['balance'] > 0 ? 'dcsf-owing' : '' ) . '">';
			echo '<td>' . esc_html( $row['date_ts'] ? wp_date( get_option( 'date_format' ), $row['date_ts'] ) : '—' ) . '</td>';
			if ( $links && $row['edit_url'] ) {
				echo '<td><a href="' . esc_url( $row['edit_url'] ) . '">#' . esc_html( $row['number'] ) . '</a></td>';
			} else {
				echo '<td>#' . esc_html( $row['number'] ) . '</td>';
			}
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( $row['payment'] ) . '</td>';
			echo '<td class="dcsf-num">' . wp_kses_post( wc_price( $row['invoiced'] ) ) . '</td>';
			echo '<td class="dcsf-num">' . wp_kses_post( wc_price( $row['paid_amt'] ) ) . '</td>';
			echo '<td class="dcsf-num">' . ( $row['balance'] > 0 ? wp_kses_post( wc_price( $row['balance'] ) ) : '—' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody><tfoot><tr class="dcsf-totals">';
		echo '<th colspan="4">' . sprintf(
			/* translators: %d: number of orders. */
			esc_html( _n( '%d order', '%d orders', $totals['count'], 'demfilz-customer-statements-for-woocommerce' ) ),
			(int) $totals['count']
		) . '</th>';
		echo '<th class="dcsf-num">' . wp_kses_post( wc_price( $totals['invoiced'] ) ) . '</th>';
		echo '<th class="dcsf-num">' . wp_kses_post( wc_price( $totals['paid'] ) ) . '</th>';
		echo '<th class="dcsf-num dcsf-balance">' . wp_kses_post( wc_price( $totals['balance'] ) ) . '</th>';
		echo '</tr></tfoot></table>';
	}

	/**
	 * Render the grand-total block across all customers.
	 *
	 * @param array  $grand     Grand totals.
	 * @param string $tax_label Tax label.
	 * @param bool   $admin     Admin context.
	 * @param string $mode      'paid' or 'account'.
	 * @return void
	 */
	private static function render_grand( $grand, $tax_label, $admin, $mode = 'paid' ) {
		echo '<div class="dcsf-grand">';
		echo '<h3>' . esc_html__( 'All selected customers — total', 'demfilz-customer-statements-for-woocommerce' ) . '</h3>';
		echo '<table class="widefat dcsf-table dcsf-grand-table"><tbody>';
		if ( 'account' === $mode ) {
			self::kv( __( 'Orders', 'demfilz-customer-statements-for-woocommerce' ), esc_html( (string) (int) $grand['count'] ) );
			self::kv( __( 'Invoiced', 'demfilz-customer-statements-for-woocommerce' ), wp_kses_post( wc_price( $grand['invoiced'] ) ) );
			self::kv( __( 'Paid', 'demfilz-customer-statements-for-woocommerce' ), wp_kses_post( wc_price( $grand['paid'] ) ) );
			self::kv( __( 'Outstanding balance', 'demfilz-customer-statements-for-woocommerce' ), wp_kses_post( wc_price( $grand['balance'] ) ) );
		} else {
			self::kv( __( 'Paid orders', 'demfilz-customer-statements-for-woocommerce' ), esc_html( (string) (int) $grand['count'] ) );
			self::kv( __( 'Net', 'demfilz-customer-statements-for-woocommerce' ), wp_kses_post( wc_price( $grand['net'] ) ) );
			/* translators: %s: tax label such as GST. */
			self::kv( $tax_label, wp_kses_post( wc_price( $grand['tax'] ) ) );
			self::kv( __( 'Total (inc. tax)', 'demfilz-customer-statements-for-woocommerce' ), wp_kses_post( wc_price( $grand['total'] ) ) );
			if ( $grand['refunded'] > 0 ) {
				self::kv( __( 'Refunded', 'demfilz-customer-statements-for-woocommerce' ), '-' . wp_kses_post( wc_price( $grand['refunded'] ) ) );
				self::kv( __( 'Net received', 'demfilz-customer-statements-for-woocommerce' ), wp_kses_post( wc_price( $grand['net_paid'] ) ) );
			}
		}
		echo '</tbody></table>';
		echo '</div>';
		unset( $admin );
	}

	/**
	 * Render a key/value row in a totals table.
	 *
	 * @param string $key   Label (escaped here).
	 * @param string $value Value (already-escaped HTML).
	 * @return void
	 */
	private static function kv( $key, $value ) {
		echo '<tr><th>' . esc_html( $key ) . '</th><td class="dcsf-num">' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value pre-escaped by caller.
	}

	/**
	 * Friendly comma list of status names.
	 *
	 * @param array $statuses Status slugs.
	 * @return string
	 */
	private static function status_names( $statuses ) {
		$names = array();
		foreach ( (array) $statuses as $status ) {
			$names[] = wc_get_order_status_name( $status );
		}
		return implode( ', ', $names );
	}

	// ----- Print document -----

	/**
	 * Output a complete standalone HTML document for printing / Save-as-PDF.
	 *
	 * @param array $data Built dataset.
	 * @return void
	 */
	public static function render_document( $data ) {
		// Register the document's stylesheet and print script so they can be
		// emitted by WordPress (wp_print_styles / wp_print_scripts) rather than
		// hard-coded into the markup.
		wp_register_style( 'dcsf-statement-doc', DCSF_URL . 'admin/css/statement-doc.css', array(), DCSF_VERSION );
		wp_register_script( 'dcsf-statement-print', DCSF_URL . 'admin/js/statement-print.js', array(), DCSF_VERSION, true );

		header( 'Content-Type: text/html; charset=utf-8' );
		// Document HTML is assembled from individually-escaped parts below.
		echo self::get_document_html( $data, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the full statement document as an HTML string.
	 *
	 * Shared by the browser print view and (in the Pro add-on) the PDF engine.
	 * Uses a table-based layout with no flexbox so it renders identically in
	 * browsers and in dompdf.
	 *
	 * @param array $data    Built dataset.
	 * @param bool  $for_pdf When true, omit the print button/script and the
	 *                       screen background (for PDF rendering).
	 * @return string
	 */
	public static function get_document_html( $data, $for_pdf = false ) {
		$company   = DCSF_Settings::company_display();
		$tax_label = DCSF_Settings::get( 'tax_label', 'GST' );
		$title     = sprintf(
			/* translators: %s: period label. */
			__( 'Customer Statement — %s', 'demfilz-customer-statements-for-woocommerce' ),
			$data['range']['label']
		);

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?></title>
		<?php
		/*
		* For the browser print view, WordPress emits the registered stylesheet here.
		* For the PDF path ($for_pdf) the Pro add-on injects the CSS inline because
		* the PDF engine cannot resolve an enqueued URL.
		*/
		if ( ! $for_pdf ) {
			wp_print_styles( 'dcsf-statement-doc' );
		}
		?>
</head>
<body class="<?php echo esc_attr( $for_pdf ? 'is-pdf' : 'is-screen' ); ?>">
<div class="doc">
	<table class="doc-head"><tr>
		<td class="doc-company">
			<?php if ( $company['logo'] ) : ?>
				<img class="doc-logo" src="<?php echo esc_url( $company['logo'] ); ?>" alt="">
			<?php endif; ?>
			<div class="doc-company-name"><?php echo esc_html( $company['name'] ); ?></div>
			<?php if ( $company['abn'] ) : ?>
				<div class="doc-abn"><?php echo esc_html( $company['abn'] ); ?></div>
			<?php endif; ?>
			<?php if ( $company['address'] ) : ?>
				<div class="doc-addr"><?php echo nl2br( esc_html( $company['address'] ) ); ?></div>
			<?php endif; ?>
		</td>
		<td class="doc-title">
			<div class="doc-title-h"><?php esc_html_e( 'Statement', 'demfilz-customer-statements-for-woocommerce' ); ?></div>
			<div class="doc-period"><?php echo esc_html( $data['range']['label'] ); ?></div>
			<div class="doc-generated">
				<?php
				printf(
					/* translators: %s: generation date. */
					esc_html__( 'Generated %s', 'demfilz-customer-statements-for-woocommerce' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) )
				);
				?>
			</div>
		</td>
	</tr></table>

		<?php
		$doc_mode = isset( $data['mode'] ) ? $data['mode'] : 'paid';
		foreach ( $data['sections'] as $section ) :
			?>
			<?php self::render_print_section( $section, $tax_label, $doc_mode ); ?>
	<?php endforeach; ?>

		<?php if ( $data['multi'] ) : ?>
			<?php self::render_print_grand( $data['grand'], $tax_label, $doc_mode ); ?>
	<?php endif; ?>

		<?php if ( $company['notes'] ) : ?>
		<div class="doc-notes"><?php echo nl2br( esc_html( $company['notes'] ) ); ?></div>
	<?php endif; ?>

		<?php if ( ! $for_pdf ) : ?>
		<div class="doc-foot">
			<button type="button" id="dcsf-doc-print" class="doc-print-btn no-print"><?php esc_html_e( 'Print / Save as PDF', 'demfilz-customer-statements-for-woocommerce' ); ?></button>
		</div>
	<?php endif; ?>
</div>
		<?php
		if ( ! $for_pdf ) {
			wp_print_scripts( 'dcsf-statement-print' );
		}
		?>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render a customer's block inside the print document.
	 *
	 * @param array  $section   Section data.
	 * @param string $tax_label Tax label.
	 * @param string $mode      'paid' or 'account'.
	 * @return void
	 */
	private static function render_print_section( $section, $tax_label, $mode = 'paid' ) {
		$t       = $section['target'];
		$totals  = $section['totals'];
		$has_ref = $totals['refunded'] > 0;

		echo '<section class="doc-section">';
		echo '<h3 class="doc-cust">' . esc_html( $t['name'] );
		if ( $t['email'] && $t['email'] !== $t['name'] ) {
			echo ' <span class="doc-cust-email">&lt;' . esc_html( $t['email'] ) . '&gt;</span>';
		}
		echo '</h3>';

		if ( empty( $section['rows'] ) ) {
			echo '<p class="doc-empty">' . esc_html__( 'No orders in this period.', 'demfilz-customer-statements-for-woocommerce' ) . '</p></section>';
			return;
		}

		if ( 'account' === $mode ) {
			self::render_print_section_account( $section );
			echo '</section>';
			return;
		}

		echo '<table class="doc-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Payment', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="num">' . esc_html__( 'Net', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="num">' . esc_html( $tax_label ) . '</th>';
		echo '<th class="num">' . esc_html__( 'Total', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		if ( $has_ref ) {
			echo '<th class="num">' . esc_html__( 'Refunded', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $section['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['date_ts'] ? wp_date( get_option( 'date_format' ), $row['date_ts'] ) : '—' ) . '</td>';
			echo '<td>#' . esc_html( $row['number'] ) . '</td>';
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( $row['payment'] ) . '</td>';
			echo '<td class="num">' . wp_kses_post( wc_price( $row['net'] ) ) . '</td>';
			echo '<td class="num">' . wp_kses_post( wc_price( $row['tax'] ) ) . '</td>';
			echo '<td class="num">' . wp_kses_post( wc_price( $row['total'] ) ) . '</td>';
			if ( $has_ref ) {
				echo '<td class="num">' . ( $row['refunded'] > 0 ? '-' . wp_kses_post( wc_price( $row['refunded'] ) ) : '—' ) . '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody><tfoot><tr>';
		echo '<th colspan="4">' . sprintf(
			/* translators: %d: number of paid orders. */
			esc_html( _n( '%d paid order', '%d paid orders', $totals['count'], 'demfilz-customer-statements-for-woocommerce' ) ),
			(int) $totals['count']
		) . '</th>';
		echo '<th class="num">' . wp_kses_post( wc_price( $totals['net'] ) ) . '</th>';
		echo '<th class="num">' . wp_kses_post( wc_price( $totals['tax'] ) ) . '</th>';
		echo '<th class="num">' . wp_kses_post( wc_price( $totals['total'] ) ) . '</th>';
		if ( $has_ref ) {
			echo '<th class="num">-' . wp_kses_post( wc_price( $totals['refunded'] ) ) . '</th>';
		}
		echo '</tr></tfoot></table>';
		echo '</section>';
	}

	/**
	 * Render a customer's ACCOUNT block in the print document.
	 *
	 * @param array $section Section data.
	 * @return void
	 */
	private static function render_print_section_account( $section ) {
		$totals = $section['totals'];

		echo '<table class="doc-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Payment', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="num">' . esc_html__( 'Invoiced', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="num">' . esc_html__( 'Paid', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '<th class="num">' . esc_html__( 'Balance', 'demfilz-customer-statements-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $section['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['date_ts'] ? wp_date( get_option( 'date_format' ), $row['date_ts'] ) : '—' ) . '</td>';
			echo '<td>#' . esc_html( $row['number'] ) . '</td>';
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( $row['payment'] ) . '</td>';
			echo '<td class="num">' . wp_kses_post( wc_price( $row['invoiced'] ) ) . '</td>';
			echo '<td class="num">' . wp_kses_post( wc_price( $row['paid_amt'] ) ) . '</td>';
			echo '<td class="num">' . ( $row['balance'] > 0 ? wp_kses_post( wc_price( $row['balance'] ) ) : '—' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody><tfoot><tr>';
		echo '<th colspan="4">' . sprintf(
			/* translators: %d: number of orders. */
			esc_html( _n( '%d order', '%d orders', $totals['count'], 'demfilz-customer-statements-for-woocommerce' ) ),
			(int) $totals['count']
		) . '</th>';
		echo '<th class="num">' . wp_kses_post( wc_price( $totals['invoiced'] ) ) . '</th>';
		echo '<th class="num">' . wp_kses_post( wc_price( $totals['paid'] ) ) . '</th>';
		echo '<th class="num">' . wp_kses_post( wc_price( $totals['balance'] ) ) . '</th>';
		echo '</tr></tfoot></table>';
	}

	/**
	 * Render the grand-total block in the print document.
	 *
	 * @param array  $grand     Grand totals.
	 * @param string $tax_label Tax label.
	 * @param string $mode      'paid' or 'account'.
	 * @return void
	 */
	private static function render_print_grand( $grand, $tax_label, $mode = 'paid' ) {
		echo '<section class="doc-grand"><h3>' . esc_html__( 'All selected customers — total', 'demfilz-customer-statements-for-woocommerce' ) . '</h3>';
		echo '<table class="doc-table doc-grand-table"><tbody>';
		if ( 'account' === $mode ) {
			echo '<tr><th>' . esc_html__( 'Orders', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . esc_html( (string) (int) $grand['count'] ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Invoiced', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . wp_kses_post( wc_price( $grand['invoiced'] ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Paid', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . wp_kses_post( wc_price( $grand['paid'] ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Outstanding balance', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . wp_kses_post( wc_price( $grand['balance'] ) ) . '</td></tr>';
		} else {
			echo '<tr><th>' . esc_html__( 'Paid orders', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . esc_html( (string) (int) $grand['count'] ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Net', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . wp_kses_post( wc_price( $grand['net'] ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html( $tax_label ) . '</th><td class="num">' . wp_kses_post( wc_price( $grand['tax'] ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Total (inc. tax)', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . wp_kses_post( wc_price( $grand['total'] ) ) . '</td></tr>';
			if ( $grand['refunded'] > 0 ) {
				echo '<tr><th>' . esc_html__( 'Refunded', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">-' . wp_kses_post( wc_price( $grand['refunded'] ) ) . '</td></tr>';
				echo '<tr><th>' . esc_html__( 'Net received', 'demfilz-customer-statements-for-woocommerce' ) . '</th><td class="num">' . wp_kses_post( wc_price( $grand['net_paid'] ) ) . '</td></tr>';
			}
		}
		echo '</tbody></table></section>';
	}

	// ----- CSV -----

	/**
	 * Stream the dataset as a CSV download.
	 *
	 * @param array $data Built dataset.
	 * @return void
	 */
	public static function stream_csv( $data ) {
		$tax_label = DCSF_Settings::get( 'tax_label', 'GST' );
		$slug      = sanitize_title( $data['range']['label'] );
		$filename  = 'customer-statement-' . ( $slug ? $slug : 'export' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- writing to the PHP output stream for a file download.
		$out = fopen( 'php://output', 'w' );

		// UTF-8 BOM for spreadsheet apps.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- output stream.
		fwrite( $out, "\xEF\xBB\xBF" );

		$account = ( isset( $data['mode'] ) && 'account' === $data['mode'] );

		if ( $account ) {
			self::csv_row(
				$out,
				array(
					__( 'Customer', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Email', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Date', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Order', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Status', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Payment', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Invoiced', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Paid', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Balance', 'demfilz-customer-statements-for-woocommerce' ),
				)
			);
		} else {
			self::csv_row(
				$out,
				array(
					__( 'Customer', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Email', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Date', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Order', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Status', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Payment', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Net', 'demfilz-customer-statements-for-woocommerce' ),
					$tax_label,
					__( 'Total', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Refunded', 'demfilz-customer-statements-for-woocommerce' ),
					__( 'Net paid', 'demfilz-customer-statements-for-woocommerce' ),
				)
			);
		}

		foreach ( $data['sections'] as $section ) {
			$t = $section['target'];
			foreach ( $section['rows'] as $row ) {
				$date = $row['date_ts'] ? wp_date( 'Y-m-d', $row['date_ts'] ) : '';
				if ( $account ) {
					self::csv_row(
						$out,
						array(
							$t['name'],
							$t['email'],
							$date,
							'#' . $row['number'],
							$row['status'],
							$row['payment'],
							self::num( $row['invoiced'] ),
							self::num( $row['paid_amt'] ),
							self::num( $row['balance'] ),
						)
					);
				} else {
					self::csv_row(
						$out,
						array(
							$t['name'],
							$t['email'],
							$date,
							'#' . $row['number'],
							$row['status'],
							$row['payment'],
							self::num( $row['net'] ),
							self::num( $row['tax'] ),
							self::num( $row['total'] ),
							self::num( $row['refunded'] ),
							self::num( $row['net_paid'] ),
						)
					);
				}
			}
			$tot = $section['totals'];
			/* translators: %d: count of orders. */
			$subtotal = sprintf( __( 'SUBTOTAL (%d orders)', 'demfilz-customer-statements-for-woocommerce' ), (int) $tot['count'] );
			if ( $account ) {
				self::csv_row( $out, array( $t['name'], $t['email'], '', '', $subtotal, '', self::num( $tot['invoiced'] ), self::num( $tot['paid'] ), self::num( $tot['balance'] ) ) );
			} else {
				self::csv_row( $out, array( $t['name'], $t['email'], '', '', $subtotal, '', self::num( $tot['net'] ), self::num( $tot['tax'] ), self::num( $tot['total'] ), self::num( $tot['refunded'] ), self::num( $tot['net_paid'] ) ) );
			}
		}

		if ( $data['multi'] ) {
			$g = $data['grand'];
			self::csv_row( $out, array() );
			/* translators: %d: count of orders. */
			$gcount = sprintf( __( '%d orders', 'demfilz-customer-statements-for-woocommerce' ), (int) $g['count'] );
			if ( $account ) {
				self::csv_row( $out, array( __( 'GRAND TOTAL', 'demfilz-customer-statements-for-woocommerce' ), '', '', '', $gcount, '', self::num( $g['invoiced'] ), self::num( $g['paid'] ), self::num( $g['balance'] ) ) );
			} else {
				self::csv_row( $out, array( __( 'GRAND TOTAL', 'demfilz-customer-statements-for-woocommerce' ), '', '', '', $gcount, '', self::num( $g['net'] ), self::num( $g['tax'] ), self::num( $g['total'] ), self::num( $g['refunded'] ), self::num( $g['net_paid'] ) ) );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- output stream.
		fclose( $out );
	}

	/**
	 * Write one CSV row.
	 *
	 * @param resource $handle Output handle.
	 * @param array    $fields Field values.
	 * @return void
	 */
	private static function csv_row( $handle, $fields ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- writing CSV to the output stream.
		fputcsv( $handle, $fields );
	}

	/**
	 * Format a number for CSV (plain, 2 decimals, no thousands separator).
	 *
	 * @param float $value Value.
	 * @return string
	 */
	private static function num( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}
}
