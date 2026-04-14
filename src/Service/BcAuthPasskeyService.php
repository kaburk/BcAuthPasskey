<?php
declare(strict_types=1);

namespace BcAuthPasskey\Service;

use BcAuthPasskey\Model\Entity\BcAuthPasskeyCredential;
use BcAuthPasskey\Model\Table\BcAuthPasskeyCredentialsTable;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

class BcAuthPasskeyService implements BcAuthPasskeyServiceInterface
{
    private BcAuthPasskeyCredentialsTable $credentials;

    public function __construct()
    {
        $this->credentials = TableRegistry::getTableLocator()->get('BcAuthPasskey.BcAuthPasskeyCredentials');
    }

    public function generateLoginChallenge(string $prefix, ?string $redirect = null): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->getRpId(),
            [],
            PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            60000
        );

        Router::getRequest()->getSession()->write('BcAuthPasskey.loginChallenge.' . $prefix, [
            'options' => $this->serialize($options),
            'redirect' => $redirect,
            'generated_at' => time(),
        ]);

        return ['publicKey' => $this->normalize($options)];
    }

    public function verifyLoginAssertion(array $assertionResponse, string $prefix): int
    {
        $session = Router::getRequest()->getSession();
        $stored = $session->read('BcAuthPasskey.loginChallenge.' . $prefix);
        $session->delete('BcAuthPasskey.loginChallenge.' . $prefix);

        if (empty($stored['options'])) {
            throw new RuntimeException('challenge が見つかりません。');
        }

        $credentialId = $assertionResponse['id'] ?? null;
        if (!$credentialId) {
            throw new RuntimeException('credential ID が含まれていません。');
        }

        $credential = $this->credentials->findByCredentialId($credentialId);
        if (!$credential) {
            throw new RuntimeException('登録されていない credential です。');
        }

        $publicKeyCredential = $this->deserializeCredential($assertionResponse);
        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            throw new RuntimeException('assertion レスポンスが不正です。');
        }

        $validated = AuthenticatorAssertionResponseValidator::create(
            ceremonyStepManager: $this->createCeremonyFactory()->requestCeremony([$this->getRpId()])
        )->check(
            $this->toCredentialSource($credential),
            $publicKeyCredential->response,
            $this->getSerializer()->deserialize($stored['options'], PublicKeyCredentialRequestOptions::class, 'json'),
            $this->getHost(),
            $this->decodeBase64Url($credential->user_handle),
            [$this->getRpId()]
        );

        $credential = $this->credentials->patchEntity($credential, [
            'counter' => $validated->counter,
            'last_used' => date('Y-m-d H:i:s'),
            'transports' => $validated->transports ? json_encode($validated->transports) : $credential->transports,
        ]);
        $this->credentials->saveOrFail($credential);

        return (int)$credential->user_id;
    }

    public function generateRegisterChallenge(int $userId, string $prefix): array
    {
        $users = TableRegistry::getTableLocator()->get('BaserCore.Users');
        /** @var \BaserCore\Model\Entity\User $user */
        $user = $users->get($userId);
        $userHandle = random_bytes(32);
        $existingCredentials = $this->credentials->findByUser($userId, $prefix)->toArray();

        $displayName = method_exists($user, 'getDisplayName') ? $user->getDisplayName() : ($user->name ?: (string)$user->id);

        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($this->getRpName(), $this->getRpId()),
            PublicKeyCredentialUserEntity::create(
                $user->email ?: (string)$user->id,
                $userHandle,
                $displayName
            ),
            random_bytes(32),
            [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            array_map(function (BcAuthPasskeyCredential $credential) {
                return PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $this->decodeBase64Url($credential->credential_id),
                    $credential->transports ? (json_decode($credential->transports, true) ?: []) : []
                );
            }, $existingCredentials),
            60000
        );

        Router::getRequest()->getSession()->write('BcAuthPasskey.registerChallenge.' . $prefix, [
            'options' => $this->serialize($options),
            'user_id' => $userId,
            'user_handle' => Base64UrlSafe::encodeUnpadded($userHandle),
            'generated_at' => time(),
        ]);

        return ['publicKey' => $this->normalize($options)];
    }

    public function verifyRegistrationAttestation(
        array $attestationResponse,
        int $userId,
        string $prefix,
        ?string $name = null
    ): BcAuthPasskeyCredential {
        $session = Router::getRequest()->getSession();
        $stored = $session->read('BcAuthPasskey.registerChallenge.' . $prefix);
        $session->delete('BcAuthPasskey.registerChallenge.' . $prefix);

        if (empty($stored['options']) || (int)$stored['user_id'] !== $userId) {
            throw new RuntimeException('登録チャレンジが無効または期限切れです。');
        }

        $publicKeyCredential = $this->deserializeCredential($attestationResponse);
        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new RuntimeException('attestation レスポンスが不正です。');
        }

        $validated = AuthenticatorAttestationResponseValidator::create(
            ceremonyStepManager: $this->createCeremonyFactory()->creationCeremony([$this->getRpId()])
        )->check(
            $publicKeyCredential->response,
            $this->getSerializer()->deserialize($stored['options'], PublicKeyCredentialCreationOptions::class, 'json'),
            $this->getHost(),
            [$this->getRpId()]
        );

        $encodedCredentialId = Base64UrlSafe::encodeUnpadded($validated->publicKeyCredentialId);
        if ($this->credentials->findByCredentialId($encodedCredentialId)) {
            throw new RuntimeException('この credential はすでに登録されています。');
        }

        $entity = $this->credentials->newEntity([
            'user_id' => $userId,
            'prefix' => $prefix,
            'user_handle' => $stored['user_handle'],
            'credential_id' => $encodedCredentialId,
            'public_key' => base64_encode($validated->credentialPublicKey),
            'counter' => $validated->counter,
            'transports' => $validated->transports ? json_encode($validated->transports) : null,
            'aaguid' => $validated->aaguid->toRfc4122(),
            'name' => $name,
        ]);

        $saved = $this->credentials->save($entity);
        if (!$saved) {
            throw new RuntimeException('パスキーの保存に失敗しました。');
        }

        return $saved;
    }

    public function getCredentials(int $userId, string $prefix = 'Admin'): array
    {
        return $this->credentials->findByUser($userId, $prefix)->toArray();
    }

    public function deleteCredential(int $id, int $userId): bool
    {
        $credential = $this->credentials->find()->where(['id' => $id, 'user_id' => $userId])->first();
        if (!$credential) {
            return false;
        }
        return $this->credentials->delete($credential);
    }

    private function getSerializer(): object
    {
        return (new WebauthnSerializerFactory(new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ])))->create();
    }

    private function normalize(object $options): array
    {
        return json_decode($this->serialize($options), true, 512, JSON_THROW_ON_ERROR);
    }

    private function serialize(object $options): string
    {
        return $this->getSerializer()->serialize($options, 'json');
    }

    private function deserializeCredential(array $payload): PublicKeyCredential
    {
        return $this->getSerializer()->deserialize(
            json_encode($payload, JSON_THROW_ON_ERROR),
            PublicKeyCredential::class,
            'json'
        );
    }

    private function createCeremonyFactory(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setSecuredRelyingPartyId([$this->getRpId()]);
        return $factory;
    }

    private function toCredentialSource(BcAuthPasskeyCredential $credential): PublicKeyCredentialSource
    {
        return PublicKeyCredentialSource::create(
            $this->decodeBase64Url($credential->credential_id),
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            $credential->transports ? (json_decode($credential->transports, true) ?: []) : [],
            'none',
            EmptyTrustPath::create(),
            $credential->aaguid ? Uuid::fromString($credential->aaguid) : Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            base64_decode($credential->public_key, true) ?: '',
            $this->decodeBase64Url($credential->user_handle),
            (int)$credential->counter
        );
    }

    private function decodeBase64Url(string $value): string
    {
        return Base64UrlSafe::decodeNoPadding($value);
    }

    private function getRpId(): string
    {
        return (string)(Configure::read('BcAuthPasskey.rpId') ?: Router::getRequest()->getUri()->getHost());
    }

    private function getRpName(): string
    {
        return (string)(Configure::read('BcAuthPasskey.rpName') ?: Router::getRequest()->getUri()->getHost());
    }

    private function getHost(): string
    {
        return Router::getRequest()->getUri()->getHost();
    }
}
