<?php
/**
 * パスキーログインボタン element
 *
 * AuthEntryService 経由で login.php から呼ばれます。
 *
 * @var \App\View\AppView $this
 * @var string $prefix 'Admin' | 'Front'
 */
$prefix = $prefix ?? 'Admin';
$loginUrl = $prefix === 'Admin'
  ? $this->Url->build(['plugin' => 'BcAuthPasskey', 'prefix' => 'Admin', 'controller' => 'BcAuthPasskeys', 'action' => 'login'])
  : $this->Url->build(['plugin' => 'BcAuthPasskey', 'prefix' => false, 'controller' => 'BcAuthPasskeys', 'action' => 'login']);
$challengeUrl = $prefix === 'Admin'
  ? $this->Url->build(['plugin' => 'BcAuthPasskey', 'prefix' => 'Admin', 'controller' => 'BcAuthPasskeys', 'action' => 'loginChallenge'])
  : $this->Url->build(['plugin' => 'BcAuthPasskey', 'prefix' => false, 'controller' => 'BcAuthPasskeys', 'action' => 'loginChallenge']);
$csrfToken = (string) $this->getRequest()->getAttribute('csrfToken');
?>
<?php if ($prefix === 'Admin'): ?>
<div class="submit bca-login-form-btn-group bca-login-form-btn-group--alt">
  <button
    type="button"
    id="BtnPasskeyLogin"
    class="bca-btn bca-login-alt-methods__btn bca-login-alt-methods__btn--passkey"
    data-bca-btn-type="login"
    data-login-url="<?= h($loginUrl) ?>"
    data-challenge-url="<?= h($challengeUrl) ?>"
    data-csrf-token="<?= h($csrfToken) ?>"
  >
    <span class="bca-login-alt-methods__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" role="img" aria-hidden="true">
        <path fill="currentColor" d="M17 8a5 5 0 1 0-7.7 4.2V14H8a1 1 0 0 0 0 2h1.3v1.1A1.9 1.9 0 0 0 11.2 19H13v2h2v-2h1v-2h-4.7v-1H13a1 1 0 0 0 0-2h-1.7v-1.8A5 5 0 0 0 17 8Zm-5 0a1.5 1.5 0 1 1 0 .01V8Z"/>
      </svg>
    </span>
    <span class="bca-login-alt-methods__body">
      <span class="bca-login-alt-methods__title"><?= __d('baser_core', 'パスキーでログイン') ?></span>
      <span class="bca-login-alt-methods__note"><?= __d('baser_core', 'WebAuthn / Passkey を利用') ?></span>
    </span>
  </button>
</div>
<?php else: ?>
<div class="bs-login-passkey">
  <button
    type="button"
    id="BtnPasskeyLogin"
    class="bs-btn bs-btn--passkey"
    data-login-url="<?= h($loginUrl) ?>"
    data-challenge-url="<?= h($challengeUrl) ?>"
    data-csrf-token="<?= h($csrfToken) ?>"
  >
    <?= __d('baser_core', 'パスキーでログイン') ?>
  </button>
</div>
<?php endif; ?>
