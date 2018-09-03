<?php

if (!defined('ABSPATH')) {

	die('This file cannot be accessed directly');
}

if (!class_exists('RCP_Payir')) {

	class RCP_Payir
	{
		public function __construct()
		{
			add_action('init', array($this, 'Payir_Verify'));
			add_action('rcp_payments_settings', array($this, 'Payir_Setting'));
			add_action('rcp_gateway_Payir', array($this, 'Payir_Request'));

			add_filter('rcp_payment_gateways', array($this, 'Payir_Register'));

			if (!function_exists('Payir_Currencies') && !function_exists('Payir_Currencies')) {

				add_filter('rcp_currencies', array($this, 'Payir_Currencies'));
			}
		}

		public function Payir_Currencies($currencies)
		{
			unset($currencies['RIAL']);

			$currencies['تومان'] = __('تومان', 'rcp_payir');
			$currencies['ریال'] = __('ریال', 'rcp_payir');

			return $currencies;
		}
				
		public function Payir_Register($gateways)
		{
			global $rcp_options;

			$payir = 'درگاه پرداخت و کیف پول الکترونیک Pay.ir';

			if (version_compare(RCP_PLUGIN_VERSION, '2.1.0', '<')) {

				$gateways['Payir'] = isset($rcp_options['payir_name']) ? $rcp_options['payir_name'] : __($payir, 'rcp_payir');

			} else {

				$gateways['Payir'] = array(

					'label'       => isset($rcp_options['payir_name']) ? $rcp_options['payir_name'] : __($payir, 'rcp_payir'),
					'admin_label' => isset($rcp_options['payir_name']) ? $rcp_options['payir_name'] : __($payir, 'rcp_payir'),
				);
			}

			return $gateways;
		}

		public function Payir_Setting($rcp_options)
		{
		?>	
			<hr/>
			<table class="form-table">
				<?php do_action('RCP_Payir_before_settings', $rcp_options); ?>
				<tr valign="top">
					<th colspan="2">
						<h3><?php _e('تنظیمات درگاه پرداخت و کیف پول الکترونیک Pay.ir', 'rcp_payir'); ?></h3>
					</th>
				</tr>
				<tr valign="top">
					<th>
						<label for="rcp_settings[payir_api]"><?php _e('کلید API', 'rcp_payir'); ?></label>
					</th>
					<td>
						<input class="regular-text" id="rcp_settings[payir_api]" style="width:300px;" name="rcp_settings[payir_api]" value="<?php if (isset($rcp_options['payir_api'])) { echo $rcp_options['payir_api']; } ?>"/>
					</td>
				</tr>				
				<tr valign="top">
					<th>
						<label for="rcp_settings[payir_query_name]"><?php _e('نام لاتین درگاه پرداخت', 'rcp_payir'); ?></label>
					</th>
					<td>
						<input class="regular-text" id="rcp_settings[payir_query_name]" style="width:300px;" name="rcp_settings[payir_query_name]" value="<?php echo isset($rcp_options['payir_query_name']) ? $rcp_options['payir_query_name'] : 'Payir'; ?>"/>
						<div class="description"><?php _e('این نام در هنگام بازگشت از بانک در آدرس بازگشت از بانک نمایان خواهد شد<br/>این نام باید با نام سایر درگاه ها متفاوت باشد', 'rcp_payir'); ?></div>
					</td>
				</tr>
				<tr valign="top">
					<th>
						<label for="rcp_settings[payir_name]"><?php _e('نام نمایشی درگاه پرداخت', 'rcp_payir'); ?></label>
					</th>
					<td>
						<input class="regular-text" id="rcp_settings[payir_name]" style="width:300px;" name="rcp_settings[payir_name]" value="<?php echo isset($rcp_options['payir_name']) ? $rcp_options['payir_name'] : __('درگاه پرداخت و کیف پول الکترونیک Pay.ir', 'rcp_payir'); ?>"/>
					</td>
				</tr>
				<tr valign="top">
					<th>
						<label><?php _e('تذکر ', 'rcp_payir'); ?></label>
					</th>
					<td>
						<div class="description"><?php _e('از سربرگ مربوط به ثبت نام در تنظیمات افزونه حتما یک برگه برای بازگشت از بانک انتخاب نمایید<br/>ترجیحا نامک برگه را لاتین قرار دهید<br/> نیازی به قرار دادن شورت کد خاصی در برگه نیست و میتواند برگه ی خالی باشد', 'rcp_payir'); ?></div>
					</td>
				</tr>
				<?php do_action('RCP_Payir_after_settings', $rcp_options); ?>
			</table>
			<?php
		}
		
		public function Payir_Request($subscription_data)
		{
			$new_subscription_id = get_user_meta($subscription_data['user_id'], 'rcp_subscription_level', TRUE);

			if (!empty($new_subscription_id)) {

				update_user_meta($subscription_data['user_id'], 'rcp_subscription_level_new', $new_subscription_id);
			}
			
			$old_subscription_id = get_user_meta($subscription_data['user_id'], 'rcp_subscription_level_old', TRUE);

			update_user_meta($subscription_data['user_id'], 'rcp_subscription_level', $old_subscription_id);
			
			global $rcp_options;

			ob_start();

			$query  = isset($rcp_options['payir_query_name']) ? $rcp_options['payir_query_name'] : 'Payir';
			$amount = str_replace(',', '', $subscription_data['price']);

			$payir_payment_data = array(

				'user_id'           => $subscription_data['user_id'],
				'subscription_name' => $subscription_data['subscription_name'],
				'subscription_key'  => $subscription_data['key'],
				'amount'            => $amount
			);		

			@session_start();

			$_SESSION['payir_payment_data'] = $payir_payment_data;

			do_action('RCP_Before_Sending_to_Payir', $subscription_data);

			if (extension_loaded('curl')) {

				$currency = $rcp_options['currency'];
				

				if ($currency == 'تومان' || $currency == 'TOMAN' || $currency == 'تومان ایران' || $currency == 'IRT' || $currency == 'Iranian Toman') {

					$amount = $amount * 10;
				}

				$api_key  = isset($rcp_options['payir_api']) ? $rcp_options['payir_api'] : NULL;
				$callback = add_query_arg('gateway', $query, $subscription_data['return_url']);

				$params = array(

					'api'          => $api_key,
					'amount'       => intval($amount),
					'redirect'     => urlencode($callback),
					'factorNumber' => $subscription_data['post_data']['rcp_register_nonce']
				);

				$result = $this->common('https://pay.ir/payment/send', $params);

				if ($result && isset($result->status) && $result->status == 1) {

					$gateway_url = 'https://pay.ir/payment/gateway/' . $result->transId;

					wp_redirect($gateway_url);

				} else {

					$fault = 'در ارتباط با وب سرویس Pay.ir خطایی رخ داده است';
					$fault = isset($result->errorMessage) ? $result->errorMessage : $fault;

					wp_die(sprintf(__('متاسفانه پرداخت به دلیل خطای زیر امکان پذیر نمی باشد<br/><b>%s</b>', 'rcp_payir'), $fault));
				}

			} else {

				$fault = 'تابع cURL در سرور فعال نمی باشد';

				wp_die(sprintf(__('متاسفانه پرداخت به دلیل خطای زیر امکان پذیر نمی باشد<br/><b>%s</b>', 'rcp_payir'), $fault));
			}

			exit;
		}
		
		public function Payir_Verify()
		{
			if (!isset($_GET['gateway'])) {

				return;
			}

			if (!class_exists('RCP_Payments')) {

				return;
			}

			global $rcp_options, $wpdb, $rcp_payments_db_name;
			

			@session_start();

			$payir_payment_data = isset($_SESSION['payir_payment_data']) ? $_SESSION['payir_payment_data'] : NULL;

			$query = isset($rcp_options['payir_query_name']) ? $rcp_options['payir_query_name'] : 'Payir';

			if (($_GET['gateway'] == $query) && $payir_payment_data) {

				$user_id           = $payir_payment_data['user_id'];
				$user_id           = intval($user_id);
				$subscription_name = $payir_payment_data['subscription_name'];
				$subscription_key  = $payir_payment_data['subscription_key'];
				$amount            = $payir_payment_data['amount'];

				$payment_method = isset($rcp_options['payir_name']) ? $rcp_options['payir_name'] : __('درگاه پرداخت و کیف پول الکترونیک Pay.ir', 'rcp_payir');

				$new_payment = TRUE;

				$get_result = $wpdb->get_results($wpdb->prepare("SELECT id FROM " . $rcp_payments_db_name . " WHERE `subscription_key`='%s' AND `payment_type`='%s';", $subscription_key, $payment_method));

				if ($get_result) {

					$new_payment = FALSE;
				}

				unset($GLOBALS['payir_new']);

				$GLOBALS['payir_new'] = $new_payment;

				global $new;

				$new = $new_payment;

				if ($new_payment == 1) {

					if (isset($_POST['status']) && isset($_POST['transId']) && isset($_POST['factorNumber'])) {	

						$status        = sanitize_text_field($_POST['status']);
						$trans_id      = sanitize_text_field($_POST['transId']);
						$factor_number = sanitize_text_field($_POST['factorNumber']);
						$message       = sanitize_text_field($_POST['message']);

						if (isset($status) && $status == 1) {

							$api_key = isset($rcp_options['payir_api']) ? $rcp_options['payir_api'] : NULL;

							$params = array (

								'api'     => $api_key,
								'transId' => $trans_id
							);

							$result = $this->common('https://pay.ir/payment/verify', $params);

							if ($result && isset($result->status) && $result->status == 1) {

								$card_number = isset($_POST['cardNumber']) ? sanitize_text_field($_POST['cardNumber']) : 'Null';
								$currency = $rcp_options['currency'];
								
								if ($currency == 'تومان' || $currency == 'TOMAN' || $currency == 'تومان ایران' || $currency == 'IRT' || $currency == 'Iranian Toman') {

									$amount = $amount * 10;
								}

								if (intval($amount) == $result->amount) {

									$fault = NULL;

									$payment_status = 'completed';
									$transaction_id = $trans_id;

								} else {

									$fault = 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد';

									$payment_status = 'failed';
									$transaction_id = $trans_id;
								}

							} else {

								$fault = 'در ارتباط با وب سرویس Pay.ir و بررسی تراکنش خطایی رخ داده است';
								$fault = isset($result->errorMessage) ? $result->errorMessage : $fault;

								$payment_status = 'failed';
								$transaction_id = $trans_id;
							}

						} else {

							if ($message) {

								$fault = $message;

								$payment_status = 'failed';
								$transaction_id = $trans_id;

							} else {

								$fault = 'تراكنش با خطا مواجه شد و یا توسط پرداخت کننده کنسل شده است';

								$payment_status = 'cancelled';
								$transaction_id = $trans_id;
							}
						}

					} else {

						$fault = 'اطلاعات ارسال شده مربوط به تایید تراکنش ناقص و یا غیر معتبر است';

						$payment_status = 'failed';
						$transaction_id = NULL;
					}

					unset($GLOBALS['payir_payment_status']);
					unset($GLOBALS['payir_transaction_id']);
					unset($GLOBALS['payir_fault']);
					unset($GLOBALS['payir_subscription_key']);

					$GLOBALS['payir_payment_status']   = $payment_status;
					$GLOBALS['payir_transaction_id']   = $transaction_id;
					$GLOBALS['payir_subscription_key'] = $subscription_key;
					$GLOBALS['payir_fault']            = $fault;

					global $payir_transaction;

					$payir_transaction = array();

					$payir_transaction['payir_payment_status']   = $payment_status;
					$payir_transaction['payir_transaction_id']   = $transaction_id;
					$payir_transaction['payir_subscription_key'] = $subscription_key;
					$payir_transaction['payir_fault']            = $fault;

					if ($payment_status == 'completed') {

						$payment_data = array(

							'date'             => date('Y-m-d g:i:s'),
							'subscription'     => $subscription_name,
							'payment_type'     => $payment_method,
							'subscription_key' => $subscription_key,
							'amount'           => $amount,
							'user_id'          => $user_id,
							'transaction_id'   => $transaction_id
						);

						do_action('RCP_Payir_Insert_Payment', $payment_data, $user_id);

						$rcp_payments = new RCP_Payments();

						$rcp_payments->insert($payment_data);

						$new_subscription_id = get_user_meta($user_id, 'rcp_subscription_level_new', TRUE);

						if (!empty($new_subscription_id)) {

							update_user_meta($user_id, 'rcp_subscription_level', $new_subscription_id);
						}

						rcp_set_status($user_id, 'active');

						if (version_compare(RCP_PLUGIN_VERSION, '2.1.0', '<')) {

							rcp_email_subscription_status($user_id, 'active');

							if (! isset($rcp_options['disable_new_user_notices'])) {

								wp_new_user_notification($user_id);
							}
						}

						update_user_meta($user_id, 'rcp_payment_profile_id', $user_id);
						update_user_meta($user_id, 'rcp_signup_method', 'live');
						update_user_meta($user_id, 'rcp_recurring', 'no'); 
					
						$subscription = rcp_get_subscription_details(rcp_get_subscription_id($user_id));
						$member_new_expiration = date('Y-m-d H:i:s', strtotime('+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59'));

						rcp_set_expiration_date($user_id, $member_new_expiration);
						delete_user_meta($user_id, '_rcp_expired_email_sent');

						$post_title   = __('تایید پرداخت', 'rcp_payir');
						$post_content = __('پرداخت با موفقیت انجام شد شماره تراکنش: ' . $transaction_id, 'rcp_payir') . __(' روش پرداخت: ', 'rcp_payir');

						$log_data = array(

							'post_title'   => $post_title,
							'post_content' => $post_content . $payment_method,
							'post_parent'  => 0,
							'log_type'     => 'gateway_error'
						);

						$log_meta = array(

							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);

						$log_entry = WP_Logging::insert_log($log_data, $log_meta);

						do_action('RCP_Payir_Completed', $user_id);
					}

					if ($payment_status == 'cancelled') {

						$post_title   = __('انصراف از پرداخت', 'rcp_payir');
						$post_content = __('تراکنش به دلیل خطای رو به رو ناموفق باقی ماند: ', 'rcp_payir') . $fault . __(' روش پرداخت: ', 'rcp_payir');

						$log_data = array(

							'post_title'   => $post_title,
							'post_content' => $post_content . $payment_method,
							'post_parent'  => 0,
							'log_type'     => 'gateway_error'
						);

						$log_meta = array(

							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);

						$log_entry = WP_Logging::insert_log($log_data, $log_meta);

						do_action('RCP_Payir_Cancelled', $user_id);
					}

					if ($payment_status == 'failed') {

						$post_title   = __('خطا در پرداخت', 'rcp_payir');
						$post_content = __('تراکنش به دلیل خطای رو به رو ناموفق باقی ماند: ', 'rcp_payir') . $fault . __(' روش پرداخت: ', 'rcp_payir');

						$log_data = array(

							'post_title'   => $post_title,
							'post_content' => $post_content . $payment_method,
							'post_parent'  => 0,
							'log_type'     => 'gateway_error'
						);

						$log_meta = array(

							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);

						$log_entry = WP_Logging::insert_log($log_data, $log_meta);

						do_action('RCP_Payir_Failed', $user_id);
					}
				}

				add_filter('the_content', array($this, 'Payir_Content_After_Return'));
			}
		}

		public function Payir_Content_After_Return($content)
		{ 
			global $payir_transaction, $new;

			@session_start();

			$new_payment = isset($GLOBALS['payir_new']) ? $GLOBALS['payir_new'] : $new;
			
			$payment_status = isset($GLOBALS['payir_payment_status']) ? $GLOBALS['payir_payment_status'] : $payir_transaction['payir_payment_status'];
			$transaction_id = isset($GLOBALS['payir_transaction_id']) ? $GLOBALS['payir_transaction_id'] : $payir_transaction['payir_transaction_id'];

			$fault = isset($GLOBALS['payir_fault']) ? $GLOBALS['payir_fault'] : $payir_transaction['payir_fault'];
			
			if ($new_payment == 1)  {
			
				$payir_data = array(

					'payment_status' => $payment_status,
					'transaction_id' => $transaction_id,
					'fault'          => $fault
				);
				
				$_SESSION['payir_data'] = $payir_data;
			
			} else {

				$payir_payment_data = isset($_SESSION['payir_data']) ? $_SESSION['payir_data'] : NULL;
			
				$payment_status = isset($payir_payment_data['payment_status']) ? $payir_payment_data['payment_status'] : NULL;
				$transaction_id = isset($payir_payment_data['transaction_id']) ? $payir_payment_data['transaction_id'] : NULL;

				$fault = isset($payir_payment_data['fault']) ? $payir_payment_data['fault'] : NULL;
			}

			$message = NULL;

			if ($payment_status == 'completed') {

				$message = '<br/>' . __('تراکنش با موفقیت انجام شد. شماره پیگیری تراکنش ', 'rcp_payir') . $transaction_id . '<br/>';
			}

			if ($payment_status == 'cancelled') {

				$message = '<br/>' . __('تراکنش به دلیل انصراف شما نا تمام باقی ماند', 'rcp_payir');
			}

			if ($payment_status == 'failed') {

				$message = '<br/>' . __('تراکنش به دلیل خطای زیر ناموفق باقی باند', 'rcp_payir') . '<br/>' . $fault . '<br/>';
			}

			return $content . $message;
		}

		private static function common($url, $params)
		{
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

			$response = curl_exec($ch);
			$error    = curl_errno($ch);

			curl_close($ch);

			$output = $error ? FALSE : json_decode($response);

			return $output;
		}
	}
}

new RCP_Payir();

if (!function_exists('change_cancelled_to_pending')) {	

	add_action('rcp_set_status', 'change_cancelled_to_pending', 10, 2);

	function change_cancelled_to_pending($status, $user_id)
	{
		if ($status == 'cancelled') {

			rcp_set_status($user_id, 'expired');

			return TRUE;
		}
	}
}

if (!function_exists('RCP_User_Registration_Data') && !function_exists('RCP_User_Registration_Data')) {

	add_filter('rcp_user_registration_data', 'RCP_User_Registration_Data');

	function RCP_User_Registration_Data($user)
	{
		$old_subscription_id = get_user_meta($user['id'], 'rcp_subscription_level', TRUE);

		if (!empty($old_subscription_id)) {

			update_user_meta($user['id'], 'rcp_subscription_level_old', $old_subscription_id);
		}

		$user_info = get_userdata($user['id']);

		$old_user_role = implode(', ', $user_info->roles);

		if (!empty($old_user_role)) {

			update_user_meta($user['id'], 'rcp_user_role_old', $old_user_role);
		}

		return $user;
	}
}
