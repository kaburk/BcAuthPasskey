<?php
/**
 * @var \BaserCore\View\AppView $this
 * @var \BcAuthPasskey\Model\Entity\PasskeyCredential[] $credentials
 */
$csrfToken = (string) $this->request->getAttribute('csrfToken');
$this->BcAdmin->setTitle(__d('baser_core', 'パスキー管理'));
$this->BcBaser->js('BcAuthPasskey.bc_auth_passkey', false, ['defer' => true]);
?>

<div id="PasskeyIndex">
    <?php $this->BcBaser->flash() ?>

    <section class="bca-section" data-bca-section-type="type1">
        <p><?= __d('baser_core', '初回導入時は、下の「パスキーを登録する」からこの端末の生体認証デバイスを登録してください。') ?></p>
    </section>

    <section class="bca-section" data-bca-section-type="type1">
        <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '登録済みパスキー') ?></h2>

        <?php if (empty($credentials)): ?>
            <p class="bca-passkey-index__empty"><?= __d('baser_core', '登録済みのパスキーはありません。') ?></p>
        <?php else: ?>
            <table class="bca-table-listup">
                <thead class="bca-table-listup__thead">
                    <tr>
                        <th class="bca-table-listup__thead-th"><?= __d('baser_core', 'パスキー名') ?></th>
                        <th class="bca-table-listup__thead-th"><?= __d('baser_core', '最終利用日時') ?></th>
                        <th class="bca-table-listup__thead-th"><?= __d('baser_core', '登録日時') ?></th>
                        <th class="bca-table-listup__thead-th"><?= __d('baser_core', '操作') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as $credential): ?>
                        <tr>
                            <td class="bca-table-listup__tbody-td">
                                <?= h($credential->name ?? __d('baser_core', '(名前なし)')) ?>
                            </td>
                            <td class="bca-table-listup__tbody-td">
                                <?= $credential->last_used
                                    ? h($credential->last_used->i18nFormat('yyyy/MM/dd HH:mm'))
                                    : '—' ?>
                            </td>
                            <td class="bca-table-listup__tbody-td">
                                <?= h($credential->created->i18nFormat('yyyy/MM/dd HH:mm')) ?>
                            </td>
                            <td class="bca-table-listup__tbody-td bca-passkey-index__actions">
                                <?= $this->BcAdminForm->postLink(
                                    __d('baser_core', '削除'),
                                    ['action' => 'delete', $credential->id],
                                    [
                                        'block'   => true,
                                        'confirm' => __d('baser_core', 'このパスキーを削除してもよいですか？'),
                                        'class'   => 'bca-btn bca-actions__item',
                                        'data-bca-btn-type' => 'delete',
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="bca-section" data-bca-section-type="form-group">
        <h2 class="bca-main__heading" data-bca-heading-size="lg"><?= __d('baser_core', '新しいパスキーを登録する') ?></h2>
        <div id="PasskeyRegister"
            class="bca-passkey-index__register"
            data-challenge-url="<?= h($this->Url->build(['plugin' => 'BcAuthPasskey', 'prefix' => 'Admin', 'controller' => 'BcAuthPasskeys', 'action' => 'registerChallenge'])) ?>"
            data-register-url="<?= h($this->Url->build(['plugin' => 'BcAuthPasskey', 'prefix' => 'Admin', 'controller' => 'BcAuthPasskeys', 'action' => 'register'])) ?>"
            data-csrf-token="<?= h($csrfToken) ?>">
            <table class="form-table bca-form-table" data-bca-table-type="type2">
                <tbody>
                <tr>
                    <th class="col-head bca-form-table__label">
                        <?= $this->BcAdminForm->label('passkey_name', __d('baser_core', 'パスキー名（任意）')) ?>
                    </th>
                    <td class="col-input bca-form-table__input">
                        <div class="bca-textbox bca-passkey-index__textbox">
                            <?= $this->BcAdminForm->text('passkey_name', [
                                'id' => 'PasskeyName',
                                'class' => 'bca-textbox__input bca-passkey-index__name-input',
                                'placeholder' => __d('baser_core', '例: MacBook Touch ID'),
                                'autocomplete' => 'off',
                            ]) ?>
                        </div>
                        <p class="bca-passkey-index__help"><?= __d('baser_core', 'あとから識別しやすい名前を付けられます。未入力でも登録できます。') ?></p>
                    </td>
                </tr>
                </tbody>
            </table>
            <div class="bca-actions">
                <div class="bca-actions__before"></div>
                <div class="bca-actions__main">
                    <button type="button" id="BtnPasskeyRegister" class="bca-btn bca-actions__item" data-bca-btn-type="save">
                        <?= __d('baser_core', 'パスキーを登録する') ?>
                    </button>
                </div>
                <div class="bca-actions__sub"></div>
            </div>
            <p id="PasskeyRegisterMessage" class="bca-passkey-index__message" aria-live="polite"></p>
        </div>
    </section>
</div>
<?= $this->fetch('postLink') ?>
