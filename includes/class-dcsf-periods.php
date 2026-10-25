<?php
/**
 * Period resolution — turns a financial year, quarter, month or custom range
 * into concrete start/end instants and a human label.
 *
 * @package DemfilzCustomerStatements
 */

defined( 'ABSPATH' ) || exit;

/**
 * Date-range helpers for statements.
 */
class DCSF_Periods {

	/**
	 * The configured financial-year start month (1-12).
	 *
	 * @return int
	 */
	public static function start_month() {
		return max( 1, min( 12, (int) DCSF_Settings::get( 'fy_start_month', 7 ) ) );
	}

	/**
	 * Build the start-of-financial-year date for a given start calendar year.
	 *
	 * @param int $year Calendar year the FY begins in.
	 * @return DateTimeImmutable
	 */
	public static function fy_start_date( $year ) {
		$dt = new DateTimeImmutable( 'now', wp_timezone() );
		return $dt->setDate( (int) $year, self::start_month(), 1 )->setTime( 0, 0, 0 );
	}

	/**
	 * The calendar year in which the current financial year started.
	 *
	 * @return int
	 */
	public static function current_fy_start_year() {
		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		$year  = (int) $now->format( 'Y' );
		$start = self::fy_start_date( $year );
		if ( $now < $start ) {
			--$year;
		}
		return $year;
	}

	/**
	 * Human label for a financial year, e.g. "FY 2025–26".
	 *
	 * @param int $year Calendar year the FY begins in.
	 * @return string
	 */
	public static function fy_label( $year ) {
		$year = (int) $year;
		if ( 1 === self::start_month() ) {
			/* translators: %d: calendar year. */
			return sprintf( __( 'FY %d', 'demfilz-customer-statements-for-woocommerce' ), $year );
		}
		/* translators: 1: start year, 2: two-digit end year. */
		return sprintf( __( 'FY %1$d–%2$s', 'demfilz-customer-statements-for-woocommerce' ), $year, substr( (string) ( $year + 1 ), 2 ) );
	}

	/**
	 * Offer a list of selectable financial years (current down to current-9).
	 *
	 * @return array<int,string> Map of start-year => label.
	 */
	public static function fy_options() {
		$current = self::current_fy_start_year();
		$out     = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$year         = $current - $i;
			$out[ $year ] = self::fy_label( $year );
		}
		return $out;
	}

	/**
	 * Resolve a filter set into a concrete date window.
	 *
	 * @param array $filters Parsed request filters.
	 * @return array {start, end, start_ts, end_ts, label}
	 */
	public static function resolve( $filters ) {
		$tz     = wp_timezone();
		$period = isset( $filters['period'] ) ? $filters['period'] : 'fy';

		switch ( $period ) {
			case 'quarter':
				$year    = (int) $filters['fy'];
				$quarter = max( 1, min( 4, (int) $filters['quarter'] ) );
				$start   = self::fy_start_date( $year )->modify( '+' . ( ( $quarter - 1 ) * 3 ) . ' months' );
				$end     = $start->modify( '+3 months' )->modify( '-1 second' );
				/* translators: 1: quarter number, 2: financial-year label. */
				$label = sprintf( __( 'Q%1$d %2$s', 'demfilz-customer-statements-for-woocommerce' ), $quarter, self::fy_label( $year ) );
				break;

			case 'month':
				$year  = (int) $filters['cal_year'];
				$month = max( 1, min( 12, (int) $filters['month'] ) );
				$start = ( new DateTimeImmutable( 'now', $tz ) )->setDate( $year, $month, 1 )->setTime( 0, 0, 0 );
				$end   = $start->modify( 'first day of next month' )->modify( '-1 second' );
				$label = wp_date( 'F Y', $start->getTimestamp(), $tz );
				break;

			case 'custom':
				$start = self::parse_date( $filters['from'] ?? '', false, $tz );
				$end   = self::parse_date( $filters['to'] ?? '', true, $tz );
				if ( ! $start || ! $end ) {
					return self::resolve(
						array(
							'period' => 'fy',
							'fy'     => self::current_fy_start_year(),
						)
					);
				}
				if ( $start > $end ) {
					$swap  = $start;
					$start = $end->setTime( 0, 0, 0 );
					$end   = $swap->setTime( 23, 59, 59 );
				}
				$label = wp_date( get_option( 'date_format' ), $start->getTimestamp(), $tz ) . ' – ' . wp_date( get_option( 'date_format' ), $end->getTimestamp(), $tz );
				break;

			case 'fy':
			default:
				$year  = (int) $filters['fy'];
				$start = self::fy_start_date( $year );
				$end   = $start->modify( '+1 year' )->modify( '-1 second' );
				$label = self::fy_label( $year );
				break;
		}

		return array(
			'start'    => $start,
			'end'      => $end,
			'start_ts' => $start->getTimestamp(),
			'end_ts'   => $end->getTimestamp(),
			'label'    => $label,
		);
	}

	/**
	 * Parse a Y-m-d string into a DateTimeImmutable.
	 *
	 * @param string            $value       The Y-m-d string.
	 * @param bool              $end_of_day  Whether to snap to 23:59:59.
	 * @param DateTimeZone|null $tz          Timezone.
	 * @return DateTimeImmutable|false
	 */
	private static function parse_date( $value, $end_of_day, $tz ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return false;
		}
		$dt = DateTimeImmutable::createFromFormat( 'Y-m-d', $value, $tz );
		if ( ! $dt ) {
			return false;
		}
		return $end_of_day ? $dt->setTime( 23, 59, 59 ) : $dt->setTime( 0, 0, 0 );
	}
}
