<div id="c-view">
    <div class="lang-row" id="c-lang-row"></div>

    <div id="c-info-bar">
        <span class="pill pill-table" id="c-table-badge"></span>
        <span id="c-restaurant-short-name">QR Dasturxon</span>
        <button id="c-sos-open">SOS</button>
    </div>

    <div id="c-status">Yuklanmoqda…</div>

    <div id="c-menu-view">
        <main>
            <div id="c-demo-banner" class="demo-banner hidden"></div>
            <div id="c-flash-banner" class="flash-banner hidden"></div>

            <div id="c-badges-row"></div>
            <h1 id="c-restaurant-name" class="hidden"></h1>
            <div id="c-rating-row" class="hidden"></div>

            {{-- Everything below only appears once the menu has actually
                 loaded — previously these rendered unconditionally with
                 blank labels (translated text is only set once loading
                 succeeds), showing as empty white boxes / an empty green
                 button whenever the session/menu request failed. --}}
            <div id="c-loaded-content" class="hidden">
                <section id="c-chef-section" class="hidden">
                    <div class="section-label" id="c-chef-label"></div>
                    <div class="section-title" id="c-chef-title"></div>
                    <div class="chef-card" id="c-chef-card"></div>
                </section>

                <div class="float-row">
                    <button class="float-btn" id="c-waiter-btn"></button>
                    <button class="float-btn" id="c-bill-btn"></button>
                </div>

                <section id="c-menu-section">
                    <div class="section-label" id="c-menu-label"></div>
                    <div class="section-title" id="c-menu-title"></div>
                    <div id="c-category-tabs"></div>
                    <div id="c-menu-list"></div>
                </section>

                <section>
                    <div class="section-label" id="c-reviews-label"></div>
                    <div class="section-title" id="c-reviews-title"></div>
                    <div id="c-reviews-list"></div>
                    <button id="c-leave-review-btn"></button>
                </section>
            </div>
        </main>
    </div>

    <div id="c-checkout-view" class="hidden">
        <main>
            <div class="section-title" id="c-checkout-title" style="margin-bottom:10px;"></div>
            <p><span id="c-checkout-table-label"></span> <span id="c-checkout-table"></span></p>
            <div id="c-checkout-items"></div>
            <div class="co-total"><span id="c-checkout-total-label"></span><b id="c-checkout-total"></b></div>
            <button class="confirm-btn" id="c-confirm-order"></button>
            <button class="ghost-btn" id="c-back-to-menu" style="width:100%;margin-top:8px;"></button>
        </main>
    </div>

    <div id="c-order-success" class="hidden">
        <main>
            <div id="c-order-success-title"></div>
            <p><span id="c-order-number-label"></span>: <span id="c-order-number"></span></p>
            <p><span id="c-order-total-label"></span>: <b id="c-order-total"></b></p>
            <button class="confirm-btn" id="c-new-order-btn"></button>
        </main>
    </div>

    <div id="c-cart-bar">
        <span id="c-cart-summary"></span>
        <button id="c-place-order"></button>
    </div>
</div>

<div class="modal-overlay" id="sos-modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="sheet" style="border-radius:18px;text-align:center;max-width:320px;">
        <div style="font-size:30px;">🆘</div>
        <h3 id="sos-title" style="margin:6px 0 4px;color:#C1443B;font-family:'Cormorant Garamond',serif;"></h3>
        <p id="sos-body" class="muted"></p>
        <div class="sos-num"><span id="sos-police-label"></span><b>102</b></div>
        <div class="sos-num"><span id="sos-ambulance-label"></span><b>103</b></div>
        <div class="sos-num"><span id="sos-tourist-label"></span><b>1178</b></div>
        <button class="modal-close" id="sos-close"></button>
    </div>
</div>

<div class="modal-overlay" id="dish-modal-overlay">
    <div class="sheet">
        <div id="dm-photo">🍽️</div>
        <h3 id="dm-name"></h3>
        <p id="dm-description" class="muted"></p>
        <div id="dm-price" style="margin-bottom:10px;"></div>
        <p id="dm-ingredients-row" class="hidden"><b id="dm-ingredients-label"></b> <span id="dm-ingredients" class="muted"></span></p>
        <div id="dm-taste" class="hidden"></div>
        <div id="dm-tags"></div>
        <button class="confirm-btn" id="dm-add-btn"><span id="dm-add-btn-label"></span></button>
    </div>
</div>

<div class="modal-overlay" id="review-modal-overlay">
    <div class="sheet" style="border-radius:18px;">
        <h3 id="review-modal-title" style="margin:0 0 10px;font-family:'Cormorant Garamond',serif;"></h3>
        <div id="review-form-body">
            <div id="review-order-picker-row" class="hidden">
                <label id="review-pick-label" style="font-size:12px;font-weight:700;"></label>
                <select id="review-order-select" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--paper-2);margin:6px 0 12px;font-family:'Manrope',sans-serif;"></select>
            </div>
            <label id="review-rating-label" style="font-size:12px;font-weight:700;"></label>
            <div id="review-stars">
                <span class="star" data-n="1">★</span><span class="star" data-n="2">★</span><span class="star" data-n="3">★</span><span class="star" data-n="4">★</span><span class="star" data-n="5">★</span>
            </div>
            <textarea id="review-comment"></textarea>
            <button class="confirm-btn" id="review-submit-btn"></button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="thankyou-modal-overlay" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="sheet" style="border-radius:18px;text-align:center;">
        <h3 id="thankyou-title" style="margin:0 0 8px;font-family:'Cormorant Garamond',serif;color:var(--maroon);"></h3>
        <p id="thankyou-body" class="muted" style="margin-bottom:14px;"></p>
        <div id="thankyou-form">
            <div id="thankyou-stars" style="margin-bottom:10px;">
                <span class="star" data-n="1">★</span><span class="star" data-n="2">★</span><span class="star" data-n="3">★</span><span class="star" data-n="4">★</span><span class="star" data-n="5">★</span>
            </div>
            <textarea id="thankyou-comment"></textarea>
            <button class="confirm-btn" id="thankyou-submit-btn"></button>
            <button class="ghost-btn" id="thankyou-skip-btn" style="width:100%;margin-top:8px;"></button>
        </div>
        <p id="thankyou-success" class="hidden"></p>
    </div>
</div>
