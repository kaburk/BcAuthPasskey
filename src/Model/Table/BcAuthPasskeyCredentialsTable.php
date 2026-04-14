<?php
declare(strict_types=1);

namespace BcAuthPasskey\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * BcAuthPasskeyCredentialsTable
 *
 * WebAuthn / Passkey の資格情報を管理するテーブルクラスです。
 * 1 ユーザーが複数の credential を登録できます。
 */
class BcAuthPasskeyCredentialsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bc_auth_passkey_credentials');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'className'  => 'BaserCore.Users',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->nonNegativeInteger('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->scalar('prefix')
            ->maxLength('prefix', 50)
            ->requirePresence('prefix', 'create')
            ->notEmptyString('prefix');

        $validator
            ->scalar('user_handle')
            ->maxLength('user_handle', 191)
            ->requirePresence('user_handle', 'create')
            ->notEmptyString('user_handle');

        $validator
            ->scalar('credential_id')
            ->requirePresence('credential_id', 'create')
            ->notEmptyString('credential_id');

        $validator
            ->scalar('public_key')
            ->requirePresence('public_key', 'create')
            ->notEmptyString('public_key');

        $validator
            ->nonNegativeInteger('counter')
            ->requirePresence('counter', 'create')
            ->notEmptyString('counter');

        $validator
            ->scalar('transports')
            ->maxLength('transports', 255)
            ->allowEmptyString('transports');

        $validator
            ->scalar('aaguid')
            ->maxLength('aaguid', 36)
            ->allowEmptyString('aaguid');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->allowEmptyString('name');

        return $validator;
    }

    /**
     * credential_id からレコードを取得する
     *
     * @param string $credentialId Base64URL エンコード済み credential ID
     * @return \BcAuthPasskey\Model\Entity\BcAuthPasskeyCredential|null
     */
    public function findByCredentialId(string $credentialId): ?object
    {
        return $this->find()
            ->where(['credential_id' => $credentialId])
            ->first();
    }

    /**
     * ユーザー ID 単位で一覧を取得する
     *
     * @param int $userId
     * @param string $prefix
     * @return \Cake\ORM\Query
     */
    public function findByUser(int $userId, string $prefix = 'Admin'): object
    {
        return $this->find()
            ->where([
                'user_id' => $userId,
                'prefix'  => $prefix,
            ])
            ->orderByAsc('created');
    }
}
