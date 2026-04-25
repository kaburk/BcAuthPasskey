<?php
declare(strict_types=1);

namespace BcAuthPasskey\Controller;

use BaserCore\Controller\BcFrontAppController;
use BaserCore\Service\UsersService;
use BcAuthCommon\Service\AuthLoginService;
use BcAuthCommon\Service\AuthLoginLogService;
use BcAuthPasskey\Service\BcAuthPasskeyService;
use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * BcAuthPasskeysController (Front)
 *
 * フロントエンド向けパスキー認証エンドポイントを提供します。
 *
 * ルーティング（config/routes.php で設定）:
 *   GET  /bc-auth-passkey/passkeys/login_challenge
 *   POST /bc-auth-passkey/passkeys/login
 */
class BcAuthPasskeysController extends BcFrontAppController
{
    public function initialize(): void
    {
        parent::initialize();
        if ($this->components()->has('Authentication')) {
            $this->Authentication->allowUnauthenticated([
                'loginChallenge',
                'login',
            ]);
        }
    }

    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->FormProtection->setConfig('unlockedActions', [
            'login',
        ]);
    }

    /**
     * ログイン challenge の発行
     *
     * GET /bc-auth-passkey/passkeys/login_challenge
     */
    public function loginChallenge(): Response
    {
        $this->request->allowMethod('get');

        $service  = new BcAuthPasskeyService();
        $redirect = $this->request->getQuery('redirect');
        $data     = $service->generateLoginChallenge('Front', $redirect);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));
    }

    /**
     * パスキーでのログイン（assertion 検証）
     *
     * POST /bc-auth-passkey/passkeys/login
     *
     * 成功時は AuthLoginService 経由でセッション確立し redirect_url を JSON で返します。
     */
    public function login(): Response
    {
        $this->request->allowMethod('post');

        $service = new BcAuthPasskeyService();

        try {
            $userId = $service->verifyLoginAssertion(
                $this->request->getData(),
                'Front'
            );
        } catch (\RuntimeException $e) {
            AuthLoginLogService::writeWithContext(
                event: 'login_failure',
                prefix: 'Front',
                authSource: 'passkey',
                request: $this->request,
                context: [
                    'request_path' => (string) $this->request->getRequestTarget(),
                    'referer' => (string) $this->request->getHeaderLine('Referer'),
                    'payload' => ['error' => $e->getMessage()],
                ]
            );
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => __d('baser_core', '認証に失敗しました。')]));
        }

        $stored = $this->request->getSession()->read('BcAuthPasskey.loginChallenge.Front') ?? [];

        $loginService = new AuthLoginService();
        try {
            $loginResult = $loginService->login([
                'user_id'     => $userId,
                'prefix'      => 'Front',
                'auth_source' => 'passkey',
                'redirect'    => $stored['redirect'] ?? null,
                'saved'       => false,
            ], $this->request, $this->response);
        } catch (\RuntimeException $e) {
            AuthLoginLogService::writeWithContext(
                event: 'login_failure',
                userId: $userId,
                prefix: 'Front',
                authSource: 'passkey',
                request: $this->request,
                context: [
                    'request_path' => (string) $this->request->getRequestTarget(),
                    'referer' => (string) $this->request->getHeaderLine('Referer'),
                    'payload' => ['error' => $e->getMessage()],
                ]
            );
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => $e->getMessage()]));
        }

        $this->request  = $loginResult->request;
        $this->response = $loginResult->response;

        if ($loginResult->status === 'completed') {
            $this->setLoginSuccessMessage($userId);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['redirect_url' => $loginResult->redirect_url]));
    }

    private function setLoginSuccessMessage(int $userId): void
    {
        /** @var \BaserCore\Model\Entity\User $user */
        $user = (new UsersService())->get($userId);
        $this->BcMessage->setInfo(__d('baser_core', 'ようこそ、{0}さん。', $user->getDisplayName()));
    }
}
