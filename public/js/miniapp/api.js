window.MiniApp = (function () {
    const tg = window.Telegram && window.Telegram.WebApp;
    if (tg) { tg.ready(); tg.expand(); }
    const initData = tg ? tg.initData : '';

    let lang = 'uz';

    function t(key) {
        const dict = window.MINIAPP_I18N[lang] || window.MINIAPP_I18N.uz;
        return dict[key] !== undefined ? dict[key] : key;
    }

    function setLang(code) {
        if (window.MINIAPP_I18N[code]) lang = code;
    }

    function getLang() {
        return lang;
    }

    function formatPrice(value) {
        return Number(value).toLocaleString('uz-UZ') + " so'm";
    }

    // Every request — customer or staff — carries the same signed Telegram
    // initData. The server decides who you are (customer vs. staff, and
    // which staff) purely from your Telegram id; there is no separate
    // staff login/token to manage on the client.
    async function apiFetch(path, options) {
        options = options || {};
        const res = await fetch(path, Object.assign({}, options, {
            headers: Object.assign(
                { 'X-Telegram-Init-Data': initData, 'Content-Type': 'application/json' },
                options.headers || {},
            ),
        }));
        const body = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            const err = new Error(body.message || 'Xatolik (' + res.status + ')');
            err.status = res.status;
            throw err;
        }
        return body;
    }

    function haptic(type) {
        if (tg && tg.HapticFeedback) {
            if (type === 'success' || type === 'error' || type === 'warning') {
                tg.HapticFeedback.notificationOccurred(type);
            } else {
                tg.HapticFeedback.impactOccurred(type || 'light');
            }
        }
    }

    return {
        tg,
        initData,
        t,
        setLang,
        getLang,
        formatPrice,
        apiFetch,
        haptic,
    };
})();
