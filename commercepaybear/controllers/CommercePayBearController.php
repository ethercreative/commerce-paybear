<?php

namespace Craft;

use Commerce\Gateways\PaymentFormModels\BasePaymentFormModel;
use Commerce\Gateways\PaymentFormModels\OffsitePaymentFormModel;

class CommercePayBearController extends BaseController {

	protected $allowAnonymous = true;

	public function actionCurrency ()
	{
		$this->returnJson($this->_getCurrencies());
	}

	/**
	 * Returns the config for the embed
	 */
	public function actionConfig ()
	{
		/** @var Commerce_OrderModel $cart */
		$cart = craft()->commerce_cart->getCart();

		$this->returnJson([
			'currencies' => array_values($this->_getCurrencies()),
			'fiatValue' => $cart->totalPrice,
			'fiatCurrency' => $cart->paymentCurrency,
			'fiatSign' => '',

			'statusUrl' => UrlHelper::getActionUrl('CommercePayBear/status', [
				'orderId' => $cart->id,
			]),
		]);
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
		$code = strtolower(craft()->request->getRequiredParam('code'));

		// Calculate the amount
		$curr = $this->_getCurrencies()[$code];
		// TODO: Convert amount to USD
		$amount = $cart->totalPrice / (float) $curr['mid'];

		// Create the payment URL
		$url = sprintf(
			'https://api.paybear.io/v2/%s/payment/%s?token=%s',
			$code,
			urlencode($callbackUrl),
			$this->_getPaymentMethod()->settings['apiSecretKey']
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
		HeaderHelper::setContentTypeByExtension('txt');

		$orderId = craft()->request->getRequiredParam('orderId');
		$data = file_get_contents('php://input');

		if (!$data) die();

		$data = json_decode($data);
		$invoice = $data->invoice;

		/** @var Commerce_OrderModel $order */
		$order = craft()->commerce_orders->getOrderById($orderId);

		// Ensure we have an order
		if (!$order) {
			CommercePayBearPlugin::log(
				'Unable to find order with ID: ' . $orderId,
				LogLevel::Error,
				true
			);
			die('Unable to find order');
		}

		/** @var CommercePayBearRecord $record */
		$record = CommercePayBearRecord::model()->findByAttributes([
			'orderId' => $orderId,
			'invoice' => $invoice,
		]);

		// Ensure we have a payment record
		if (!$record) {
			CommercePayBearPlugin::log(
				'Attempted to run callback on non-existent payment for Order: '
				. $orderId . ', Invoice: ' . $invoice,
				LogLevel::Warning,
				true
			);
			die('Attempted to run callback on non-existent payment');
		}

		// Get the confirmations & amount paid
		$confirmations = (int) $data->confirmations;
		$exp = pow(10, $data->inTransaction->exp);
		$amount = $data->inTransaction->amount / $exp;

		// Update the number of confirmations
		$record->confirmations = $confirmations;

		// If the amount paid doesn't match the amount required, error
//		if ($record->amount < $amount) {
//			$record->message = [
//				'type' => 'error',
//				'message' => 'Amount (' . $amount . ') paid does not match amount required for order ' . $orderId . ' (' . $record->amount . ')',
//			];
//			$record->save();
//
//			CommercePayBearPlugin::log(
//				$record->message['message'],
//				LogLevel::Error,
//				true
//			);
//
//			die('Amount paid does not match amount required for order');
//		}

		// If the confirmations are below the required amount, wait
		if ($record->confirmations < $record->maxConfirmations) {
			$record->message = [
				'type' => 'info',
				'message' => 'Waiting for confirmations',
			];
			$record->save();

			die('waiting for confirmations');
		}

		$record->message = [
			'type' => 'success',
			'message' => 'Payment successful',
		];
		$record->save();

		// Create transaction & complete order
		$paymentMethod = $this->_getPaymentMethod();
		craft()->commerce_cart->setPaymentMethod($order, $paymentMethod->id);

		/** @var BasePaymentFormModel $paymentForm */
		$paymentForm = new OffsitePaymentFormModel();
		$paymentForm->populateModelFromPost($order->getContent());

		if (craft()->commerce_payments->processPayment($order, $paymentForm)) {
			craft()->commerce_orders->completeOrder($order);
			die($invoice);
		} else {
			CommercePayBearPlugin::log(
				print_r($paymentForm->getErrors(), true),
				LogLevel::Error,
				true
			);
		}

		die('Something\'s gone wrong!');
	}

	/**
	 * Retrieves the payment record for the given order from the database.
	 * Used for displaying the confirmation progress of the order to the user.
	 *
	 * @throws HttpException
	 */
	public function actionStatus ()
	{
		$orderId = craft()->request->getRequiredParam('orderId');

		/** @var CommercePayBearRecord $record */
		$record = CommercePayBearRecord::model()->findByAttributes([
			'orderId' => $orderId,
		]);

		$confirmations = $record->confirmations;
		$max = $record->maxConfirmations;

		$return = [
			'success' => $confirmations >= $max,
		];

		if ($confirmations > 0 || $record->message != null)
			$return['confirmations'] = $confirmations;

		$this->returnJson($return);
	}

	// Private
	// =========================================================================

	/**
	 * @return array
	 */
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
				$this->_getPaymentMethod()->settings['apiSecretKey']
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
			$ret[$key]['coinsValue'] = $cart->totalPrice / (float) $rates[$key]['mid'];
			$ret[$key]['currencyUrl'] = UrlHelper::getActionUrl(
				'CommercePayBear/payment',
				[
					'orderId' => $cart->id,
					'code' => $key,
				]
			);
		}

		return $ret;
	}

	/**
	 * Get's the PayBear payment method
	 *
	 * @return Commerce_PaymentMethodModel
	 */
	private function _getPaymentMethod ()
	{
		$id = craft()->config->get('paymentMethodId', 'commercepaybear');
		return craft()->commerce_paymentMethods->getPaymentMethodById($id);
	}

}