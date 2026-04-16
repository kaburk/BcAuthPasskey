<?php
declare(strict_types=1);

namespace BcAuthPasskey\Controller\Admin;

use BaserCore\Controller\Admin\BcAdminAppController;
use BaserCore\Service\UsersService;
use BcAuthCommon\Service\AuthLoginService;
use BcAuthCommon\Service\AuthLoginLogService;
use BcAuthPasskey\Service\BcAuthPasskeyService;
use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * BcAuthPasskeysController (Admin)
 *
 * Admin プレフィックスにおけるパスキーの管理・認証エンドポイントを提供します。
 *
 * ログイン challenge / verify は認証なしで呼び出せるよう allowUnauthenticated に含めます。
 * register / delete は認証済みセッションが必要です。
 *
 * ルーティング（config/routes.php で設定）:
 *   GET  /baser/admin/bc-auth-passkey/passkeys/login_challenge
 *   POST /baser/admin/bc-auth-passkey/passkeys/login
 *   GET  /baser/admin/bc-auth-passkey/passkeys/register_challenge
 *   POST /baser/admin/bc-auth-passkey/passkeys/register
 *   GET  /baser/admin/bc-auth-passkey/passkeys/index
 *   POST /baser/admin/bc-auth-passkey/passkeys/delete/{id}
 */
class BcAuthPasskeysController extends BcAdminAppController
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
        $this->FormProtection->setConfig('unlockedActions', [
            'login',
            'register',
        ]);

        if (in_array($this->request->getParam('action'), ['loginChallenge', 'login'], true)) {
            return;
        }

        parent::beforeFilter($event);
    }

    /**
     * ログイン challenge の発行
     *
     * GET /baser/admin/bc-auth-passkey/passkeys/login_challenge
     */
    public function loginChallenge(): Response
    {
        $this->request->allowMethod('get');

        $service  = new BcAuthPasskeyService();
        $redirect = $this->request->getQuery('redirect');
        $data     = $service->generateLoginChallenge('Admin', $redirect);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));
    }

    /**
     * パスキーでのログイン（assertion 検証）
     *
    * POST /baser/admin/bc-auth-passkey/passkeys/login
    *
    * 成功時は AuthLoginService 経由でセッション確立またはログインコード画面へリダイレクトします。
     */
    public function login(): Response
    {
        $this->request->allowMethod('post');

        $service = new BcAuthPasskeyService();

        try {
            $userId = $service->verifyLoginAssertion(
                $this->request->getData(),
                'Admin'
            );
        } catch (\RuntimeException $e) {
            AuthLoginLogService::write('login_failure', prefix: 'Admin', authSource: 'passkey', request: $this->request, detail: $e->getMessage());
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => __d('baser_core', '認証に失敗しました。')]));
        }

        $stored = $this->request->getSession()->read('BcAuthPasskey.loginChallenge.Admin') ?? [];

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

        if ($loginResult->status === 'completed') {
            $this->setLoginSuccessMessage($userId);
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['redirect_url' => $loginResult->redirect_url]));
    }

    /**
     * 登録 challenge の発行
     *
     * GET /baser/admin/bc-auth-passkey/passkeys/register_challenge
     * 認証済みセッションが必要です。
     */
    public function registerChallenge(): Response
    {
        $this->request->allowMethod('get');

        $user    = $this->Authentication->getIdentity();
        $service = new BcAuthPasskeyService();
        $data    = $service->generateRegisterChallenge($user->id, 'Admin');

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data));
    }

    /**
     * パスキーの登録（attestation 検証）
     *
     * POST /baser/admin/bc-auth-passkey/passkeys/register
     * 認証済みセッションが必要です。
     */
    public function register(): Response
    {
        $this->request->allowMethod('post');

        $user    = $this->Authentication->getIdentity();
        $service = new BcAuthPasskeyService();

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

    private function setLoginSuccessMessage(int $userId): void
    {
        /** @var \BaserCore\Model\Entity\User $user */
        $user = (new UsersService())->get($userId);
        $this->BcMessage->setInfo(__d('baser_core', 'ようこそ、{0}さん。', $user->getDisplayName()));
    }

    /**
     * 登録済みパスキー一覧
     *
     * GET /baser/admin/bc-auth-passkey/passkeys/index
     * 認証済みセッションが必要です。
     */
    public function index(): void
    {
        $user        = $this->Authentication->getIdentity();
        $service     = new BcAuthPasskeyService();
        $credentials = $service->getCredentials($user->id, 'Admin');

        $this->set(compact('credentials'));
    }

    /**
     * パスキーの削除
     *
     * POST /baser/admin/bc-auth-passkey/passkeys/delete/{id}
     * 認証済みセッションが必要です。
     */
    public function delete(int $id): Response
    {
        $this->request->allowMethod('post');

        $user    = $this->Authentication->getIdentity();
        $service = new BcAuthPasskeyService();

        if (!$service->deleteCredential($id, $user->id)) {
            $this->BcMessage->setError(__d('baser_core', '削除に失敗しました。'));
        } else {
            $this->BcMessage->setInfo(__d('baser_core', 'パスキーを削除しました。'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
