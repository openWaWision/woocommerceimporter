<?php
/*
Plugin Name: WaWision Importer
Description: WaWision Importer für WooCommerce
Version: 1.0
Author: Bastian Aigner
Author URI: http://bastiaigner.com/
*/

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


// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'I\'m a plugin :-)';
	exit;
}


// Error-Logging ausschalten, damit es zu keinen Fehlern beim Import kommt
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");


include( plugin_dir_path( __FILE__ ) . 'options-panel.php');
include( plugin_dir_path( __FILE__ ) . 'wawision-importer.class.php');


add_action('parse_request', 'wawision_url_handler');

function wawision_url_handler() {

	if(substr($_SERVER["REQUEST_URI"], 0, strlen('/wawision-import/')) === '/wawision-import/') {
		
		$importer = new WaWision_Importer;

		exit();
	}
}






// Metabox, die bei der Detailansicht einer Bestellung im WooCommerce-Backend angezeigt wird
// Damit kann man zum Debuggen einzelne Bestellungen nochmal in WaWision importieren


// Metabox registrieren
function wawision_imported_checkbox() {
    add_meta_box( 'wawision_imported_checkbox', 'WaWision', 'wawision_imported_checkbox_callback', 'shop_order', 'side', 'high');
}
add_action( 'add_meta_boxes', 'wawision_imported_checkbox' );



// Metabox calllback fuer HTML-Content der Metabox ansich
function wawision_imported_checkbox_callback( $post ) {
    wp_nonce_field( basename( __FILE__ ), 'wawision_nonce' );
    
    $stored_meta = get_post_meta( $post->ID, '_wawision_imported', true );
    
    ?>
    <p>
        <label for="meta-text" class="prfx-row-title">Bereits in WaWision importiert</label>
        <input type="checkbox" name="wawision-imported" <?php if ($stored_meta) {echo 'checked';} ?> />
    </p>
 
    <?php
}


// Callback beim Speichern des postes
function wawision_imported_checkbox_meta_save( $post_id ) {
 
    // save status ueberpruefen
    $is_autosave = wp_is_post_autosave( $post_id );
    $is_revision = wp_is_post_revision( $post_id );
    $is_valid_nonce = ( isset( $_POST[ 'wawision_nonce' ] ) && wp_verify_nonce( $_POST[ 'wawision_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
 
    // Abbrechen abhaengig von save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }
 
    // post_meta updaten / loeschen
    if( isset( $_POST[ 'wawision-imported' ] ) ) {
        update_post_meta( $post_id, '_wawision_imported', true );
    } else {
	    delete_post_meta($post_id, '_wawision_imported');
    }
}
add_action( 'save_post', 'wawision_imported_checkbox_meta_save' );



