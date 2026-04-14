<?php
/**
 * baserCMS Front ログイン画面（BcAuthPasskey オーバーライド）
 *
 * bc-front の login.php にパスキーログインボタンと
 * ソーシャル認証ボタン（BcAuthSocial が有効な場合）を追加します。
 *
 * @var \App\View\AppView $this
 * @var bool $savedEnable
 */

use BcAuthCommon\Service\AuthEntryService;

$this->BcBaser->setTitle(__d('baser_core', 'ログイン'));
$this->BcBaser->js('BcAuthPasskey.bc_auth_passkey', false, ['defer' => true]);
?>

<div id="Login" class="bs-login">
  <div id="LoginInner">
    <?php $this->BcBaser->flash() ?>

    <h1 class="bs-login__title">
      <?php $this->BcBaser->contentsTitle() ?>
    </h1>
    <?= $this->BcAdminForm->create() ?>
    <div class="login-input bs-login-form-item">
      <?php echo $this->BcAdminForm->label('email', __d('baser_core', 'Eメール')) ?>
      <?= $this->BcAdminForm->control('email', [
        'type' => 'text',
        'tabindex' => 1,
        'autofocus' => true,
        'class' => 'bs-textbox__input',
      ]) ?>
    </div>
    <div class="login-input bs-login-form-item">
      <?php echo $this->BcAdminForm->label('password', __d('baser_core', 'パスワード')) ?>
      <?= $this->BcAdminForm->control('password', [
        'type' => 'password',
        'tabindex' => 2,
        'class' => 'bs-textbox__input',
      ]) ?>
    </div>
    <div class="submit bs-login-form-btn-group">
      <?= $this->BcAdminForm->button(__d('baser_core', 'ログイン'), [
        'type' => 'submit',
        'div' => false,
        'class' => 'bs-btn--login bs-btn',
        'data-bs-btn-type' => 'login',
        'id' => 'BtnLogin',
        'tabindex' => 4
      ]); ?>
    </div>
    <div class="clear login-etc bs-login-form-ctrl">
      <?php if ($savedEnable): ?>
        <div class="bs-login-form-checker">
          <?php echo $this->BcAdminForm->control('saved', [
            'type' => 'checkbox',
            'label' => __d('baser_core', 'ログイン状態を保存する'),
            'class' => 'bs-checkbox__input bs-login-form-checkbox',
            'tabindex' => 3
          ]); ?>
        </div>
      <?php endif; ?>
      <div class="bs-login-forgot-pass">
        <?php $this->BcBaser->link(__d('baser_core', 'パスワードを忘れた場合はこちら'), ['controller' => 'password_requests', 'action' => 'entry', $this->request->getParam('prefix') => true]) ?>
      </div>
    </div>
    <?= $this->BcAdminForm->end() ?>

    <?php $entries = AuthEntryService::getInstance()->getOrderedEntries('Front'); ?>
    <?php if (!empty($entries)): ?>
    <div class="bs-login-alt-methods">
      <?php foreach ($entries as $index => $entry): ?>
        <?php if ($index > 0): ?>
        <div class="bs-login-divider">
          <span><?= __d('baser_core', 'または') ?></span>
        </div>
        <?php endif; ?>
        <?= $this->element($entry['element'], ['prefix' => 'Front']) ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
