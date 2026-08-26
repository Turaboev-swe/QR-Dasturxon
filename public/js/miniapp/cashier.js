(function () {
    const M = window.MiniApp;
    const NEXT_STAGE = {
        pending: 'confirmed',
        confirmed: 'preparing',
        preparing: 'ready',
        ready: 'served',
        served: 'paid',
    };
    const STATUS_KEY = {
        pending: 'statusPending', confirmed: 'statusConfirmed', preparing: 'statusPreparing',
        ready: 'statusReady', served: 'statusServed', paid: 'statusPaid', cancelled: 'statusCancelled',
    };

    let pollHandle = null;

    function el(id) { return document.getElementById(id); }

    function applyStaticI18n() {
        const t = M.t;
        el('k-title').textContent = t('kassaTitle');
        el('k-sub').textContent = t('kassaSub');
        el('k-calls-title').textContent = t('waiterCallsTitle');
    }

    async function init() {
        applyStaticI18n();

        if (!M.initData) {
            showMessage(M.t('onlyInTelegram'));
            return;
        }

        try {
            await M.apiFetch('/api/staff/me');
            showDashboard();
        } catch (e) {
            showMessage(e.status === 403 ? M.t('notStaffMessage') : e.message);
        }
    }

    function showMessage(text) {
        el('k-message').textContent = text;
        el('k-message').classList.remove('hidden');
        el('k-dashboard').classList.remove('show');
        clearInterval(pollHandle);
    }

    function showDashboard() {
        el('k-message').classList.add('hidden');
        el('k-dashboard').classList.add('show');
        refresh();
        pollHandle = setInterval(refresh, 6000);
    }

    async function refresh() {
        try {
            const [ordersRes, callsRes] = await Promise.all([
                M.apiFetch('/api/staff/orders'),
                M.apiFetch('/api/staff/waiter-calls'),
            ]);
            renderOrders(ordersRes.orders);
            renderCalls(callsRes.waiter_calls);
        } catch (e) {
            if (e.status === 403) showMessage(M.t('notStaffMessage'));
        }
    }

    function renderOrders(orders) {
        const list = el('k-order-list');
        if (!orders.length) {
            list.innerHTML = '<div class="empty-state">' + M.t('noOrdersYet') + '</div>';
            return;
        }

        list.innerHTML = orders.map(function (order) {
            const items = order.items.map(function (i) { return i.dish.name_translations.uz + ' × ' + i.quantity; }).join('<br>');
            const isTerminal = order.status === 'paid' || order.status === 'cancelled';
            const nextStage = NEXT_STAGE[order.status];

            return '<div class="order-card status-' + order.status + '">' +
                '<div class="order-top"><span class="order-table">' + (order.table ? order.table.name || order.table.code : '') + '</span>' +
                '<span class="order-time">' + new Date(order.created_at).toLocaleTimeString().slice(0, 5) + '</span></div>' +
                '<div class="order-items">' + items + '</div>' +
                '<div class="order-foot">' +
                '<span class="status-pill">' + M.t(STATUS_KEY[order.status]) + '</span>' +
                '<div>' +
                '<b class="order-total">' + M.formatPrice(order.total_price) + '</b> ' +
                (!isTerminal && nextStage ? '<button class="k-btn" data-advance="' + order.id + '" data-to="' + nextStage + '">' + M.t('nextStage') + '</button>' : '') +
                (!isTerminal ? ' <button class="k-btn ghost" data-cancel="' + order.id + '">' + M.t('cancelOrder') + '</button>' : '') +
                '</div></div></div>';
        }).join('');

        list.querySelectorAll('[data-advance]').forEach(function (btn) {
            btn.onclick = function () { updateOrderStatus(btn.getAttribute('data-advance'), btn.getAttribute('data-to')); };
        });
        list.querySelectorAll('[data-cancel]').forEach(function (btn) {
            btn.onclick = function () { updateOrderStatus(btn.getAttribute('data-cancel'), 'cancelled'); };
        });
    }

    async function updateOrderStatus(orderId, status) {
        try {
            await M.apiFetch('/api/staff/orders/' + orderId + '/status', { method: 'PATCH', body: JSON.stringify({ status: status }) });
            M.haptic('light');
            refresh();
        } catch (e) {
            alert(e.message);
        }
    }

    function renderCalls(calls) {
        const list = el('k-calls-list');
        if (!calls.length) {
            list.innerHTML = '<div class="empty-state">' + M.t('noCallsYet') + '</div>';
            return;
        }

        list.innerHTML = calls.map(function (call) {
            const icon = call.type === 'bill' ? '🧾' : '🔔';
            const label = call.type === 'bill' ? M.t('callTypeBill') : M.t('callTypeWaiter');
            const nextAction = call.status === 'pending'
                ? '<button class="k-btn" data-ack="' + call.id + '">' + M.t('acknowledge') + '</button>'
                : '<button class="k-btn" data-resolve="' + call.id + '">' + M.t('resolve') + '</button>';

            return '<div class="call-card"><span>' + icon + ' ' + label + ' — ' + (call.table ? call.table.name || call.table.code : '') + '</span>' + nextAction + '</div>';
        }).join('');

        list.querySelectorAll('[data-ack]').forEach(function (btn) {
            btn.onclick = function () { updateCallStatus(btn.getAttribute('data-ack'), 'acknowledged'); };
        });
        list.querySelectorAll('[data-resolve]').forEach(function (btn) {
            btn.onclick = function () { updateCallStatus(btn.getAttribute('data-resolve'), 'resolved'); };
        });
    }

    async function updateCallStatus(callId, status) {
        try {
            await M.apiFetch('/api/staff/waiter-calls/' + callId + '/status', { method: 'PATCH', body: JSON.stringify({ status: status }) });
            M.haptic('light');
            refresh();
        } catch (e) {
            alert(e.message);
        }
    }

    function stop() {
        clearInterval(pollHandle);
    }

    window.MiniAppCashier = { init: init, applyStaticI18n: applyStaticI18n, stop: stop };
})();
