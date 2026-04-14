# BcAuthPasskey 設計書

## 概要

BcAuthPasskey は、baserCMS 5 のログインに WebAuthn / Passkey ベースの認証を追加するためのプラグインです。

初期ターゲットは次の 2 系統です。

- 管理画面ログイン
- マイページなど Front プレフィックスのログイン

本プラグインは、既存のユーザー管理やセッション認証を置き換えるのではなく、baserCMS が持つ既存の認証基盤に対してパスキー認証の入口を追加し、認証成功後は既存のログイン処理に接続する構成を採用します。

## 目的

- 管理画面ログインにパスキー認証を導入できるようにする
- Front プレフィックスのログインにも同じ仕組みを転用できるようにする
- コア改修を極力避け、プラグインとして独立して導入できる構成とする
- ユーザーごとに複数のパスキーを登録できるようにする
- 段階的導入ができるよう、パスワード認証との共存を前提とする
- 将来的に SNS ログインなど別方式の認証も横並びで追加できる設計にする

## 非目的

- baserCMS コアの認証方式そのものを全面的に置き換えること
- Admin API の JWT 認証を初期段階でパスキー化すること
- 1 回目の実装で完全なアカウントレス運用や高度なポリシー管理まで含めること

## 想定ユースケース

### 1. 管理画面ログイン

管理画面ログインページに パスキーでログイン ボタンを追加し、ユーザーが登録済みパスキーを用いてログインできるようにする。

### 2. マイページログイン

Front プレフィックスで運用している会員向けログインページにも同じ UI と API を提供し、会員認証にパスキーを利用できるようにする。

### 3. パスキー登録

通常ログイン後、ユーザー自身がアカウント設定画面などからパスキーを登録できるようにする。

### 4. スマートフォンの生体認証を利用したログイン

ユーザーが iPhone や Android 端末に保存したパスキーを使い、Face ID、Touch ID、指紋認証、顔認証、端末 PIN などのローカル認証を通じてログインできるようにする。

### 5. 将来の外部認証追加

Google、Apple、LINE、GitHub などの外部 IdP を利用したログイン認証を、別プラグインまたは共通認証基盤の追加実装として組み込めるようにする。

## 採用技術

- WebAuthn Level 2
- Passkey 対応ブラウザの PublicKeyCredential API
- PHP ライブラリ: web-auth/webauthn-lib
- baserCMS 既存の UsersService によるセッション確立

## スマートフォン生体認証との関係

本プラグインが直接 指紋情報や顔画像を取得することはありません。

パスキー認証では、端末や OS が提供する認証器がローカルで本人確認を行い、その結果として WebAuthn の署名結果が返されます。

そのため、次の利用形態を想定できます。

- iPhone の Face ID や Touch ID を利用した認証
- Android の指紋認証や顔認証を利用した認証
- Mac や Windows の生体認証、または端末 PIN を利用した認証
- 同一プラットフォーム内のパスキー同期、または QR 経由のクロスデバイス認証

アプリケーションが扱うのは、あくまで WebAuthn の公開鍵資格情報と検証結果です。

## 認証方式の拡張方針

本設計は、パスキーだけを特別扱いするのではなく、将来的に複数の認証方式を並列で提供できる構造を目指します。

初期段階では BcAuthPasskey を単独プラグインとして進めますが、内部設計は次のような拡張を意識します。

- ログイン画面に複数の認証入口を並べられる
- 認証方式ごとに独立した challenge / redirect / callback 処理を持てる
- 認証成功後のセッション確立は共通化する
- 外部認証プロバイダごとのユーザーひも付け情報を別テーブルで管理できる

## 認証方式の分類

将来的な認証方式は大きく次の 3 系統を想定します。

### 1. ローカル認証

- メールアドレス + パスワード
- パスキー

### 2. 外部認証

- OAuth 2.0 / OpenID Connect による SNS ログイン
- 企業 IdP や SSO 基盤との連携

### 3. 追加認証

- 既存の二段階認証
- 将来の認証強化ポリシー

## 基本方針

- 認証成功後のログイン確立は既存の UsersService を利用する
- ログイン画面にはプラグイン側からボタンや補助 UI を追加する
- Discoverable Credential を前提にし、ユーザー名先行入力なしでも認証できる設計を優先する
- 将来の拡張として、ユーザー識別後の allowCredentials 指定にも対応可能なサービス構成にする
- 将来の OAuth / OpenID Connect 系プラグインと競合しにくいよう、認証成功後のセッション確立部分は共通化しやすい境界で切る

## フェーズ 1 の仕様決定

初期実装で先に固定しておく事項は次の通りです。

### 対象範囲

- パスキーによる Admin ログインを最優先とする
- パスキー登録は ログイン済みユーザー本人 に限定する
- Front ログインも同一基盤で対応する
- Admin API の JWT ログインは初期対象外とする

### アカウント作成ポリシー

- パスキー認証成功時に新規ユーザー自動作成は行わない
- 既存ユーザーに登録済みの credential のみログイン対象とする
- 初回登録は、既存のパスワードログインまたは同等の既存ログイン状態を前提とする

### 二段階認証との関係

Admin で二段階認証が有効な場合、パスキー認証成功だけではログイン完了としません。

理由は、コアの二段階認証は Authentication.afterIdentify ベースであり、パスキー側が UsersService::login() を直接呼ぶだけでは既存ポリシーを迂回してしまうためです。

初期仕様では次を採用します。

- パスキーでユーザー特定後、共通ログイン層で二段階認証要否を判定する
- 二段階認証が必要な場合は login_code 画面へ処理を引き渡す
- 二段階認証が不要な場合のみ UsersService::login() でセッションを確立する

つまり、フェーズ 1 では パスキーは強固な第一要素または代替第一要素 であり、二段階認証を自動的に免除しません。

## 認証方式

### 初期採用: Discoverable Credential

初期実装は Discoverable Credential を優先します。

この方式では、ログイン前にメールアドレスやユーザー名を入力させず、デバイス側で候補アカウントを選択して認証できます。

利点は次の通りです。

- パスキーらしい自然な UX を提供できる
- 管理画面と Front の両方で同一フローを採用しやすい
- フォーム構造への依存が少なく、既存ログイン画面への追加実装に向いている

## システム構成

想定ディレクトリ構成は次の通りです。

```text
plugins/BcAuthPasskey/
├── README.md
├── config/
│   ├── routes.php
│   └── Migrations/
├── docs/
│   └── passkey-auth-design.md
├── src/
│   ├── BcAuthPasskeyPlugin.php
│   ├── Controller/
│   │   ├── Admin/
│   │   │   └── PasskeysController.php
│   │   └── PasskeysController.php
│   ├── Model/
│   │   ├── Entity/
│   │   │   └── PasskeyCredential.php
│   │   └── Table/
│   │       └── PasskeyCredentialsTable.php
│   ├── Service/
│   │   ├── PasskeyAuthService.php
│   │   └── PasskeyAuthServiceInterface.php
├── templates/
│   ├── Admin/
│   └── plugin/
└── webroot/
    └── js/
        └── bc_auth_passkey.js
```

将来的に認証方式を増やす場合のイメージは次の通りです。

```text
plugins/
├── BcAuthPasskey/         # WebAuthn / Passkey
├── BcAuthSocial/          # OAuth / OpenID Connect 系
└── BcAuthCommon/          # 共通の認証連携部品
```

認証完了後に baserCMS のログイン状態へ接続する処理は、`BcAuthCommon` の `AuthLoginService` と `AuthRedirectService` に切り出しています。
一方で、WebAuthn の challenge / attestation / assertion 検証は BcAuthPasskey 側に残します。

## ログインフロー

### 管理画面ログイン

1. ユーザーが管理画面ログインページを開く
2. プラグインがログイン画面に パスキーでログイン ボタンを表示する
3. ボタンクリックでログインチャレンジを取得する
4. ブラウザで navigator.credentials.get() を実行する
5. ブラウザと認証器が本人確認を行う
6. アサーションをサーバーへ送信する
7. サーバーで署名と challenge を検証する
8. 該当ユーザーを特定する
9. `BcAuthCommon` の `AuthLoginService` を呼び出してセッションを確立する
10. 管理画面トップまたはリダイレクト先へ遷移する

### Front ログイン

Front プレフィックスでも基本的に同一フローを採用します。現在は Front 用コントローラーとログイン画面 override まで実装済みです。

差分は次の点です。

- 利用するログイン画面のテンプレート
- 認証後のリダイレクト先
- 使用するプレフィックス設定

## 登録フロー

1. 既に通常ログイン済みのユーザーが登録画面を開く
2. サーバーが registration challenge を発行する
3. ブラウザで navigator.credentials.create() を実行する
4. 生成された公開鍵資格情報をサーバーへ送信する
5. サーバーが attestation を検証する
6. credential_id、公開鍵、counter などを保存する
7. ユーザーに登録完了を通知する

### 登録時の追加ルール

- 登録操作は認証済みセッション上でのみ許可する
- 登録時の user verification は `required` とする
- 同一 credential_id の重複登録は許可しない
- ユーザーが識別しやすい表示名は任意入力とし、未入力時はブラウザまたは端末由来の既定名を補助的に採用する
- 削除は本人のみ可能とし、削除後も既存パスワードログインが残る前提で運用する

## データモデル

### テーブル名

passkey_credentials

### 主なカラム

| カラム | 型 | 用途 |
| --- | --- | --- |
| id | integer | 主キー |
| user_id | integer | users テーブルの参照 |
| prefix | string | Admin / Front などの利用プレフィックス |
| user_handle | string | WebAuthn の user handle |
| credential_id | text | Base64URL 化した credential ID |
| public_key | text | 公開鍵データ |
| counter | integer | 認証器の sign counter |
| transports | text nullable | usb、ble、internal などの情報 |
| name | string nullable | ユーザーが識別するための名称 |
| last_used | datetime nullable | 最終利用日時 |
| created | datetime | 作成日時 |
| modified | datetime | 更新日時 |

### インデックス方針

- credential_id に一意インデックス
- user_id に通常インデックス
- user_handle に必要に応じて一意インデックス

## 将来の外部認証用データモデル案

パスキーとは別に、外部認証を扱う場合は専用テーブルを分ける想定です。

例:

| テーブル | 用途 |
| --- | --- |
| auth_provider_links | baserCMS ユーザーと外部プロバイダアカウントのひも付け |
| auth_login_histories | 認証成功、失敗、利用プロバイダの監査ログ |

auth_provider_links の主な項目例:

| カラム | 用途 |
| --- | --- |
| id | 主キー |
| user_id | baserCMS 側ユーザー |
| provider | google, apple, github, line など |
| provider_user_id | 外部プロバイダ側の subject または user id |
| email | 受領したメールアドレス |
| profile | 受領したプロフィール情報の一部 |
| access_token_scope | 必要最小限のスコープ記録 |
| created | 作成日時 |
| modified | 更新日時 |

このテーブルは BcAuthPasskey に含めず、別プラグインまたは共通基盤で扱う方が責務分離しやすい想定です。

## 認証状態の管理

challenge はサーバー側セッションまたは短命ストアで管理します。

保持したい情報は次の通りです。

- challenge
- prefix
- redirect
- 発行時刻
- 処理種別 login / register

セッションキーは、少なくとも prefix と用途が衝突しない構造にします。

例:

- `BcAuthPasskey.loginChallenge.Admin`
- `BcAuthPasskey.registerChallenge.Admin`

将来的に Front を同時導入しても challenge が混線しないことを優先します。

challenge は検証後すぐに破棄し、再利用できないようにします。

## ルーティング案

### Admin

- GET /baser/admin/bc-auth-passkey/passkeys/login_challenge
- POST /baser/admin/bc-auth-passkey/passkeys/login
- GET /baser/admin/bc-auth-passkey/passkeys/register_challenge
- POST /baser/admin/bc-auth-passkey/passkeys/register
- GET /baser/admin/bc-auth-passkey/passkeys/index
- POST /baser/admin/bc-auth-passkey/passkeys/delete/{id}

### Front

- GET /bc-auth-passkey/passkeys/login_challenge
- POST /bc-auth-passkey/passkeys/login
- GET /bc-auth-passkey/passkeys/register_challenge
- POST /bc-auth-passkey/passkeys/register

命名は今後の baserCMS の既存ルーティング流儀に合わせて調整する余地があります。

## サービス設計

### PasskeyAuthService の責務

- registration challenge の生成
- authentication challenge の生成
- attestation の検証
- assertion の検証
- credential_id から登録情報を取得
- 認証成功後に counter を更新
- baserCMS ユーザーへのひも付け処理

### PasskeyCredentialsTable の責務

- 資格情報の永続化
- user_id 単位の一覧取得
- credential_id 単位の検索
- 論理上の重複登録防止

### Controller の責務

- JSON レスポンスの入出力
- セッション上の challenge の読み書き
- 認証済みかどうかの制御
- Service の呼び出し
- 成功時のリダイレクトレスポンス返却

## ログイン完了仕様

PasskeyAuthService は assertion 検証までを責務とし、その後のログイン完了処理は次の入力を共通ログイン層へ渡す前提とします。

- user_id
- prefix
- redirect
- saved
- auth_source = `passkey`

この責務分離により、パスキー固有ロジックと baserCMS 側ログイン確立を分離できます。

### 成功時の分岐

1. assertion の検証に成功する
2. credential にひも付く user_id を特定する
3. 共通ログイン層が二段階認証要否を判定する
4. 必要なら login_code に遷移する
5. 不要なら UsersService::login() と Users.afterLogin を実行する

### 失敗時の返却方針

- login challenge の期限切れは 400 系エラーとして扱う
- 認証器キャンセルはエラーではなくキャンセル結果として返す
- credential 未登録、署名不正、origin 不一致は失敗として監査ログへ残す
- UI 向けメッセージは詳細を出しすぎず、内部ログには分類コードを残す

### 将来の共通認証サービス案

複数認証方式に広げる場合は、次のような共通サービス境界を設ける想定です。

- AuthLoginService: 認証成功後に baserCMS のログイン状態へ接続する
- AuthRedirectService: ログイン後リダイレクト先の正規化を行う
- AuthProviderLinkService: 外部アカウントとのひも付けを管理する

BcAuthPasskey では、少なくとも 認証成功後のログイン確立 と リダイレクト判定 を後から切り出せるように実装する方針とします。

## baserCMS との連携方針

### ログイン確立

認証成功後は、既存の UsersService::login() を呼び出してセッションを確立します。

これにより、baserCMS 標準のログイン状態や既存の権限制御と整合性を保ちやすくなります。

### ログイン画面への組み込み

候補は次の 2 つです。

1. イベントフックでボタン表示に必要な変数や Element を注入する
2. プラグインテンプレートで対象ログイン画面をオーバーライドする

BcAuthSocial と同様に、初期段階では template override を採用します。

理由は次の通りです。

- 差し込み位置を正確に制御しやすい
- Passkey ボタンを既存のログインフォーム近傍へ安定して配置しやすい
- 現状のログインテンプレートには汎用的な差し込みポイントが不足している

ただし、override テンプレート内では認証ボタン領域を部分テンプレートや配列定義で分離し、将来的に共通入口方式へ寄せやすい構造を維持します。

### プレフィックス対応

baserCMS の BcPrefixAuth 設定に合わせて、Admin と Front で処理を分岐可能な構成にします。

### 複数認証方式の UI 共存

将来的には 1 つのログイン画面に次の入口を並べられる構成を目指します。

- パスワードでログイン
- パスキーでログイン
- Google でログイン
- Apple でログイン
- その他の外部認証でログイン

このため、ログイン画面への追加 UI は、単一の認証方式に密結合しすぎない実装を意識します。

## UI 方針

### ログインページ

- 既存のメールアドレス / パスワードフォームは維持する
- その下または上に パスキーでログイン ボタンを追加する
- 将来的には SNS ログインボタン群も同一エリアに追加できる構造にする
- パスキー未対応環境ではボタンを非表示または無効化する
- 認証失敗時は既存メッセージエリアまたは専用メッセージを表示する

### パスキー管理画面

- 登録済みパスキー一覧
- 新しいパスキーを追加
- 名前変更
- 削除
- 最終利用日時の表示

## セキュリティ方針

### 必須要件

- HTTPS 前提で運用する
- RP ID と Origin を厳密に検証する
- challenge は使い捨てにする
- sign counter を検証してリプレイ攻撃を防ぐ
- 資格情報登録時と認証時で user verification を required にする

### サーバー側で検証する項目

- challenge
- origin
- rpId
- type
- signature
- credential_id
- user_handle または登録資格情報との整合性
- sign counter

### 保存しないもの

- 秘密鍵
- 生体情報
- 認証器が内部で管理する PIN や端末固有秘密

## エラー処理方針

- challenge 不一致は認証失敗として扱う
- 未登録 credential_id は認証失敗として扱う
- counter の逆行は高リスクとしてログに記録する
- WebAuthn 非対応ブラウザではパスワードログインにフォールバックさせる
- ユーザーキャンセルは異常ではなく通常キャンセルとして扱う

## 段階的実装案

### フェーズ 1

- Admin ログイン対応
- Discoverable Credential ログイン
- 認証済みユーザーによるパスキー登録
- 管理画面での登録済み一覧と削除

### フェーズ 2

- Front ログイン対応
- パスキー名称変更
- ログイン成功履歴や最終利用日時の更新

### フェーズ 3

- 2 要素認証との併用方針整理
- 管理者による強制リセット
- API や追加プレフィックス対応

## 二段階認証との関係

baserCMS には既存の二段階認証機構があります。

パスキーは高い本人確認強度を持つため、将来的には二段階認証との関係を次のいずれかで整理する必要があります。

- パスキー認証成功時は追加コード入力を不要とみなす
- サイト設定で、パスキー利用時も二段階認証を維持する

初期段階では、既存の二段階認証フローとの競合を避けるため、挙動を明示したうえで実装方針を固定する必要があります。

## 未確定事項

- Front 側ログインの実体をどのプラグインで担うか
- パスキー管理画面をどこに配置するか
- 複数ドメイン運用時の RP ID と Origin の扱い
- Discoverable Credential から usernameless 優先で始めつつ、allowCredentials 指定フローをいつ導入するか
- パスキー管理画面を Admin のユーザー編集配下に置くか、独立メニューにするか

## 実装開始時の優先順位

1. プラグイン骨格作成
2. マイグレーションとテーブル定義
3. PasskeyAuthService の最小実装
4. Admin ログイン challenge / verify API 実装
5. ログイン画面 UI 追加
6. 登録画面実装
7. Front 対応

## 認証プラグインの分割方針

現時点の推奨は次の通りです。

- BcAuthPasskey は WebAuthn / Passkey 専用プラグインとして進める
- SNS ログインは BcAuthSocial のような別プラグインとして分離する
- 共通化が必要になった段階でのみ、共通サービスや共通 UI 部品を抽出する

この分割を推奨する理由は次の通りです。

- WebAuthn と OAuth / OpenID Connect ではプロトコルも失敗パターンも異なる
- 依存ライブラリや設定項目が大きく異なる
- 管理画面上の設定や運用ポリシーも別になりやすい
- ただしログイン成功後の baserCMS への接続処理は共通化できる

この方針は BcAuthSocial 側の設計とも整合しており、Google / X は BcAuthSocial 本体に同梱し、LINE / Apple / GitHub などは外部アドオンとして追加する前提です。

## まとめ

BcAuthPasskey は、baserCMS の既存認証基盤を活かしながら、パスキー認証をプラグインとして追加するための設計を採用します。

スマートフォンの指紋認証や顔認証は、端末側のパスキー認証器を通じて利用する想定です。

また、初期段階では Admin を中心に最小構成で成立させつつ、将来の SNS ログインなど別方式の認証を追加しやすいよう、認証成功後の接続部分と UI 差し込み部分を疎結合に保つ方針とします。
