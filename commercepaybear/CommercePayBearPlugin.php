<?php

namespace Craft;

class CommercePayBearPlugin extends BasePlugin
{

	public function getName ()
	{
		return 'Commerce - PayBear.io';
	}

	public function getDescription ()
	{
		return 'PayBear.io integration with Craft Commerce.';
	}

	public function getVersion ()
	{
		return '0.0.1';
	}

	public function getSchemaVersion ()
	{
		return '0.0.1';
	}

	public function getDeveloper ()
	{
		return 'Ether Creative';
	}

	public function getDeveloperUrl ()
	{
		return 'https://ethercreative.com';
	}

	public function init ()
	{
		require __DIR__ . '/vendor/autoload.php';
	}

	public function commerce_registerGatewayAdapters ()
	{
		require_once __DIR__ . '/PayBear_GatewayAdapter.php';
		return ['\Commerce\Gateways\Omnipay\PayBear_GatewayAdapter'];
	}

}