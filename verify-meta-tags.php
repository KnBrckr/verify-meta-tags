<?php
/*
Plugin Name: Verify Meta Tags
Plugin URI: http://pumastudios.com/software/
Description: Add verification meta tags to a site
Version: 0.1
Author: Kenneth J. Brucker
Author URI: http://pumastudios.com/
Text Domain: verify-meta-tags

Copyright: 2014 Kenneth J. Brucker (email: ken@pumastudios.com)

This file is part of verify-meta-tags, a plugin for Wordpress.

verify-meta-tags is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

verify-meta-tags is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with verify-meta-tags.  If not, see <http://www.gnu.org/licenses/>.
*/

global $verify_meta_tags;

// =====================================
// = Define the verify-meta-tags class =
// =====================================

if ( ! class_exists('verify_meta_tags')) {
	class verify_meta_tags {
		
		/**
		 * Constructor function
		 *
		 * @return void
		 */
		function __construct()
		{
			$this->options = $this->sanitize_settings(get_option('verify-meta-tags'));
			
			// Register uninstall hook
			register_uninstall_hook(__FILE__, array('verify_meta_tags', 'uninstall_hook'));
			
			// Run the init during WP init processing
			add_action('init', array($this, 'wp_init'));
		}

		/**
		 * Run during WordPress wp_init
		 *
		 * @return void
		 */
		function wp_init() 
		{
			if (is_admin()) {
				add_action('admin_init', array($this, 'vmt_init'));
				add_action('admin_menu', array($this, 'vmt_options_page'));
			}
			add_action('wp_head', array($this, 'vmt_head'));
		}
		
		/**
		 * Do plugin uninstall actions - Called as a static function, $this references not allowed
		 *   - Delete plugin options
		 *
		 * @return void
		 */
		function uninstall_hook()
		{
			// Delete plugin options			
			delete_option('verify-meta-tags');
		}
		
		/**
		 * Do admin init operations
		 *
		 * @return void
		 */
		function vmt_init()
		{
			// Register admin style sheet
			wp_register_style('vmt-style-admin', plugins_url('verify-meta-tags') . '/admin.css');
	
			// Register the settings name and sanitization function
			register_setting( 'vmt-options-page', 'verify-meta-tags', array (&$this, 'sanitize_settings') );
		}
		
		/**
		 * Make sure options are safe!
		 *
		 * @access private
		 * @return array of option settings
		 */
		function sanitize_settings($options)
		{
			// Setup array of valid options for return
			$valid_options = array();
			
			// Establish defaults
			$option_defaults = array(
				'analytics' => '',
				'google' => '',
				'pinterest' => ''
			);
			
			$option_types = array(
				'analytics' => 'html',
				'google' => 'text',
				'pinterest' => 'text'
			);
			
			// Merge pass options and the defaults
			$options = (array) wp_parse_args($options, $option_defaults);
			
			// Sanitize each value
			foreach ($option_types as $key => $type) {
				$valid_options[$key] = $this->sanitize_an_option($options[$key], $type);
			}

			return $valid_options;
		}
		
		/**
		 * Sanitize an option based on field type
		 *
		 * @access private
		 * @param $val Value of option to clean
		 * @param $type Option type (text, bool, etc.)
		 * @return sanitized option value
		 **/
		private function sanitize_an_option($val, $type)
		{
			switch($type) {
				case 'bool' :
				  return $val ? true : false;
			
				default:
				case 'text' :
					return wp_filter_nohtml_kses($val);  // HTML not allowed in value string for meta tags
				
				case 'int' :
					return intval($val);
					
				case 'html' :
					return wp_kses_stripslashes($val);
			}
		}
		
		/**
		 * Create Options page for plugin
		 *
		 * @return void
		 */
		function vmt_options_page()
		{
			// Create the options page
			$options_page = add_options_page(
				'Verify Meta Tags Plugin Options', 
				'Verify Meta Tags',
				'manage_options',
				'vmt-options-page', 
				array(&$this, 'options_page_html')
			);
			
			add_action( 'admin_print_styles-' . $options_page, array($this, 'enqueue_admin_styles'), 1000 );
			
			// Add settings section
			
			add_settings_section( 
				'vmt-options-section', 
				'Owner Verification Meta Tags', 
				array( &$this, 'display_ID_section_html'), 
				'vmt-options-page'
			);
			
			// Google verification ID
			add_settings_field(
				'verify-meta-tags[google]',	
				'Google Verification ID', 
				array( &$this, 'display_verify_id_html' ), 
				'vmt-options-page', 
				'vmt-options-section',
				array('code'=>'google') 
			);

			// Pinterest verification ID
			add_settings_field(
				'verify-meta-tags[pinterest]',	
				'Pinterest Verification ID', 
				array( &$this, 'display_verify_id_html' ), 
				'vmt-options-page', 
				'vmt-options-section',
				array('code'=>'pinterest') 
			);
			
			// Analytics Code block in it's own section
			add_settings_section( 
				'vmt-options-analytics-section', 
				'Site Statistics Tracking', 
				array( &$this, 'display_analytics_section_html'), 
				'vmt-options-page'
			);
			add_settings_field(
				'verify-meta-tags[analytics]',	
				'Analytics code', 
				array( &$this, 'display_analytics_html' ), 
				'vmt-options-page', 
				'vmt-options-analytics-section',
				array('code'=>'analytics') 
			);
		}
		
		/**
		 * Enqueue plugin style sheet
		 *
		 * @return void
		 */
		function enqueue_admin_styles()
		{
			wp_enqueue_style('vmt-style-admin');
		}
		
		/**
		 * Generate the plugin Options page HTML
		 *
		 * @return void
		 */
		function options_page_html()
		{
			if (!current_user_can('manage_options')) {
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}
			
			echo '<div class="wrap">';
			echo '<h2>';
			echo 'Verify Meta Tags Plugin Options';
			echo '</h2>';
			echo '<form method="post" action="options.php">';
			settings_fields('vmt-options-page');
			do_settings_sections('vmt-options-page');
			echo '<p class=submit>';
			echo '<input type="submit" class="button-primary" value="' . __('Save Changes') . '" />';
			echo '</p>';
			echo '</form>';
			echo '</div>';
		}
		
		/**
		 * Emit HTML for ID section
		 *
		 * @return void
		 */
		function display_ID_section_html()
		{
			echo '<p>';
			echo 'Set verification IDs for web services';
			echo '</p>';
		}
		
		/**
		 * Emit HTML for analytics section
		 *
		 * @return void
		 */
		function display_analytics_section_html()
		{
			echo '<p>';
			echo 'Enter code block used for site analytics';
			echo '</p>';
		}
		
		/**
		 * Emit HTML for verification ID fields
		 *
		 * @param $args[code] string, name of verification ID field
		 * @return void
		 */
		function display_verify_id_html($args)
		{
			$field = 'verify-meta-tags[' . $args['code'] . ']';
			echo '<input type="text" name="' . $field . '" class="verify-id" value="' . esc_attr($this->options[$args['code']]) . '" id="' . $field . '" />';
		}

		/**
		 * Emit HTML for analytics field
		 *
		 * @return void
		 */
		function display_analytics_html($args)
		{
			echo '<textarea name="verify-meta-tags[analytics]" class="analytics" id="verify-meta-tags[analytics]">' . esc_attr($this->options['analytics']) . '</textarea>';
		}
		
		/**
		 * Emit verification IDs in page header
		 *
		 * @return void
		 */
		function vmt_head()
		{
			if (! empty($this->options['google'])) {
				echo '<meta name="google-site-verification" content="' . esc_attr($this->options['google']) . '" />';
			}
			if (! empty($this->options['pinterest'])) {
				echo '<meta name="p:domain_verify" content="' . esc_attr($this->options['pinterest']) . '" />';
			}
			if (! empty($this->options['analytics'])) {
				echo $this->options['analytics'];
			}
		}		
	}
}

// =========================
// = Plugin initialization =
// =========================

$verify_meta_tags = new verify_meta_tags();

?>