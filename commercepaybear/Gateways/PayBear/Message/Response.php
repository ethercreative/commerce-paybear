<?php

namespace Omnipay\PayBear\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\ResponseInterface;

class Response extends AbstractResponse implements ResponseInterface
{

	/**
	 * Is the response successful?
	 *
	 * @return boolean
	 */
	public function isSuccessful ()
	{
		return true;
	}

}