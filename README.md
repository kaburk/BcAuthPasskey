# BcPasskeyAuth plugin for baserCMS

BcPasskeyAuth は、baserCMS 5 のログインに WebAuthn / Passkey ベースの認証を追加するためのプラグインです。

スマートフォンの指紋認証や顔認証は、端末に保存されたパスキーを通じて利用する想定です。
アプリケーションが生体情報そのものを扱うわけではなく、WebAuthn の検証結果を使ってログインを成立させます。

## 目的

- 管理画面ログインにパスキー認証を追加する
- Front プレフィックスのログインにも展開できる構成にする
- baserCMS の既存ログイン機構と共存させる
- 将来的に SNS ログインなど別方式の認証も追加しやすい構成にする

## 想定機能

- パスキーでログイン
- ログイン済みユーザーによるパスキー登録
- 登録済みパスキーの一覧表示と削除
- Discoverable Credential を前提としたログイン体験
- 将来的な Google や Apple などの外部認証追加を妨げない設計

## 方針

- baserCMS コア改修を避け、プラグインとして実装する
- 認証成功後は既存の UsersService を用いてログイン状態を確立する
- 初期段階では Admin ログインを優先し、その後 Front に展開する
- ログイン画面への組み込みは初期段階では template override を採用する
- パスキーは専用プラグインとして進め、SNS ログインは BcSocialAuth などの別プラグインで分離する前提を基本とする

## ドキュメント

詳細設計は docs/passkey-auth-design.md を参照してください。

横断整理は ../BcAuthCommon/docs/auth-plugin-spec-summary.md を参照してください。

特に次の観点を設計書に含めています。

- スマートフォン生体認証との関係
- 将来の外部認証拡張方針
- 認証プラグインの分割方針

## 実装ステータス

フェーズ 1 を中心に実装済みです。

- 初回 install 時の migration 自動実行前提で動作
- Admin ログインの passkey challenge / assertion 検証
- Admin ログイン済みユーザーによるパスキー登録、一覧、削除
- `web-auth/webauthn-lib` を用いた registration / assertion 検証
- `BcAuthCommon` の `AuthLoginService` を経由したログイン完了
- Front ログイン用 challenge / assertion エンドポイントとログイン画面 override
- `bc-admin-third` / `bc-front` へのログイン画面組み込み

### 作成済みファイル

| ファイル | 概要 |
|---|---|
| `config.php` | プラグインディスクリプタ |
| `src/BcPasskeyAuthPlugin.php` | プラグインクラス |
| `config/Migrations/20260409000001_CreatePasskeyCredentials.php` | マイグレーション |
| `src/Model/Entity/PasskeyCredential.php` | エンティティ |
| `src/Model/Table/PasskeyCredentialsTable.php` | テーブルクラス |
| `src/Service/PasskeyAuthServiceInterface.php` | サービスインターフェース |
| `src/Service/PasskeyAuthService.php` | WebAuthn challenge / assertion / attestation のサービス実装 |
| `src/Controller/Admin/PasskeysController.php` | Admin コントローラー（6 アクション） |
| `src/Controller/PasskeysController.php` | Front ログイン用コントローラー |
| `templates/Admin/Passkeys/index.php` | パスキー管理画面 |
| `templates/plugin/BcAdminThird/Admin/Users/login.php` | Admin ログイン画面オーバーライド |
| `templates/plugin/BcFront/Users/login.php` | Front ログイン画面オーバーライド |
| `config/routes.php` | ルート定義 |
| `webroot/js/passkey-auth.js` | WebAuthn フロントエンド JS |

### 残タスク

1. Docker 上での end-to-end 動作確認と登録導線の確認
2. install 後にパスキー登録へ進む導線の補強
3. Front 側の実運用画面に合わせたデザイン調整
4. 監査ログや認証入口定義の共通化要否の判断
