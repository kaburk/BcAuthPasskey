# BcAuthPasskey plugin for baserCMS

BcAuthPasskey は、baserCMS 5 のログインに WebAuthn / Passkey ベースの認証を追加するためのプラグインです。

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
- パスキーは専用プラグインとして進め、SNS ログインは BcAuthSocial などの別プラグインで分離する前提を基本とする

## ドキュメント

詳細設計は docs/passkey-auth-design.md を参照してください。

横断整理は ../BcAuthCommon/docs/auth-plugin-spec-summary.md を参照してください。

特に次の観点を設計書に含めています。

- スマートフォン生体認証との関係
- 将来の外部認証拡張方針
- 認証プラグインの分割方針

## 実装ステータス

フェーズ 1 実装済みです（Admin / Front 両対応）。

### 作成済みファイル

| ファイル | 概要 |
|---|---|
| `config.php` | adminLink / installMessage 設定 |
| `config/routes.php` | Admin / Front ルート定義 |
| `config/setting.php` | 管理ナビメニュー登録 |
| `config/Migrations/20260409000001_CreateBcAuthPasskeyCredentials.php` | マイグレーション（テーブル名: `bc_auth_passkey_credentials`） |
| `src/BcAuthPasskeyPlugin.php` | プラグインクラス |
| `src/Model/Entity/BcAuthPasskeyCredential.php` | エンティティ |
| `src/Model/Table/BcAuthPasskeyCredentialsTable.php` | テーブルクラス |
| `src/Service/BcAuthPasskeyServiceInterface.php` | サービスインターフェース |
| `src/Service/BcAuthPasskeyService.php` | WebAuthn challenge / assertion / attestation のサービス実装 |
| `src/Event/BcAuthPasskeyViewEventListener.php` | View イベントリスナー |
| `src/Controller/Admin/BcAuthPasskeysController.php` | Admin コントローラー（login_challenge / login / register_challenge / register / index / delete） |
| `src/Controller/BcAuthPasskeysController.php` | Front ログイン用コントローラー（login_challenge / login） |
| `templates/Admin/BcAuthPasskeys/index.php` | パスキー管理画面 |
| `templates/element/passkey_login_button.php` | ログインボタン element（AuthEntryService 経由で呼ばれる） |
| `templates/plugin/BcAdminThird/Admin/Users/login.php` | Admin ログイン画面 override（AuthEntryService でボタン群を描画） |
| `templates/plugin/BcFront/Users/login.php` | Front ログイン画面 override（AuthEntryService でボタン群を描画） |
| `webroot/js/bc_auth_passkey.js` | Base64URL 変換・login / register フロー（WebAuthn フロントエンド実装済み） |
| `webroot/css/admin/bc_auth_passkey_admin.css` | パスキー管理画面スタイル |

## DB テーブル

| テーブル | 用途 |
|---|---|
| `bc_auth_passkey_credentials` | ユーザーごとのパスキー資格情報（credential_id・公開鍵・counter など） |

## ルーティング

| URL | 用途 |
|---|---|
| `GET /baser/admin/bc-auth-passkey/passkeys` | パスキー管理画面 |
| `GET /baser/admin/bc-auth-passkey/passkeys/login_challenge` | ログイン challenge 発行 |
| `POST /baser/admin/bc-auth-passkey/passkeys/login` | assertion 検証・ログイン完了 |
| `GET /baser/admin/bc-auth-passkey/passkeys/register_challenge` | 登録 challenge 発行 |
| `POST /baser/admin/bc-auth-passkey/passkeys/register` | attestation 検証・登録完了 |
| `POST /baser/admin/bc-auth-passkey/passkeys/delete/{id}` | パスキー削除 |
| `GET /bc-auth-passkey/passkeys/login_challenge` | Front ログイン challenge 発行 |
| `POST /bc-auth-passkey/passkeys/login` | Front assertion 検証・ログイン完了 |

## 残タスク

- **Front 側デザイン調整**: 実運用テーマ確定後に `templates/plugin/BcFront/Users/login.php` の見た目を調整する（機能自体は実装済み）
