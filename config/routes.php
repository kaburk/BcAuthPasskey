<?php
declare(strict_types=1);

use Cake\Routing\RouteBuilder;

/** @var \Cake\Routing\RouteBuilder $routes */
$routes->plugin(
    'BcPasskeyAuth',
    ['path' => '/bc-passkey-auth'],
    function (RouteBuilder $builder): void {

        // Admin プレフィックス
        $builder->prefix('Admin', ['path' => '/baser/admin'], function (RouteBuilder $builder): void {
            $builder->connect('/passkeys/login_challenge',  ['controller' => 'Passkeys', 'action' => 'loginChallenge'], ['_name' => 'bc_passkey_auth_admin_login_challenge']);
            $builder->connect('/passkeys/login',            ['controller' => 'Passkeys', 'action' => 'login'], ['_name' => 'bc_passkey_auth_admin_login']);
            $builder->connect('/passkeys/register_challenge', ['controller' => 'Passkeys', 'action' => 'registerChallenge'], ['_name' => 'bc_passkey_auth_admin_register_challenge']);
            $builder->connect('/passkeys/register',         ['controller' => 'Passkeys', 'action' => 'register'], ['_name' => 'bc_passkey_auth_admin_register']);
            $builder->connect('/passkeys',                  ['controller' => 'Passkeys', 'action' => 'index']);
            $builder->connect('/passkeys/delete/{id}',      ['controller' => 'Passkeys', 'action' => 'delete'], ['id' => '\d+', 'pass' => ['id']]);
        });

        // Front プレフィックスなし（フロントエンド向け）
        $builder->connect('/passkeys/login_challenge', ['controller' => 'Passkeys', 'action' => 'loginChallenge']);
        $builder->connect('/passkeys/login',           ['controller' => 'Passkeys', 'action' => 'login']);
    }
);
