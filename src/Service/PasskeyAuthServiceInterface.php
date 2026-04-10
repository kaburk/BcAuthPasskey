<?php
declare(strict_types=1);

namespace BcPasskeyAuth\Service;

use BcPasskeyAuth\Model\Entity\PasskeyCredential;

/**
 * PasskeyAuthServiceInterface
 *
 * WebAuthn / Passkey 認証サービスのインターフェースです。
 * PasskeyAuthService がこのインターフェースを実装します。
 */
interface PasskeyAuthServiceInterface
{
    /**
     * ログイン用 challenge を生成してセッションへ保存する
     *
     * @param string $prefix 利用プレフィックス（Admin / Front）
     * @param string|null $redirect ログイン後のリダイレクト先
     * @return array{challenge: string} チャレンジ文字列（Base64URL）を含む配列
     */
    public function generateLoginChallenge(string $prefix, ?string $redirect = null): array;

    /**
     * ログイン assertion を検証してユーザー ID を返す
     *
     * @param array $assertionResponse クライアントからのアサーションレスポンス
     * @param string $prefix 利用プレフィックス
     * @return int 認証成功時のユーザー ID
     * @throws \RuntimeException 検証失敗時
     */
    public function verifyLoginAssertion(array $assertionResponse, string $prefix): int;

    /**
     * 登録用 challenge を生成してセッションへ保存する
     *
     * @param int    $userId  登録対象のユーザー ID
     * @param string $prefix  利用プレフィックス
     * @return array{challenge: string, user: array} チャレンジとユーザー情報
     */
    public function generateRegisterChallenge(int $userId, string $prefix): array;

    /**
     * 登録 attestation を検証して passkey_credentials へ保存する
     *
     * @param array  $attestationResponse クライアントからの attestation レスポンス
     * @param int    $userId  登録ユーザー ID
     * @param string $prefix  利用プレフィックス
     * @param string|null $name ユーザーが付けるパスキー名称
     * @return PasskeyCredential 保存した credential エンティティ
     * @throws \RuntimeException 検証失敗または重複登録時
     */
    public function verifyRegistrationAttestation(
        array $attestationResponse,
        int $userId,
        string $prefix,
        ?string $name = null
    ): PasskeyCredential;

    /**
     * 指定したユーザーのパスキー一覧を返す
     *
     * @param int    $userId
     * @param string $prefix
     * @return PasskeyCredential[]
     */
    public function getCredentials(int $userId, string $prefix = 'Admin'): array;

    /**
     * パスキーを削除する
     *
     * 削除は credential の所有ユーザー本人のみ可能です。
     *
     * @param int $id credential ID
     * @param int $userId 認証済みユーザーの ID（所有者確認用）
     * @return bool
     */
    public function deleteCredential(int $id, int $userId): bool;
}
