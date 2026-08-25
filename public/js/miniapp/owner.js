(function () {
    const M = window.MiniApp;
    let dishes = [];

    function el(id) { return document.getElementById(id); }

    function applyStaticI18n() {
        const t = M.t;
        el('o-title').textContent = t('ownerTitle');
        el('o-flash-title').textContent = t('flashEditorTitle');
        el('o-dish-label').textContent = t('dish');
        el('o-percent-label').textContent = t('discountPercent');
        el('o-portions-label').textContent = t('portions');
        el('o-minutes-label').textContent = t('minutes');
        el('o-set-btn').textContent = t('setDiscount');
        el('o-clear-btn').textContent = t('clearDiscount');
        el('o-availability-title').textContent = t('availabilityTitle');
        el('o-stats-title').textContent = t('statsTitle');
    }

    async function init() {
        applyStaticI18n();
        el('o-set-btn').onclick = setDiscount;
        el('o-clear-btn').onclick = clearDiscount;

        if (!M.initData) {
            showMessage(M.t('onlyInTelegram'));
            return;
        }

        try {
            const me = await M.apiFetch('/api/staff/me');
            if (me.staff.role !== 'admin') {
                showMessage(M.t('adminOnly'));
                return;
            }
            showDashboard();
        } catch (e) {
            showMessage(e.status === 403 ? M.t('notStaffMessage') : e.message);
        }
    }

    function showMessage(text) {
        el('o-message').textContent = text;
        el('o-message').classList.remove('hidden');
        el('o-dash').classList.remove('show');
    }

    function showDashboard() {
        el('o-message').classList.add('hidden');
        el('o-dash').classList.add('show');
        refresh();
    }

    async function refresh() {
        try {
            const [dishesRes, statsRes] = await Promise.all([
                M.apiFetch('/api/staff/admin/dishes'),
                M.apiFetch('/api/staff/admin/stats'),
            ]);
            dishes = dishesRes.dishes;
            renderDishSelect();
            renderAvailability();
            renderDiscountStatus();
            renderStats(statsRes);
        } catch (e) {
            if (e.status === 403) showMessage(M.t('adminOnly'));
        }
    }

    function renderDishSelect() {
        const select = el('o-dish-select');
        select.innerHTML = dishes.map(function (d) {
            return '<option value="' + d.id + '">' + d.name + '</option>';
        }).join('');
    }

    function renderAvailability() {
        const list = el('o-availability-list');
        list.innerHTML = dishes.map(function (d) {
            return '<div class="avail-row"><span class="avail-name">' + d.name + (!d.is_available ? ' <span class="unavail-tag">' + M.t('soldOut') + '</span>' : '') + '</span>' +
                '<div class="avail-toggle ' + (d.is_available ? 'on' : '') + '" data-dish="' + d.id + '"></div></div>';
        }).join('');

        list.querySelectorAll('.avail-toggle').forEach(function (toggle) {
            toggle.onclick = async function () {
                const dishId = toggle.getAttribute('data-dish');
                try {
                    await M.apiFetch('/api/staff/admin/dishes/' + dishId + '/availability', { method: 'PATCH' });
                    M.haptic('light');
                    refresh();
                } catch (e) {
                    alert(e.message);
                }
            };
        });
    }

    function renderDiscountStatus() {
        const active = dishes.find(function (d) { return d.discount_live; });
        el('o-discount-status').textContent = active
            ? M.t('activeDiscount') + ': ' + active.name + ' — ' + active.discount_percent + '%, ' + active.discount_portions_remaining + '/' + active.discount_portions_total
            : M.t('noActiveDiscount');
    }

    function renderStats(stats) {
        el('o-stats-body').innerHTML =
            '<div class="stat-row"><span>' + M.t('totalOrders') + '</span><b>' + stats.total_orders + '</b></div>' +
            '<div class="stat-row"><span>' + M.t('completedOrders') + '</span><b>' + stats.completed_orders + '</b></div>' +
            '<div class="stat-row"><span>' + M.t('totalRevenue') + '</span><b>' + M.formatPrice(stats.total_revenue) + '</b></div>';
    }

    async function setDiscount() {
        const dishId = el('o-dish-select').value;
        const percent = parseInt(el('o-percent').value, 10);
        const portions = parseInt(el('o-portions').value, 10);
        const minutes = parseInt(el('o-minutes').value, 10);

        try {
            await M.apiFetch('/api/staff/admin/dishes/' + dishId + '/discount', {
                method: 'POST',
                body: JSON.stringify({ percent: percent, portions: portions, minutes: minutes }),
            });
            M.haptic('success');
            refresh();
        } catch (e) {
            alert(e.message);
        }
    }

    async function clearDiscount() {
        try {
            await M.apiFetch('/api/staff/admin/discounts', { method: 'DELETE' });
            refresh();
        } catch (e) {
            alert(e.message);
        }
    }

    window.MiniAppOwner = { init: init, applyStaticI18n: applyStaticI18n };
})();
