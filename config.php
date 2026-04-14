<?php
return [
    'type' => 'Plugin',
    'title' => __d('baser_core', 'パスキー認証'),
    'description' => __d('baser_core', 'baserCMS の管理画面ログインに WebAuthn / Passkey 認証を追加するプラグインです。'),
    'author' => 'kaburk',
    'url' => 'https://blog.kaburk.com/',
    'adminLink' => [
        'prefix' => 'Admin',
        'plugin' => 'BcPasskeyAuth',
        'controller' => 'Passkeys',
        'action' => 'index',
    ],
    'installMessage' => __d('baser_core', 'インストール完了後に開く「パスキー管理」画面から、すぐに生体認証デバイスを登録してください。'),
];
