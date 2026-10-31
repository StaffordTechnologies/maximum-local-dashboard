<?php
/**
 * GA4 Data API client.
 *
 * @package MaximumLocalDashboard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MLD_GA4
 */
class MLD_GA4 {

	const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

	/**
	 * Run a GA4 report.
	 *
	 * @param array $body Request body for runReport.
	 * @return array|WP_Error Decoded JSON.
	 */
	public static function run_report( array $body ) {
		$property = MLD_Settings::get_ga4_property_id();
		if ( '' === $property ) {
			return new WP_Error( 'mld_not_configured', __( 'GA4 property ID is not set.', 'maximum-local-dashboard' ) );
		}

		$token = MLD_Google_Auth::get_access_token( array( self::SCOPE ) );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = sprintf(
			'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport',
			rawurlencode( $property )
		);

		$cache_key = 'mld_ga4_' . md5( $url . wp_json_encode( $body ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'GA4 request failed.', 'maximum-local-dashboard' );
			return new WP_Error( 'mld_ga4_error', $msg, array( 'status' => $code ) );
		}

		set_transient( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
		return $data;
	}

	/**
	 * Overview metrics for a date range, optionally filtered by campaign.
	 *
	 * @param string $start    YYYY-MM-DD.
	 * @param string $end      YYYY-MM-DD.
	 * @param string $campaign Optional utm_campaign filter.
	 * @return array|WP_Error
	 */
	public static function overview( $start, $end, $campaign = '' ) {
		$body = array(
			'dateRanges' => array(
				array(
					'startDate' => $start,
					'endDate'   => $end,
				),
				array(
					'startDate' => self::previous_start( $start, $end ),
					'endDate'   => self::previous_end( $start ),
				),
			),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'totalUsers' ),
				array( 'name' => 'conversions' ),
				array( 'name' => 'eventCount' ),
			),
		);

		$filter = self::campaign_filter( $campaign );
		if ( $filter ) {
			$body['dimensionFilter'] = $filter;
		}

		return self::run_report( $body );
	}

	/**
	 * Campaigns breakdown.
	 *
	 * @param string $start YYYY-MM-DD.
	 * @param string $end   YYYY-MM-DD.
	 * @return array|WP_Error
	 */
	public static function campaigns( $start, $end ) {
		$body = array(
			'dateRanges' => array(
				array(
					'startDate' => $start,
					'endDate'   => $end,
				),
			),
			'dimensions' => array(
				array( 'name' => 'sessionCampaignName' ),
				array( 'name' => 'sessionSource' ),
				array( 'name' => 'sessionMedium' ),
			),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'totalUsers' ),
				array( 'name' => 'conversions' ),
				array( 'name' => 'bounceRate' ),
			),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => 'sessions' ),
					'desc'   => true,
				),
			),
			'limit'      => 100,
		);

		return self::run_report( $body );
	}

	/**
	 * Conversion events by page and campaign.
	 *
	 * @param string $start    YYYY-MM-DD.
	 * @param string $end      YYYY-MM-DD.
	 * @param string $campaign Optional filter.
	 * @return array|WP_Error
	 */
	public static function conversions( $start, $end, $campaign = '' ) {
		$body = array(
			'dateRanges'      => array(
				array(
					'startDate' => $start,
					'endDate'   => $end,
				),
			),
			'dimensions'      => array(
				array( 'name' => 'eventName' ),
				array( 'name' => 'pagePath' ),
				array( 'name' => 'sessionCampaignName' ),
			),
			'metrics'         => array(
				array( 'name' => 'eventCount' ),
			),
			'dimensionFilter' => array(
				'filter' => array(
					'fieldName'    => 'eventName',
					'inListFilter' => array(
						'values' => array(
							'phone_click',
							'email_click',
							'outbound_click',
							'file_download',
							'cta_click',
							'form_submit',
							'generate_lead',
						),
					),
				),
			),
			'orderBys'        => array(
				array(
					'metric' => array( 'metricName' => 'eventCount' ),
					'desc'   => true,
				),
			),
			'limit'           => 250,
		);

		$filter = self::campaign_filter( $campaign );
		if ( $filter ) {
			$body['dimensionFilter'] = array(
				'andGroup' => array(
					'expressions' => array(
						$body['dimensionFilter'],
						$filter,
					),
				),
			);
		}

		return self::run_report( $body );
	}

	/**
	 * Traffic sources / devices / top pages.
	 *
	 * @param string $start    YYYY-MM-DD.
	 * @param string $end      YYYY-MM-DD.
	 * @param string $campaign Optional filter.
	 * @return array|WP_Error Associative with sources, devices, pages.
	 */
	public static function traffic( $start, $end, $campaign = '' ) {
		$base_filter = self::campaign_filter( $campaign );

		$sources_body = array(
			'dateRanges' => array(
				array(
					'startDate' => $start,
					'endDate'   => $end,
				),
			),
			'dimensions' => array(
				array( 'name' => 'sessionSource' ),
				array( 'name' => 'sessionMedium' ),
			),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'totalUsers' ),
				array( 'name' => 'conversions' ),
			),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => 'sessions' ),
					'desc'   => true,
				),
			),
			'limit'      => 50,
		);
		if ( $base_filter ) {
			$sources_body['dimensionFilter'] = $base_filter;
		}

		$devices_body = array(
			'dateRanges' => array(
				array(
					'startDate' => $start,
					'endDate'   => $end,
				),
			),
			'dimensions' => array( array( 'name' => 'deviceCategory' ) ),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'totalUsers' ),
			),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => 'sessions' ),
					'desc'   => true,
				),
			),
		);
		if ( $base_filter ) {
			$devices_body['dimensionFilter'] = $base_filter;
		}

		$pages_body = array(
			'dateRanges' => array(
				array(
					'startDate' => $start,
					'endDate'   => $end,
				),
			),
			'dimensions' => array( array( 'name' => 'pagePath' ) ),
			'metrics'    => array(
				array( 'name' => 'sessions' ),
				array( 'name' => 'screenPageViews' ),
				array( 'name' => 'conversions' ),
			),
			'orderBys'   => array(
				array(
					'metric' => array( 'metricName' => 'sessions' ),
					'desc'   => true,
				),
			),
			'limit'      => 50,
		);
		if ( $base_filter ) {
			$pages_body['dimensionFilter'] = $base_filter;
		}

		$sources = self::run_report( $sources_body );
		if ( is_wp_error( $sources ) ) {
			return $sources;
		}
		$devices = self::run_report( $devices_body );
		if ( is_wp_error( $devices ) ) {
			return $devices;
		}
		$pages = self::run_report( $pages_body );
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}

		return array(
			'sources' => $sources,
			'devices' => $devices,
			'pages'   => $pages,
		);
	}

	/**
	 * Build a campaign name dimension filter, or null.
	 *
	 * @param string $campaign Campaign name.
	 * @return array|null
	 */
	private static function campaign_filter( $campaign ) {
		$campaign = trim( (string) $campaign );
		if ( '' === $campaign || 'all' === strtolower( $campaign ) ) {
			return null;
		}
		return array(
			'filter' => array(
				'fieldName'    => 'sessionCampaignName',
				'stringFilter' => array(
					'matchType' => 'EXACT',
					'value'     => $campaign,
				),
			),
		);
	}

	/**
	 * Previous period start (same length, immediately before $start).
	 *
	 * @param string $start Start date.
	 * @param string $end   End date.
	 * @return string
	 */
	private static function previous_start( $start, $end ) {
		$days = ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS;
		return gmdate( 'Y-m-d', strtotime( $start ) - ( ( $days + 1 ) * DAY_IN_SECONDS ) );
	}

	/**
	 * Day before $start.
	 *
	 * @param string $start Start date.
	 * @return string
	 */
	private static function previous_end( $start ) {
		return gmdate( 'Y-m-d', strtotime( $start ) - DAY_IN_SECONDS );
	}
}
