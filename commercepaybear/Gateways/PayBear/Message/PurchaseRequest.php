<?php

namespace Omnipay\PayBear\Message;

use Craft\Commerce_OrderModel;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

class PurchaseRequest extends AbstractRequest
{

	// Getters & Setters
	// =========================================================================

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
		return $this->response = new Response($this, null);
	}

}