(function () {
    const M = window.MiniApp;
    const state = {
        restaurant: null,
        table: null,
        categories: [],
        activeCategory: 'all',
        dishesById: {},
        cart: {},
        flashDish: null,
        flashInterval: null,
        demo: false,
    };

    const CATEGORY_EMOJI = { asosiy: '🍛', main: '🍛', "sho'rva": '🍲', soup: '🍲', salat: '🥗', salad: '🥗', ichimliklar: '🥤', drinks: '🥤' };

    function el(id) { return document.getElementById(id); }

    function showStatus(text, isError) {
        const s = el('c-status');
        s.textContent = text;
        s.classList.remove('hidden');
        s.classList.toggle('error', Boolean(isError));
    }
    function hideStatus() { el('c-status').classList.add('hidden'); }

    function applyStaticI18n() {
        const t = M.t;
        el('c-verified-text').textContent = t('verifiedBadge');
        el('c-waiter-btn').textContent = '🔔 ' + t('waiterBtn');
        el('c-bill-btn').textContent = '🧾 ' + t('billBtn');
        el('c-chef-label').textContent = t('chefLabel');
        el('c-chef-title').textContent = t('chefTitle');
        el('c-menu-label').textContent = t('menuLabel');
        el('c-menu-title').textContent = t('menuTitle');
        el('c-reviews-label').textContent = t('reviewsLabel');
        el('c-reviews-title').textContent = t('reviewsTitle');
        el('c-leave-review-btn').textContent = t('leaveReview');
        el('c-place-order').textContent = t('viewOrder');
        el('c-checkout-title').textContent = t('checkoutTitle');
        el('c-checkout-table-label').textContent = t('table') + ':';
        el('c-checkout-total-label').textContent = t('total');
        el('c-confirm-order').textContent = t('confirmOrder');
        el('c-back-to-menu').textContent = t('backToMenu');
        el('c-new-order-btn').textContent = t('newOrder');
        el('sos-title').textContent = t('sosTitle');
        el('sos-body').textContent = t('sosBody');
        el('sos-police-label').textContent = t('sosPolice');
        el('sos-ambulance-label').textContent = t('sosAmbulance');
        el('sos-tourist-label').textContent = t('sosTourist');
        el('sos-close').textContent = t('close');
        el('dm-add-btn-label').textContent = t('addToCart');
        renderCategoryTabs();
        renderDishes();
        renderReviews();
    }

    async function init() {
        if (!M.initData) {
            showStatus(M.t('onlyInTelegram'), true);
            return;
        }

        showStatus(M.t('checkingTable'));

        let session;
        try {
            session = await M.apiFetch('/api/session', { method: 'POST' });
        } catch (e) {
            await enterDemoMode(e);
            return;
        }

        M.setLang(session.language);
        renderLangRow();
        state.table = session.table;

        el('c-table-badge').textContent = session.table.name || (M.t('table') + ' ' + session.table.code);

        showStatus(M.t('loadingMenu'));

        try {
            const menu = await M.apiFetch('/api/menu?lang=' + M.getLang());
            renderMenu(menu);
            applyStaticI18n();
            hideStatus();
        } catch (e) {
            showStatus(e.message, true);
        }
    }

    // No start_param (e.g. opened via the plain ☰ menu button instead of a
    // QR/startapp link) — show a read-only demo view of the menu instead of
    // a dead-end error. Ordering/waiter/bill/review all require a real
    // table, so their controls stay hidden here; the server independently
    // rejects those endpoints without a valid start_param either way.
    async function enterDemoMode(sessionError) {
        state.demo = true;
        state.table = null;
        renderLangRow();

        el('c-table-badge').textContent = M.t('demoBadge');

        showStatus(M.t('loadingMenu'));

        try {
            const menu = await M.apiFetch('/api/menu?lang=' + M.getLang());
            renderMenu(menu);
            applyStaticI18n();
            el('c-waiter-btn').classList.add('hidden');
            el('c-bill-btn').classList.add('hidden');
            el('c-leave-review-btn').classList.add('hidden');
            const banner = el('c-demo-banner');
            banner.textContent = M.t('demoBannerText');
            banner.classList.remove('hidden');
            hideStatus();
        } catch (e) {
            showStatus(sessionError.message, true);
        }
    }

    function renderLangRow() {
        const row = el('c-lang-row');
        row.innerHTML = '';
        [['uz', '🇺🇿'], ['en', '🇬🇧'], ['ru', '🇷🇺'], ['ko', '🇰🇷'], ['fr', '🇫🇷'], ['zh', '🇨🇳']].forEach(function (pair) {
            const btn = document.createElement('button');
            btn.className = 'lang-btn' + (pair[0] === M.getLang() ? ' active' : '');
            btn.textContent = pair[1] + ' ' + pair[0].toUpperCase();
            btn.onclick = function () { switchLanguage(pair[0]); };
            row.appendChild(btn);
        });
    }

    async function switchLanguage(code) {
        if (code === M.getLang()) return;
        M.setLang(code);
        renderLangRow();
        try {
            const menu = await M.apiFetch('/api/menu?lang=' + code);
            renderMenu(menu);
            applyStaticI18n();
        } catch (e) {
            showStatus(e.message, true);
        }
    }

    function renderMenu(menu) {
        state.restaurant = menu.restaurant;
        document.getElementById('c-restaurant-short-name').textContent = menu.restaurant.name;

        const nameEl = el('c-restaurant-name');
        nameEl.textContent = menu.restaurant.name;
        nameEl.classList.remove('hidden');

        const badgesRow = el('c-badges-row');
        badgesRow.innerHTML = '';
        if (menu.restaurant.is_verified) {
            const b = document.createElement('span');
            b.id = 'c-verified-text';
            b.className = 'pill badge-verified';
            b.textContent = M.t('verifiedBadge');
            badgesRow.appendChild(b);
        } else {
            // keep an (empty) element so applyStaticI18n's lookup never throws
            const b = document.createElement('span');
            b.id = 'c-verified-text';
            b.style.display = 'none';
            badgesRow.appendChild(b);
        }
        if (menu.restaurant.badge_text) {
            const b = document.createElement('span');
            b.className = 'pill badge-featured';
            b.textContent = menu.restaurant.badge_text;
            badgesRow.appendChild(b);
        }

        const ratingRow = el('c-rating-row');
        if (menu.restaurant.rating) {
            ratingRow.innerHTML = '<span class="stars">★★★★★</span> ' + menu.restaurant.rating.toFixed(1) +
                ' · ' + menu.restaurant.reviews_count;
            ratingRow.classList.remove('hidden');
        } else {
            ratingRow.classList.add('hidden');
        }

        const chefSection = el('c-chef-section');
        if (menu.restaurant.chef) {
            const chef = menu.restaurant.chef;
            el('c-chef-card').innerHTML =
                '<div class="chef-avatar">' + chef.name.trim().charAt(0) + '</div>' +
                '<div>' +
                '<div class="chef-name">' + chef.name + '</div>' +
                '<div class="chef-meta">' + [chef.title, chef.experience_years ? chef.experience_years + ' ' + M.t('yearsExperience') : null].filter(Boolean).join(' · ') + '</div>' +
                (chef.specialty ? '<div class="chef-specialty">' + chef.specialty + '</div>' : '') +
                (chef.tier_badge ? '<span class="chef-tier">🏅 ' + chef.tier_badge + '</span>' : '') +
                '</div>';
            chefSection.classList.remove('hidden');
        } else {
            chefSection.classList.add('hidden');
        }

        state.recentReviews = menu.restaurant.recent_reviews || [];

        state.categories = menu.categories;
        state.dishesById = {};
        state.flashDish = null;
        menu.categories.forEach(function (category) {
            category.dishes.forEach(function (dish) {
                state.dishesById[dish.id] = dish;
                if (dish.discount) state.flashDish = dish;
            });
        });

        renderFlashBanner();
        renderCategoryTabs();
        renderDishes();
        renderReviews();
        el('c-loaded-content').classList.remove('hidden');
    }

    function renderFlashBanner() {
        clearInterval(state.flashInterval);
        const banner = el('c-flash-banner');

        if (!state.flashDish) {
            banner.classList.add('hidden');
            return;
        }

        banner.classList.remove('hidden');
        const dish = state.flashDish;

        function tick() {
            const remainingMs = new Date(dish.discount.ends_at).getTime() - Date.now();
            if (remainingMs <= 0) {
                banner.classList.add('hidden');
                clearInterval(state.flashInterval);
                return;
            }
            const totalSeconds = Math.floor(remainingMs / 1000);
            const m = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
            const s = String(totalSeconds % 60).padStart(2, '0');
            banner.innerHTML =
                '<div class="flash-top">' +
                '<span class="flash-fire">🔥</span>' +
                '<div><div class="flash-title">' + M.t('flashTitle') + '</div>' +
                '<div class="flash-sub">' + dish.name + ' — ' + dish.discount.percent + '%</div></div>' +
                '<div class="flash-timer">' + m + ':' + s + '</div>' +
                '</div>' +
                '<div class="flash-bottom">' + dish.discount.portions_remaining + '/' + dish.discount.portions_total + ' ' + M.t('portionsLeft') + '</div>';
        }

        tick();
        state.flashInterval = setInterval(tick, 1000);
    }

    function renderCategoryTabs() {
        const wrap = el('c-category-tabs');
        wrap.innerHTML = '';

        const allTab = document.createElement('button');
        allTab.className = 'category-tab' + (state.activeCategory === 'all' ? ' active' : '');
        allTab.textContent = M.t('allCategory');
        allTab.onclick = function () { state.activeCategory = 'all'; renderCategoryTabs(); renderDishes(); };
        wrap.appendChild(allTab);

        state.categories.forEach(function (category) {
            const tab = document.createElement('button');
            tab.className = 'category-tab' + (state.activeCategory === category.id ? ' active' : '');
            tab.textContent = category.name;
            tab.onclick = function () { state.activeCategory = category.id; renderCategoryTabs(); renderDishes(); };
            wrap.appendChild(tab);
        });
    }

    function emojiFor(categoryName) {
        return CATEGORY_EMOJI[(categoryName || '').toLowerCase()] || '🍽️';
    }

    function renderDishes() {
        const list = el('c-menu-list');
        list.innerHTML = '';

        state.categories.forEach(function (category) {
            if (state.activeCategory !== 'all' && state.activeCategory !== category.id) return;

            category.dishes.forEach(function (dish) {
                const row = document.createElement('div');
                row.className = 'dish';

                const thumb = document.createElement('div');
                thumb.className = 'dish-thumb';
                thumb.textContent = emojiFor(category.name);
                thumb.onclick = function () { openDishModal(dish.id); };
                row.appendChild(thumb);

                const info = document.createElement('div');
                info.className = 'dish-info';
                info.onclick = function () { openDishModal(dish.id); };
                const priceHtml = dish.discount
                    ? '<span class="old-price">' + M.formatPrice(dish.discount.original_price) + '</span>' +
                      '<span class="dish-price discounted">' + M.formatPrice(dish.discount.price) + '</span>'
                    : '<span class="dish-price">' + M.formatPrice(dish.price) + '</span>';
                info.innerHTML =
                    '<div class="dish-name">' + dish.name + '</div>' +
                    (dish.allergens && dish.allergens.length
                        ? dish.allergens.map(function (a) { return '<span class="dish-allergen">' + a.charAt(0).toUpperCase() + a.slice(1) + '</span>'; }).join(' ')
                        : '') +
                    '<div>' + priceHtml + '</div>';
                row.appendChild(info);

                const quantity = state.cart[dish.id] || 0;

                if (quantity > 0) {
                    const controls = document.createElement('div');
                    controls.className = 'qty-controls';
                    const minus = document.createElement('button');
                    minus.textContent = '−';
                    minus.onclick = function (e) { e.stopPropagation(); changeQuantity(dish.id, -1); };
                    const count = document.createElement('span');
                    count.textContent = String(quantity);
                    const plus = document.createElement('button');
                    plus.textContent = '+';
                    plus.onclick = function (e) { e.stopPropagation(); changeQuantity(dish.id, 1); };
                    controls.appendChild(minus);
                    controls.appendChild(count);
                    controls.appendChild(plus);
                    row.appendChild(controls);
                } else if (!state.demo) {
                    const addBtn = document.createElement('button');
                    addBtn.className = 'add-btn';
                    addBtn.textContent = '+';
                    addBtn.onclick = function (e) { e.stopPropagation(); changeQuantity(dish.id, 1); };
                    row.appendChild(addBtn);
                }

                list.appendChild(row);
            });
        });
    }

    function changeQuantity(dishId, delta) {
        if (state.demo) return;
        const current = state.cart[dishId] || 0;
        const next = Math.max(0, Math.min(50, current + delta));
        state.cart[dishId] = next;
        renderDishes();
        updateCartBar();
        M.haptic('light');
    }

    function updateCartBar() {
        let count = 0;
        let total = 0;
        Object.keys(state.cart).forEach(function (dishId) {
            const quantity = state.cart[dishId];
            if (quantity > 0) {
                const dish = state.dishesById[dishId];
                const price = dish.discount ? dish.discount.price : Number(dish.price);
                count += quantity;
                total += quantity * price;
            }
        });

        const bar = el('c-cart-bar');
        if (count > 0) {
            el('c-cart-summary').textContent = count + ' ' + M.t('cartSummary') + ' — ' + M.formatPrice(total);
            bar.classList.add('visible');
        } else {
            bar.classList.remove('visible');
        }
    }

    function openDishModal(dishId) {
        const dish = state.dishesById[dishId];
        el('dm-name').textContent = dish.name;
        el('dm-description').textContent = dish.description || '';
        el('dm-description').classList.toggle('hidden', !dish.description);
        el('dm-ingredients-label').textContent = M.t('ingredientsLabel') + ':';
        el('dm-ingredients').textContent = dish.ingredients || '—';
        el('dm-ingredients-row').classList.toggle('hidden', !dish.ingredients);

        const priceEl = el('dm-price');
        priceEl.innerHTML = dish.discount
            ? '<span class="old-price">' + M.formatPrice(dish.discount.original_price) + '</span> <span class="dish-price discounted">' + M.formatPrice(dish.discount.price) + '</span>'
            : '<span class="dish-price">' + M.formatPrice(dish.price) + '</span>';

        const tasteWrap = el('dm-taste');
        if (dish.taste) {
            tasteWrap.classList.remove('hidden');
            tasteWrap.innerHTML = [
                ['tasteSpicy', dish.taste.spicy],
                ['tasteSweet', dish.taste.sweet],
                ['tasteSalty', dish.taste.salty],
            ].map(function (pair) {
                return '<div class="taste-row"><span>' + M.t(pair[0]) + '</span><div class="taste-track"><div class="taste-fill" style="width:' + pair[1] + '%;"></div></div></div>';
            }).join('');
        } else {
            tasteWrap.classList.add('hidden');
        }

        el('dm-tags').innerHTML = (dish.allergens || []).map(function (a) {
            return '<span class="dish-allergen">⚠ ' + a.charAt(0).toUpperCase() + a.slice(1) + '</span>';
        }).join('');

        el('dm-add-btn').classList.toggle('hidden', state.demo);
        el('dm-add-btn').onclick = function () { changeQuantity(dish.id, 1); closeDishModal(); };
        el('dish-modal-overlay').classList.add('show');
    }
    function closeDishModal() { el('dish-modal-overlay').classList.remove('show'); }

    function renderReviews() {
        const list = el('c-reviews-list');
        const reviews = state.recentReviews || [];
        if (!reviews.length) {
            list.innerHTML = '<p class="muted">' + M.t('noReviewsYet') + '</p>';
            return;
        }
        list.innerHTML = reviews.map(function (r) {
            return '<div class="review">' +
                '<div class="review-top"><span class="review-name">' + r.name + '</span><span>' + '★'.repeat(r.rating) + '</span></div>' +
                (r.comment ? '<div class="review-text">' + r.comment + '</div>' : '') +
                '</div>';
        }).join('');
    }

    async function openReviewForm() {
        const select = el('review-order-select');
        select.innerHTML = '';

        let orders;
        try {
            orders = (await M.apiFetch('/api/orders')).orders;
        } catch (e) {
            showStatus(e.message, true);
            return;
        }

        const reviewable = orders.filter(function (o) {
            return (o.status === 'served' || o.status === 'paid') && !o.review;
        });

        if (!reviewable.length) {
            el('review-form-body').classList.add('hidden');
            el('review-form-empty').textContent = M.t('noReviewableOrders');
            el('review-form-empty').classList.remove('hidden');
        } else {
            el('review-form-empty').classList.add('hidden');
            el('review-form-body').classList.remove('hidden');
            reviewable.forEach(function (o) {
                const opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = '#' + o.id + ' — ' + M.formatPrice(o.total_price);
                select.appendChild(opt);
            });
        }

        el('review-modal-title').textContent = M.t('leaveReview');
        el('review-pick-label').textContent = M.t('pickOrder');
        el('review-rating-label').textContent = M.t('rating');
        el('review-comment').placeholder = M.t('commentOptional');
        el('review-submit-btn').textContent = M.t('submitReview');
        el('review-modal-overlay').classList.add('show');
        setStarRating(5);
    }
    function closeReviewForm() { el('review-modal-overlay').classList.remove('show'); }

    let selectedRating = 5;
    function setStarRating(n) {
        selectedRating = n;
        const stars = document.querySelectorAll('#review-stars .star');
        stars.forEach(function (star, i) {
            star.classList.toggle('active', i < n);
        });
    }

    async function submitReview() {
        const orderId = el('review-order-select').value;
        const comment = el('review-comment').value.trim();

        try {
            await M.apiFetch('/api/reviews', {
                method: 'POST',
                body: JSON.stringify({ order_id: Number(orderId), rating: selectedRating, comment: comment || null }),
            });
            closeReviewForm();
            M.haptic('success');
            showStatus(M.t('reviewSubmitted'), false);
            setTimeout(hideStatus, 3000);
        } catch (e) {
            alert(e.message);
        }
    }

    function goToCheckout() {
        el('c-menu-view').classList.add('hidden');
        el('c-checkout-view').classList.remove('hidden');
        renderCheckout();
    }
    function backToMenu() {
        el('c-checkout-view').classList.add('hidden');
        el('c-menu-view').classList.remove('hidden');
    }

    function renderCheckout() {
        el('c-checkout-table').textContent = state.table.name || state.table.code;
        const list = el('c-checkout-items');
        let total = 0;
        list.innerHTML = Object.keys(state.cart).filter(function (id) { return state.cart[id] > 0; }).map(function (id) {
            const dish = state.dishesById[id];
            const price = dish.discount ? dish.discount.price : Number(dish.price);
            const lineTotal = price * state.cart[id];
            total += lineTotal;
            return '<div class="co-item"><span>' + dish.name + ' × ' + state.cart[id] + '</span><span>' + M.formatPrice(lineTotal) + '</span></div>';
        }).join('');
        el('c-checkout-total').textContent = M.formatPrice(total);
    }

    async function placeOrder() {
        const items = Object.keys(state.cart)
            .filter(function (id) { return state.cart[id] > 0; })
            .map(function (id) { return { dish_id: Number(id), quantity: state.cart[id] }; });

        if (!items.length) return;

        try {
            const result = await M.apiFetch('/api/orders', {
                method: 'POST',
                body: JSON.stringify({ items: items }),
            });

            state.cart = {};
            updateCartBar();
            el('c-checkout-view').classList.add('hidden');
            el('c-order-success').classList.remove('hidden');
            el('c-order-success-title').textContent = M.t('orderSuccessTitle');
            el('c-order-number-label').textContent = M.t('orderNumber');
            el('c-order-number').textContent = '#' + result.order.id;
            el('c-order-total-label').textContent = M.t('total');
            el('c-order-total').textContent = M.formatPrice(result.order.total_price);
            M.haptic('success');
        } catch (e) {
            showStatus(e.message, true);
        }
    }

    function wireEvents() {
        el('c-sos-open').onclick = function () { el('sos-modal-overlay').classList.add('show'); };
        el('sos-close').onclick = function () { el('sos-modal-overlay').classList.remove('show'); };

        el('c-waiter-btn').onclick = async function () {
            try {
                await M.apiFetch('/api/waiter-calls', { method: 'POST', body: JSON.stringify({ type: 'waiter' }) });
                M.haptic('success');
                showStatus(M.t('waiterCalled'), false);
            } catch (e) {
                showStatus(e.message, true);
            }
            setTimeout(hideStatus, 3000);
        };

        el('c-bill-btn').onclick = async function () {
            try {
                await M.apiFetch('/api/waiter-calls', { method: 'POST', body: JSON.stringify({ type: 'bill' }) });
                M.haptic('success');
                showStatus(M.t('billRequested'), false);
            } catch (e) {
                showStatus(e.message, true);
            }
            setTimeout(hideStatus, 3000);
        };

        el('dish-modal-overlay').addEventListener('click', function (e) {
            if (e.target.id === 'dish-modal-overlay') closeDishModal();
        });

        el('c-leave-review-btn').onclick = openReviewForm;
        el('review-modal-overlay').addEventListener('click', function (e) {
            if (e.target.id === 'review-modal-overlay') closeReviewForm();
        });
        el('review-submit-btn').onclick = submitReview;
        document.querySelectorAll('#review-stars .star').forEach(function (star, i) {
            star.onclick = function () { setStarRating(i + 1); };
        });

        el('c-place-order').onclick = goToCheckout;
        el('c-back-to-menu').onclick = backToMenu;
        el('c-confirm-order').onclick = placeOrder;
        el('c-new-order-btn').onclick = function () {
            el('c-order-success').classList.add('hidden');
            el('c-menu-view').classList.remove('hidden');
        };
    }

    window.MiniAppCustomer = { init: init, wireEvents: wireEvents };
})();
