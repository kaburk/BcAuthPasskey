<?php
declare(strict_types=1);

namespace BcPasskeyAuth\Controller\Admin;

use BaserCore\Controller\Admin\BcAdminAppController;
use BcAuthCommon\Service\AuthLoginService;
use BcPasskeyAuth\Service\PasskeyAuthService;
use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * PasskeysController (Admin)
 *
 * Admin プレフィックスにおけるパスキーの管理・認証エンドポイントを提供します。
 *
 * ログイン challenge / verify は認証なしで呼び出せるよう allowUnauthenticated に含めます。
 * register / delete は認証済みセッションが必要です。
 *
 * ルーティング（config/routes.php で設定）:
 *   GET  /baser/admin/bc-passkey-auth/passkeys/login_challenge
 *   POST /baser/admin/bc-passkey-auth/passkeys/login
 *   GET  /baser/admin/bc-passkey-auth/passkeys/register_challenge
 *   POST /baser/admin/bc-passkey-auth/passkeys/register
 *   GET  /baser/admin/bc-passkey-auth/passkeys/index
 *   POST /baser/admin/bc-passkey-auth/passkeys/delete/{id}
 */
class PasskeysController extends BcAdminAppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated([
            'login_challenge',
            'login',
        ]);
    }

    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->FormProtection->setConfig('unlockedActions', [
            'login',
            'register',
        ]);
    }

    /**
     * ログイン challenge の発行
     *
     * GET /baser/admin/bc-passkey-auth/passkeys/login_challenge
     */
    public function login_challenge(): Response
    {
        $this->request->allowMethod('get');

        $service  = new PasskeyAuthService();
        $redirect = $this->request->getQuery('redirect');
        $data     = $service->generateLoginChallenge('Admin', $redirect);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));
    }

    /**
     * パスキーでのログイン（assertion 検証）
     *
    * POST /baser/admin/bc-passkey-auth/passkeys/login
    *
    * 成功時は AuthLoginService 経由でセッション確立またはログインコード画面へリダイレクトします。
     */
    public function login(): Response
    {
        $this->request->allowMethod('post');

        $service = new PasskeyAuthService();

        try {
            $userId = $service->verifyLoginAssertion(
                $this->request->getData(),
                'Admin'
            );
        } catch (\RuntimeException $e) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => __d('baser_core', '認証に失敗しました。')]));
        }

        $stored = $this->request->getSession()->read('BcPasskeyAuth.loginChallenge.Admin') ?? [];

        $loginService = new AuthLoginService();
        try {
            $loginResult = $loginService->login([
                'user_id'     => $userId,
                'prefix'      => 'Admin',
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

    /**
     * 登録 challenge の発行
     *
     * GET /baser/admin/bc-passkey-auth/passkeys/register_challenge
     * 認証済みセッションが必要です。
     */
    public function register_challenge(): Response
    {
        $this->request->allowMethod('get');

        $user    = $this->Authentication->getIdentity();
        $service = new PasskeyAuthService();
        $data    = $service->generateRegisterChallenge($user->id, 'Admin');

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));
    }

    /**
     * パスキーの登録（attestation 検証）
     *
     * POST /baser/admin/bc-passkey-auth/passkeys/register
     * 認証済みセッションが必要です。
     */
    public function register(): Response
    {
        $this->request->allowMethod('post');

        $user    = $this->Authentication->getIdentity();
        $service = new PasskeyAuthService();

        try {
            $credential = $service->verifyRegistrationAttestation(
                $this->request->getData(),
                $user->id,
                'Admin',
                $this->request->getData('name')
            );
        } catch (\RuntimeException $e) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => $e->getMessage()]));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'id'   => $credential->id,
                'name' => $credential->name,
            ]));
    }

    /**
     * 登録済みパスキー一覧
     *
     * GET /baser/admin/bc-passkey-auth/passkeys/index
     * 認証済みセッションが必要です。
     */
    public function index(): void
    {
        $user        = $this->Authentication->getIdentity();
        $service     = new PasskeyAuthService();
        $credentials = $service->getCredentials($user->id, 'Admin');

        $this->set(compact('credentials'));
    }

    /**
     * パスキーの削除
     *
     * POST /baser/admin/bc-passkey-auth/passkeys/delete/{id}
     * 認証済みセッションが必要です。
     */
    public function delete(int $id): Response
    {
        $this->request->allowMethod('post');

        $user    = $this->Authentication->getIdentity();
        $service = new PasskeyAuthService();

        if (!$service->deleteCredential($id, $user->id)) {
            $this->BcMessage->setError(__d('baser_core', '削除に失敗しました。'));
        } else {
            $this->BcMessage->setInfo(__d('baser_core', 'パスキーを削除しました。'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
