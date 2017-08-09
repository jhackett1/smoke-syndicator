<?php
/*
Plugin Name: Smoke Syndicator
Plugin URI: http://joshuahackett.com
Description: Allows editors to control whether they want content to appear in the app, website, or both. Modifies the Wordpress API in a fit state for the app to consume. Disable this and the app will break.
Version: 1.0.0
Author: Joshua Hackett
Author URI: http://joshuahackett.com
*/

// Register a meta box to contain fields for adding custom post meta
add_action( 'add_meta_boxes', 'smoke_syndication_box_setup' );
function smoke_syndication_box_setup(){
	add_meta_box( 'smoke_syndication', 'Smoke Syndicator', 'smoke_syndication_content', 'post', 'side', 'high');
}
// Callback function to fill the meta box with form input content, passing in the post object
function smoke_syndication_content( $post ){
	// Fetch all post meta data and save as an array var
	$values = get_post_custom( $post->ID );
	// Save current values of particular meta keys as variables for display
	$syndication = isset( $values['smoke_syndication'] ) ? esc_attr( $values['smoke_syndication'][0] ) : "";
	//What a nonce
	wp_nonce_field( 'smoke_post_options_nonce', 'meta_box_nonce' );
	// Display input fields, using variables above to show current values
    ?>
		<p>
      <label for="syndication">Where should this content be visible?</label><br/>
      <select name="syndication" id="syndication">
          <option value="0" <?php selected( $syndication, '0' ); ?>>App and website</option>
			    <option value="1" <?php selected( $syndication, '1' ); ?>>App only</option>
			    <option value="2" <?php selected( $syndication, '2' ); ?>>Website only</option>
      </select>
		</p>
    <?php
}
// Having registered the meta box and filled it with content, now we save the form inputs to the post meta table
add_action( 'save_post', 'smoke_syndication_save' );
// A function to save form inputs to the database
function smoke_syndication_save( $post_id ){
	// If this is an autosave, do nothing
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	// Verify the nonce before proceeding
	// if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'my_meta_box_nonce' ) ) return;
	// Check user permissions before proceeding
	if( !current_user_can( 'edit_post' ) ) return;
  $allowed = array(
      'a' => array( // on allow a tags
          'href' => array() // and those anchors can only have href attribute
      )
  );
	// Save syndication field
	if( isset( $_POST['syndication'] ) )
      update_post_meta( $post_id, 'smoke_syndication', esc_attr( $_POST['syndication'] ) );
}


// Add extra fields to the REST API response
function smoke_rest_prepare_post( $data, $post, $request ) {
	$_data = $data->data;
	// Add images to the API response for the app to consume
	$thumbnail = get_the_post_thumbnail_url($post->ID, 'medium');
	$_data['featured_image_thumbnail_url'] = $thumbnail;
	$full = get_the_post_thumbnail_url($post->ID, 'large');
	$_data['featured_image_large_url'] = $full;
	// Add categories to API response for app to consume
	$cats = get_the_category($post->ID);
	$_data['category_names'] = $cats;
	// Add the syndication field to the API response, for the app to consume
	// 0 = both
	// 1 = app only
	// 2 = website only
	if ( isset(get_post_custom( $post->ID )['smoke_syndication']) ) {
		// If the field exists, return the value as a string
		$_data['syndication'] = 	get_post_custom( $post->ID )['smoke_syndication'][0];
	} else {
		// If the value is not set, return 0, assume both publishing platforms
		$_data['syndication'] = 	"0";
	}
	// Pass out the data
	$data->data = $_data;
	return $data;
}
add_filter( 'rest_prepare_post', 'smoke_rest_prepare_post', 10, 3 );


// Hide posts in the website which have the 'app only' value of 1...
function featured_posts( $query ) {
		// Only affect home page and category pages. Will NOT affect searches, admin backend or direct permalinks to article
    if ($query->is_home() || $query->is_category()) {
			 $meta_query = $query->get('meta_query');
				 $meta_query[] = array(
						// Show posts that have a 0 or a 2 set
	         'relation' => 'OR',
	             array( // new and edited posts
	                 'key' => 'smoke_syndication',
	                 'compare' => '!=',
	                 'value' => '1'
	             ),
							//  And show all posts that don't have the value set (eg. old ones)
	             array(
	                 'key' => 'smoke_syndication',
	                 'value' => '1',
	                 'compare' => 'NOT EXISTS'
	             )
	         );
			// Add this back into the query
			 $query->set('meta_query',$meta_query);
    }
	}
add_action( 'pre_get_posts', 'featured_posts' );

// ... and hide web-only posts from the API
function remove_web_only ( $args) {
    $args['meta_query'] = array(
			 // Show posts that have a 0 or a 2 set
			'relation' => 'OR',
					array( // new and edited posts
							'key' => 'smoke_syndication',
							'compare' => '!=',
							'value' => '2'
					),
				 //  And show all posts that don't have the value set (eg. old ones)
					array(
							'key' => 'smoke_syndication',
							'value' => '1',
							'compare' => 'NOT EXISTS'
					)
			);
    return $args;
}
add_filter( 'rest_post_query', 'remove_web_only' );







// Finally, let's improve usability by adding an extra column to the posts admin panel
function syndication_columns_head($defaults) {
    $defaults['syndication'] = 'Syndication';
    return $defaults;
}
add_filter('manage_post_posts_columns', 'syndication_columns_head');

// Populate it with content
function syndication_columns_content($column_name, $post_ID) {
    if ($column_name == 'syndication') {
			// Check whether the meta is set
			if( isset(get_post_custom( $post_ID )['smoke_syndication']) ){
				$syndication = get_post_custom( $post_ID )['smoke_syndication'][0];
			} else {
				$syndication = false;
			}
			// Display the correct value
      if ($syndication == 0 ) {
        echo 'App & web';
      } else if ($syndication == 1) {
				echo 'App';
			} else if ($syndication == 2) {
				echo 'Web';
			} else if ($syndication == false) {
				echo 'App & web';
			}
    }
}
add_action('manage_post_posts_custom_column', 'syndication_columns_content', 10, 2);
