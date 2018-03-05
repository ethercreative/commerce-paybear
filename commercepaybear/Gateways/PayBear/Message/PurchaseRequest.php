<?php

namespace Omnipay\PayBear\Message;

use Craft\Commerce_OrderModel;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

class PurchaseRequest extends AbstractRequest
{

	// Getters & Setters
	// =========================================================================

	// Getters & Setters: API Key
	// -------------------------------------------------------------------------

	public function getApiSecretKey ()
	{
		return $this->getParameter('apiSecretKey');
	}

	public function setApiSecretKey ($value)
	{
		return $this->setParameter('apiSecretKey', $value);
	}

	// Getters & Setters: Currency
	// -------------------------------------------------------------------------

	public function getCurrency ()
	{
		return $this->getParameter('currency');
	}

	public function setCurrency ($value)
	{
		return $this->setParameter('currency', $value);
	}

	// Getters & Setters: Order
	// -------------------------------------------------------------------------

	/**
	 * @return Commerce_OrderModel
	 */
	public function getOrder ()
	{
		return $this->getParameter("order");
	}

	public function setOrder ($value)
	{
		return $this->setParameter("order", $value);
	}

	// Data
	// =========================================================================

	/**
	 * Get the raw data array for this message. The format of this varies from
	 * gateway to gateway, but will usually be either an associative array, or
	 * a SimpleXMLElement.
	 *
	 * @return mixed
	 * @throws \Omnipay\Common\Exception\InvalidRequestException
	 */
	public function getData ()
	{
		$this->validate("amount", "order");

		return [];
	}

	/**
	 * Send the request with specified data
	 *
	 * @param  mixed $data The data to send
	 *
	 * @return ResponseInterface
	 */
	public function sendData ($data)
	{
		$token  = $this->getApiSecretKey();
		$crypto = $this->getCurrency();
		$notify = urlencode($this->getNotifyUrl());

		$endPoint = "https://api.paybear.io/v2/{$crypto}/payment/{$notify}?token={$token}";

		return $this->response = new PurchaseResponse(
			$this,
			$data,
			$endPoint
		);
	}

}