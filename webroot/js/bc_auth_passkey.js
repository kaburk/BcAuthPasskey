/**
 * パスキーログイン / 登録のフロントエンドスクリプト
 *
 * このファイルは WebAuthn API を呼び出すクライアント側コードの骨格です。
 * web-auth/webauthn-lib に合わせたサーバー側の実装が完了した後に
 * 実際の PublicKeyCredential オプション変換処理を追加します。
 *
 * TODO: Base64URL ユーティリティ、navigator.credentials.get / create の呼び出し
 */

(function () {
    'use strict';

    function setPasskeyLoginPending(btn, pending) {
        if (!btn) {
            return;
        }

        const body = document.body;
        const title = btn.querySelector('.bca-login-alt-methods__title');

        if (!btn.dataset.originalDisabledText) {
            btn.dataset.originalDisabledText = title ? title.textContent : btn.textContent;
        }

        btn.disabled = pending;
        btn.setAttribute('aria-busy', pending ? 'true' : 'false');
        btn.dataset.loading = pending ? 'true' : 'false';
        btn.style.cursor = pending ? 'progress' : '';
        if (body) {
            body.style.cursor = pending ? 'progress' : '';
        }

        if (title) {
            title.textContent = pending ? '認証中...' : btn.dataset.originalDisabledText;
            return;
        }

        btn.textContent = pending ? '認証中...' : btn.dataset.originalDisabledText;
    }

    function getCsrfToken(rootElement) {
        if (rootElement && rootElement.dataset && rootElement.dataset.csrfToken) {
            return rootElement.dataset.csrfToken;
        }
        const meta = document.querySelector('meta[name="csrfToken"]');
        if (meta && meta.content) {
            return meta.content;
        }
        const input = document.querySelector('input[name="_csrfToken"]');
        return input ? input.value : '';
    }

    function showMessage(element, type, message) {
        if (!element) {
            if (message && type === 'error') console.error(message);
            return;
        }
        element.textContent = message || '';
        element.classList.remove('is-info', 'is-error');
        if (!message) {
            return;
        }
        element.classList.add(type === 'error' ? 'is-error' : 'is-info');
    }

    // --- ユーティリティ ---

    /**
     * Base64URL 文字列を ArrayBuffer に変換する
     * @param {string} base64url
     * @returns {ArrayBuffer}
     */
    function base64urlToBuffer(base64url) {
        const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
        const binary = atob(base64);
        const buffer = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            buffer[i] = binary.charCodeAt(i);
        }
        return buffer.buffer;
    }

    /**
     * ArrayBuffer を Base64URL 文字列に変換する
     * @param {ArrayBuffer} buffer
     * @returns {string}
     */
    function bufferToBase64url(buffer) {
        const bytes  = new Uint8Array(buffer);
        let binary   = '';
        for (const b of bytes) binary += String.fromCharCode(b);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    // --- ログイン ---

    /**
     * Base64URL なバイナリフィールドを ArrayBuffer に再帰変換する helper
     * WebAuthn ライブラリが返す JSON の challenge / id / allowCredentials 等を
     * browser API が要求する ArrayBuffer 形式に変換するために使用します。
     */
    function convertPublicKeyOptions(options) {
        const converted = Object.assign({}, options);
        if (converted.challenge) {
            converted.challenge = base64urlToBuffer(converted.challenge);
        }
        if (Array.isArray(converted.allowCredentials)) {
            converted.allowCredentials = converted.allowCredentials.map(function (cred) {
                return Object.assign({}, cred, {id: base64urlToBuffer(cred.id)});
            });
        }
        return converted;
    }

    function convertCreationOptions(options) {
        const converted = Object.assign({}, options);
        if (converted.challenge) {
            converted.challenge = base64urlToBuffer(converted.challenge);
        }
        if (converted.user && converted.user.id) {
            converted.user = Object.assign({}, converted.user, {
                id: base64urlToBuffer(converted.user.id),
            });
        }
        if (Array.isArray(converted.excludeCredentials)) {
            converted.excludeCredentials = converted.excludeCredentials.map(function (cred) {
                return Object.assign({}, cred, {id: base64urlToBuffer(cred.id)});
            });
        }
        return converted;
    }

    async function passkeyLogin(btn) {
        if (btn.disabled) {
            return;
        }

        const challengeUrl = btn.dataset.challengeUrl;
        const loginUrl     = btn.dataset.loginUrl;
        const csrfToken    = getCsrfToken(btn);

        setPasskeyLoginPending(btn, true);

        if (!navigator.credentials || !navigator.credentials.get) {
            console.error('このブラウザはパスキーログインに対応していません');
            setPasskeyLoginPending(btn, false);
            return;
        }

        const challengeRes = await fetch(challengeUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? {'X-CSRF-Token': csrfToken} : {}),
            },
            credentials: 'same-origin',
        });
        if (!challengeRes.ok) {
            console.error('challenge 取得に失敗しました');
            setPasskeyLoginPending(btn, false);
            return;
        }
        const { publicKey } = await challengeRes.json();
        const publicKeyCredentialRequestOptions = convertPublicKeyOptions(publicKey);

        let assertion;
        try {
            assertion = await navigator.credentials.get({ publicKey: publicKeyCredentialRequestOptions });
        } catch (err) {
            // ユーザーキャンセルは正常系
            if (err.name !== 'NotAllowedError') console.error(err);
            setPasskeyLoginPending(btn, false);
            return;
        }

        const assertionPayload = {
            id:       assertion.id,
            rawId:    bufferToBase64url(assertion.rawId),
            type:     assertion.type,
            response: {
                clientDataJSON:    bufferToBase64url(assertion.response.clientDataJSON),
                authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
                signature:         bufferToBase64url(assertion.response.signature),
                userHandle:        assertion.response.userHandle
                    ? bufferToBase64url(assertion.response.userHandle)
                    : null,
            },
        };

        const loginRes = await fetch(loginUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? {'X-CSRF-Token': csrfToken} : {}),
            },
            credentials: 'same-origin',
            body:    JSON.stringify(assertionPayload),
        });

        if (loginRes.ok) {
            const data = await loginRes.json().catch(() => ({}));
            window.location.href = data.redirect_url || '/baser/admin';
        } else {
            const data = await loginRes.json().catch(() => ({}));
            console.error('ログインに失敗しました:', data.message || '');
            setPasskeyLoginPending(btn, false);
        }
    }

    // --- 登録 ---

    async function passkeyRegister(name, registerChallengeUrl, registerUrl, csrfToken, messageElement) {
        const challengeUrl = registerChallengeUrl || '/baser/admin/bc-auth-passkey/passkeys/register_challenge';
        const postUrl      = registerUrl           || '/baser/admin/bc-auth-passkey/passkeys/register';

        showMessage(messageElement, 'info', '');

        const challengeRes = await fetch(challengeUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? {'X-CSRF-Token': csrfToken} : {}),
            },
            credentials: 'same-origin',
        });
        if (!challengeRes.ok) {
            const data = await challengeRes.json().catch(() => ({}));
            showMessage(messageElement, 'error', data.message || '登録 challenge の取得に失敗しました。');
            return;
        }
        const { publicKey } = await challengeRes.json();
        const publicKeyCredentialCreationOptions = convertCreationOptions(publicKey);

        let attestation;
        try {
            attestation = await navigator.credentials.create({ publicKey: publicKeyCredentialCreationOptions });
        } catch (err) {
            if (err.name !== 'NotAllowedError') {
                console.error(err);
                showMessage(messageElement, 'error', 'パスキー登録の開始に失敗しました。');
            }
            return;
        }

        const attestationPayload = {
            id:    attestation.id,
            rawId: bufferToBase64url(attestation.rawId),
            type:  attestation.type,
            name:  name || '',
            response: {
                clientDataJSON:    bufferToBase64url(attestation.response.clientDataJSON),
                attestationObject: bufferToBase64url(attestation.response.attestationObject),
                transports:        attestation.response.getTransports
                    ? attestation.response.getTransports()
                    : [],
            },
        };

        const registerRes = await fetch(postUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? {'X-CSRF-Token': csrfToken} : {}),
            },
            credentials: 'same-origin',
            body:    JSON.stringify(attestationPayload),
        });

        if (registerRes.ok) {
            showMessage(messageElement, 'info', 'パスキーを登録しました。画面を更新します。');
            window.location.reload();
        } else {
            const data = await registerRes.json().catch(() => ({}));
            showMessage(messageElement, 'error', data.message || '登録に失敗しました。');
            console.error('登録に失敗しました:', data.message || '');
        }
    }

    // --- イベント登録 ---

    document.addEventListener('DOMContentLoaded', function () {
        // ログインボタン（ログイン画面に設置）
        const loginBtn = document.getElementById('BtnPasskeyLogin');
        if (loginBtn) {
            loginBtn.addEventListener('click', function () {
                passkeyLogin(loginBtn);
            });
        }

        // 登録ボタン（パスキー管理画面に設置）
        const registerBtn = document.getElementById('BtnPasskeyRegister');
        if (registerBtn) {
            registerBtn.addEventListener('click', function () {
                const registerRoot = document.getElementById('PasskeyRegister');
                const nameInput = document.getElementById('PasskeyName');
                const messageElement = document.getElementById('PasskeyRegisterMessage');
                const challengeUrl = registerRoot ? registerRoot.dataset.challengeUrl : registerBtn.dataset.challengeUrl;
                const registerUrl  = registerRoot ? registerRoot.dataset.registerUrl : registerBtn.dataset.registerUrl;
                const csrfToken = getCsrfToken(registerRoot || registerBtn);
                passkeyRegister(nameInput ? nameInput.value : '', challengeUrl, registerUrl, csrfToken, messageElement);
            });
        }
    });
}());
