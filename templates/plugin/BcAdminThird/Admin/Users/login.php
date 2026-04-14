<?php
/**
 * baserCMS Admin ログイン画面（BcAuthPasskey オーバーライド）
 *
 * bc-admin-third の login.php を継承しつつ、パスキーログインボタンと
 * ソーシャル認証ボタン（BcAuthSocial が有効な場合）を追加します。
 *
 * @var \App\View\AppView $this
 * @var string $isEnableLoginCredit
 * @var bool $savedEnable
 */

use BaserCore\View\AppView;
use BcAuthCommon\Service\AuthEntryService;

$this->BcAdmin->setTitle(__d('baser_core', 'ログイン'));
$this->BcBaser->js('admin/users/login.bundle', false, [
  'defer' => true,
  'id' => 'AdminUsersLoginScript',
  'data-isEnableLoginCredit' => $isEnableLoginCredit
]);
$this->BcBaser->js('BcAuthPasskey.bc_auth_passkey', false, ['defer' => true]);
?>

<div id="Login" class="bca-login">
  <div id="LoginInner">
    <?php $this->BcBaser->flash() ?>

    <h1 class="bca-login__title">
      <?php echo $this->BcBaser->getImg('admin/logo_large.png', ['alt' => $this->BcBaser->getContentsTitle(), 'class' => 'bca-login__logo']) ?>
    </h1>

    <?= $this->BcAdminForm->create() ?>
    <div class="login-input bca-login-form-item">
      <?php echo $this->BcAdminForm->label('email', __d('baser_core', 'Eメール')) ?>
      <?= $this->BcAdminForm->control('email', ['type' => 'text', 'tabindex' => 1, 'autofocus' => true]) ?>
    </div>
    <div class="login-input bca-login-form-item">
      <?php echo $this->BcAdminForm->label('password', __d('baser_core', 'パスワード')) ?>
      <?= $this->BcAdminForm->control('password', ['type' => 'password', 'tabindex' => 2]) ?>
    </div>
    <div class="submit bca-login-form-btn-group">
      <?= $this->BcAdminForm->button(__d('baser_core', 'ログイン'), [
        'type' => 'submit',
        'div' => false,
        'class' => 'bca-btn--login bca-btn',
        'data-bca-btn-type' => 'login',
        'id' => 'BtnLogin',
        'tabindex' => 4
      ]); ?>
    </div>
    <div class="clear login-etc bca-login-form-ctrl">
      <?php if ($savedEnable): ?>
        <div class="bca-login-form-checker">
          <?php echo $this->BcAdminForm->control('saved', [
            'type' => 'checkbox',
            'label' => __d('baser_core', 'ログイン状態を保存する'),
            'class' => 'bca-checkbox__input bca-login-form-checkbox ',
            'tabindex' => 3
          ]); ?>
        </div>
      <?php endif; ?>
      <div class="bca-login-forgot-pass">
        <?php $this->BcBaser->link(__d('baser_core', 'パスワードを忘れた場合はこちら'), ['controller' => 'password_requests', 'action' => 'entry', $this->request->getParam('prefix') => true]) ?>
      </div>
    </div>
    <?= $this->BcAdminForm->end() ?>

    <?php $entries = AuthEntryService::getInstance()->getOrderedEntries('Admin'); ?>
    <?php if (!empty($entries)): ?>
    <div class="bca-login-alt-methods">
      <?php foreach ($entries as $index => $entry): ?>
        <?php if ($index > 0): ?>
        <div class="bca-login-divider">
          <span><?= __d('baser_core', 'または') ?></span>
        </div>
        <?php endif; ?>
        <?= $this->element($entry['element'], ['prefix' => 'Admin']) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
