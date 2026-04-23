<?php

declare(strict_types=1);

use Cake\Utility\Hash;

$config = [
    'BcApp' => [
        'adminNavigation' => [
            'Plugins' => [
                'menus' => [
                    'BcAuthPasskeyManage' => [
                        'title' => __d('baser_core', 'パスキー管理'),
                        'url' => [
                            'prefix' => 'Admin',
                            'plugin' => 'BcAuthPasskey',
                            'controller' => 'BcAuthPasskeys',
                            'action' => 'index',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'BcAuthPasskey' => [
        // RP ID（Relying Party ID）
        // 通常は未設定で問題ありません。未設定時は現在のホスト名を利用します。
        // 例: example.com
        'rpId' => null,

        // RP 名称（Relying Party Name）
        // パスキー登録時にブラウザ / OS の UI に表示されるサービス名です。
        // 未設定時は現在のホスト名を利用します。
        // 例: baserCMS Demo Site
        'rpName' => null,
    ],
];

if (file_exists(__DIR__ . DS . 'setting_customize.php')) {
    include __DIR__ . DS . 'setting_customize.php';
    $config = Hash::merge($config, $customize_config);
}

return $config;
