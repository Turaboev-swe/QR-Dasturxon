<div id="o-view">
    <div id="o-message" class="hidden" style="margin:20px 16px;padding:16px;background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);text-align:center;color:var(--ink-soft);"></div>

    <div id="o-dash" class="staff-dash">
        <main>
            <div class="staff-header">
                <div class="staff-header-title" id="o-title"></div>
            </div>

            <div class="staff-section-title" id="o-flash-title"></div>
            <div class="staff-card">
                <div class="of-row"><label id="o-dish-label"></label><select id="o-dish-select"></select></div>
                <div class="of-row"><label id="o-percent-label"></label><input type="number" id="o-percent" value="50" min="1" max="95"></div>
                <div class="of-row"><label id="o-portions-label"></label><input type="number" id="o-portions" value="5" min="1"></div>
                <div class="of-row"><label id="o-minutes-label"></label><input type="number" id="o-minutes" value="45" min="1"></div>
                <div class="of-actions">
                    <button class="confirm-btn" id="o-set-btn"></button>
                    <button class="ghost-btn" id="o-clear-btn"></button>
                </div>
                <div class="of-status" id="o-discount-status"></div>
            </div>

            <div class="staff-section-title" id="o-availability-title"></div>
            <div class="staff-card" id="o-availability-list"></div>

            <div class="staff-section-title" id="o-stats-title"></div>
            <div class="staff-card" id="o-stats-body"></div>
        </main>
    </div>
</div>
