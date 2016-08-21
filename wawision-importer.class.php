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



error_reporting(0);
ini_set('display_errors', FALSE);

require( plugin_dir_path( __FILE__ ) . 'lib/aes.class.php');

class WaWision_Importer {

	// Instanz der AES-Verschluesselungsklasse wird beim Aufruff von __constuct initialisiert
	private $aes = NULL;

	// Error-Variable. Array aus strings. wird beim Aufruf von sendResonse ausgegeben, wenn count > 0
	private $error = [];


	// WP_Query, basierend auf der die Auftrage gefetch'ed werden, die fuer wawision relevant sind.
	// wird von __construct initialisert. 
	private $orderQuery;


	// constructor: initialisert password, schaut, ob zugansdaten richtig, ruft funktion zum routen auf.

	function __construct() {

		// orderQuery initialiserien
		$this->orderQuery = new WP_Query([
			'post_status' => ['wc-completed', 'wc-on-hold', 'wc-processing'], // Muss evtl. angepasst werden. 
			'post_type' => 'shop_order',
			'posts_per_page' => 3, // Anzahl erstmal auf 3 begrenzt, da der Import nach WaWision so schon extrem lange dauert. TODO: Optimieren. 
			'meta_query' => [[
				'key' => '_wawision_imported',
				'compare' => 'NOT EXISTS',
				],
			],
		]);


		// auth checken
		// WICHTIG! NIE AUSKOMMENTIEREN! 
		$password = get_option('wawision_options')['wawision_password'];
		$this->aes = new AES($password);


		$this->checkAuth();

		// routes definieren
		$this->addRoute('auth', 'importAuth');
		$this->addRoute('getauftraegeanzahl', 'importGetAuftraegeAnzahl');
		$this->addRoute('getauftrag', 'importGetAuftrag');
		$this->addRoute('deleteauftrag', 'importDeleteAuftrag');
		$this->addRoute('updateauftrag', 'importUpdateAuftrag');

		//routen
		$this->routeRequest();




	}


	// -------------------
	// |     HANDLERS    |
	// -------------------

	function importAuth() {
		$sentToken = $this->catchRemoteCommand('token', true);
		$wpToken = get_option('wawision_options')['wawision_token'];

		if ($sentToken == $wpToken) {
			$this->writeToLog("auth ok");
			$this->sendResponse('success');
		} else {
			$this->writeToLog("auth failed");
			$this->sendResponse('failed');
		}
	}


	function importGetAuftraegeAnzahl() {
		$this->sendResponse($this->orderQuery->post_count);
	}

	function importGetAuftrag() {

		// order initialiseren mit der ID des ersten results der order Query
		$order = new WC_Order($this->orderQuery->posts[0]->ID);

		$this->writeToLog("did get order id: {$order->id}");


		$cart['auftrag'] = $order->id;
		$cart['gesamtsumme'] = $order->get_total();

		$cart['transaktionsnummer'] = $order->get_transaction_id();
		$cart['onlinebestellnummer'] = $order->get_order_number();

		$cart['versandkostenbrutto'] = $order->get_total_shipping();
		$cart['versandkostennetto'] = $order->get_total_shipping() - $order->get_shipping_tax(); // netto-versandkosten berechnen

		$cart['internebemerkung'] = $order->customer_message;

		if ($order->billing_company == '') {
			$cart['name'] = $order->billing_first_name . ' ' . $order->billing_last_name;
		} else {
			$cart['name'] = $order->billing_company;
			$cart['ansprechpartner'] = $order->billing_first_name . ' ' . $order->billing_last_name;
		}


		// Anrede wird von WooCommerce nicht abgefragt -> lassen wir erstmal weg.
		

		$cart['strasse'] = $order->billing_address_1 . $order->billing_address_2;
		$cart['plz'] = $order->billing_postcode;
		$cart['ort'] = $order->billing_city;
		$cart['land'] = $order->billing_country;
		$cart['email'] = $order->billing_email;
		// affilate_ref, abteilung und steuerfrei lassen wir erstmal weg. wo ist das bei WC?

		switch ($order->payment_method) {
			case "bacs" : $cart['zahlungsweise'] = 'vorkasse'; break;
			case "cod" : $cart['zahlungsweise'] = 'nachnahme'; break;
			case "paypal" : $cart['zahlungsweise'] = 'paypal'; break;
			case "SaferpayCw_DirectDebits" : $cart['zahlungsweise'] = 'lastschrift'; break;
			case "SaferpayCw_OpenInvoice" : $cart['zahlungsweise'] = 'rechnung'; break;
			case "SaferpayCw_CreditCard" : $cart['zahlungsweise'] = 'kreditkarte'; break;
			default: $cart['zahlungsweise'] = 'unbekannt';
		}


		// TODO Selbstabholung / Versandunternehmen

		$cart['bestelldatum'] = $order->order_date;
		// ustid gibt's nicht??
		$cart['telefon'] = $order->billing_phone;
		// fax gibt's auch nicht




		if ($order->shipping_company == '') {
			$tempCart['lieferadresse_name'] = $order->shipping_first_name . ' ' . $order->shipping_last_name;
		} else {
			$tempCart['lieferadresse_name'] = $order->shipping_company;
			$tempCart['lieferadresse_ansprechpartner'] = $order->shipping_first_name . ' ' . $order->shipping_last_name;
		}

		$tempCart['lieferadresse_strasse'] = $order->shipping_address_1 . $order->shipping_address_2;
		$tempCart['lieferadresse_plz'] = $order->shipping_postcode;
		$tempCart['lieferadresse_ort'] = $order->shipping_city;
		$tempCart['lieferadresse_land'] = $order->shipping_country;
		$tempCart['lieferadresse_email'] = $order->shipping_email;

		if ($tempCart['lieferadresse_name'] != $cart['name'] ||
			$tempCart['lieferadresse_ansprechpartner'] != $cart['ansprechpartner'] ||
			$tempCart['lieferadresse_strasse'] != $cart['strasse'] ||
			$tempCart['lieferadresse_plz'] != $cart['plz'] ||
			$tempCart['lieferadresse_ort'] != $cart['ort'] ||
			$tempCart['lieferadresse_land'] != $cart['land'] ) {


			$cart['abweichendelieferadresse'] = '1';
			$cart['lieferadresse_strasse'] = $tempCart['lieferadresse_strasse'];
			$cart['lieferadresse_plz'] = $tempCart['lieferadresse_plz'];
			$cart['lieferadresse_ort'] = $tempCart['lieferadresse_ort'];
			$cart['lieferadresse_land'] = $tempCart['lieferadresse_land'];
			$cart['lieferadresse_email'] = $tempCart['lieferadresse_email'];
		}

		foreach ($order->get_items() as $item) {

			$product = new WC_Product($item['product_id']);

			$articleArray[] = array(
				'articleid' => $product->get_sku(), // wir nehmen nicht die Produkt-DB-ID, sondern die gleiche nummer wie sie in wawison eingetragen ist
				'name' => $item['name'],
				//'price' => number_format(($item['line_total'] + $item['line_tax']) / $item['qty'],2,'.',''), // brauchen wir line_total o. line_subtotal?
				'price' => number_format($product->get_price(),2,'.',''), // ist brutto
				'quantity' => $item['qty'],
				);
				
				//var_dump($item);
		}

		$cart['articlelist'] = $articleArray;

		$tmp[0]['id'] = $cart['auftrag'];
		$tmp[0]['sessionid'];
		$tmp[0]['logdatei'];
		$tmp[0]['warenkorb'] = base64_encode(serialize($cart));

		$this->sendResponse($tmp);
	}

	function importDeleteAuftrag() {
		$orderId = $this->catchRemoteCommand('data')['auftrag'];

		$this->writeToLog("did delete order id: $orderId");

		update_post_meta($orderId, '_wawision_imported', '1'); // key value aendern

		$this->sendResponse('ok');
	}



	function importUpdateAuftrag() {

		$data = $this->catchRemoteCommand('data');

		$order = new WC_Order($data['aufrag']);
		$paymentOk = $data['zahlung'];
		$shippingOk = $data['versand'];
		$trackingCode = $data['tracking'];


		if ($paymentOk == 'ok' || $paymentOk == '1')
			$paymentOk = true;


		if ($shippingOk == 'ok' || $shippingOk == '1')
			$shippingOk = true;

		if ($paymentOk)
			$order->payment_complete();

		if ($shippingOk)
			$order->update_status( 'completed', "DHL Tracking code: $trackingCode" );
	 }





	// -------------------
	// |     ROUTING     |
	// -------------------


  
 	// speichert die definierten routes

	private $routes = [];


	// fuegt route hinzu. arg0 = action name, arg1 = string des callback-function-names in dieser class

	function addRoute($action, $callback) {
		$this->routes[$action] = $callback;
	}


	// routet das request

	function routeRequest() {


		$action = $_REQUEST['action'];

		$this->writeToLog('route: ' . $action);

		if (array_key_exists($action, $this->routes)) {
			call_user_func(array($this, $this->routes[$action]));
		} else {
			$this->error[] = 'unknown action.';
			$this->sendResponse(NULL);
		}

	} 



	// -------------------
	// |     HELPERS     |
	// -------------------



	// SICHERHEITSKRITISCH
	// ueberprueft, ob der Token & Password richtig ist und bricht ab, wenn nicht. 

	private function checkAuth() {

		if ($this->isDebug()) {return;} // debug darf alles

		$sentToken = $this->catchRemoteCommand('token', true);
		$wpToken = get_option('wawision_options')['wawision_token'];

		if ($sentToken != $wpToken) {
			
			$this->writeToLog("auth failed: $sentToken != $wpToken");
			$this->sendResponse('failed');
		}
	}



	// holt sich base64 encoded wert mit dem key $value aus dem request, wird aes-decoded wenn $aes=true

	function catchRemoteCommand($value, $aes=false) {
		if ($aes) {
			return unserialize($this->aes->decrypt(base64_decode($_REQUEST[$value])));
		} else {
			return unserialize(base64_decode($_REQUEST[$value]));
		}
	}



	// Das ganze ausgeben. Davon abhängig, ob isDebug == true
	// Wenn Fehler aufgetreten sind, werden diese auch ausgegeben.

	function sendResponse($what, $aes = false) {
		if (!$this->isDebug()) {
			
			if(count($this->error)>0)
			{ 
				echo base64_encode(serialize("error: ".implode(',',$this->error)));
			} else {
				if ($aes) {
					echo base64_encode($aes->encrypt(serialize($what)));
				} else {
					echo base64_encode(serialize($what));
				}
				
			}

		} else {
			if(count($this->error)>0)
			{ 
				echo 'error:';
				var_dump($this->error);
			} else {
				var_dump($what);
			}
		}

		exit;
	}

	

	function writeToLog($what) {
		if ($this->isDebug()) {
			$debug = 'DBG';
		} else {
			$debug = 'RLS';
		}
		
		// Kann dann auskommentiert werden. 
		file_put_contents('logs.txt', date('Y-m-d H:i:s: ', $_SERVER['REQUEST_TIME']) . $debug . ' ' . $what . PHP_EOL , FILE_APPEND);
	}


	// SICHERHEITSKRITISCH
	// sollen wir das ganze als Debug betrachten? Wenn JA, dann keine Prüfung der Token & Ausgabe
	// wird nicht base64 encoded
	
	function isDebug() {
		
		// ACHTUNG!!!
		// NUR ZUM DEBUGGEN - UMBEDINGT AENDERN!!! (einfach immer return false;)

/*
		if (strpos( $_SERVER['HTTP_USER_AGENT'], 'Safari') !== false) { 
			return true;
		}
*/
		return false;
	}
}


?>