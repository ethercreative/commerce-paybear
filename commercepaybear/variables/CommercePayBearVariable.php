<?php

namespace Craft;

class CommercePayBearVariable {

	/**
	 * `{{ craft.commercepaybear.embed({ button: '', redirect: '', modal: true, timer: 15*60 }) }}`
	 *
	 * @param array $config
	 *
	 * @return string
	 * @throws Exception
	 */
	public function embed ($config = [])
	{
		craft()->templates->includeJsResource('commercepaybear/paybear.js');
		craft()->templates->includecssResource('commercepaybear/paybear.css');

		$settingsUrl = UrlHelper::getActionUrl('CommercePayBear/config');

		$config = JsonHelper::encode(array_merge($config, [
			'settingsUrl' => $settingsUrl,
		]));

		$js = <<<js
(function () {
	window.paybear = new Paybear($config);
})();
js;

		craft()->templates->includeJs($js);

		$oldMode = craft()->templates->getTemplateMode();
		craft()->templates->setTemplateMode(TemplateMode::CP);
		$view = craft()->templates->render('commercepaybear/paybear');
		craft()->templates->setTemplateMode($oldMode);

		return new \Twig_Markup(
			$view,
			craft()->templates->getTwig()->getCharset()
		);
	}

}