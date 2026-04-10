<?php

declare(strict_types=1);

return [
    'BcApp' => [
        'adminNavigation' => [
            'Plugins' => [
                'menus' => [
                    'BcPasskeyAuthManage' => [
                        'title' => __d('baser_core', 'パスキー管理'),
                        'url' => [
                            'prefix' => 'Admin',
                            'plugin' => 'BcPasskeyAuth',
                            'controller' => 'Passkeys',
                            'action' => 'index',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
