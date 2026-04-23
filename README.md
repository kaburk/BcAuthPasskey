# BcAuthPasskey plugin for baserCMS

BcAuthPasskey は、baserCMS 5 のログインに WebAuthn / Passkey ベースの認証を追加するためのプラグインです。

スマートフォンの指紋認証や顔認証は、端末に保存されたパスキーを通じて利用する想定です。
アプリケーションが生体情報そのものを扱うわけではなく、WebAuthn の検証結果を使ってログインを成立させます。

※ このプラグイン単体では動作しません。事前に[BcAuthCommon](https://github.com/kaburk/BcAuthCommon)をインストールしてください。

## 目的

- 管理画面ログインにパスキー認証を追加する
- Front プレフィックスのログインにも展開できる構成にする
- baserCMS の既存ログイン機構と共存させる

## 機能

- パスキーでログイン
- ログイン済みユーザーによるパスキー登録
- 登録済みパスキーの一覧表示と削除

## ドキュメント

詳細設計は docs/passkey-auth-design.md を参照してください。

横断整理は ../BcAuthCommon/docs/auth-plugin-spec-summary.md を参照してください。

## ライセンス

MIT License. 詳細は `LICENSE.txt` を参照してください。
