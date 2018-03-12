<?php

namespace Craft;

/**
 * Class CommercePayBearRecord
 *
 * @property int      $orderId
 * @property string   $currency
 * @property float    $amount
 * @property string   $invoice
 * @property int      $confirmations
 * @property int      $maxConfirmations
 * @property string   $message
 * @property DateTime $timeStarted
 *
 * @package Craft
 */
class CommercePayBearRecord extends BaseRecord
{

	public function getTableName ()
	{
		return 'commerce_paybear';
	}

	protected function defineAttributes ()
	{
		return [
			'orderId'  => [AttributeType::Number, 'required' => true, 'length' => 11],
			'currency' => [AttributeType::String, 'required' => true],
			'amount'   => [AttributeType::Number, 'required' => true, 'length' => 10, 'decimals' => 8],
			'invoice'  => [AttributeType::String, 'required' => true],
			'confirmations' => [AttributeType::Number, 'required' => true, 'default' => 0],
			'maxConfirmations' => [AttributeType::Number, 'required' => true, 'default' => 0],
			'message'  => [AttributeType::Mixed, 'required' => false, 'nullable' => true],
			'timeStarted' => [AttributeType::DateTime, 'required' => true],
		];
	}

	public function defineRelations ()
	{
		return [
			'order' => [
				self::BELONGS_TO,
				'Commerce_OrderRecord',
				'required' => true,
				'onDelete' => self::CASCADE,
			]
		];
	}

}