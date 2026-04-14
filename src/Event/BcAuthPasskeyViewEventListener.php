<?php
declare(strict_types=1);

namespace BcAuthPasskey\Event;

use BaserCore\Event\BcViewEventListener;
use Cake\Routing\Router;

class BcAuthPasskeyViewEventListener extends BcViewEventListener
{
    public $events = [
        'BaserCore.Users.beforeGetTemplateFileName' => ['priority' => 20],
    ];

    public function baserCoreUsersBeforeGetTemplateFileName($event): void
    {
        if (!$this->isAction('Users.Login')) {
            return;
        }

        $request = Router::getRequest();
        $prefix = (string)$request->getParam('prefix');

        if ($prefix === 'Admin') {
            $event->setData('name', 'BcAuthPasskey./plugin/BcAdminThird/Admin/Users/login');
            return;
        }

        $event->setData('name', 'BcAuthPasskey./plugin/BcFront/Users/login');
    }
}
