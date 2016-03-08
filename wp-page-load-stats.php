<?php
/*
Plugin Name: WP Page Load Stats
Plugin URI: https://github.com/mikejolley/WP-Page-Load-Stats
Description: Display memory, page load time, average load time and query count in the footer. Requires PHP 5.2.0+
Version: 1.0.2
Requires at least: 3.0
Tested up to: 4.4
Author: Mike Jolley
Author URI: http://mikejolley.com
Text Domain: wp-page-load-stats
Domain Path: /languages/
*/

/**
 * WP_Page_Load_Stats Class
 */
class WP_Page_Load_Stats {

	/**
	 * Stores the name of the option where averages get saved.
	 * @var string
	 */
	private $average_option;

	/**
	 * Gets things started
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_head', array( $this, 'wp_head' ) );
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
		add_action( 'admin_head', array( $this, 'wp_head' ) );
		add_action( 'admin_footer', array( $this, 'wp_footer' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * init function.
	 */
	public function init() {
		$this->average_option = is_admin() ? 'wp_pls_admin_load_times' : 'wp_pls_load_times';

		load_plugin_textdomain( 'wp-page-load-stats', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		if ( isset( $_GET['reset_wp_pls_stats'] ) && $_GET['reset_wp_pls_stats'] == 1 ) {
			delete_option( $this->average_option );
			wp_safe_redirect( wp_get_referer() );
			exit;
		}
	}

	/**
	 * wp_head function.
	 */
	public function wp_head() {
	echo "<script>window.addEventListener('load', function(){
								setTimeout(function() {
							  var timing = window.performance.timing;
							  var userTime = (timing.loadEventEnd - timing.navigationStart) / 1000;
							  var pageTime = (timing.loadEventEnd - timing.responseEnd) / 1000;
							  var connection = (timing.connectEnd - timing.connectStart) / 1000;
							  var requestTime = (timing.responseEnd - timing.requestStart) / 1000;
							  var fetchTime = (timing.responseEnd - timing.fetchStart) / 1000;
							  var perf = document.getElementById('wp-pls-stats');

							  perf.innerHTML += `<span class='wp-pls-value' title='Total perceived load time for the user'>Total load: \${userTime}s | </span>
																	 <span class='wp-pls-value' title='Time to request'>Request: \${requestTime}s | </span>
																	 <span class='wp-pls-value' title='Time for client to load this page after response from server'>Page load: \${pageTime}s | </span>
																	 <span class='wp-pls-value' title='How long it took for server to fetch data'>Network: \${fetchTime}s | </span>
																	 <span class='wp-pls-value' title='How long it took for connection to server to be created'>Connection: \${connection}s </span>`;
							}, 0);
							}, false);
				</script>";
	}

	/**
	 * wp_footer function.
	 */
	public function wp_footer() {
		$this->display();
		//wp_enqueue_script( 'wp_pls-client' );
	}

	/**
	 * enqueue function.
	 */
	public function enqueue() {
        wp_enqueue_style( 'wp_pls-style', plugins_url('style.css', __FILE__) );
        //wp_register_script( 'wp_pls-client', plugins_url('/js/clientside_stats.js', __FILE__),  null, false, true );
	}

		/**
	 * admin enqueue function.
	 */
	public function admin_enqueue() {
        wp_enqueue_style( 'wp_pls-style-admin', plugins_url('admin-style.css', __FILE__) );
	}

	/**
	 * display function.
	 */
	public function display() {
		// Get values we're displaying
		$timer_stop 		= timer_stop(0);
		$query_count 		= get_num_queries();
		$memory_usage 		= round( size_format( memory_get_usage() ), 2 );
		$memory_peak_usage 	= round( size_format( memory_get_peak_usage() ), 2 );
		$memory_limit 		= round( size_format( $this->let_to_num( WP_MEMORY_LIMIT ) ), 2 );
		$load_times			= array_filter( (array) get_option( $this->average_option, array() ) );
		$load_times[]		= $timer_stop;
		$memory_percentile = round( ( $memory_usage / $memory_limit ), 2 ) * 100;

		// Update load times
		update_option( $this->average_option, $load_times );

		// Get average
		if ( sizeof( $load_times ) > 0 ) {
			$average_load_time = round( array_sum( $load_times ) / sizeof( $load_times ), 4 );
		}

		// Display the info for admins only (users with manage_options)
			  ?><div id="wp-pls-container">
			  <p id="wp-pls-stats">
				<?php if ( current_user_can( 'manage_options' ) ) { ?>
					<span class="wp-pls-value"><?php printf( __( '%s queries in %ss | ', 'wp-page-load-stats' ), $query_count, $timer_stop ); ?></span>
					<span class="wp-pls-value"><?php printf( __( 'Average load: %ss (%s runs) | ', 'wp-page-load-stats' ), $average_load_time, sizeof( $load_times ) ); ?></span>
					<span class="wp-pls-value"><?php printf( __( '%s/%s MB (%s) memory used | ', 'wp-page-load-stats' ), $memory_usage, $memory_limit, $memory_percentile . '%' ); ?></span>
					<span class="wp-pls-value"><?php printf( __( 'Peak memory usage %s MB | ', 'wp-page-load-stats' ), $memory_peak_usage ); ?></span>
					<br />
				<?php } ?>
				</p>
			</div>
		<?php

		//collecting data temporarily in log file

		$rando = rand(0, 1000);
		if ($rando % 5 == 0 AND $rando % 7 == 0) { //Only sample 14% of requests
			$load_size = sizeof($load_times);
			$logdata = "$timer_stop,$query_count,$average_load_time,$load_size,$memory_usage,$memory_limit,$memory_percentile,$memory_peak_usage" . PHP_EOL;
			$log_file = WP_CONTENT_DIR . '/load_stats.log';
			file_put_contents($log_file, $logdata, FILE_APPEND);
		}
	}

	/**
	 * let_to_num function.
	 *
	 * This function transforms the php.ini notation for numbers (like '2M') to an integer
	 *
	 * @param $size
	 * @return int
	 */
	public function let_to_num( $size ) {
	    $l 		 = substr( $size, -1 );
	    $ret 	 = substr( $size, 0, -1 );
	    switch( strtoupper( $l ) ) {
		    case 'P':
		        $ret *= 1024;
		    case 'T':
		        $ret *= 1024;
		    case 'G':
		        $ret *= 1024;
		    case 'M':
		        $ret *= 1024;
		    case 'K':
		        $ret *= 1024;
	    }
	    return $ret;
	}
}

$WP_Page_Load_Stats = new WP_Page_Load_Stats();
