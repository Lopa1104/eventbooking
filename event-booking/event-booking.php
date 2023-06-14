<?php
/**
 * Event Booking
 *
 * @package       EVENTBOOK
 * @author        Lopamudra Mohanty
 * @license       gplv2
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   Event Booking
 * Plugin URI:    http://testeventbooking.com
 * Description:   Built a very simple Create / Read / Update / Delete (CRUD) API that will let you manage / retrieve the list of events.
 * Version:       1.0.0
 * Author:        Lopamudra Mohanty
 * Author URI:    http://testeventbooking.com
 * Text Domain:   event-booking
 * Domain Path:   /languages
 * License:       GPLv2
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with Event Booking. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize
 *
 * Register an Event post type.
 */
function create_posttype() {

	register_post_type(
		'events',
		// CPT Options.
		array(
			'labels' => array(
				'name'          => __( 'Events' ),
				'singular_name' => __( 'Event' ),
			),
			'public' => true,
		)
	);
}
// Hooking up our function to theme setup.
add_action( 'init', 'create_posttype' );
/**
 * Initialize
 *
 * Register for Create / Read / Update / Delete (CRUD) API.
 */
function register_custom_api_endpoint() {
	register_rest_route(
		'my-api/v1',
		'/events/show/',
		array(
			'methods'  => 'GET',
			'callback' => 'read_event',
		)
	);

	register_rest_route(
		'my-api/v1',
		'/events/show/(?P<date>[\w-]+)',
		array(
			'methods'  => 'GET',
			'callback' => 'read_event',
		)
	);

	register_rest_route(
		'my-api/v1',
		'/events/create/',
		array(
			'methods'  => 'POST',
			'callback' => 'create_event',
		)
	);

	register_rest_route(
		'my-api/v1',
		'/events/show/(?P<id>\d+)',
		array(
			'methods'  => 'GET',
			'callback' => 'read_event',
		)
	);

	register_rest_route(
		'my-api/v1',
		'/events/update/(?P<id>\d+)',
		array(
			'methods'  => 'PUT',
			'callback' => 'update_event',
		)
	);

	register_rest_route(
		'my-api/v1',
		'/events/delete/(?P<id>\d+)',
		array(
			'methods'  => 'DELETE',
			'callback' => 'delete_event',
		)
	);
}
add_action( 'rest_api_init', 'register_custom_api_endpoint' );

/**
 * Code to Create an Event.
 *
 * @param array $request Send request to API.
 *
 * @return int event_id.
 */
function create_event( WP_REST_Request $request ) {
	$event_data           = $request->get_params(); // Retrieve submitted data.
	$post                 = array();
	$post['post_title']   = sanitize_text_field( $request->get_param( 'title' ) );
	$post['post_content'] = sanitize_text_field( $request->get_param( 'content' ) );
	$post['meta_input']   = array(
		'event_date' => gmdate( 'Y-m-d', strtotime( sanitize_text_field( $request->get_param( 'date' ) ) ) ),
	);

	$post['post_status'] = 'publish';
	$post['post_type']   = 'events';
	$new_post_id         = wp_insert_post( $post );

	if ( ! is_wp_error( $new_post_id ) ) {
		$response['status']   = 200;
		$response['success']  = true;
		$response['data']     = get_post( $new_post_id );
		$response['datasend'] = $post;
	} else {
		$response['status']  = 200;
		$response['success'] = false;
		$response['message'] = 'No POST found';
	}

	// Return the response.
	return new WP_REST_Response( $response );
}
/**
 * Code to Display an Event by Date or Id.
 *
 * @param array $request Send request to API.
 *
 * @return int event list.
 */
function read_event( WP_REST_Request $request ) {
	$event_id   = $request->get_param( 'id' ); // Retrieve the event ID.
	$event_date = $request->get_param( 'date' );
	if ( ! empty( $event_date ) ) {
		$event_date = gmdate( 'Y-m-d', strtotime( $event_date ) );
	} else {
		$event_date = '';
	}

	if ( empty( $event_id ) ) {
		$args = array(
			'numberposts' => -1,
			'post_type'   => 'events',
			'post_status' => 'publish',
		);
		if ( ! empty( $event_date ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'event_date',
					'value'   => $event_date,
					'compare' => 'LIKE',
					'type'    => 'DATE',
				),
			);
		}

		$posts = get_posts( $args );
		wp_reset_postdata();

		if ( count( $posts ) > 0 ) {
			$response['status']  = 200;
			$response['success'] = true;
			$response['data']    = $posts;
		} else {
			$response['status']  = 200;
			$response['success'] = false;
			$response['message'] = 'No Event found';
		}
	} else {
		if ( $event_id > 0 ) {
			$post = get_post( $event_id );
			if ( $post ) {
				$response['status']  = 200;
				$response['success'] = true;
				$response['data']    = $post;
			} else {
				$response['status']  = 200;
				$response['success'] = false;
				$response['message'] = 'No Event Found';
			}
		}
	}

	// Return the response.
	return new WP_REST_Response( $response );
}
/**
 * Code to Update Event by Id.
 *
 * @param array $request Send request to API.
 *
 * @return int event_id.
 */
function update_event( WP_REST_Request $request ) {
	$event_id     = $request->get_param( 'id' ); // Retrieve the event ID.
	$updated_data = $request->get_params(); // Retrieve updated data.

	if ( $event_id > 0 ) {
		$new_post_key          = ( get_post_status( $event_id ) ) ? 'ID' : 'import_id';
		$post[ $new_post_key ] = $event_id;
		$post['post_title']    = sanitize_text_field( $request->get_param( 'title' ) );
		$post['post_content']  = sanitize_text_field( $request->get_param( 'content' ) );
		$post['meta_input']    = array(
			'event_date' => gmdate( 'Y-m-d', strtotime( sanitize_text_field( $request->get_param( 'date' ) ) ) ),
		);
		$post['post_type']     = 'events';
		$new_post_id           = wp_update_post( $post, true );

		if ( ! is_wp_error( $new_post_id ) ) {
			$response['status']   = 200;
			$response['success']  = true;
			$response['data']     = get_post( $new_post_id );
			$response['datasend'] = $post;
		} else {
			$response['status']   = 200;
			$response['success']  = false;
			$response['message']  = $new_post_id;
			$response['datasend'] = $post;
		}
	} else {
		$response['status']  = 200;
		$response['success'] = false;
		$response['message'] = 'Event ID not found';
	}

	// Return the response.
	return new WP_REST_Response( $response );
}
/**
 * Code to Delete Event by Id.
 *
 * @param array $request Send request to API.
 *
 * @return int event_id.
 */
function delete_event( WP_REST_Request $request ) {
	$event_id = $request->get_param( 'id' ); // Retrieve the event ID.
	if ( $event_id > 0 ) {
		$delete_post = wp_delete_post( $event_id );

		if ( ! empty( $delete_post ) ) {
			$response['status']  = 200;
			$response['success'] = true;
			$response['data']    = $delete_post;
		} else {
			$response['status']  = 200;
			$response['success'] = false;
			$response['message'] = 'No Event found';
		}
	} else {
		$response['status']  = 200;
		$response['success'] = false;
		$response['message'] = 'EVENT ID not found';
	}

	// Return the response.
	return new WP_REST_Response( $response );
}
/**
 * Remove Search parameters.
 *
 * @param array $query_vars Get parameters.
 *
 * @return unset $query_vars.
 */
function remove_search_parameter( $query_vars ) {
	if ( isset( $query_vars->s ) ) {
		unset( $query_vars->s );
	}

	return $query_vars;
}
add_filter( 'parse_query', 'remove_search_parameter' );
