<?php

namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\OffsiteGatewayAdapter;

/**
 * @method  protected
 */
class PayBear_GatewayAdapter extends OffsiteGatewayAdapter {

	public function handle()
	{
		return "PayBear";
	}

}