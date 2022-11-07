<?php

/*
Plugin Name: Api To Post
Plugin URI: https://www.kleeq.co.uk
Description: A Plugin for gathering API data and importing to a custom post type.
Version: 1.0
Author: Ashley James
Author URI: https://www.kleeq.co.uk
License: GPL2
*/

//This Requires ACF to Work Correctly

// Ajax Calls
//admin-ajax.php?action=get_yachts_from_api
//admin-ajax.php?action=delete_all_imported_posts

//Register the Post Type
add_action('init', 'register_yacht_cpt');
function register_yacht_cpt() {
    register_post_type( 'yacht', [
        'label' => 'Yachts',
        'public' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'capability_type' => 'post'
    ]);
}

//Set Up the WP Cron to Run The Function Once a Week
// if ( ! wp_next_scheduled( 'update_yacht_list')) {
//     wp_schedule_event( time(), 'weekly', 'get_yachts_from_api' );
// }

//Ajax Function Calls

// Logged OUT User Ajax to crawl API and get Yachts
// add_action( 'wp_ajax_nopriv_get_yachts_from_api', 'get_yachts_from_api');

// Logged in User Ajax to crawl API and get Yachts
add_action( 'wp_ajax_get_yachts_from_api', 'get_yachts_from_api');

// Logged OUT User Ajax to delete all yacht posts
// add_action( 'wp_ajax_nopriv_delete_all_imported_posts', 'delete_all_imported_posts');

// Logged in User Ajax to delete all yacht posts
add_action( 'wp_ajax_delete_all_imported_posts', 'delete_all_imported_posts');

//The Actual Function To Get the Data And Pagination
function get_yachts_from_api() {
    
    $current_page = ( ! empty($_POST['current_page']) ) ? $_POST['current_page'] : 1;
    $yachts = [];

    $results = wp_remote_retrieve_body(
        wp_remote_get( 'https://api-gateway.boats.com/api-yachtworld/search?apikey=827d6e453fef4ce390afe017b5a7db4b&uom=m&locale=en_GB&currency=GBP&multiFacetedMakeModel=[%22sunseeker%22]&page=' . $current_page . '&variant=1&sort=recommended-desc&isMultiSearch=true&fields=mappedURL,tags,date.created,date.indexed,make,model,condition,boat.boatType,boat.class,year,boat.specifications.dimensions.lengths.nominal,price.type.amount,price.discount,owner.logos,owner.id,owner.name,media.0,id,aliases.imt,aliases.yachtworld,mediaCount,status,isCurrentModel,isOemModel,location.city,location.normalizedCity,location.countrySubDivisionCode,location.isoSubDivisionCode,location.countryCode,location.postalCode,boat.attributes,boat.cpybLogo,salesRep.certs')
    );

    $results = json_decode($results);

    if( empty($results) ) {
        return false;
    }

    $yachts[] = $results;

    foreach($yachts as $yacht) {
        foreach($yacht->search->records as $yacht_record) {

            $yacht_slug = sanitize_title( $yacht_record->make . "-" . $yacht_record->model . "-" . $yacht_record->id . "-" . $current_page);

            $existing_yacht = get_page_by_path( $yacht_slug, 'OBJECT', 'yacht' );

            if($existing_yacht === null) {

                $inserted_yacht = wp_insert_post( [
                    'post_name' => $yacht_slug,
                    'post_title' => $yacht_slug,
                    'post_type' => 'yacht',
                    'post_status' => 'publish'
                ]);

                if( is_wp_error($inserted_yacht) ) {
                    continue;
                }

                $fillable = [
                    'field_63666e6f6d5e5' => 'make',
                    'field_63666e746d5e6' => 'model',
                    'field_63666e796d5e7' => 'condition',
                    'field_63666e7e6d5e8' => 'year',
                ];

                foreach($fillable as $key => $name) {
                    update_field($key, $yacht_record->$name, $inserted_yacht);
                }

                // Created Date
                update_field('field_63668de716ba5', $yacht_record->date->created, $inserted_yacht);

                // Indexed Date
                update_field('field_6367c14695b64', $yacht_record->date->indexed, $inserted_yacht);               

                // Boat Type
                update_field('field_63669d580fea6', $yacht_record->boat->boatType, $inserted_yacht);

                // Class
                update_field('field_63669d8767f62', $yacht_record->boat->class, $inserted_yacht);

                // Length
                update_field('field_63669de660ce3', $yacht_record->boat->specifications->dimensions->lengths->nominal->ft, $inserted_yacht);

                // Price
                if(isset($yacht_record->price)){
                    foreach($yacht_record->price->type as $currency) {
                        if(!empty($currency->GBP)){
                            $gbp = $currency->GBP;
                            $price = true;
                            break;
                        }
                    }
                    if($price === true){
                        update_field('field_63669e6a11e1b', $gbp, $inserted_yacht);
                    } 
                }
                
                
                // Main Image
                foreach($yacht_record->media as $media) {
                    if(!empty($media->url)) {
                        $main_image_url = $media->url;
                        break;
                    }
                }
                if(!empty($media->url)) {
                    $image_id = upload_image_get_attachment_id('https://images.yachtworld.com/resize/'.$main_image_url , $inserted_yacht);
                    update_field('field_6366a090637be', $image_id , $inserted_yacht);
                }

            } else {

                $existing_yacht_id = $existing_yacht->ID;
                $existing_yacht_timestamp = get_field('indexed', $existing_yacht_id);

                if($yacht_record->date->indexed >= $existing_yacht_timestamp) {
                    
                    $fillable = [
                        'field_63666e6f6d5e5' => 'make',
                        'field_63666e746d5e6' => 'model',
                        'field_63666e796d5e7' => 'condition',
                        'field_63666e7e6d5e8' => 'year',
                    ];
    
                    foreach($fillable as $key => $name) {
                        update_field($key, $yacht_record->$name, $inserted_yacht);
                    }
    
                    // Created Date
                    update_field('field_63668de716ba5', $yacht_record->date->created, $inserted_yacht);

                    // Indexed Date
                    update_field('field_6367c14695b64', $yacht_record->date->indexed, $inserted_yacht);  
    
                    // Boat Type
                    update_field('field_63669d580fea6', $yacht_record->boat->boatType, $inserted_yacht);
    
                    // Class
                    update_field('field_63669d8767f62', $yacht_record->boat->class, $inserted_yacht);
    
                    // Length
                    update_field('field_63669de660ce3', $yacht_record->boat->specifications->dimensions->lengths->nominal->ft, $inserted_yacht);
    
                    // Price
                    if(isset($yacht_record->price)){
                        foreach($yacht_record->price->type as $currency) {
                            if(!empty($currency->GBP)){
                                $gbp = $currency->GBP;
                                $price = true;
                                break;
                            }
                        }
                        if($price === true){
                                update_field('field_63669e6a11e1b', $gbp, $inserted_yacht);
                        } 
                    }  
                    
                    // Main Image
                    foreach($yacht_record->media as $media) {
                        if(!empty($media->url)) {
                            $main_image_url = $media->url;
                            break;
                        }
                    }
                    if(!empty($media->url)) {
                        $image_id = upload_image_get_attachment_id('https://images.yachtworld.com/resize/'.$main_image_url , $inserted_yacht);
                        update_field('field_6366a090637be', $image_id , $inserted_yacht);
                    }
                   
                }
            }
        }
    }

    $current_page = $current_page + 1;
    wp_remote_post( admin_url('admin-ajax.php?action=get_yachts_from_api'), [
        'blocking' => false,
        'sslverify' => false,
        'body' => [
            'current_page' => $current_page
        ] 
    ]);

    wp_die();

}

function upload_image_get_attachment_id($file, $post_id) {

    require_once(ABSPATH . 'wp-admin' . '/includes/image.php');
    require_once(ABSPATH . 'wp-admin' . '/includes/file.php');
    require_once(ABSPATH . 'wp-admin' . '/includes/media.php');

    // upload image to server
    media_sideload_image($file, $post_id);

    // get the newly uploaded image
    $attachments = get_posts( array(
        'post_type' => 'attachment',
        'number_posts' => 1,
        'post_status' => null,
        'post_parent' => $post_id,
        'orderby' => 'post_date',
        'order' => 'DESC',) 
    );

    // returns the id of the image
    return $attachments[0]->ID;
}

// Save Demo Field Groups
function my_plugin_update_field_group($group) {
    // list of field groups that should be saved to my-plugin/acf-json
    $groups = array('group_5d921d2978e9e');
  
    if (in_array($group['key'], $groups)) {
      add_filter('acf/settings/save_json', function() {
        return dirname(__FILE__) . '/acf-json';
      });
    }
}
add_action('acf/update_field_group', 'my_plugin_update_field_group', 1, 1);
  
// Load - includes the /acf-json folder in this plugin to the places to look for ACF Local JSON files
add_filter('acf/settings/load_json', function($paths) {
$paths[] = dirname(__FILE__) . '/acf-json';
return $paths;
});

//Ajax Function to Delete All Imported Posts
function delete_all_imported_posts() {
    $allposts= get_posts( array('post_type'=>'yacht','numberposts'=>-1) );
    foreach ($allposts as $eachpost) {
        wp_delete_post( $eachpost->ID, true );
    }
    wp_die();
}
//Deletes Media Attached to Post
function delete_all_attached_media( $post_id ) {
  if( get_post_type($post_id) == "yacht" ) {
    $attachments = get_attached_media( '', $post_id );
    foreach ($attachments as $attachment) {
      wp_delete_attachment( $attachment->ID, 'true' );
    }
  }
}
add_action( 'before_delete_post', 'delete_all_attached_media' );



// Plugin Settings Page
function api_to_post_top_lvl_menu(){
 
	add_menu_page(
		'API to Post Settings', // page <title>Title</title>
		'API to Post', // link text
		'manage_options', // user capabilities
		'api_to_post', // page slug
		'api_to_post_page_callback', // this function prints the page content
		'dashicons-archive', // icon (from Dashicons for example)
		4 // menu position
	);
}
add_action( 'admin_menu', 'api_to_post_top_lvl_menu' );
 

function api_to_post_page_callback(){
	?>
		<div class="wrap">
			<h1><?php echo get_admin_page_title() ?></h1>
            <?php echo '<a href="'. admin_url( 'admin-ajax.php?action=get_yachts_from_api', 'https' ) .'" class="button-primary">Import Posts</a>'; ?>
            <?php echo '<a href="'. admin_url( 'admin-ajax.php?action=delete_all_imported_posts', 'https' ) .'" class="button-primary">Delete Posts</a>'; ?>
		</div>
	<?php
}

