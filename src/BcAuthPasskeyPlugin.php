<?php
declare(strict_types=1);

namespace BcAuthPasskey;

use BaserCore\BcPlugin;
use BcAuthCommon\Service\AuthEntryService;
use BcAuthPasskey\Event\BcAuthPasskeyViewEventListener;
use Cake\Event\EventManager;
use Cake\Core\PluginApplicationInterface;

/**
 * plugin for BcAuthPasskey
 */
class BcAuthPasskeyPlugin extends BcPlugin
{
	public function bootstrap(PluginApplicationInterface $app): void
	{
		parent::bootstrap($app);

		EventManager::instance()->on(new BcAuthPasskeyViewEventListener());

		AuthEntryService::getInstance()->register([
			'id'       => 'passkey',
			'label'    => 'パスキーでログイン',
			'element'  => 'BcAuthPasskey.passkey_login_button',
			'prefixes' => ['Admin', 'Front'],
			'order'    => 10,
		]);
	}
}
