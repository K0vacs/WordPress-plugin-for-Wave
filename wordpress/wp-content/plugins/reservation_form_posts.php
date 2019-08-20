<?php
/**
* Plugin Name: 4K Media
* Description: Reservation Form Posts
* Version: 0.1
* Author: Ferenc Kovacs
* License: GPL2
*/

// Variables in all CAPS are defined in wp-config.php
// ** Email details for WP Mail ** //
// define( 'SMTP_HOST', 'mail.yourdomain.com' );  // A2 Hosting server name. For example, "a2ss10.a2hosting.com"
// define( 'SMTP_AUTH', true );
// define( 'SMTP_PORT', '465' );
// define( 'SMTP_SECURE', 'ssl' );
// define( 'SMTP_USERNAME', 'youremail@.co.za' );  // Username for SMTP authentication
// define( 'SMTP_PASSWORD', 'yourpassword' );          // Password for SMTP authentication
// define( 'SMTP_FROM',     'fromaddress@yourdomain.com' );  // SMTP From address
// define( 'SMTP_FROMNAME', 'From Name' );

// Token for Wave Account
// define( 'BEARER_TOKEN', 'longstringandintcode' );

// Config settings for PHP Mailer.
add_action( 'phpmailer_init', 'send_smtp_email' );
function send_smtp_email( $phpmailer ) {
    include '../../wp-load.php';
    
    $phpmailer->isSMTP();
    $phpmailer->Host       = SMTP_HOST;
    $phpmailer->SMTPAuth   = SMTP_AUTH;
    $phpmailer->Port       = SMTP_PORT;
    $phpmailer->SMTPSecure = SMTP_SECURE;
    $phpmailer->Username   = SMTP_USERNAME;
    $phpmailer->Password   = SMTP_PASSWORD;
    $phpmailer->From       = SMTP_FROM;
    $phpmailer->FromName   = SMTP_FROMNAME;
}

// Send email and save to Wave when the reservations post is saved.
add_action('acf/save_post', 'post_published_notification', 15);
function post_published_notification( $post_id ) {
  
  if( get_post_type( $post_id ) == 'reservations') {
    
    $fields     = get_fields( $post_id );
    $subject    = get_field( "mothers_name", $post_id );
        
    foreach( $fields as $name => $value ) {
        $name =   ucwords(str_replace( "_", " " ,$name ));
        $rows .=  
          "<tr>
            <th style='text-align: left;'>" 
              . $name . 
            "</th>
            <td>"
              . $value . 
            "</td>
          </tr>";
    }
    
    $message = "
      <table>
        <tbody>
          {$rows}
        </tbody>
      </table>
    ";
    
    add_filter('wp_mail_content_type', function( $content_type ) {
            return 'text/html';
    });
    
    wp_mail( 'mr.f.kovacs@gmail.com', $subject, $message );
  	
    $customerVariables = wp_json_encode([
      'input' => array(
        'businessId'  => 'QnVzaW5lc3M6NjNiOTVkZGItNWRkOS00MzI0LWEzNGYtMDkxOTJmNjNjNDc0', 
        'name'        => get_field( "mothers_name", $post_id ), 
        'firstName'   => get_field( "mothers_name", $post_id ), 
        'email'       => get_field( "email_address", $post_id ),
        'phone'       => get_field( "mothers_cell", $post_id ),
      )
    ]);
    
    $request = wp_remote_post( 'https://gql.waveapps.com/graphql/public', [
        'headers' => [
          'Content-Type' => 'application/json',
    	    'Accept' => 'application/json',
          'Authorization' => 'Bearer ' . BEARER_TOKEN,
        ],
        'body' => wp_json_encode([
          'query' => 'mutation($input: CustomerCreateInput!) { 
            customerCreate(input: $input) {
              didSucceed
              inputErrors {
                code
                message
                path
              }
              customer {
                id
                name
                firstName
                lastName
                email
              }
            }
          }',
          'variables' => $customerVariables
        ])
    ]);
      
    $decoded_response = json_decode( $request['body'], true );    
    
  	$myfile = fopen("../011.txt", "w") or die("Unable to open file!");
  	$txt = "John Doe\n" . $ID . "\n";
  	fwrite($myfile, $txt);
  	$txt = "Mothers Name: " . $request['body'];
  	fwrite($myfile, $txt);
  	fclose($myfile);
  
  }
}

// Create a mail log text file when there is an email error.
add_action('wp_mail_failed', 'log_mailer_errors', 10, 1);
function log_mailer_errors( $wp_error ){
  $fn = ABSPATH . '/mail.log'; // say you've got a mail.log file in your server root
  $fp = fopen($fn, 'a');
  fputs($fp, "Mailer Error: " . $wp_error->get_error_message() ."\n");
  fclose($fp);
}

// Creates the reservations CPT.
add_action('init', 'reservations');
function reservations() {

 	$labels = array(
        'name'               => 'Reservations',
        'singular_name'      => 'Reservation',
        'menu_name'          => 'Reservations',
        'name_admin_bar'     => 'Reservations',
        'add_new'            => 'Add New Reservation',
        'add_new_item'       => 'Add New Reservation',
        'new_item'           => 'New Reservation',
        'edit_item'          => 'Edit Reservation',
        'view_item'          => 'View Reservation',
        'all_items'          => 'All Reservations',
        'search_items'       => 'Search Reservations',
        'parent_item_colon'  => 'Parent Reservations:',
        'not_found'          => 'No Reservations found.',
        'not_found_in_trash' => 'No Reservations found in Trash.',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-id',
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'reservations' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'supports'           => array( 'title', 'custom-fields')
    );
    register_post_type( 'reservations', $args );
}

// Rewrite flush for CPT permalinks to remain accurate.
function my_rewrite_flush() {
    reservation_post_type();
    flush_rewrite_rules();
}

// Registration of the rewrite flush.
register_activation_hook( __FILE__, 'my_rewrite_flush' );