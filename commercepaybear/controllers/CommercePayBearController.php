<?php

namespace Craft;

class CommercePayBearController extends BaseController {

	protected $allowAnonymous = true;

	public function actionCurrency ()
	{
		$this->returnJson($this->_getCurrencies());
	}

	/**
	 * @throws HttpException
	 */
	public function actionPayment ()
	{
		/** @var Commerce_OrderModel $cart */
		$cart = craft()->commerce_cart->getCart();

		// Create the callback URL
		$callbackUrl = UrlHelper::getActionUrl(
			'CommercePayBear/callback',
			[ 'orderId' => $cart->id ]
		);

		// Get the currency code
		$code = strtolower(craft()->request->getRequiredPost('code'));

		// Calculate the amount
		$curr = $this->_getCurrencies()[$code];
		// TODO: Convert amount to USD
		$amount = $cart->totalPrice / (float) $curr['mid'];

		// Create the payment URL
		$url = sprintf(
			'https://api.paybear.io/v2/%s/payment/%s?token=%s',
			$code,
			urlencode($callbackUrl),
			craft()->config->get('apiKey', 'commercepaybear')
		);

		// Request the payment URL
		if ($response = file_get_contents($url)) {
			$response = json_decode($response, true);

			// TODO: Error handling

			$data = $response['data'];

			// Check if we already have a crypto payment for this payment
			$record = CommercePayBearRecord::model()->findByAttributes([
				'orderId' => $cart->id,
			]);

			// Or create a new record for this payment
			if (!$record)
				$record = new CommercePayBearRecord();

			$record->orderId = $cart->id;
			$record->currency = $code;
			$record->amount = $amount;
			$record->invoice = $data['invoice'];
			$record->confirmations = 0;
			$record->maxConfirmations = $curr['maxConfirmations'];
			$record->timeStarted = new DateTime();

			if ($record->save()) {
				$this->returnJson([
					'address' => $data['address'],
					'currency' => $curr,
				]);
			} else {
				die(var_dump($record->getErrors()));
			}
		}
	}

	/**
	 * @throws HttpException
	 */
	public function actionCallback ()
	{
		$orderId = craft()->request->getRequiredParam('orderId');
		$data = file_get_contents('php://input');

		/** @var Commerce_OrderModel $order */
		$order = craft()->commerce_orders->getOrderById($orderId);

		// Ensure we have an order
		if (!$order) {
			CommercePayBearPlugin::log(
				'Unable to find order with ID: ' . $orderId,
				LogLevel::Error,
				true
			);
			return;
		}

		/** @var CommercePayBearRecord $record */
		$record = CommercePayBearRecord::model()->findByAttributes([
			'orderId' => $orderId,
			'invoice' => $data['invoice'],
		]);

		// Ensure we have a payment record
		if (!$record) {
			CommercePayBearPlugin::log(
				'Attempted to run callback on non-existent payment for Order: '
				. $orderId . ', Invoice: ' . $data['invoice'],
				LogLevel::Warning,
				true
			);
			return;
		}

		// Get the confirmations & amount paid
		$confirmations = (int) $data['confirmations'];
		$amount = (float) $data['inTransaction']['amount'];

		// If this is the first confirmation...
		if ($record->confirmations == 0) {
			$startTime = $record->timeStarted;
			$timeSincePossibleStart = (new DateTime())->modify('-15 minutes');

			// If the start timestamp is less than the possible start timestamp
			if ($startTime->getTimestamp() < $timeSincePossibleStart->getTimestamp()) {
				// TODO: Should we refund anything that had been paid? How?
				$record->message = [
					'type' => 'error',
					'message' => 'This payment has expired',
				];
				$record->save();
				return;
			}
		}

		// Update the number of confirmations
		$record->confirmations = $confirmations;

		// If the amount paid doesn't match the amount required, error
		if ($record->amount != $amount) {
			$record->message = [
				'type' => 'error',
				'message' => 'Amount paid does not match amount required for order ' . $orderId,
			];
			$record->save();

			CommercePayBearPlugin::log(
				$record->message['message'],
				LogLevel::Error,
				true
			);
			return;
		}

		// If the confirmations are below the required amount, wait
		if ($record->confirmations < $record->maxConfirmations) {
			$record->message = [
				'type' => 'info',
				'message' => 'Waiting for confirmations',
			];
			$record->save();
			return;
		}

		$record->message = [
			'type' => 'success',
			'message' => 'Payment successful',
		];
		$record->save();

		// TODO: Add transaction to order

		craft()->commerce_orders->completeOrder($order);
	}

	/**
	 * Retrieves the payment record for the given order from the database.
	 * Used for displaying the confirmation progress of the order to the user.
	 *
	 * @throws HttpException
	 */
	public function actionGetPayment ()
	{
		$orderId = craft()->request->getRequiredParam('orderId');
		/** @var Commerce_OrderModel $order */
		$order = craft()->commerce_orders->getOrderById($orderId);

		if (!$order) {
			$this->returnErrorJson('Unable to find order with ID: ' . $orderId);
			return;
		}

		/** @var CommercePayBearRecord $record */
		$record = CommercePayBearRecord::model()->findByAttributes([
			'orderId' => $orderId,
		]);

		if ($record) {
			$this->returnErrorJson('Unable to find payment record for order with ID: ' . $orderId);
			return;
		}

		$this->returnJson([
			'confirmations' => $record->confirmations,
			'maxConfirmations' => $record->maxConfirmations,
			'message' => $record->message,
		]);
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