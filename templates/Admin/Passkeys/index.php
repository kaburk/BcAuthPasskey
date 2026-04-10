<?php
/**
 * @var \BaserCore\View\AppView $this
 * @var \BcPasskeyAuth\Model\Entity\PasskeyCredential[] $credentials
 */
$this->BcAdmin->setTitle(__d('baser_core', 'パスキー管理'));
?>

<div id="PasskeyIndex">
    <?php $this->BcBaser->flash() ?>

    <div class="bca-section">
        <h2 class="bca-main__heading"><?= __d('baser_core', '登録済みパスキー') ?></h2>

        <?php if (empty($credentials)): ?>
            <p><?= __d('baser_core', '登録済みのパスキーはありません。') ?></p>
        <?php else: ?>
            <table class="bca-table-listup">
                <thead>
                    <tr>
                        <th><?= __d('baser_core', '名前') ?></th>
                        <th><?= __d('baser_core', '最終利用日時') ?></th>
                        <th><?= __d('baser_core', '登録日時') ?></th>
                        <th><?= __d('baser_core', '操作') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as $credential): ?>
                        <tr>
                            <td>
                                <?= h($credential->name ?? __d('baser_core', '(名前なし)')) ?>
                            </td>
                            <td>
                                <?= $credential->last_used
                                    ? h($credential->last_used->i18nFormat('yyyy/MM/dd HH:mm'))
                                    : '—' ?>
                            </td>
                            <td>
                                <?= h($credential->created->i18nFormat('yyyy/MM/dd HH:mm')) ?>
                            </td>
                            <td>
                                <?= $this->BcAdminForm->postLink(
                                    __d('baser_core', '削除'),
                                    ['action' => 'delete', $credential->id],
                                    [
                                        'confirm' => __d('baser_core', 'このパスキーを削除してもよいですか？'),
                                        'class'   => 'bca-btn bca-btn--danger',
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="bca-section">
        <h2 class="bca-main__heading"><?= __d('baser_core', '新しいパスキーを登録する') ?></h2>
        <div id="PasskeyRegister">
            <div class="bca-control-group">
                <?= $this->BcAdminForm->label('passkey_name', __d('baser_core', 'パスキー名（任意）')) ?>
                <?= $this->BcAdminForm->control('passkey_name', [
                    'type'  => 'text',
                    'id'    => 'PasskeyName',
                    'class' => 'bca-textbox__input',
                ]) ?>
            </div>
            <button type="button" id="BtnPasskeyRegister" class="bca-btn"
                data-challenge-url="<?= $this->Url->build([
                    'plugin' => 'BcPasskeyAuth',
                    'prefix' => 'Admin',
                    'controller' => 'Passkeys',
                    'action' => 'register_challenge',
                ]) ?>"
                data-register-url="<?= $this->Url->build([
                    'plugin' => 'BcPasskeyAuth',
                    'prefix' => 'Admin',
                    'controller' => 'Passkeys',
                    'action' => 'register',
                ]) ?>">
                <?= __d('baser_core', 'パスキーを登録する') ?>
            </button>
        </div>
    </div>
</div>
