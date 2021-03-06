<?php
/**
 * The Analytics Module
 *
 * @since      1.0.49
 * @package    RankMath
 * @subpackage RankMath\modules
 * @author     Rank Math <support@rankmath.com>
 */

namespace RankMath\Analytics;

use RankMath\Helper;
use RankMath\Traits\Hooker;
use MyThemeShop\Helpers\Param;
use RankMathPro\Analytics\Pageviews;

defined( 'ABSPATH' ) || exit;

/**
 * Stats class.
 */
class Stats extends Keywords {

	use Hooker;

	/**
	 * Start date.
	 *
	 * @var string
	 */
	public $start_date = '';

	/**
	 * End date.
	 *
	 * @var string
	 */
	public $end_date = '';

	/**
	 * Compare Start date.
	 *
	 * @var string
	 */
	public $compare_start_date = '';

	/**
	 * Compare End date.
	 *
	 * @var string
	 */
	public $compare_end_date = '';

	/**
	 * Number of days.
	 *
	 * @var int
	 */
	public $days = 0;

	/**
	 * Main instance
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return Stats
	 */
	public static function get() {
		static $instance;

		if ( is_null( $instance ) && ! ( $instance instanceof Stats ) ) {
			$instance = new Stats();
			$instance->set_date_range();
		}

		return $instance;
	}

	/**
	 * Date range.
	 *
	 * @param string $range Range of days.
	 */
	public function set_date_range( $range = false ) {
		// Dates.
		$subtract = DAY_IN_SECONDS * 3;
		$start    = strtotime( false !== $range ? $range : $this->get_date_from_cookie( 'date_range', '-30 days' ) ) - $subtract;
		$end      = strtotime( $this->do_filter( 'analytics/end_date', 'today' ) ) - $subtract;

		// Timestamp.
		$this->end   = Helper::get_midnight( $end );
		$this->start = Helper::get_midnight( $start );

		// Period.
		$this->end_date   = date_i18n( 'Y-m-d 23:59:59', $end );
		$this->start_date = date_i18n( 'Y-m-d 00:00:00', $start );

		// Compare with.
		$this->days               = ceil( abs( $end - $start ) / DAY_IN_SECONDS );
		$this->compare_end_date   = $start - DAY_IN_SECONDS;
		$this->compare_start_date = $this->compare_end_date - ( $this->days * DAY_IN_SECONDS );
		$this->compare_end_date   = date_i18n( 'Y-m-d 23:59:59', $this->compare_end_date );
		$this->compare_start_date = date_i18n( 'Y-m-d 00:00:00', $this->compare_start_date );
	}

	/**
	 * Get sql range.
	 *
	 * @param string $column Column name.
	 *
	 * @return string
	 */
	public function get_sql_range( $column = 'date' ) {
		$range    = $this->get_date_from_cookie( 'date_range', '-30 days' );
		$interval = [
			'-30 days'  => 'WEEK(' . $column . ')',
			'-3 months' => 'WEEK(' . $column . ')',
			'-6 months' => 'MONTH(' . $column . ')',
			'-1 year'   => 'MONTH(' . $column . ')',
		];

		return isset( $interval[ $range ] ) ? $interval[ $range ] : $column;
	}

	/**
	 * Get intervals for graph.
	 *
	 * @return array
	 */
	public function get_intervals() {
		$range    = $this->get_date_from_cookie( 'date_range', '-30 days' );
		$interval = [
			'-7 days'   => '0 days',
			'-15 days'  => '-3 days',
			'-30 days'  => '-6 days',
			'-3 months' => '-6 days',
			'-6 months' => '-30 days',
			'-1 year'   => '-30 days',
		];

		$ticks = [
			'-7 days'   => 7,
			'-15 days'  => 5,
			'-30 days'  => 5,
			'-3 months' => 13,
			'-6 months' => 6,
			'-1 year'   => 12,
		];

		$addition = [
			'-7 days'   => 0,
			'-15 days'  => DAY_IN_SECONDS,
			'-30 days'  => DAY_IN_SECONDS,
			'-3 months' => -DAY_IN_SECONDS / 6,
			'-6 months' => DAY_IN_SECONDS / 2,
			'-1 year'   => 0,
		];

		$ticks    = $ticks[ $range ];
		$interval = $interval[ $range ];
		$addition = $addition[ $range ];

		$map   = [];
		$dates = [];

		$end   = $this->end;
		$start = strtotime( $interval, $end );

		for ( $i = 0; $i < $ticks; $i++ ) {
			$end_date   = date_i18n( 'Y-m-d', $end );
			$start_date = date_i18n( 'Y-m-d', $start );

			$dates[ $end_date ] = [
				'start'     => $start_date,
				'end'       => $end_date,
				'formatted' => $start_date === $end_date ?
					date_i18n( 'd M, Y', $end ) :
					date_i18n( 'd M', $start ) . ' - ' . date_i18n( 'd M, Y', $end ),
			];

			$map[ $start_date ] = $end_date;
			for ( $j = 1; $j < 32; $j++ ) {
				$date = date_i18n( 'Y-m-d', strtotime( $j . ' days', $start ) );
				if ( $start_date === $end_date ) {
					break;
				}

				if ( $date === $end_date ) {
					break;
				}

				$map[ $date ] = $end_date;
			}
			$map[ $end_date ] = $end_date;

			$end   = \strtotime( '-1 days', $start );
			$start = \strtotime( $interval, $end + $addition );
		}

		return [
			'map'   => $map,
			'dates' => \array_reverse( $dates ),
		];
	}

	/**
	 * Get date intervals sql part.
	 *
	 * @param  array  $intervals Date Intervals.
	 * @param  string $column Column name to check.
	 * @param  string $newcolumn Column name to return.
	 * @return string
	 */
	public function get_sql_date_intervals( $intervals, $column = 'created', $newcolumn = 'range_group' ) {
		$sql_parts = [];
		array_push( $sql_parts, 'CASE' );

		$index = 1;
		foreach ( $intervals['dates'] as $date_range ) {
			$start_date = $date_range['start'] . ' 00:00:00';
			$end_date   = $date_range['end'] . ' 23:59:59';

			array_push( $sql_parts, sprintf( "WHEN %s BETWEEN '%s' AND '%s' THEN 'range%d'", $column, $start_date, $end_date, $index ) );

			$index ++;
		}

		array_push( $sql_parts, "ELSE 'none'" );
		array_push( $sql_parts, sprintf( "END AS '%s'", $newcolumn ) );

		return implode( ' ', $sql_parts );
	}

	/**
	 * Get date array
	 *
	 * @param  array $dates Dates.
	 * @param  array $default Default value.
	 * @return array
	 */
	public function get_date_array( $dates, $default ) {
		$data = [];
		foreach ( $dates as $date => $d ) {
			$data[ $date ]                  = $default;
			$data[ $date ]['date']          = $date;
			$data[ $date ]['dateFormatted'] = $d['formatted'];
		}

		return $data;
	}

	/**
	 * Convert data to proper type.
	 *
	 * @param  array $row Row to normalize.
	 * @return array
	 */
	public function normalize_graph_rows( $row ) {
		foreach ( $row as $col => $val ) {
			if ( in_array( $col, [ 'query', 'page', 'date', 'created', 'dateFormatted' ], true ) ) {
				continue;
			}

			if ( in_array( $col, [ 'ctr', 'position', 'earnings' ], true ) ) {
				$row->$col = round( $row->$col, 0 );
				continue;
			}

			$row->$col = absint( $row->$col );
		}

		return $row;
	}

	/**
	 * Remove uncessary graph rows.
	 *
	 * @param  array $rows Rows to filter.
	 * @return array
	 */
	public function filter_graph_rows( $rows ) {
		foreach ( $rows as $key => $row ) {
			if ( isset( $row->range_group ) && 'none' === $row->range_group ) {
				unset( $rows[ $key ] );
			}
		}
		return $rows;
	}

	/**
	 * Extract proper data.
	 *
	 * @param  array  $rows   Data rows.
	 * @param  string $column Column name contains mixed data.
	 * @param  string $sep    Separator for mixed data.
	 * @param  array  $keys   Column array to extract.
	 * @return array
	 */
	public function extract_data_from_mixed( $rows, $column, $sep, $keys ) {
		foreach ( $rows as $index => &$row ) {
			if ( ! isset( $row->$column ) ) {
				continue;
			}

			$mixed       = explode( $sep, $row->$column );
			$mixed_count = count( $mixed );
			if ( ! $mixed_count ) {
				continue;
			}

			foreach ( $keys as $key_idx => $key ) {
				if ( 'position' === $key ) {
					$value = 100 - (int) $mixed[ $mixed_count - $key_idx - 1 ];
				} else {
					$value = $mixed[ $mixed_count - $key_idx - 1 ];
				}
				$row->$key = $value;
			}

			unset( $row->$column );
		}

		return $rows;
	}

	/**
	 * Merge two metrics array into one
	 *
	 * @param  array   $metrics_rows1 Metrics Rows to merge.
	 * @param  array   $metrics_rows2 Metrics Rows to merge.
	 * @param  boolean $has_traffic   Flag to include/exclude traffic data.
	 * @return array
	 */
	public function get_merged_metrics( $metrics_rows1, $metrics_rows2, $has_traffic = false ) {
		$data = [];

		$base_array = [
			'position'        => 0,
			'diffPosition'    => 0,
			'clicks'          => 0,
			'diffClicks'      => 0,
			'impressions'     => 0,
			'diffImpressions' => 0,
			'ctr'             => 0,
			'diffCtr'         => 0,
		];

		if ( $has_traffic ) {
			$base_array['pageviews']  = 0;
			$base_array['difference'] = 0;
		}

		foreach ( $metrics_rows1 as $key => $row ) {
			if ( isset( $metrics_rows2[ $key ] ) ) {
				if ( is_object( $row ) ) {
					$data[ $key ] = (object) array_merge( $base_array, (array) $row, (array) $metrics_rows2[ $key ] );
				} else {
					$data[ $key ] = array_merge( $base_array, $row, $metrics_rows2[ $key ] );
				}
				unset( $metrics_rows2[ $key ] );
			} else {
				$data[ $key ] = array_merge( $base_array, $row );
			}
		}

		foreach ( $metrics_rows2 as $key => $row ) {
			if ( is_object( $row ) ) {
				$metrics_rows2[ $key ] = (object) array_merge( $base_array, (array) $row );
			} else {
				$metrics_rows2[ $key ] = array_merge( $base_array, $row );
			}
		}

		return array_merge( $data, $metrics_rows2 );
	}

	/**
	 * [get_merge_data_graph description]
	 *
	 * @param  array $rows Rows to merge.
	 * @param  array $data Data array.
	 * @param  array $map  Interval map.
	 * @return array
	 */
	public function get_merge_data_graph( $rows, $data, $map ) {
		foreach ( $rows as $row ) {
			if ( ! isset( $map[ $row->date ] ) ) {
				continue;
			}

			$date = $map[ $row->date ];
			foreach ( $row as $key => $value ) {
				if ( 'date' === $key || 'created' === $key ) {
					continue;
				}

				// trick to invert Position Graph YAxis.
				if ( 'position' === $key ) {
					$value = 0 - $value;
				}
				$data[ $date ][ $key ][] = $value;
			}
		}

		return $data;
	}

	/**
	 * Flat graph data.
	 *
	 * @param  array $rows Graph data.
	 * @return array
	 */
	public function get_graph_data_flat( $rows ) {
		foreach ( $rows as &$row ) {
			if ( isset( $row['clicks'] ) ) {
				$row['clicks'] = \array_sum( $row['clicks'] );
			}

			if ( isset( $row['impressions'] ) ) {
				$row['impressions'] = \array_sum( $row['impressions'] );
			}

			if ( isset( $row['earnings'] ) ) {
				$row['earnings'] = \array_sum( $row['earnings'] );
			}

			if ( isset( $row['pageviews'] ) ) {
				$row['pageviews'] = \array_sum( $row['pageviews'] );
			}

			if ( isset( $row['ctr'] ) ) {
				$row['ctr'] = empty( $row['ctr'] ) ? 0 : ceil( array_sum( $row['ctr'] ) / count( $row['ctr'] ) );
			}

			if ( isset( $row['position'] ) ) {
				if ( empty( $row['position'] ) ) {
					unset( $row['position'] );
				} else {
					$row['position'] = ceil( array_sum( $row['position'] ) / count( $row['position'] ) );
				}
			}

			if ( isset( $row['keywords'] ) ) {
				$row['keywords'] = empty( $row['keywords'] ) ? 0 : ceil( array_sum( $row['keywords'] ) / count( $row['keywords'] ) );
			}
		}

		return $rows;
	}

	/**
	 * Get filter data.
	 *
	 * @param string $filter  Filter key.
	 * @param string $default Filter default value.
	 *
	 * @return mixed
	 */
	public function get_date_from_cookie( $filter, $default ) {
		$cookie_key = 'rank_math_analytics_' . $filter;
		$new_value  = sanitize_title( Param::post( $filter ) );
		if ( $new_value ) {
			setcookie( $cookie_key, $new_value, time() + ( HOUR_IN_SECONDS * 30 ), COOKIEPATH, COOKIE_DOMAIN, false, true );
			return $new_value;
		}

		if ( ! empty( $_COOKIE[ $cookie_key ] ) ) {
			return $_COOKIE[ $cookie_key ];
		}

		return $default;
	}

	/**
	 * Get rows from analytics.
	 *
	 * @param  array $args Array of arguments.
	 * @return array
	 */
	public function get_analytics_data( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			[
				'dimension' => 'page',
				'order'     => 'DESC',
				'orderBy'   => 'diffPosition',
				'objects'   => false,
				'pageview'  => false,
				'where'     => '',
				'sub_where' => '',
				'pages'     => [],
				'type'      => '',
				'offset'    => 0,
				'perpage'   => 5,
			]
		);

		$dimension      = $args['dimension'];
		$type           = $args['type'];
		$offset         = $args['offset'];
		$perpage        = $args['perpage'];
		$order_by_field = $args['orderBy'];

		$order_position_fields = [ 'position', 'diffPosition' ];
		$order_metrics_fields  = [ 'clicks', 'diffClicks', 'impressions', 'diffImpressions', 'ctr', 'diffCtr' ];

		if ( in_array( $order_by_field, $order_position_fields, true ) ) {
			// In case order by position related fields, get position data first.
			$positions = $this->get_position_data_by_dimension( $args );

			// Filter position data by condition.
			$positions = $this->filter_analytics_data( $positions, $args );

			// Get dimension list from above result.
			$dimensions = wp_list_pluck( $positions, $dimension );
			$dimensions = array_map( 'esc_sql', $dimensions );

			// Get metrics data based on above dimension list.
			$metrics = $this->get_metrics_data_by_dimension(
				[
					'dimension' => $dimension,
					'sub_where' => ' AND ' . $dimension . " IN ('" . join( "', '", $dimensions ) . "')",
				]
			);

			// Merge above two data into one.
			$rows = $this->get_merged_metrics( $positions, $metrics, true );

		} elseif ( in_array( $order_by_field, $order_metrics_fields, true ) ) {
			// In case order by fields which are not related with position, get metrics data first.
			$metrics = $this->get_metrics_data_by_dimension( $args );

			// Filter metrics data by condition.
			$metrics = $this->filter_analytics_data( $metrics, $args );

			// Get dimension list from above result.
			$dimensions = wp_list_pluck( $metrics, $dimension );
			$dimensions = array_map( 'esc_sql', $dimensions );

			// Get position data based on above dimension list.
			$positions = $this->get_position_data_by_dimension(
				[
					'dimension' => $dimension,
					'sub_where' => ' AND ' . $dimension . " IN ('" . join( "', '", $dimensions ) . "')",
				]
			);

			// Merge above two data into one.
			$rows = $this->get_merged_metrics( $metrics, $positions, true );
		} else {
			// Get position data and other metrics data separately.
			$positions = $this->get_position_data_by_dimension( $args );
			$metrics   = $this->get_metrics_data_by_dimension( $args );

			// Merge above two data into one.
			$rows = $this->get_merged_metrics( $positions, $metrics, true );

			// Filter array by condition.
			$rows = $this->filter_analytics_data( $rows, $args );
		}

		$page_urls = \array_merge( \array_keys( $rows ), $args['pages'] );

		$pageviews = [];
		if ( \class_exists( 'RankMathPro\Analytics\Pageviews' ) && $args['pageview'] && ! empty( $page_urls ) ) {
			$pageviews = Pageviews::get_pageviews( [ 'pages' => $page_urls ] );
			$pageviews = $pageviews['rows'];
		}

		if ( $args['objects'] ) {
			$objects = $this->get_objects( $page_urls );
		}
		foreach ( $rows as $page => $row ) {
			$rows[ $page ]['pageviews'] = [
				'total'      => 0,
				'difference' => 0,
			];

			$rows[ $page ]['clicks'] = [
				'total'      => (int) $rows[ $page ]['clicks'],
				'difference' => (int) $rows[ $page ]['diffClicks'],
			];

			$rows[ $page ]['impressions'] = [
				'total'      => (int) $rows[ $page ]['impressions'],
				'difference' => (int) $rows[ $page ]['diffImpressions'],
			];

			$rows[ $page ]['position'] = [
				'total'      => (float) $rows[ $page ]['position'],
				'difference' => (float) $rows[ $page ]['diffPosition'],
			];

			$rows[ $page ]['ctr'] = [
				'total'      => (float) $rows[ $page ]['ctr'],
				'difference' => (float) $rows[ $page ]['diffCtr'],
			];

			unset(
				$rows[ $page ]['diffClicks'],
				$rows[ $page ]['diffImpressions'],
				$rows[ $page ]['diffPosition'],
				$rows[ $page ]['diffCtr'],
				$rows[ $page ]['difference']
			);
		}

		if ( $args['pageview'] && ! empty( $pageviews ) ) {
			foreach ( $pageviews as $pageview ) {
				$page = $pageview['page'];
				if ( ! isset( $rows[ $page ] ) ) {
					$rows[ $page ] = [];
				}

				$rows[ $page ]['pageviews'] = [
					'total'      => (int) $pageview['pageviews'],
					'difference' => (int) $pageview['difference'],
				];
			}
		}

		if ( $args['objects'] && ! empty( $objects ) ) {
			foreach ( $objects as $object ) {
				$page = $object['page'];
				if ( ! isset( $rows[ $page ] ) ) {
					$rows[ $page ] = [];
				}
				$rows[ $page ] = array_merge( $rows[ $page ], $object );
			}
		}

		return $rows;
	}

	/**
	 * Get position data.
	 *
	 * @param array $args Argument array.
	 * @return array
	 */
	public function get_position_data_by_dimension( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			[
				'dimension' => 'page',
				'where'     => '',
				'sub_where' => '',
			]
		);

		$dimension = $args['dimension'];
		$where     = $args['where'];
		$sub_where = $args['sub_where'];

		if ( 'page' === $dimension ) {
			// phpcs:disable
			// Get current position data.
			$query = $wpdb->prepare(
				"SELECT {$dimension}, MAX(CONCAT({$dimension}, ':', DATE(created), ':', LPAD((100 - position), 3, '0'))) as uid
				FROM {$wpdb->prefix}rank_math_analytics_gsc 
				WHERE created BETWEEN %s AND %s {$sub_where}
				GROUP BY {$dimension}",
				$this->start_date,
				$this->end_date
			);
			$positions = $wpdb->get_results( $query );

			// Get old position data.
			$query = $wpdb->prepare(
				"SELECT {$dimension}, MAX(CONCAT({$dimension}, ':', DATE(created), ':', LPAD((100 - position), 3, '0'))) as uid
				FROM {$wpdb->prefix}rank_math_analytics_gsc 
				WHERE created BETWEEN %s AND %s 
				GROUP BY {$dimension}",
				$this->compare_start_date,
				$this->compare_end_date
			);
			$old_positions = $wpdb->get_results( $query );
			// phpcs:enable

			// Extract proper position data.
			$positions     = $this->extract_data_from_mixed( $positions, 'uid', ':', [ 'position', 'date' ] );
			$old_positions = $this->extract_data_from_mixed( $old_positions, 'uid', ':', [ 'position', 'date' ] );

			// Set 'page' as key.
			$positions     = $this->set_dimension_as_key( $positions, $dimension );
			$old_positions = $this->set_dimension_as_key( $old_positions, $dimension );

			// Calculate position difference, merge old into current.
			foreach ( $positions as $page => &$row ) {
				$row = (array) $row; // force to convert as array.
				if ( ! isset( $old_positions[ $page ] ) ) {
					$old_position_value = 100; // Should set as 100 here to get correct position difference.
				} else {
					$old_position_value = $old_positions[ $page ]->position;
				}

				$row['diffPosition'] = $row['position'] - $old_position_value;
			}
		} else {
			// phpcs:disable
			$query = $wpdb->prepare(
				"SELECT
					t1.{$dimension} as {$dimension}, ROUND( t1.position, 0 ) as position,
					COALESCE( ROUND( t1.position - COALESCE( t2.position, 100 ), 0 ), 0 ) as diffPosition
				FROM
					( SELECT a.{$dimension}, a.position FROM {$wpdb->prefix}rank_math_analytics_gsc AS a WHERE 1 = 1 AND id IN (SELECT MAX(id) FROM {$wpdb->prefix}rank_math_analytics_gsc WHERE created BETWEEN %s AND %s {$sub_where} GROUP BY {$dimension})) AS t1
				LEFT JOIN
					( SELECT a.{$dimension}, a.position FROM {$wpdb->prefix}rank_math_analytics_gsc AS a WHERE 1 = 1 AND id IN (SELECT MAX(id) FROM {$wpdb->prefix}rank_math_analytics_gsc WHERE created BETWEEN %s AND %s {$sub_where} GROUP BY {$dimension})) AS t2
				ON t1.{$dimension} = t2.{$dimension}
				{$where}",
				$this->start_date,
				$this->end_date,
				$this->compare_start_date,
				$this->compare_end_date
			);
			$positions = $wpdb->get_results( $query, ARRAY_A );
			// phpcs:enable

			$positions = $this->set_dimension_as_key( $positions, $dimension );
		}

		return $positions;
	}

	/**
	 * Get metrics data.
	 *
	 * @param array $args Argument array.
	 * @return array
	 */
	public function get_metrics_data_by_dimension( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			[
				'dimension' => 'page',
				'sub_where' => '',
			]
		);

		$dimension = $args['dimension'];
		$sub_where = $args['sub_where'];

		// phpcs:disable
		// Get metrics data like impressions, click, ctr, etc.
		$query = $wpdb->prepare(
			"SELECT
				t1.{$dimension} as {$dimension}, t1.clicks, t1.impressions, t1.ctr,
				COALESCE( t1.clicks - t2.clicks, 0 ) as diffClicks,
				COALESCE( t1.impressions - t2.impressions, 0 ) as diffImpressions,
				COALESCE( t1.ctr - t2.ctr, 0 ) as diffCtr
			FROM
				( SELECT {$dimension}, SUM( clicks ) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr
					FROM {$wpdb->prefix}rank_math_analytics_gsc
					WHERE 1 = 1 AND created BETWEEN %s AND %s {$sub_where}
					GROUP BY {$dimension}) as t1
			LEFT JOIN
				( SELECT {$dimension}, SUM( clicks ) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr
					FROM {$wpdb->prefix}rank_math_analytics_gsc
					WHERE 1 = 1 AND created BETWEEN %s AND %s {$sub_where}
					GROUP BY {$dimension}) as t2
			ON t1.{$dimension} = t2.{$dimension}",
			$this->start_date,
			$this->end_date,
			$this->compare_start_date,
			$this->compare_end_date
		);
		
		$metrics = $wpdb->get_results( $query, ARRAY_A );
		// phpcs:enable

		$metrics = $this->set_dimension_as_key( $metrics, $dimension );

		return $metrics;
	}

	/**
	 * Filter analytics data.
	 *
	 * @param array $data Data to process.
	 * @param array $args Argument array.
	 * @return array
	 */
	public function filter_analytics_data( $data, $args ) {
		$order_position_fields = [ 'position', 'diffPosition' ];
		$dimension             = $args['dimension'];
		$type                  = $args['type'];
		$offset                = $args['offset'];
		$perpage               = $args['perpage'];
		$order_by_field        = $args['orderBy'];

		// Filter array by $type value.
		$order_by_position = in_array( $order_by_field, [ 'diffPosition', 'position' ], true ) ? true : false;

		if ( ( 'win' === $type && $order_by_position ) || ( 'lose' === $type && ! $order_by_position ) ) {
			$data = array_filter(
				$data,
				function( $row ) use ( $order_by_field ) {
					return $row[ $order_by_field ] < 0;
				}
			);
		} elseif ( ( 'lose' === $type && $order_by_position ) || ( 'win' === $type && ! $order_by_position ) ) {
			$data = array_filter(
				$data,
				function( $row ) use ( $order_by_field ) {
					return $row[ $order_by_field ] > 0;
				}
			);
		}

		// Sort array by $args['order'], $order_by_field value.
		if ( ! empty( $args['order'] ) ) {
			$sort_base_arr = array_column( $data, $order_by_field, $dimension );
			array_multisort( $sort_base_arr, 'ASC' === $args['order'] ? SORT_ASC : SORT_DESC, $data );
		}

		// Filter array by $offset, $perpage value.
		$data = array_slice( $data, $offset, $perpage, true );

		return $data;
	}

	/**
	 * Set page as key.
	 *
	 * @param array $data Rows to process.
	 * @return array
	 */
	public function set_page_as_key( $data ) {
		$rows = [];
		foreach ( $data as $row ) {
			$page          = $this->get_relative_url( $row['page'] );
			$rows[ $page ] = $row;
		}

		return $rows;
	}

	/**
	 * Set query as key.
	 *
	 * @param array $data Rows to process.
	 * @return array
	 */
	public function set_query_as_key( $data ) {
		$rows = [];
		foreach ( $data as $row ) {
			$rows[ $row['query'] ] = $row;
		}
		return $rows;
	}

	/**
	 * Set dimension as key.
	 *
	 * @param array  $data      Rows to process.
	 * @param string $dimension Dimension to set as key.
	 * @return array
	 */
	public function set_dimension_as_key( $data, $dimension = 'query' ) {
		$rows = [];
		foreach ( $data as $row ) {
			if ( is_object( $row ) ) {
				$value = $row->$dimension;
			} else {
				$value = $row[ $dimension ];
			}
			$key          = 'page' === $dimension ? $this->get_relative_url( $value ) : $value;
			$rows[ $key ] = $row;
		}

		return $rows;
	}

	/**
	 * Set query position history.
	 *
	 * @param array $data    Rows to process.
	 * @param array $history Rows to process.
	 *
	 * @return array
	 */
	public function set_query_position( $data, $history ) {
		foreach ( $history as $row ) {
			if ( ! isset( $data[ $row->query ]['query'] ) ) {
				$data[ $row->query ]['query'] = $row->query;
			}

			if ( ! isset( $data[ $row->query ]['graph'] ) ) {
				$data[ $row->query ]['graph'] = [];
			}

			$data[ $row->query ]['graph'][] = $row;
		}

		return $data;
	}

	/**
	 * Set page position history.
	 *
	 * @param array $data    Rows to process.
	 * @param array $history Rows to process.
	 *
	 * @return array
	 */
	public function set_page_position_graph( $data, $history ) {
		foreach ( $history as $row ) {
			if ( ! isset( $data[ $row->page ]['graph'] ) ) {
				$data[ $row->page ]['graph'] = [];
			}

			$data[ $row->page ]['graph'][] = $row;
		}

		return $data;
	}

	/**
	 * Generate Cache Keys.
	 *
	 * @param string $what What for you need the key.
	 * @param mixed  $args more salt to add into key.
	 *
	 * @return string
	 */
	public function get_cache_key( $what, $args = [] ) {
		$key = 'rank_math_' . $what;

		if ( ! empty( $args ) ) {
			$key .= '_' . join( '_', (array) $args );
		}

		return $key;
	}

	/**
	 * Get relative url.
	 *
	 * @param  string $url Url to make relative.
	 * @return string
	 */
	public static function get_relative_url( $url ) {
		$home_url = home_url();

		$domain = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$domain = str_replace( [ 'www.', '.' ], [ '', '\.' ], $domain );
		$regex  = "/http[s]?:\/\/(www\.)?$domain/mU";
		$url    = strtolower( trim( $url ) );
		$url    = preg_replace( $regex, '', $url );

		/**
		 * Google API and get_permalink sends URL Encoded strings so we need
		 * to urldecode in order to get them to match with whats saved in DB.
		 */
		$url = urldecode( $url );
		return \str_replace( $home_url, '', $url );
	}
}
