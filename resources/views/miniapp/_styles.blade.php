<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    /* Tokens copied verbatim from the prototype (qr-dasturxon-prototype-3.html) */
    :root {
        --paper: #F6F1E7;
        --paper-2: #ECE1CB;
        --ink: #2E1B12;
        --ink-soft: #6B584A;
        --maroon: #7A2331;
        --maroon-deep: #4E1620;
        --maroon-dark: #3A1420;
        --gold: #C79A3E;
        --gold-soft: #E7CD8F;
        --teal: #1F6F6A;
        --white: #FFFFFF;
        --radius: 18px;
        --shadow: 0 10px 30px rgba(46, 27, 18, 0.16);
        --verified-bg: #DDEFE0;
        --verified-text: #1F6F45;
        --allergen-bg: #FBE3DC;
        --allergen-text: #A5432A;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Manrope', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: var(--paper-2);
        color: var(--ink);
        padding-bottom: 96px;
    }

    .hidden { display: none !important; }

    .lang-row {
        display: flex;
        gap: 6px;
        overflow-x: auto;
        padding: 10px 12px;
        background: var(--maroon-dark);
    }

    .lang-btn {
        flex-shrink: 0;
        border: none;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        background: rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.7);
    }

    .lang-btn.active { background: var(--gold); color: var(--maroon-dark); }

    #c-info-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px 16px;
        background: var(--maroon);
        flex-wrap: wrap;
    }

    .pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 700;
    }

    .pill-table { background: var(--gold); color: var(--maroon-deep); }

    #c-restaurant-short-name {
        color: #fff;
        font-weight: 700;
        font-family: 'Cormorant Garamond', serif;
        font-size: 16px;
    }

    #c-sos-open {
        margin-left: auto;
        border: none;
        border-radius: 999px;
        padding: 5px 9px;
        font-weight: 800;
        font-size: 10px;
        background: #C1443B;
        color: #fff;
    }

    #c-status {
        margin: 16px;
        padding: 12px 16px;
        border-radius: var(--radius);
        background: var(--paper);
        box-shadow: var(--shadow);
        font-size: 14px;
    }

    /* An error status becomes a full-bleed brand screen, matching the
       prototype's own entry-screen gradient/brandmark treatment — not a
       generic pink alert box, since the prototype has no page-level error
       state of its own to copy. */
    #c-status.error {
        position: fixed;
        inset: 0;
        margin: 0;
        z-index: 90;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
        text-align: center;
        padding: 26px 22px;
        background: linear-gradient(165deg, var(--maroon-deep), var(--maroon));
        color: #fff;
        border-radius: 0;
        box-shadow: none;
        font-size: 15px;
        font-weight: 600;
    }

    #c-status.error::before {
        content: 'QR Dasturxon';
        font-family: 'Cormorant Garamond', serif;
        font-weight: 700;
        font-size: 22px;
        color: var(--gold);
    }

    main, .view-main { padding: 16px; }

    #c-badges-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
    .badge-verified { background: var(--verified-bg); color: var(--verified-text); }
    .badge-featured { background: var(--gold-soft); color: var(--maroon-deep); }

    #c-restaurant-name {
        margin: 0 0 6px;
        font-family: 'Cormorant Garamond', serif;
        font-weight: 700;
        font-size: 26px;
    }
    #c-rating-row { font-size: 13px; color: var(--ink-soft); margin-bottom: 20px; }
    #c-rating-row .stars { color: var(--gold); letter-spacing: 1px; }

    .demo-banner {
        margin: 0 0 16px;
        background: var(--teal);
        color: #fff;
        border-radius: var(--radius);
        padding: 12px 16px;
        box-shadow: var(--shadow);
        font-size: 12.5px;
        font-weight: 600;
        line-height: 1.4;
    }

    .flash-banner {
        margin: 0 0 16px;
        background: linear-gradient(120deg, #8A2C1E, #B23A22);
        color: #fff;
        border-radius: var(--radius);
        padding: 14px 16px;
        box-shadow: var(--shadow);
    }
    .flash-top { display: flex; align-items: center; gap: 10px; }
    .flash-fire { font-size: 22px; }
    .flash-title { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #FFD9B0; }
    .flash-sub { font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 16px; }
    .flash-timer { margin-left: auto; font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 18px; background: rgba(0,0,0,0.2); padding: 4px 10px; border-radius: 8px; }
    .flash-bottom { margin-top: 8px; font-size: 11px; color: #FFE0C4; font-weight: 600; }

    .section-label { font-size: 10.5px; font-weight: 800; letter-spacing: 1.4px; color: var(--teal); text-transform: uppercase; }
    .section-title { font-family: 'Cormorant Garamond', serif; font-size: 19px; font-weight: 700; margin: 1px 0 12px; }

    #c-chef-section { margin-bottom: 24px; }
    .chef-card { display: flex; gap: 12px; background: var(--white); border-radius: var(--radius); padding: 14px; box-shadow: var(--shadow); align-items: center; }
    .chef-avatar {
        width: 58px; height: 58px; border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), var(--maroon));
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 22px; flex-shrink: 0;
    }
    .chef-name { font-weight: 800; font-size: 13.5px; }
    .chef-meta, .chef-specialty { color: var(--ink-soft); font-size: 11px; margin-top: 2px; }
    .chef-tier { display: inline-block; margin-top: 4px; background: var(--paper-2); color: var(--maroon-deep); border-radius: 999px; padding: 2px 7px; font-size: 9.5px; font-weight: 800; }

    #c-category-tabs { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 4px; }
    .category-tab { flex-shrink: 0; background: var(--white); border: 1.5px solid var(--paper-2); border-radius: 999px; padding: 7px 14px; font-size: 11.5px; font-weight: 700; color: var(--ink-soft); white-space: nowrap; }
    .category-tab.active { background: var(--maroon); border-color: var(--maroon); color: #fff; }

    .dish { background: var(--white); border-radius: var(--radius); padding: 13px; box-shadow: var(--shadow); margin-bottom: 10px; display: flex; gap: 12px; }
    .dish-thumb { width: 64px; height: 64px; border-radius: 12px; background: var(--paper-2); display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; cursor: pointer; }
    .dish-info { flex: 1; min-width: 0; cursor: pointer; }
    .dish-name { font-weight: 800; font-size: 13.5px; }
    .dish-allergen { display: inline-block; background: var(--allergen-bg); color: var(--allergen-text); border-radius: 6px; padding: 2px 6px; font-size: 9px; font-weight: 700; margin-top: 4px; margin-right: 3px; }
    .dish-price { font-family: 'Cormorant Garamond', serif; color: var(--maroon); font-weight: 700; font-size: 15px; margin-top: 4px; }
    .dish-price.discounted { color: #B23A22; }
    .old-price { font-size: 11px; color: #A99A8C; text-decoration: line-through; margin-right: 4px; }

    .add-btn { background: var(--maroon); color: #fff; border: none; border-radius: 8px; width: 26px; height: 26px; font-size: 16px; font-weight: 800; line-height: 1; flex-shrink: 0; }
    .qty-controls { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .qty-controls button { background: var(--maroon); color: #fff; border: none; border-radius: 8px; width: 26px; height: 26px; font-size: 16px; line-height: 1; }

    #c-cart-bar {
        position: fixed; left: 0; right: 0; bottom: 0; padding: 12px 20px;
        background: var(--maroon-deep); color: #fff; display: none;
        align-items: center; justify-content: space-between; font-size: 12px; z-index: 20;
    }
    #c-cart-bar.visible { display: flex; }
    #c-cart-bar b { font-family: 'Cormorant Garamond', serif; font-size: 16px; color: var(--gold); }
    #c-cart-bar button { background: var(--gold); color: var(--maroon-deep); border: none; border-radius: 10px; padding: 9px 16px; font-weight: 800; font-size: 12px; }

    .float-row { display: flex; gap: 10px; margin-bottom: 20px; }
    .float-btn { flex: 1; background: var(--white); border: 1.5px solid var(--paper-2); border-radius: 12px; padding: 10px; text-align: center; font-size: 11.5px; font-weight: 800; color: var(--maroon-deep); }

    .review { background: var(--white); border-radius: 14px; padding: 12px 14px; margin-bottom: 8px; box-shadow: var(--shadow); }
    .review-top { display: flex; justify-content: space-between; font-size: 11.5px; margin-bottom: 4px; }
    .review-name { font-weight: 800; }
    .review-verified { font-weight: 700; font-size: 10px; color: var(--teal); }
    .review-text { font-size: 12px; color: var(--ink-soft); line-height: 1.5; }
    .muted { color: var(--ink-soft); font-size: 13px; }
    #c-leave-review-btn { width: 100%; margin-top: 8px; background: var(--teal); color: #fff; border: none; border-radius: 10px; padding: 0 14px; height: 40px; font-weight: 700; }

    /* modals (dish detail, review form, SOS) */
    .modal-overlay { position: fixed; inset: 0; background: rgba(46,27,18,0.55); display: none; align-items: flex-end; justify-content: center; z-index: 60; }
    .modal-overlay.show { display: flex; }
    .modal-overlay.center { align-items: center; padding: 20px; }
    .sheet { background: var(--white); width: 100%; max-width: 420px; border-radius: 24px 24px 0 0; padding: 20px; max-height: 85vh; overflow-y: auto; }
    .modal-overlay.center .sheet { border-radius: var(--radius); text-align: center; }

    #dm-photo { width: 100%; height: 130px; border-radius: 14px; background: linear-gradient(135deg, var(--gold-soft), var(--paper-2)); display: flex; align-items: center; justify-content: center; font-size: 40px; margin-bottom: 12px; }
    #dm-name { font-family: 'Cormorant Garamond', serif; font-size: 20px; margin: 0 0 4px; }
    .taste-row { display: flex; align-items: center; gap: 8px; font-size: 11px; margin-bottom: 5px; }
    .taste-row span:first-child { width: 56px; color: var(--ink-soft); }
    .taste-track { flex: 1; height: 6px; background: var(--paper-2); border-radius: 4px; overflow: hidden; }
    .taste-fill { height: 100%; background: var(--gold); }

    .confirm-btn { width: 100%; background: var(--maroon); color: #fff; border: none; border-radius: 12px; padding: 13px; font-weight: 800; font-size: 13px; margin-top: 6px; }

    .sos-num { display: flex; justify-content: space-between; align-items: center; background: var(--paper-2); border-radius: 10px; padding: 9px 12px; margin-bottom: 8px; font-size: 12.5px; }
    .sos-num b { color: var(--maroon-deep); }
    .modal-close { margin-top: 10px; background: var(--paper-2); border: none; border-radius: 10px; padding: 9px; width: 100%; font-weight: 700; }

    #review-stars { font-size: 28px; letter-spacing: 6px; margin: 10px 0; cursor: pointer; }
    #review-stars .star { color: #ddd; }
    #review-stars .star.active { color: var(--gold); }
    #review-comment { width: 100%; border: 1px solid var(--paper-2); border-radius: 10px; padding: 10px; font-family: 'Manrope', sans-serif; margin: 8px 0; resize: vertical; min-height: 60px; }

    /* checkout */
    .co-item { display: flex; justify-content: space-between; font-size: 12.5px; padding: 8px 0; border-bottom: 1px solid var(--paper-2); }
    .co-total { display: flex; justify-content: space-between; align-items: baseline; font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 18px; padding: 12px 0; color: var(--maroon); }

    #c-order-success { text-align: center; padding: 24px 16px; }
    #c-order-success-title { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 700; }
    #c-order-total { font-family: 'Cormorant Garamond', serif; color: var(--maroon); }

    /* staff shared */
    .staff-header { background: var(--maroon-deep); color: #fff; padding: 14px 16px; border-radius: var(--radius); margin-bottom: 14px; }
    .staff-header-title { font-family: 'Cormorant Garamond', serif; font-size: 18px; font-weight: 700; }
    .staff-header-sub { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 2px; }
    .staff-dash { display: none; }
    .staff-dash.show { display: block; }
    .staff-section-title { font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 15px; margin: 18px 4px 8px; color: var(--maroon-deep); }
    .staff-card { background: var(--white); border-radius: 14px; padding: 14px 16px; box-shadow: var(--shadow); }

    .empty-state { text-align: center; color: var(--ink-soft); font-size: 12px; padding: 30px 10px; }

    /* kassa */
    .order-card { background: var(--white); border-radius: 14px; padding: 13px 14px; margin-bottom: 10px; box-shadow: var(--shadow); border-left: 4px solid var(--gold); }
    .order-card.status-served, .order-card.status-paid { border-left-color: #3A9B5C; }
    .order-card.status-cancelled { border-left-color: #999; opacity: 0.6; }
    .order-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .order-table { font-family: 'Cormorant Garamond', serif; font-weight: 800; font-size: 16px; color: var(--maroon); }
    .order-time { font-size: 10.5px; color: var(--ink-soft); }
    .order-items { font-size: 12px; line-height: 1.6; margin-bottom: 8px; }
    .order-foot { display: flex; justify-content: space-between; align-items: center; }
    .status-pill { font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 999px; background: var(--paper-2); color: var(--ink-soft); }
    .k-btn { background: var(--maroon); color: #fff; border: none; border-radius: 8px; padding: 6px 12px; font-size: 11px; font-weight: 800; margin-left: 6px; }
    .k-btn.ghost { background: transparent; color: var(--maroon); border: 1px solid var(--maroon); }
    .call-card { display: flex; justify-content: space-between; align-items: center; background: var(--white); border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; box-shadow: var(--shadow); font-size: 13px; font-weight: 700; }

    /* owner */
    .of-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 9px; }
    .of-row label { font-size: 12px; color: var(--ink-soft); font-weight: 700; }
    .of-row select, .of-row input { border: 1.5px solid var(--paper-2); border-radius: 8px; padding: 7px 10px; font-family: 'Manrope', sans-serif; font-size: 12.5px; width: 150px; }
    .of-actions { display: flex; gap: 8px; margin-top: 10px; }
    .of-actions .confirm-btn { margin: 0; }
    .ghost-btn { flex: 1; background: var(--paper-2); color: var(--ink); border: none; border-radius: 10px; padding: 11px; font-weight: 800; font-size: 12.5px; }
    .of-status { margin-top: 10px; font-size: 11.5px; color: var(--teal); font-weight: 700; background: #EAF5F4; padding: 8px 10px; border-radius: 8px; }
    .avail-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--paper-2); }
    .avail-row:last-child { border-bottom: none; }
    .avail-name { font-size: 12.5px; font-weight: 700; }
    .avail-toggle { position: relative; width: 42px; height: 24px; background: #DCE7E5; border-radius: 999px; cursor: pointer; flex-shrink: 0; }
    .avail-toggle.on { background: var(--teal); }
    .avail-toggle::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: transform .2s; }
    .avail-toggle.on::after { transform: translateX(18px); }
    .unavail-tag { font-size: 9px; font-weight: 800; background: var(--paper-2); color: var(--ink-soft); padding: 2px 6px; border-radius: 6px; }
    .stat-row { display: flex; justify-content: space-between; font-size: 12.5px; padding: 5px 0; }
    .stat-row b { color: var(--maroon-deep); }
</style>
