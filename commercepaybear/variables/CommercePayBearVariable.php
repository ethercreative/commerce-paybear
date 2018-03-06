<?php

namespace Craft;

class CommercePayBearVariable {

	/**
	 * `{% includejsfile craft.commercepaybear.js() %}`
	 *
	 * @return string
	 */
	public function js ()
	{
		return UrlHelper::getResourceUrl('commercepaybear/PayBear.js');
	}

	/**
	 * `{% includecssfile craft.commercepaybear.css() %}`
	 *
	 * @return string
	 */
	public function css ()
	{
		return UrlHelper::getResourceUrl('commercepaybear/PayBear.css');
	}

	public function actionTrigger ()
	{
		return UrlHelper::getActionUrl();
	}

}