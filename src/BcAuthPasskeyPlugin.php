<?php
declare(strict_types=1);

namespace BcAuthPasskey;

use BaserCore\BcPlugin;
use BcAuthCommon\Service\AuthEntryService;
use Cake\Core\PluginApplicationInterface;
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

		// ログイン画面への描画は BcAuthCommon がテンプレートを差し替えて行うため、
		// ここでは認証入口（ログインボタン）の登録のみを行う
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
				Log::error('BcAuthPasskey: composer install failed (exit ' . $exitCode . "): \n" . $command . "\n" . implode("\n", $output));
				$errorSummary = $this->summarizeComposerError($output, $exitCode);
				throw new RuntimeException("BcAuthPasskey: composer install failed.\n実行したコマンド: " . $command . "\n\n" . $errorSummary);
			} else {
				Log::info('BcAuthPasskey: composer install completed.');
			}
		}

		return parent::install($options);
	}

	/**
	 * composer の出力から、失敗の原因が分かる部分を取り出す
	 *
	 * 進捗の行は捨て、「Your requirements could not be resolved」や
	 * 「Problem 1」以降の行を優先して返す。全文はログに出す。
	 */
	private function summarizeComposerError(array $output, int $exitCode): string
	{
		$lines = array_values(array_filter(array_map('rtrim', $output), fn($line) => $line !== ''));
		if ($exitCode === 124) {
			array_unshift($lines, 'composer install がタイムアウトしました（300 秒）。');
		}

		$start = null;
		foreach ($lines as $i => $line) {
			if (preg_match('/could not be resolved|Problem \d+|\[[A-Za-z\\\\]+Exception\]|^\s*Error|Fatal error/i', $line)) {
				$start = $i;
				break;
			}
		}
		$lines = $start === null ? array_slice($lines, -30) : array_slice($lines, $start, 40);
		$lines[] = '（全文は logs/error.log に出力しています）';

		return implode("\n", $lines);
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
				. $this->timeoutPrefix(300)
				. escapeshellarg($phpBin)
				. ' ' . escapeshellarg($composerPhar)
				. ' install --no-interaction --no-dev --no-progress --no-ansi --ignore-platform-req=php --ignore-platform-req=ext-xdebug'
				. ' --working-dir=' . escapeshellarg($pluginDir)
				. ' 2>&1 </dev/null';
		}

		$composerBin = $this->resolveComposerBin();
		if (!$composerBin) {
			return null;
		}

		return $envPrefix
			. $this->timeoutPrefix(300)
			. escapeshellarg($composerBin)
			. ' install --no-interaction --no-dev --no-progress --no-ansi --ignore-platform-req=php --ignore-platform-req=ext-xdebug'
			. ' --working-dir=' . escapeshellarg($pluginDir)
			. ' 2>&1 </dev/null';
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
	 *
	 * .env の PHP（例: export PHP="/usr/bin/php85"）を優先する。
	 * Web 経由では PHP_BINARY が php-cgi になる環境があり、
	 * max_execution_time が効いてタイムアウトするため CGI 版は除外する。
	 * php-fpm や、名前に cgi が付かない CGI 版もあるため、
	 * 実際に起動して SAPI が cli のものだけを使う。
	 */
	private function resolvePhpBin(): ?string
	{
		foreach (array_unique([
			(string) env('PHP', ''),
			PHP_BINARY,
			'/usr/local/bin/php',
			'/opt/homebrew/bin/php',
			'/usr/bin/php',
		]) as $path) {
			if (!$path || str_contains(basename($path), 'cgi') || str_contains(basename($path), 'fpm')) {
				continue;
			}
			if (file_exists($path) && is_executable($path) && $this->isCliPhp($path)) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * 指定した php が CLI 版かどうかを確かめる
	 */
	private function isCliPhp(string $path): bool
	{
		$command = $this->timeoutPrefix(10)
			. escapeshellarg($path) . ' -r ' . escapeshellarg('echo PHP_SAPI;')
			. ' 2>/dev/null </dev/null';
		$output = [];
		$exitCode = 1;
		exec($command, $output, $exitCode);

		return $exitCode === 0 && trim(implode('', $output)) === 'cli';
	}

	/**
	 * timeout コマンドがあれば、時限付きで実行するための接頭辞を返す
	 */
	private function timeoutPrefix(int $seconds): string
	{
		foreach (['/usr/bin/timeout', '/bin/timeout', '/usr/local/bin/timeout', '/opt/homebrew/bin/gtimeout'] as $path) {
			if (is_executable($path)) {
				return escapeshellarg($path) . ' ' . $seconds . ' ';
			}
		}

		return '';
	}
}
