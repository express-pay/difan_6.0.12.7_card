<?php
/**
 * Формирует данные для формы платежной системы Яндекс.Касса
 * 
 * @package    DIAFAN.CMS
 * @author     diafan.ru
 * @version    6.0
 * @license    http://www.diafan.ru/license.html
 * @copyright  Copyright (c) 2003-2018 OOO «Диафан» (http://www.diafan.ru/)
 */

if (! defined('DIAFAN'))
{
	$path = __FILE__;
	while(! file_exists($path.'/includes/404.php'))
	{
		$parent = dirname($path);
		if($parent == $path) exit;
		$path = $parent;
	}
	include $path.'/includes/404.php';
}

class Payment_expresspay_card_model extends Diafan
{
	/**
     * Формирует данные для формы платежной системы "YandexMoney"
     * 
     * @param array $params настройки платежной системы
     * @param array $pay данные о платеже
     * @return array
     */
	public function get($params, $pay)
	{
		$order_id = $params["isTest"] ? "100" :  $pay["id"];
		$baseUrl = $params["isTest"] ? 'https://sandbox-api.express-pay.by/v1/' : 'https://api.express-pay.by/v1/';
		$amount = number_format($pay["summ"], 2, ',', '');

		$request_params = array(
			'ServiceId'         => $params["serviceId"],
			'AccountNo'         => $order_id,
			'Amount'            => $amount,
			'Currency'          => 933,
			'ReturnType'        => 'redirect',
			'ReturnUrl'         => BASE_PATH."/shop/cart/step3/" ,
			'FailUrl'           => BASE_PATH."/shop/cart/step4/",
			'Expiration'        => '',
			'Info'              => $pay['text'],
		);

		$request_params['Signature'] = $this->compute_signature($request_params, $params['secretWord'], $params['token'], 'add_invoice');

		$url = $baseUrl . "web_cardinvoices";

		$response = $this->sendRequestPOST($url, $request_params);

		$response = json_decode($response, true);

		$button         = '<form method="POST" action="'.$url.'">';

        foreach($request_params as $key => $value)
        {
            $button .= "<input type='hidden' name='$key' value='$value'/>";
        }

        $button .= '<input type="submit" class="checkout_button" name="submit_button" value="Оплатить" />';
		$button .= '</form>';

		$result["output"] = $button;

		return $result;
	}

		// Отправка POST запроса
		public function sendRequestPOST($url, $params)
		{
			try{
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
				$response = curl_exec($ch);
				curl_close($ch);
				return $response;
			}catch(Exception $e){
				$this->log_info('receipt_page', 'Get response; ORDER ID - ' . $params['AccountNo'] . '; RESPONSE - ' . $response, $e);
			}
		}

		function compute_signature($request_params, $secret_word, $token, $method = 'add_invoice') {
			$secret_word = trim($secret_word);
			$normalized_params = array_change_key_case($request_params, CASE_LOWER);
			$api_method = array( 
				'add_invoice' => array(
									"serviceid",
									"accountno",
									"expiration",
									"amount",
									"currency",
									"info",
									"returnurl",
									"failurl",
									"language",
									"sessiontimeoutsecs",
									"expirationdate",
									"returntype"),
				'add_invoice_return' => array(
									"accountno"
				)
			);
		
			$result = $token;
		
			foreach ($api_method[$method] as $item)
				$result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';
		
			$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));
		
			return $hash;
		}

    private function log_info($name, $message)
    {
        $this->log($name, "INFO", $message);
    }

    private function log($name, $type, $message)
    {
        $log_url = dirname(__FILE__) . '/log';

        if (!file_exists($log_url)) {
            $is_created = mkdir($log_url, 0777);

            if (!$is_created)
                return;
        }

        $log_url .= '/express-pay-' . date('Y.m.d') . '.log';

        file_put_contents($log_url, $type . " - IP - " . $_SERVER['REMOTE_ADDR'] . "; DATETIME - " . date("Y-m-d H:i:s") . "; USER AGENT - " . $_SERVER['HTTP_USER_AGENT'] . "; FUNCTION - " . $name . "; MESSAGE - " . $message . ';' . PHP_EOL, FILE_APPEND);
	}

}