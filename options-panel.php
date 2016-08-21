<?php
/*
    Copyright (C) 2016  Bastian Aigner / Initial Version

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


// Options-Panel zum Einstellen von Passwort und Token

// WooCommerce-Backend / Einstellungen / WaWision-Importer


add_action('admin_menu', 'wawision_admin_add_page');
function wawision_admin_add_page() {
	add_options_page(__('WaWision Importer'), __('WaWision Importer'), 'manage_options', 'wawision-importer', 'wawision_options_page');
}

add_action('admin_init', 'wawision_admin_init');
function wawision_admin_init(){
	register_setting( 'wawision_options', 'wawision_options', 'wawision_options_validate' );
	add_settings_section('wawision_main', __('Zugangsdaten'), NULL, 'wawision');
	add_settings_field('wawision_password', __('Sicherheitspasswort'), 'wawision_password_field', 'wawision', 'wawision_main');
	add_settings_field('wawision_token', __('Sicherheitstoken'), 'wawision_token_field', 'wawision', 'wawision_main');
}

function wawision_password_field() {
	$options = get_option('wawision_options');
	echo "<input id='wawision_password' name='wawision_options[wawision_password]' size='32' type='text' value='{$options['wawision_password']}' />";
}

function wawision_token_field() {
		$options = get_option('wawision_options');
	echo "<input id='wawision_token' name='wawision_options[wawision_token]' size='6' type='text' value='{$options['wawision_token']}' />";
}


function wawision_options_validate($input) {
	$newinput['wawision_password'] = trim($input['wawision_password']);
	$newinput['wawision_token'] = trim($input['wawision_token']);
	// if(!preg_match('/^[a-z0-9]{32}$/i', $newinput['text_string'])) {
	// 	$newinput['text_string'] = '';
	// }
	return $newinput;
}


function wawision_options_page() {
	?>
	<div>
		<h2>WaWision Importer</h2>
		
		<form action="options.php" method="post">
			<?php settings_fields('wawision_options'); ?>
			<?php do_settings_sections('wawision'); ?>

			<input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
		</form></div>
	<?php
}
 