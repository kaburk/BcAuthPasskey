<?php
return [
    'type' => 'Plugin',
    'title' => __d('baser_core', 'パスキー認証'),
    'description' => __d('baser_core', 'baserCMS の管理画面ログインに WebAuthn / Passkey 認証を追加するプラグインです。'),
    'author' => 'baserCMS',
    'url' => 'https://basercms.net/',
    'adminLink' => [
        'prefix' => 'Admin',
        'plugin' => 'BcPasskeyAuth',
        'controller' => 'Passkeys',
        'action' => 'index',
    ],
    'installMessage' => __d('baser_core', 'インストール完了後、メニューの「パスキー管理」から生体認証デバイスを登録してください。'),
];
