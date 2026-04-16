<?php
declare(strict_types=1);

namespace BcAuthPasskey;

use BaserCore\BcPlugin;
use BcAuthCommon\Service\AuthEntryService;
use BcAuthPasskey\Event\BcAuthPasskeyViewEventListener;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Log\Log;

/**
 * plugin for BcAuthPasskey
 */
class BcAuthPasskeyPlugin extends BcPlugin
{
	public function bootstrap(PluginApplicationInterface $app): void
	{
		parent::bootstrap($app);

		$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
		if (file_exists($autoloadPath)) {
			require_once $autoloadPath;
		}

		if (!class_exists(\Webauthn\PublicKeyCredentialCreationOptions::class)) {
			Log::warning('BcAuthPasskey: WebAuthn library is not installed. Run composer install in plugins/BcAuthPasskey.');
			return;
		}

		EventManager::instance()->on(new BcAuthPasskeyViewEventListener());

		AuthEntryService::getInstance()->register([
			'id'       => 'passkey',
			'label'    => 'パスキーでログイン',
			'element'  => 'BcAuthPasskey.passkey_login_button',
			'prefixes' => ['Admin', 'Front'],
			'order'    => 10,
		]);
	}

	/**
	 * プラグインインストール時に composer install を実行する
	 */
	public function install($options = []): bool
	{
		$pluginDir = dirname(__DIR__);
		$vendorAutoload = $pluginDir . '/vendor/autoload.php';

		if (!file_exists($vendorAutoload)) {
			$composerBin = $this->resolveComposerBin();
			$command = escapeshellarg($composerBin)
				. ' install --no-interaction --no-dev --quiet'
				. ' --working-dir=' . escapeshellarg($pluginDir)
				. ' 2>&1';

			Log::info('BcAuthPasskey: Running composer install...');
			exec($command, $output, $exitCode);

			if ($exitCode !== 0) {
				Log::error('BcAuthPasskey: composer install failed: ' . implode("\n", $output));
				// 失敗してもインストール自体は続行する（手動対応可能）
			} else {
				Log::info('BcAuthPasskey: composer install completed.');
			}
		}

		return parent::install($options);
	}

	/**
	 * composer 実行ファイルのパスを解決する
	 */
	private function resolveComposerBin(): string
	{
		// プロジェクト root に composer.phar があればそちらを優先
		$projectRoot = dirname(__DIR__, 2);
		foreach ([
			$projectRoot . '/composer.phar',
			'/usr/local/bin/composer',
			'/usr/bin/composer',
		] as $path) {
			if (file_exists($path) && is_executable($path)) {
				return $path;
			}
		}

		// PATH に composer があることを期待
		return 'composer';
	}
}
