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
| `config/Migrations/20260409000001_CreatePasskeyCredentials.php` | マイグレーション |
| `src/BcAuthPasskeyPlugin.php` | プラグインクラス |
| `src/Model/Entity/PasskeyCredential.php` | エンティティ |
| `src/Model/Table/PasskeyCredentialsTable.php` | テーブルクラス |
| `src/Service/PasskeyAuthServiceInterface.php` | サービスインターフェース |
| `src/Service/PasskeyAuthService.php` | WebAuthn challenge / assertion / attestation のサービス実装 |
| `src/Controller/Admin/PasskeysController.php` | Admin コントローラー（login_challenge / login / register_challenge / register / index / delete） |
| `src/Controller/PasskeysController.php` | Front ログイン用コントローラー（login_challenge / login） |
| `templates/Admin/Passkeys/index.php` | パスキー管理画面 |
| `templates/plugin/BcAdminThird/Admin/Users/login.php` | Admin ログイン画面 override（パスキーボタン＋BcAuthSocial ボタン統合） |
| `templates/plugin/BcFront/Users/login.php` | Front ログイン画面 override（パスキーボタン） |
| `config/routes.php` | Admin / Front ルート定義 |
| `webroot/js/bc_auth_passkey.js` | Base64URL 変換・login / register フロー（WebAuthn フロントエンド実装済み） |

> **注意**: Admin ログイン画面の template override は、BcAuthSocial が同時にインストールされている場合にソーシャルログインボタンも自動表示する統合テンプレートになっています。BcAuthSocial を単体でインストールする場合は BcAuthSocial 側の独自 override が別途必要です（残タスク参照）。

### 残タスク

1. Docker 上での end-to-end 動作確認（Admin install → パスキー登録 → パスキーログインの一気通貫確認）
2. install 後にパスキー登録へ進む導線の補強（install 完了画面への案内追加）
3. Front 側の実運用画面に合わせたデザイン調整（`data-challenge-url` / `data-login-url` の URL 確認も含む）
4. 監査ログや認証入口定義の共通化要否の判断（BcAuthCommon 側での対応）
