<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;
use Phinx\Db\Adapter\MysqlAdapter;

/**
 * passkey_credentials テーブルを作成するマイグレーション
 *
 * BcAuthPasskey が管理する WebAuthn 資格情報テーブルです。
 * 1 ユーザーが複数のパスキーを登録できる 1 対多の構造をとります。
 */
class CreateBcAuthPasskeyCredentials extends BcMigration
{
    public function up(): void
    {
        $this->table('bc_auth_passkey_credentials', [
            'collation' => 'utf8mb4_general_ci',
        ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit'   => null,
                'null'    => false,
                'comment' => 'users テーブルの ID',
            ])
            ->addColumn('prefix', 'string', [
                'default' => 'Admin',
                'limit'   => 50,
                'null'    => false,
                'comment' => '利用プレフィックス（Admin / Front）',
            ])
            ->addColumn('user_handle', 'string', [
                'default' => null,
                'limit'   => 191,
                'null'    => false,
                'comment' => 'WebAuthn user handle（Base64URL）',
            ])
            ->addColumn('credential_id', 'text', [
                'default' => null,
                'null'    => false,
                'comment' => 'Base64URL エンコードした credential ID',
            ])
            ->addColumn('public_key', 'text', [
                'default' => null,
                'null'    => false,
                'limit'   => MysqlAdapter::TEXT_MEDIUM,
                'comment' => '公開鍵データ',
            ])
            ->addColumn('counter', 'integer', [
                'default' => 0,
                'limit'   => null,
                'null'    => false,
                'signed'  => false,
                'comment' => 'sign counter。リプレイ攻撃検知に使う',
            ])
            ->addColumn('transports', 'string', [
                'default' => null,
                'limit'   => 255,
                'null'    => true,
                'comment' => '利用可能なトランスポート（JSON 配列形式）',
            ])
            ->addColumn('aaguid', 'string', [
                'default' => null,
                'limit'   => 36,
                'null'    => true,
                'comment' => '認証器識別子（UUID 形式）',
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit'   => 255,
                'null'    => true,
                'comment' => 'ユーザーが識別しやすい表示名',
            ])
            ->addColumn('last_used', 'datetime', [
                'default' => null,
                'null'    => true,
                'comment' => '最終利用日時',
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null'    => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null'    => true,
            ])
            ->addIndex(['user_id'])
            ->addIndex(['user_handle'])
            ->create();

        // credential_id は text 型のため prefix 付きで UNIQUE インデックスを追加する
        $this->execute(
            'ALTER TABLE bc_auth_passkey_credentials ADD UNIQUE INDEX UNIQUE_credential_id (credential_id(191))'
        );
    }

    public function down(): void
    {
        $this->table('bc_auth_passkey_credentials')->drop()->save();
    }
}
