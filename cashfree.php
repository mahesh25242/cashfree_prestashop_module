<?php 	
// error_reporting(E_ALL);	
//use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


if (!defined('_PS_VERSION_')) {
	exit;
}
require_once(dirname(__FILE__).'/lib/encdec_cashfree.php');
class Cashfree extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();
	private $_title;
	
	function __construct()
	{		
		$this->name = 'cashfree';
		$this->tab = 'payments_gateways';
		$this->version = 3.0;
		$this->author = 'Cashfree Development Team';
				
		parent::__construct();
		$this->displayName = $this->l(' Cashfree');
		$this->description = $this->l('Module for accepting payments by Cashfree');
		
		$this->page = basename(__FILE__, '.php');
	}
	
	public function getDefaultCallbackUrl(){
		return $this->context->link->getModuleLink($this->name, 'validation');
	}
	public function install()
	{
		if(parent::install()){
			Configuration::updateValue("Cashfree_MERCHANT_ID", "");
			Configuration::updateValue("Cashfree_MERCHANT_KEY", "");
			Configuration::updateValue("Cashfree_TRANSACTION_STATUS_URL", "");
			Configuration::updateValue("Cashfree_GATEWAY_URL", "");			
			Configuration::updateValue("Cashfree_CALLBACK_URL_STATUS", 0);
			Configuration::updateValue("Cashfree_CALLBACK_URL", $this->getDefaultCallbackUrl());
			
			//$this->registerHook('paymentOptions');
			$this->registerHook("payment");
			$this->registerHook("paymentReturn");
			if(!Configuration::get('Cashfree_ORDER_STATE')){
				$this->setCashfreeOrderState('Cashfree_ID_ORDER_SUCCESS','Payment Received','#b5eaaa');
				$this->setCashfreeOrderState('Cashfree_ID_ORDER_FAILED','Payment Failed','#E77471');
				$this->setCashfreeOrderState('Cashfree_ID_ORDER_PENDING','Payment Pending','#F4E6C9');

				
				Configuration::updateValue('Cashfree_ORDER_STATE', '1');
			}		
			return true;
		}
		else {
			return false;
		}
	
	}
	public function uninstall()
	{
		if (!Configuration::deleteByName("Cashfree_MERCHANT_ID") OR 
			!Configuration::deleteByName("Cashfree_MERCHANT_KEY") OR 
			!Configuration::deleteByName("Cashfree_TRANSACTION_STATUS_URL") OR 
			!Configuration::deleteByName("Cashfree_GATEWAY_URL") OR 			
			!Configuration::deleteByName("Cashfree_CALLBACK_URL_STATUS") OR 
			!Configuration::deleteByName("Cashfree_CALLBACK_URL") OR 
			!parent::uninstall()) {
			return false;
		}
		return true;
	}
	public function setCashfreeOrderState($var_name,$status,$color){
		$orderState = new OrderState();
		$orderState->name = array();
		foreach(Language::getLanguages() AS $language){
			$orderState->name[$language['id_lang']] = $status;
		}
		$orderState->send_email = false;
		$orderState->color = $color;
		$orderState->hidden = false;
		$orderState->delivery = false;
		$orderState->logable = true;
		$orderState->invoice = true;
		if ($orderState->add())
			Configuration::updateValue($var_name, (int)$orderState->id);
		return true;
	}
	public function getContent() {
		$this->_html = "<h2>" . $this->displayName . "</h2>";
		if (isset($_POST["submitCashfree"])) {
			// trim all values
			foreach($_POST as &$v){
				$v = trim($v);
			}
			if (!isset($_POST["merchant_id"]) || $_POST["merchant_id"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Merchant APP ID.");
			}
			if (!isset($_POST["merchant_key"]) || $_POST["merchant_key"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Merchant Secret Key.");
			}
			/*if (!isset($_POST["industry_type"]) || $_POST["industry_type"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Industry Type.");
			}*/
			/*if (!isset($_POST["channel_id"]) || $_POST["channel_id"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Channel ID.");
			}/*
			if (!isset($_POST["website"]) || $_POST["website"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Website.");
			}*/
			if (!isset($_POST["gateway_url"]) || $_POST["gateway_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Gateway Url.");
			}
			if (!isset($_POST["status_url"]) || $_POST["status_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Transaction Status URL .");
			}
			if (!isset($_POST["callback_url"]) || $_POST["callback_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Callback URL.");
			} else {
				$url_parts = parse_url($_POST["callback_url"]);
				if(!isset($url_parts["scheme"]) || (strtolower($url_parts["scheme"]) != "http" 
					&& strtolower($url_parts["scheme"]) != "https") || !isset($url_parts["host"]) || $url_parts["host"] == ""){
					$this->_postErrors[] = $this->l('Callback URL is invalid. Please enter valid URL and it must be start with http:// or https://');
				}
			}
			if (!sizeof($this->_postErrors)) {
				Configuration::updateValue("Cashfree_MERCHANT_ID", $_POST["merchant_id"]);
				Configuration::updateValue("Cashfree_MERCHANT_KEY", $_POST["merchant_key"]);
				Configuration::updateValue("Cashfree_GATEWAY_URL", $_POST["gateway_url"]);
				Configuration::updateValue("Cashfree_TRANSACTION_STATUS_URL", $_POST["status_url"]);
				Configuration::updateValue("Cashfree_STATUS", $_POST["callback_url_status"]);
				Configuration::updateValue("Cashfree_CALLBACK_URL", $_POST["callback_url"]);

				
				$this->displayConf();
			} else {
				$this->displayErrors();
			}
		}
		$this->_showimageCashfree();
		$this->_displayFormSettings();
		return $this->_html;
    }
    public function displayConf(){
		$this->_html .= '
		<div class="conf confirm">
			<img src="../img/admin/ok.gif" alt="' . $this->l("Confirmation") . '" />
			' . $this->l("Settings updated") . '
		</div>';
	}
	
	public function displayErrors(){
		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
			<ol>';
		foreach ($this->_postErrors AS $error)
			$this->_html .= '<li>'.$error.'</li>';
		$this->_html .= '
			</ol>
		</div>';
	}
	
	public function _showimageCashfree(){
		$this->_html .= '
		<img src="../modules/cashfree/cashfree.png" style="float:left; padding: 0px; margin-right:15px;" />
		<b>'.$this->l('This module allows you to accept payments by Cashfree.').'</b><br /><br />
		'.$this->l('If the client chooses this payment mode, your Cashfree account will be automatically credited.').'<br />
		'.$this->l('Please ensure that your Cashfree account is configured before using this module.').'
		<br /><br /><br />';
	}
	public function _displayFormSettings() {
	 	$merchant_id = isset($_POST["merchant_id"])? 
							$_POST["merchant_id"] : Configuration::get("Cashfree_MERCHANT_ID");
		$merchant_key = isset($_POST["merchant_key"])? 
							$_POST["merchant_key"] : Configuration::get("Cashfree_MERCHANT_KEY");
		
		$gateway_url = isset($_POST["gateway_url"])? 
							$_POST["gateway_url"] : Configuration::get("Cashfree_GATEWAY_URL");
		$status_url = isset($_POST["status_url"])? 
							$_POST["status_url"] : Configuration::get("Cashfree_TRANSACTION_STATUS_URL");
		$callback_url = isset($_POST["callback_url"])? 
							$_POST["callback_url"] : Configuration::get("Cashfree_CALLBACK_URL");
		$last_updated = "";
		$path = __DIR__."/cashfree_version.txt";
		if(file_exists($path)){
			$handle = fopen($path, "r");
			if($handle !== false){
				$date = fread($handle, 10); // i.e. DD-MM-YYYY or 25-04-2018
				$last_updated = '<div class="pull-left"><p>Last Updated: '. date("d F Y", strtotime($date)) .'</p></div>';
			}
		}
		$this->bootstrap = true;
		$this->_html .= '
			<form id="module_form" class="defaultForm form-horizontal" method="POST" novalidate="">
				<div class="panel">
					<div class="panel-heading">'.$this->l("Cashfree Payment Configuration Set Up").'</div>
					<div class="form-wrapper">
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant APP ID").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_id" value="' . $merchant_id . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant Secret Key").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_key" value="' . $merchant_key . '"  class="" required="required"/>
							</div>
						</div>
						
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Transaction Url").'</label>
							<div class="col-lg-9">
								<input type="text" name="gateway_url" value="' . $gateway_url . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group hide">
							<label class="control-label col-lg-3 required"> '.$this->l("Transaction Status Url").'</label>
							<div class="col-lg-9">
								<input type="text" name="status_url" value="' . $status_url . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group hide">
							<label class="control-label col-sm-3 required" for="callback_url_status">
								'.$this->l("Custom Callback Url").'
							</label>
							<div class="col-sm-9">
								<select name="callback_url_status" id="callback_url_status" class="form-control">
									<option value="1" '.(Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "1"? "selected" : "").'>'.$this->l('Enable').'</option>
									<option value="0" '.(Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "0"? "selected" : "").'>'.$this->l('Disable').'</option>
								</select>
							</div>
						</div>
						<div class="callback_url_group form-group">
							<label class="control-label col-sm-3 required" for="callback_url">
								'.$this->l("Callback URL").'
							</label>
							<div class="col-sm-9">
								<input type="text" name="callback_url" id="callback_url" value="'. $callback_url .'" class="form-control" '.(Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "0"? "readonly" : "").'/>
							</div>
						</div>
					</div>
					<div class="panel-footer">
						<div>
							<button type="submit" value="1" id="module_form_submit_btn" name="submitCashfree" class="btn btn-default pull-right">
								<i class="process-icon-save"></i> Save
							</button>
						</div>
						'.$last_updated.'
					</div>
				</div>
			</form>
			<script type="text/javascript">
			var default_callback_url = "'.$this->getDefaultCallbackUrl().'";
			function toggleCallbackUrl(){
				if($("select[name=\"callback_url_status\"]").val() == "1"){
					$(".callback_url_group").removeClass("hidden");
					$("input[name=\"callback_url\"]").prop("readonly", false);
				} else {
					$(".callback_url_group").addClass("hidden");
					$("#callback_url").val(default_callback_url);
					$("input[name=\"callback_url\"]").prop("readonly", true);
				}
			}
			$(document).on("change", "select[name=\"callback_url_status\"]", function(){
				toggleCallbackUrl();
			});
			toggleCallbackUrl();
			</script>';
	}
	public function hookPayment($params) {
		global $smarty;
		
		$smarty->assign(array(
			"this_path" => $this->_path,
			"this_path_ssl" => Configuration::get("PS_FO_PROTOCOL") . $_SERVER["HTTP_HOST"] . __PS_BASE_URI__ . "modules/{$this->name}/"));
		
		return $this->display(__FILE__, "payment.tpl");
	}

	public function execPayment($cart) {
		
		global $smarty, $cart;

		$bill_address = new Address(intval($cart->id_address_invoice));
		$customer = new Customer(intval($cart->id_customer));

		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Cashfree error: (invalid address or customer)");


		$order_id = intval($cart->id);

		// $order_id = "RHL_" . strtotime("now") . "__" . $order_id; // just for testing

		$amount = $cart->getOrderTotal(true, Cart::BOTH);

		$post_variables = array(
			"MID" => Configuration::get("Cashfree_MERCHANT_ID"),
			"ORDER_ID" => $order_id,
			"CUST_ID" => intval($cart->id_customer),
			"TXN_AMOUNT" => $amount,
			"CHANNEL_ID" => Configuration::get("Cashfree_MERCHANT_CHANNEL_ID"),
			"INDUSTRY_TYPE_ID" => Configuration::get("Cashfree_MERCHANT_INDUSTRY_TYPE"),
			"WEBSITE" => Configuration::get("Cashfree_MERCHANT_WEBSITE"),
		);

		if(isset($bill_address->phone_mobile) && trim($bill_address->phone_mobile) != "")
			$post_variables["MOBILE_NO"] = preg_replace("#[^0-9]{0,13}#is", "", $bill_address->phone_mobile);

		if(isset($customer->email) && trim($customer->email) != "")
			$post_variables["EMAIL"] = $customer->email;

		if (Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "0")
			$post_variables["CALLBACK_URL"] = $this->getDefaultCallbackUrl();
		else
			$post_variables["CALLBACK_URL"] = Configuration::get("Cashfree_CALLBACK_URL");


		$post_variables["CHECKSUMHASH"] = getChecksumFromArray($post_variables, Configuration::get("Cashfree_MERCHANT_KEY"));


		/* make log for all payment request */
		if(Configuration::get('Cashfree_ENABLE_LOG')){
			$log_entry = "Request Type: Process Transaction (DEFAULT)". PHP_EOL;
			$log_entry .= "Request URL: " . Configuration::get("Cashfree_GATEWAY_URL") . PHP_EOL;
			$log_entry .= "Request Params: " . print_r($post_variables, true) .PHP_EOL.PHP_EOL;
			Cashfree::addLog($log_entry, __FILE__, __LINE__);
		}
		/* make log for all payment request */
		
		$smarty->assign(
						array(
							"cashfree_post" => $post_variables,
							"action" => Configuration::get("Cashfree_GATEWAY_URL")
							)
					);

		return $this->display(__FILE__, "payment_form.tpl");
	}

	public function hookPaymentReturn($params) {
		if (!$this->active)
			return;

		$state = $params["objOrder"]->getCurrentState();
		if ($state == Configuration::get("Cashfree_ID_ORDER_SUCCESS")) {
			$this->smarty->assign(array(
				"status" => "ok",
				"id_order" => $params["objOrder"]->id
			));
		} else
			$this->smarty->assign("status", "failed");
		return $this->display(__FILE__, "payment_return.tpl");
	}


	public static function addLog($message, $file = null, $line = null){

		// if log is disabled by module itself then return true to pretend everything working fine
		if(self::$debug_log == false){
			return true;
		}

		try {
			
			$log_file = __DIR__."/cashfree.log";
			$handle = fopen($log_file, "a+");
			
			// if there is some permission issue
			if($handle == false){
				return "Unable to write log file (".$log_file."). Please provide appropriate permission to enable log.";
			}

			// append Indian Standard Time for each log
			$date = new DateTime();
			$date->setTimeZone(new DateTimeZone("Asia/Kolkata"));
			$log_entry = $date->format('Y-m-d H:i:s')."(IST)".PHP_EOL;

			if($file && $line){
				$log_entry .= $file."#".$line.PHP_EOL;
			} else if($file){
				$log_entry .= $file.PHP_EOL;
			} else if($line){
				$log_entry .= $line.PHP_EOL;
			}

			$log_entry .= $message.PHP_EOL.PHP_EOL;

			fwrite($handle, $log_entry);
			fclose($handle);

		} catch(Exception $e){

		}

		return true;
	}
}
?>
