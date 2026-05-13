<?php
declare(strict_types=1);

namespace BcAuthPasskey;

use BaserCore\BcPlugin;
use BcAuthCommon\Service\AuthEntryService;
use BcAuthPasskey\Event\BcAuthPasskeyViewEventListener;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Log\Log;
use RuntimeException;

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
			$command = $this->buildComposerInstallCommand($pluginDir);
			if (!$command) {
				Log::error('BcAuthPasskey: composer executable was not found.');
				throw new RuntimeException('BcAuthPasskey: composer executable was not found. Install Composer or place composer/composer.phar.');
			}

			Log::info('BcAuthPasskey: Running composer install...');
			$output = [];
			$exitCode = 1;
			exec($command, $output, $exitCode);

			if ($exitCode !== 0) {
				$errorSummary = implode("\n", array_slice($output, 0, 15));
				Log::error('BcAuthPasskey: composer install failed: ' . $errorSummary);
				throw new RuntimeException("BcAuthPasskey: composer install failed.\n" . $errorSummary);
			} else {
				Log::info('BcAuthPasskey: composer install completed.');
			}
		}

		return parent::install($options);
	}

	/**
	 * composer install コマンドを組み立てる
	 */
	private function buildComposerInstallCommand(string $pluginDir): ?string
	{
		$composerPhar = ROOT . DS . 'composer' . DS . 'composer.phar';
		$composerHome = ROOT . DS . 'composer';
		$envPrefix = 'HOME=' . escapeshellarg($composerHome)
			. ' COMPOSER_HOME=' . escapeshellarg($composerHome)
			. ' ';
		$phpBin = $this->resolvePhpBin();
		if (file_exists($composerPhar) && $phpBin) {
			return $envPrefix
				. escapeshellarg($phpBin)
				. ' ' . escapeshellarg($composerPhar)
				. ' install --no-interaction --no-dev --quiet --ignore-platform-req=php --ignore-platform-req=ext-xdebug'
				. ' --working-dir=' . escapeshellarg($pluginDir)
				. ' 2>&1';
		}

		$composerBin = $this->resolveComposerBin();
		if (!$composerBin) {
			return null;
		}

		return $envPrefix
			. escapeshellarg($composerBin)
			. ' install --no-interaction --no-dev --quiet --ignore-platform-req=php --ignore-platform-req=ext-xdebug'
			. ' --working-dir=' . escapeshellarg($pluginDir)
			. ' 2>&1';
	}

	/**
	 * composer 実行ファイルのパスを解決する
	 */
	private function resolveComposerBin(): ?string
	{
		foreach ([
			'/usr/local/bin/composer',
			'/opt/homebrew/bin/composer',
			'/usr/bin/composer',
		] as $path) {
			if (file_exists($path) && is_executable($path)) {
				return $path;
			}
		}

		if (is_executable('/bin/sh')) {
			$path = trim((string) shell_exec('/bin/sh -lc "command -v composer"'));
			if ($path) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * php 実行ファイルのパスを解決する
	 */
	private function resolvePhpBin(): ?string
	{
		foreach ([
			PHP_BINARY,
			'/usr/local/bin/php',
			'/opt/homebrew/bin/php',
			'/usr/bin/php',
		] as $path) {
			if ($path && file_exists($path) && is_executable($path)) {
				return $path;
			}
		}

		return null;
	}
}
