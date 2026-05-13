# BcAuthPasskey plugin for baserCMS

BcAuthPasskey は、baserCMS 5 のログインに WebAuthn / Passkey ベースの認証を追加するためのプラグインです。

スマートフォンの指紋認証や顔認証は、端末に保存されたパスキーを通じて利用する想定です。
アプリケーションが生体情報そのものを扱うわけではなく、WebAuthn の検証結果を使ってログインを成立させます。

このプラグイン単体では動作しません。事前に BcAuthCommon の導入が必要です。

## 目的

- 管理画面ログインにパスキー認証を追加する
- Front プレフィックスのログインにも展開できる構成にする
- baserCMS の既存ログイン機構と共存させる

## 機能

- パスキーでログイン
- ログイン済みユーザーによるパスキー登録
- 登録済みパスキーの一覧表示と削除

## 前提

- BcAuthCommon が有効化されていること
- WebAuthn 対応ブラウザと HTTPS 環境で運用すること

## インストール補足（依存導入に失敗した場合）

管理画面からのプラグインインストール時に依存ライブラリの導入で失敗した場合は、手動で composer install を実行してください。

ローカル環境で実行する場合:

	cd plugins/BcAuthPasskey
	composer install

Docker 環境で実行する場合（baserCMS 開発環境の例）:

	docker exec bc-php php /var/www/html/composer/composer.phar install --working-dir=/var/www/html/plugins/BcAuthPasskey --no-interaction --no-dev --ignore-platform-req=php --ignore-platform-req=ext-xdebug

## 詳細ドキュメント

- 詳細設計: [docs/passkey-auth-design.md](docs/passkey-auth-design.md)
- 認証プラグイン全体整理: [../BcAuthCommon/docs/auth-plugin-spec-summary.md](../BcAuthCommon/docs/auth-plugin-spec-summary.md)

## よく参照する実装ファイル（入口）

- [src/Controller/Admin/BcAuthPasskeysController.php](src/Controller/Admin/BcAuthPasskeysController.php)
- [src/Controller/BcAuthPasskeysController.php](src/Controller/BcAuthPasskeysController.php)
- [src/Service/BcAuthPasskeyService.php](src/Service/BcAuthPasskeyService.php)
- [src/Model/Table/BcAuthPasskeyCredentialsTable.php](src/Model/Table/BcAuthPasskeyCredentialsTable.php)
- [config/Migrations](config/Migrations)

## 開発メモ

- 認証方式固有の検証は BcAuthPasskey 側に実装する
- ログイン完了処理と監査ログ共通化は BcAuthCommon 側に寄せる
- 認証履歴の auth_source は passkey を利用する

## 関連プラグイン

- [../BcAuthCommon/README.md](../BcAuthCommon/README.md)
- [../BcAuthPasskey/README.md](../BcAuthPasskey/README.md)
- [../BcAuthSocial/README.md](../BcAuthSocial/README.md)
- [../BcAuthGuard/README.md](../BcAuthGuard/README.md)

## ライセンス

MIT License.

詳細は [LICENSE.md](LICENSE.md) を参照してください。
