<?php
declare(strict_types=1);

use Cake\Routing\RouteBuilder;

/** @var \Cake\Routing\RouteBuilder $routes */

$routes->prefix('Admin', ['path' => '/baser/admin'], function (RouteBuilder $routes): void {
    $routes->plugin('BcAuthPasskey', ['path' => '/bc-auth-passkey'], function (RouteBuilder $builder): void {
        $builder->connect('/passkeys/login_challenge', ['controller' => 'BcAuthPasskeys', 'action' => 'loginChallenge'], ['_name' => 'bc_auth_passkey_admin_login_challenge']);
        $builder->connect('/passkeys/login', ['controller' => 'BcAuthPasskeys', 'action' => 'login'], ['_name' => 'bc_auth_passkey_admin_login']);
        $builder->connect('/passkeys/register_challenge', ['controller' => 'BcAuthPasskeys', 'action' => 'registerChallenge'], ['_name' => 'bc_auth_passkey_admin_register_challenge']);
        $builder->connect('/passkeys/register', ['controller' => 'BcAuthPasskeys', 'action' => 'register'], ['_name' => 'bc_auth_passkey_admin_register']);
        $builder->connect('/passkeys', ['controller' => 'BcAuthPasskeys', 'action' => 'index']);
        $builder->connect('/passkeys/delete/{id}', ['controller' => 'BcAuthPasskeys', 'action' => 'delete'], ['id' => '\d+', 'pass' => ['id']]);
    });
});

$routes->plugin('BcAuthPasskey', ['path' => '/bc-auth-passkey'], function (RouteBuilder $builder): void {
    $builder->connect('/passkeys/login_challenge', ['controller' => 'BcAuthPasskeys', 'action' => 'loginChallenge'], ['_name' => 'bc_auth_passkey_front_login_challenge']);
    $builder->connect('/passkeys/login', ['controller' => 'BcAuthPasskeys', 'action' => 'login'], ['_name' => 'bc_auth_passkey_front_login']);
});
