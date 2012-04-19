<?php

include_once(WP_PLUGIN_DIR . "/wp-e-commerce/wpsc-includes/merchant.class.php");

/*
Plugin Name: WP eCommerce Fat Zebra Gateway
Plugin URI: https://www.fatzebra.com.au/help
Description: Extends WordPress eCommerce with Fat Zebra payment gateway.
Version: 1.0.0
Author: Fat Zebra
Author URI: https://www.fatzebra.com.au
*/

/* Copyright (C) 2012 Fat Zebra Pty. Ltd.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
IN THE SOFTWARE.
*/
$nzshpcrt_gateways[$num] = array(
	'name' => 'Fat Zebra',
	'display_name' => 'Credit Card',	
	'form' => 'form_fatzebra',
	'class_name' => "wpsc_merchant_fatzebra",
	'submit_function' => 'submit_fatzebra',
	'internalname' => 'wpsc_merchant_fatzebra',
	'payment_type' => 'credit_card',
	'api_version' => 2
);

$logo_url = WP_PLUGIN_URL . "/wp-e-commerce-fat-zebra-plugin/Fat-Zebra-Certified-small.png";

if ( in_array( 'wpsc_merchant_fatzebra', (array)get_option( 'custom_gateway_options' ) ) ) {
	if ((boolean)get_option("fatzebra_show_logo")) {
		$logo_code = '<a href="https://www.fatzebra.com.au/?rel=logo" title="Fat Zebra Certified"><img src="' . $logo_url . '" alt="Fat Zebra Certified" border="0" style="float: right;"/></a>';
	}
	else {
		$logo_code = "";	
	}
	
	$gateway_checkout_form_fields["wpsc_merchant_fatzebra"] = <<<EOF
	<h4>Payment Details</h4>
	<table style="border: none; float: left; width: 500px;">
		<tr>
			<td style="width: 145px;">
				<label for="fatzebra_card_holder">
					Card Holder
					<span class="asterix">*</span>
				</label>
			</td>
			<td>
				<input type="text" name="fatzebra[card_holder]" id="fatzebra_card_holder" class="required text intra-field-label" value="{$_POST['fatzebra']['card_holder']}"/>
			</td>
		</tr>
		<tr>
			<td style="width: 145px;">
				<label for="fatzebra_card_number">Card Number</label>
			</td>
			<td>
				<input type="text" name="fatzebra[card_number]" id="fatzebra_card_number" class="required text intra-field-label" value="{$_POST['fatzebra']['card_number']}" />
			</td>
		</tr>

		<tr>
			<td style="width: 145px;">
				<label for="fatzebra_expiry">Expiry &amp; CCV</label>
			</td>
			<td>
				<input type="text" name="fatzebra[expiry_month]" id="fatzebra_expiry_month" class="required" size="2" placeholder="MM" value="{$_POST['fatzebra']['expiry_month']}" /> / 
				<input type="text" name="fatzebra[expiry_year]" id="fatzebra_expiry_year" class="required" size="4" placeholder="YYYY" value="{$_POST['fatzebra']['expiry_year']}"/>

				<input type="text" name="fatzebra[cvv]" id="fatzebra_ccv" class="required" size="4" placeholder="cvv"/>
			</td>
		</tr>
	</table>
	{$logo_code}
EOF;
}

class wpsc_merchant_fatzebra extends wpsc_merchant {
	function submit() {
		global $wpdb;

		$purchase_log = $wpdb->get_results("SELECT * FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = " . $this->cart_data['session_id'] . " LIMIT 1;", ARRAY_A);
		$amount = nzshpcrt_overall_total_price($_SESSION['delivery_country']);
		$real_amount = intval(round($amount * 100));

		$sandbox_mode = (boolean)get_option("fatzebra_sandbox_mode");
		$test_mode = (boolean)get_option("fatzebra_test_mode");
		$username = get_option("fatzebra_username");
		$token = get_option("fatzebra_token");
		
		$url = $sandbox_mode ? "https://gateway.sandbox.fatzebra.com.au/v1.0/purchases" : "https://gateway.fatzebra.com.au/v1.0/purchases";
		$params = array();
		$params["test"] = $test_mode ? "true" : "false";
		$params["reference"] = $purchase_log[0]["id"];
		$params["amount"] = $real_amount;
		$params["card_number"] = $_POST['fatzebra']['card_number'];
		$params["card_holder"] = $_POST['fatzebra']['card_holder'];
		$params["card_expiry"] = $_POST['fatzebra']['expiry_month'] . "/" . $_POST['fatzebra']['expiry_year'];
		$params["cvv"] = $_POST['fatzebra']['cvv'];
		$params["customer_ip"] = $_SERVER['REMOTE_ADDR'];

		// Validate
		if (strlen($params["card_number"]) == 0) {
			$this->set_error_message("Card Number Missing");
		}

		if (strlen($params["card_holder"]) == 0) {
			$this->set_error_message("Card Holder Missing");
		}

		if (strlen($params["cvv"]) == 0) {
			$this->set_error_message("CVV Missing");
		}

		if (strlen($_POST['fatzebra']['expiry_year']) != 4 || strlen($_POST['fatzebra']['expiry_month']) == 0) {
			$this->set_error_message("Expiry Date Invalid");
		}

		if(isset($_SESSION['wpsc_checkout_misc_error_messages']) && !empty($_SESSION['wpsc_checkout_misc_error_messages'])) {
			$this->return_to_checkout();
			exit();
		}

		$data = json_encode($params);

		$args = array(
				'method' => 'POST',
				'body' => $data,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode($username . ":" . $token),
					'X-Test-Mode' => $test_mode ? "true" : "false"
				),
				'timeout' => 30
			);
			try {
				$response = (array)wp_remote_request($url, $args);
				if ((int)$response["response"]["code"] != 200 && (int)$response["response"]["code"] != 201) {
			        $this->set_error_message(__('Sorry your transaction failed, please try again.'));

			        if ((int)$response["response"]["code"] == 401) {
			        	$this->set_error_message("Authentication Error - Please contact support");
			        } else {
			        	$this->set_error_message("Gateway Response: " . $response['body']);
			       	}
			        $this->return_to_checkout();
			        exit();
				}

				$response_data = json_decode($response['body']);


				if ($response_data->successful != 1) {
					$this->set_error_message(__("Sorry your transaction failed, please try again."));
					foreach($response_data->errors as $error) {
						$this->set_error_message($error);
					}
					$this->return_to_checkout();
					exit();
				}

				if ($response_data->response->successful == 1) {
					$this->set_transaction_details($response_data->response->id, 3);
					$this->go_to_transaction_results($this->cart_data['session_id']);
					return;
				} 

				if ($response_data->result->successful != 1) {
					$this->set_error_message("Payment declined: " . $response_data->response->message);
					$this->return_to_checkout();
					exit();
				}
			} catch(Exception $e) {
				$this->set_error_message("Unknown error with gateway.");
				$this->return_to_checkout();
				exit();
			}
	}
}
function submit_fatzebra() {
	error_log(print_r($_POST, true));

	foreach(array("fatzebra_username", "fatzebra_token") as $field):
		if (isset($_POST[$field])) {
			update_option($field, $_POST[$field]);
		}
	endforeach;

	// Booleans
	foreach(array("fatzebra_test_mode", "fatzebra_sandbox_mode", "fatzebra_show_logo") as $field):
		if (isset($_POST[$field])) {
			update_option($field, (boolean)$_POST[$field]);
		}
	endforeach;

	return true;
}

function form_fatzebra() {		

	$fatzebra_username     = get_option('fatzebra_username');
	$fatzebra_token        = get_option('fatzebra_token');
	
	$fatzebra_test_mode    = (boolean)get_option('fatzebra_test_mode');
	$test_checked = $fatzebra_test_mode ? "checked=\"checked\"" : "";

	$fatzebra_sandbox_mode = (boolean)get_option('fatzebra_sandbox_mode');
	$sandbox_checked = $fatzebra_sandbox_mode ? "checked=\"checked\"" : "";
	
	$fatzebra_show_logo = (boolean)get_option('fatzebra_show_logo');
	$logo_checked = $fatzebra_show_logo ? "checked=\"checked\"" : "";
	
	$logo_url = WP_PLUGIN_URL . "/wp-e-commerce-fat-zebra-plugin/Fat-Zebra-Certified-small.png";

	/*
		Create the form
	*/
	$output = <<<EOF
		<tr>
			<th>
				<label for="fatzebra_username">Username</label>
			</th>
			<td>
				<input type="text" name="fatzebra_username" id="fatzebra_username" value="{$fatzebra_username}" />
			</td>
		</tr>
		<tr>
			<th>
				<label for="fatzebra_token">Token</label>
			</th>
			<td>
				<input type="text" name="fatzebra_token" id="fatzebra_token" value="{$fatzebra_token}" />
			</td>
		</tr>

		<tr>
			<td></td>
			<td>
				<label for="fatzebra_sandbox_mode">
					<input name="fatzebra_sandbox_mode" type="hidden" value="0" />
					<input type="checkbox" name="fatzebra_sandbox_mode" id="fatzebra_sandbox_mode" value="1" {$sandbox_checked} />
					Sandbox Mode
				</label>
			</td>
		</tr>

		<tr>
			<td></td>
			<td>
				<label for="fatzebra_test_mode">
					<input name="fatzebra_test_mode" type="hidden" value="0" />
					<input type="checkbox" name="fatzebra_test_mode" id="fatzebra_test_mode" value="1" {$test_checked} />
					Test Mode
				</label>
			</td>
		</tr>
		<tr>
			<td></td>
			<td>
				<label for="fatzebra_show_logo">
					<input name="fatzebra_show_logo" type="hidden" value="0" />
					<input type="checkbox" name="fatzebra_show_logo" id="fatzebra_show_logo" value="1" {$logo_checked} />
					Show Fat Zebra Logo
				</label>
			</td>
		</tr>

		<tr>
			<td colspan="2" align="center">
				<a href="https://www.fatzebra.com.au/?rel=certified" title="Fat Zebra Certified"><img src="{$logo_url}" alt="Fat Zebra Certified" /></a>
			</td>
		</tr>

	</table>
EOF;

	return $output;
}

?>