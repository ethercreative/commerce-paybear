<?php

namespace Omnipay\PayBear;

use Omnipay\Common\AbstractGateway;

class Gateway extends AbstractGateway
{

	public function getName ()
	{
		return 'PayBear';
	}

	public function getDefaultParameters ()
	{
		return [
			'apiSecretKey' => '',
		];
	}

	public function getApiSecretKey ()
	{
		return $this->getParameter('apiSecretKey');
	}

	public function setApiSecretKey ($value)
	{
		return $this->setParameter('apiSecretKey', $value);
	}

	public function purchase (array $parameters = [])
	{
		return $this->createRequest(
			"\Omnipay\PayBear\Message\PurchaseRequest",
			$parameters
		);
	}

	public function completePurchase (array $parameters = [])
	{
		return $this->createRequest(
			"\Omnipay\PayBear\Message\CompletePurchaseRequest",
			$parameters
		);
	}

}