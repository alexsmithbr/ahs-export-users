<?php
/**
 * Plugin Name: AHS Export Users
 * Plugin URI:  https://developer.wordpress.org/plugins/ahs-export-users
 * Description: Adds a button on Users screen to export all data.
 * Version:     1.0.0
 * Author:      Alexandre Heitor Schmidt
 * Author URI:  https://profiles.wordpress.org/alexsmithbr/
 * Text Domain: ahs
 * Domain Path: /languages
 * License:     GPL2
 *
 * {Plugin Name} is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * 
 * {Plugin Name} is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with {Plugin Name}. If not, see {License URI}.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/*
 * Add a button to the list of users at https://<site>/wp-admin/users.php, with
 * label 'Export table data'. This button, when pressed, will output a csv with all
 * users currently listed in table.
 *
 * @param $which specifies if the code will be added to the top or
 * the bottom of the table. This parameter is not used by this function.
 *
 * @since 2019/12/11
 * @author Alex Smith
 */
function ahs_add_export_button($which) {
    $button_id = 'ahs_export_all_data';
    submit_button( __( 'Export table data' ), '', $button_id, false );
}
add_action('restrict_manage_users', 'ahs_add_export_button', 10, 5);

/*
 * This function makes use of a filter defined at wp-admin/includes/class-wp-users-list-table.php,
 * line 642. The purpose of the filter is to allow changing $args, but here we use it to
 * generate a new query and save the results to a CSV, which is output directly to screen
 * for the user to download.
 *
 * @param $args this parameter is received and returned as is, without any modifications.
 *
 * @see Check ahs_add_export_button() above.
 *
 * @since 2019/12/11
 * @author Alex Smith
 */
function ahs_export_users($args) {
    if ( array_key_exists('export_all_data', $_REQUEST) ) {
        // remove number of records limitations
        $new_args = $args;
        $new_args['number'] = 0;
        $new_args['offset'] = 0;

        // avoid existing filenames overwriting
        $file = '';
        do {
            $file = '/tmp/users_export_' . date('Y-m-d_H-i-s') . '.csv';
        } while ( file_exists($file) );

        // create query
        $wp_user_search = new WP_User_Query( $new_args );
        $items = $wp_user_search->get_results();

        // open csv file
        $fp = fopen($file, 'w');

        $header = array(
            'User ID',
            'HRMIS',
            'E-mail',
            'Name',
            'Roles',
            'Address 1',
            'Address 2',
            'City',
            'Postal code',
            'Country',
        );

        fputcsv($fp, $header);

        foreach ( $items as $userid => $user_object ) {
            $data = array(
                $user_object->ID,
                $user_object->user_login,
                $user_object->user_email,
                $user_object->first_name . ' ' . $user_object->last_name,
                implode(', ', $user_object->roles),
            );

            $entry = GFAPI::get_entry($user_object->get('entry_id')); 
            if ( get_class($entry) == 'WP_Error' ) {
                $data[] = '';
                $data[] = '';
                $data[] = '';
                $data[] = '';
                $data[] = '';
            } else {
                // FIXME: hard-coded entries can stop working when form fields change in GF.
                $data[] = $entry['10.1']; // address 1
                $data[] = $entry['10.3']; // address 2
                $data[] = $entry['10.4']; // city
                $data[] = $entry['10.5']; // postal code
                $data[] = $entry['10.6']; // country
            }

            fputcsv($fp, $data);
        }

        fclose($fp);

        $quoted = sprintf('"%s"', addcslashes(basename($file), '"\\'));
        $size   = filesize($file);

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $quoted); 
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $size);        
        echo(file_get_contents($file));
        die();
    }

    return $args;
}
add_filter('users_list_table_query_args', 'ahs_export_users', 10, 5);

/*
 * For future use.
 * @see https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/
 */
//register_activation_hook( __FILE__, 'pluginprefix_function_to_run' );
//register_deactivation_hook( __FILE__, 'pluginprefix_function_to_run' );



/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */
 
/**
 * custom option and settings
 */
function ahs_export_users_settings_init() {
	// register a new setting for "wporg" page
	register_setting(
		'ahs_export_users', // option group
		'ahs_export_users_options' // option name
	);
 
	// register a new section in the "wporg" page
	add_settings_section(
		'ahs_users_export_section_developers', // id
		__( 'The Matrix has you.', 'wporg' ), // title
		'ahs_export_users_cb', // callback
		'ahs_export_users' // page
	);
 
	// register a new field in the "wporg_section_developers" section, inside the "wporg" page
	add_settings_field(
		'wporg_field_pill', // as of WP 4.6 this value is used only internally
		// use $args' label_for to populate the id inside the callback
		__( 'Pill', 'wporg' ),
		'wporg_field_pill_cb',
		'wporg',
		'wporg_section_developers',
		[
			'label_for' => 'wporg_field_pill',
			'class' => 'wporg_row',
			'wporg_custom_data' => 'custom',
		]
	);
}
 
/**
 * register our init to the admin_init action hook
 */
add_action( 'admin_init', 'ahs_export_users_settings_init' );
 
/**
 * custom option and settings:
 * callback functions
 */
 
// developers section cb
 
// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function ahs_export_users_cb( $args ) {
?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Follow the white rabbit.', 'wporg' ); ?></p>
<?php
}
 
// pill field cb
 
// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function wporg_field_pill_cb( $args ) {
	// get the value of the setting we've registered with register_setting()
	$options = get_option( 'wporg_options' );
	// output the field
	?>
		<select id="<?php echo esc_attr( $args['label_for'] ); ?>"
		data-custom="<?php echo esc_attr( $args['wporg_custom_data'] ); ?>"
		name="wporg_options[<?php echo esc_attr( $args['label_for'] ); ?>]"
		>
			<option value="red" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'red', false ) ) : ( '' ); ?>>
			<?php esc_html_e( 'red pill', 'wporg' ); ?>
			</option>
			<option value="blue" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'blue', false ) ) : ( '' ); ?>>
			<?php esc_html_e( 'blue pill', 'wporg' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'You take the blue pill and the story ends. You wake in your bed and you believe whatever you want to believe.', 'wporg' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'You take the red pill and you stay in Wonderland and I show you how deep the rabbit-hole goes.', 'wporg' ); ?>
		</p>
	<?php
}
 
/**
 * top level menu
 */
function ahs_users_export_submenu() {
    add_submenu_page(
        'users.php', // parent slug
        'Export user settings', // page title
        'Export user settings', // menu title
        'manage_options', // capability
        'wporg', // menu slug
        'ahs_users_page_html' // function to be called
    );
}

/**
 * register our wporg_options_page to the admin_menu action hook
 */
add_action('admin_menu', 'ahs_users_export_submenu');

/**
 * top level menu:
 * callback functions
 */
function ahs_users_page_html() {
	// check user capabilities
	if ( ! current_user_can('manage_options') ) {
		return;
	}

	// add error/update messages

	// check if the user have submitted the settings
	// wordpress will add the "settings-updated" $_GET parameter to the url
	if ( isset( $_GET['settings-updated'] ) ) {
		// add settings saved message with the class of "updated"
		add_settings_error('ahs_export_users_messages', 'ahs_export_users_message', __( 'Settings Saved', 'ahs_export_users' ), 'updated');
	}

	// show error/update messages
	settings_errors( 'ahs_export_users_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="users.php" method="post">
			<?php
				// output security fields for the registered setting "wporg"
				settings_fields('ahs_export_users_options');
				// output setting sections and their fields
				// (sections are registered for "wporg", each field is registered to a specific section)
				do_settings_sections('ahs_export_users');
				// output save settings button
				submit_button( 'Save Settings' );
			?>
		</form>
	</div>
	<?php
}

?>
