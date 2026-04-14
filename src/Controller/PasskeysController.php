<?php
declare(strict_types=1);

namespace BcPasskeyAuth\Controller;

use BaserCore\Controller\BcFrontAppController;
use BcAuthCommon\Service\AuthLoginService;
use BcPasskeyAuth\Service\PasskeyAuthService;
use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * PasskeysController (Front)
 *
 * フロントエンド向けパスキー認証エンドポイントを提供します。
 *
 * ルーティング（config/routes.php で設定）:
 *   GET  /bc-passkey-auth/passkeys/login_challenge
 *   POST /bc-passkey-auth/passkeys/login
 */
class PasskeysController extends BcFrontAppController
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
     * GET /bc-passkey-auth/passkeys/login_challenge
     */
    public function loginChallenge(): Response
    {
        $this->request->allowMethod('get');

        $service  = new PasskeyAuthService();
        $redirect = $this->request->getQuery('redirect');
        $data     = $service->generateLoginChallenge('Front', $redirect);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));
    }

    /**
     * パスキーでのログイン（assertion 検証）
     *
     * POST /bc-passkey-auth/passkeys/login
     *
     * 成功時は AuthLoginService 経由でセッション確立し redirect_url を JSON で返します。
     */
    public function login(): Response
    {
        $this->request->allowMethod('post');

        $service = new PasskeyAuthService();

        try {
            $userId = $service->verifyLoginAssertion(
                $this->request->getData(),
                'Front'
            );
        } catch (\RuntimeException $e) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => __d('baser_core', '認証に失敗しました。')]));
        }

        $stored = $this->request->getSession()->read('BcPasskeyAuth.loginChallenge.Front') ?? [];

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
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => $e->getMessage()]));
        }

        $this->request  = $loginResult->request;
        $this->response = $loginResult->response;

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['redirect_url' => $loginResult->redirect_url]));
    }
}
