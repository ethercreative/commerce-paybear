<?php

namespace Craft;

class CommercePayBearController extends BaseController {

	protected $allowAnonymous = true;

	public function actionCurrency ()
	{
		$this->returnJson($this->_getCurrencies());
	}

	public function actionPayment ()
	{
		/** @var Commerce_OrderModel $cart */
		$cart = craft()->commerce_cart->getCart();

		$callbackUrl = UrlHelper::getActionUrl(
			'CommercePayBear/callback',
			[ 'orderId' => $cart->id ]
		);

		$code = strtolower(craft()->request->getPost('code'));

		$url = sprintf(
			'https://api.paybear.io/v2/%s/payment/%s?token=%s',
			$code,
			urlencode($callbackUrl),
			craft()->config->get('apiKey', 'commercepaybear')
		);

		if ($response = file_get_contents($url)) {
			$response = json_decode($response, true);

			// TODO: Error handling

			$data = $response['data'];

			// TODO: Store $data['invoice'] against this order

			$this->returnJson([
				'address' => $data['address'],
				'currency' => $this->_getCurrencies()[$code],
			]);
		}
	}

	public function actionCallback ()
	{
		$orderId = craft()->request->getParam('orderId');
	}

	// Private
	// =========================================================================

	private function _getCurrencies ()
	{
		static $currencies = false;
		static $rates = false;

		/** @var Commerce_OrderModel $cart */
		$cart = craft()->commerce_cart->getCart();

		if (!$rates) {
			$url = sprintf(
				'https://api.paybear.io/v2/exchange/%s/rate',
				$cart->paymentCurrency
			);

			if ($response = @file_get_contents($url)) {
				$response = json_decode($response, true);
				if (isset($response) && $response['success'])
					$rates = $response['data'];
			}
		}

		if (!$currencies) {
			$url = sprintf(
				'https://api.paybear.io/v2/currencies?token=%s',
				craft()->config->get('apiKey', 'commercepaybear')
			);

			if ($response = @file_get_contents($url)) {
				$response = json_decode($response, true);
				if (isset($response) && $response['success'])
					$currencies = $response['data'];
			}
		}

		$ret = [];

		foreach ($currencies as $key => $value) {
			$ret[$key] = $value;
			$ret[$key]['mid'] = $rates[$key]['mid'];
		}

		return $ret;
	}

}