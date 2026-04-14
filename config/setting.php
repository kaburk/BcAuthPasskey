<?php

declare(strict_types=1);

return [
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
];
