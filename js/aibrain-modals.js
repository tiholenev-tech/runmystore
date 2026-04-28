/* S89.AIBRAIN.MODALS — real popup modals for q1–q6 action buttons.
 *
 * Provides 4 <dialog>-based modals (OrderDraftModal, NavigateChartModal,
 * TransferDraftModal, DismissModal) and a window.handleAiAction override
 * that dispatches based on the action.intent from window.__aiAct.
 *
 * Endpoint: aibrain-modal-actions.php
 *   GET  ?action=csrf
 *   POST ?action=order_draft_submit | transfer_draft_submit | dismiss
 *
 * No build step. Mobile-first. Glass-morphism via css/aibrain-modals.css.
 */
(function () {
    'use strict';

    var ENDPOINT = 'aibrain-modal-actions.php';
    var CURRENCY = (typeof CFG !== 'undefined' && CFG.currency) ? CFG.currency : 'лв';

    // ───────── CSRF token bootstrap ─────────
    var _csrfToken = null;
    var _csrfPromise = null;
    function getCsrfToken() {
        if (_csrfToken) return Promise.resolve(_csrfToken);
        if (_csrfPromise) return _csrfPromise;
        _csrfPromise = fetch(ENDPOINT + '?action=csrf', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok && d.token) { _csrfToken = d.token; return d.token; }
                throw new Error('csrf_init_failed');
            })
            .catch(function (e) { _csrfPromise = null; throw e; });
        return _csrfPromise;
    }

    function postAction(action, payload) {
        return getCsrfToken().then(function (token) {
            var fd = new FormData();
            fd.append('_csrf', token);
            Object.keys(payload || {}).forEach(function (k) {
                var v = payload[k];
                if (v === null || v === undefined) return;
                fd.append(k, (typeof v === 'object') ? JSON.stringify(v) : String(v));
            });
            return fetch(ENDPOINT + '?action=' + encodeURIComponent(action), {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            }).then(function (r) {
                return r.json().then(function (d) { return { http: r.status, body: d }; });
            });
        });
    }

    // ───────── helpers ─────────
    function esc(s) {
        return String(s == null ? '' : s).replace(/[<>&"']/g, function (c) {
            return ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#39;' }[c]);
        });
    }
    function fmtPrice(n) {
        if (n == null || isNaN(n)) return '—';
        return Math.round(n) + ' ' + CURRENCY;
    }
    function findProduct(id) {
        try {
            id = parseInt(id, 10);
            var pools = [
                window.__loadedProducts, window._allProducts, window.S && window.S.products
            ];
            for (var i = 0; i < pools.length; i++) {
                var p = pools[i];
                if (Array.isArray(p)) {
                    for (var j = 0; j < p.length; j++) {
                        if (parseInt(p[j].id, 10) === id) return p[j];
                    }
                }
            }
        } catch (e) { /* noop */ }
        return null;
    }
    function svg(path, vb) {
        return '<svg viewBox="' + (vb || '0 0 24 24') + '" fill="none" stroke="currentColor" '
             + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
    }

    // ───────── BaseModal ─────────
    function BaseModal(opts) {
        this.opts = opts || {};
        this.qClass = this.opts.qClass || 'aim-q5';
        this.dialog = null;
        this._onKey = this._onKey.bind(this);
        this._onClick = this._onClick.bind(this);
    }
    BaseModal.prototype.open = function () {
        if (this.dialog) return;
        var d = document.createElement('dialog');
        d.className = 'aim';
        d.innerHTML = '<div class="aim-card ' + this.qClass + '">' + this.render() + '</div>';
        document.body.appendChild(d);
        this.dialog = d;
        this.bind();
        if (typeof d.showModal === 'function') {
            d.showModal();
        } else {
            d.setAttribute('open', '');
            d.style.display = 'block';
        }
        document.addEventListener('keydown', this._onKey);
        d.addEventListener('click', this._onClick);
    };
    BaseModal.prototype.close = function () {
        if (!this.dialog) return;
        document.removeEventListener('keydown', this._onKey);
        try { this.dialog.close(); } catch (e) { /* noop */ }
        if (this.dialog && this.dialog.parentNode) {
            this.dialog.parentNode.removeChild(this.dialog);
        }
        this.dialog = null;
        if (typeof this.opts.onClose === 'function') this.opts.onClose();
    };
    BaseModal.prototype._onKey = function (e) {
        if (e.key === 'Escape') { e.preventDefault(); this.close(); }
    };
    BaseModal.prototype._onClick = function (e) {
        // backdrop click — target IS the dialog itself (not its inner card)
        if (e.target === this.dialog) this.close();
        // explicit close button
        var t = e.target;
        while (t && t !== this.dialog) {
            if (t.dataset && t.dataset.aimClose === '1') { this.close(); return; }
            t = t.parentNode;
        }
    };
    BaseModal.prototype.bind = function () { /* override */ };
    BaseModal.prototype.render = function () { return ''; };

    BaseModal.prototype.headerHTML = function (title, subtitle, iconPath) {
        return '<div class="aim-hd">'
             +   '<div class="aim-hd-ic">' + svg(iconPath || '<circle cx="12" cy="12" r="10"/>') + '</div>'
             +   '<div style="flex:1;min-width:0">'
             +     '<div class="aim-hd-tt">' + esc(title) + '</div>'
             +     (subtitle ? '<div class="aim-hd-sub">' + esc(subtitle) + '</div>' : '')
             +   '</div>'
             +   '<button class="aim-x" data-aim-close="1" aria-label="Затвори">'
             +     svg('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>')
             +   '</button>'
             + '</div>';
    };
    BaseModal.prototype.errorHTML = function (msg) {
        return '<div class="aim-error" data-role="err">' + esc(msg) + '</div>';
    };
    BaseModal.prototype.showError = function (msg) {
        if (!this.dialog) return;
        var box = this.dialog.querySelector('[data-role="err-slot"]');
        if (!box) return;
        box.innerHTML = '<div class="aim-error">' + esc(msg) + '</div>';
    };

    // ───────── 1) OrderDraftModal ─────────
    function OrderDraftModal(opts) {
        BaseModal.call(this, opts);
        this.qClass = opts.qClass || 'aim-q5';
        this.items = (opts.items || []).map(function (it) {
            var p = findProduct(it.product_id);
            return {
                product_id: it.product_id,
                qty: Math.max(1, parseInt(it.qty || it.suggested_qty || 1, 10)),
                supplier_id: it.supplier_id || null,
                name: (p && p.name) || it.name || ('#' + it.product_id),
                price: (p && p.price) != null ? p.price : it.price
            };
        });
        this.suppliers = (typeof CFG !== 'undefined' && CFG.suppliers) ? CFG.suppliers : [];
        this.selectedSupplier = (opts.supplier_id) ? parseInt(opts.supplier_id, 10) : '';
        this.insightId = opts.insight_id || null;
        this.submitting = false;
    }
    OrderDraftModal.prototype = Object.create(BaseModal.prototype);
    OrderDraftModal.prototype.render = function () {
        var icon = '<path d="M3 6h18l-2 13H5z"/><path d="M16 10a4 4 0 0 1-8 0"/>';
        var rows = this.items.length === 0
            ? '<div class="aim-empty">Няма артикули в чернова.</div>'
            : this.items.map(function (it, idx) {
                return '<div class="aim-row" data-idx="' + idx + '">'
                     +   '<div>'
                     +     '<div class="aim-row-nm">' + esc(it.name) + '</div>'
                     +     '<div class="aim-row-meta">'
                     +       '<span class="price-display">' + esc(fmtPrice(it.price)) + '</span>'
                     +     '</div>'
                     +   '</div>'
                     +   '<div class="aim-qty">'
                     +     '<button type="button" data-act="dec">−</button>'
                     +     '<input type="number" min="1" step="1" value="' + it.qty + '" data-role="qty">'
                     +     '<button type="button" data-act="inc">+</button>'
                     +   '</div>'
                     + '</div>';
            }).join('');

        var supOpts = '<option value="">— Избери доставчик —</option>'
            + this.suppliers.map(function (s) {
                var sel = (s.id == this.selectedSupplier) ? ' selected' : '';
                return '<option value="' + esc(s.id) + '"' + sel + '>' + esc(s.name) + '</option>';
            }.bind(this)).join('');

        return this.headerHTML('Чернова поръчка', this.items.length + ' артикул(а)', icon)
             + '<div class="aim-bd">'
             +   '<div data-role="err-slot"></div>'
             +   '<label class="aim-field">'
             +     '<span class="aim-lbl">Доставчик</span>'
             +     '<select class="aim-select" data-role="supplier">' + supOpts + '</select>'
             +   '</label>'
             +   rows
             + '</div>'
             + '<div class="aim-ft">'
             +   '<button class="aim-btn" data-aim-close="1">Отказ</button>'
             +   '<button class="aim-btn primary" data-role="submit">Изпрати поръчка</button>'
             + '</div>';
    };
    OrderDraftModal.prototype.bind = function () {
        var self = this;
        var d = this.dialog;

        d.querySelectorAll('.aim-row').forEach(function (row) {
            var idx = parseInt(row.dataset.idx, 10);
            var input = row.querySelector('[data-role="qty"]');
            row.querySelector('[data-act="dec"]').addEventListener('click', function () {
                self.items[idx].qty = Math.max(1, self.items[idx].qty - 1);
                input.value = self.items[idx].qty;
            });
            row.querySelector('[data-act="inc"]').addEventListener('click', function () {
                self.items[idx].qty = self.items[idx].qty + 1;
                input.value = self.items[idx].qty;
            });
            input.addEventListener('input', function () {
                var v = parseInt(input.value, 10);
                self.items[idx].qty = (isNaN(v) || v < 1) ? 1 : v;
            });
        });

        d.querySelector('[data-role="supplier"]').addEventListener('change', function (e) {
            self.selectedSupplier = e.target.value || '';
        });

        d.querySelector('[data-role="submit"]').addEventListener('click', function () { self.submit(); });
    };
    OrderDraftModal.prototype.submit = function () {
        if (this.submitting) return;
        if (this.items.length === 0) { this.showError('Няма артикули.'); return; }
        this.submitting = true;
        var btn = this.dialog.querySelector('[data-role="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Изпращане…'; }

        var self = this;
        postAction('order_draft_submit', {
            items: this.items.map(function (it) {
                return { product_id: it.product_id, qty: it.qty };
            }),
            supplier_id: this.selectedSupplier || '',
            insight_id: this.insightId || ''
        }).then(function (resp) {
            if (resp.body && resp.body.ok) {
                if (typeof self.opts.onSuccess === 'function') self.opts.onSuccess(resp.body);
                self.close();
                if (resp.body.redirect_url) {
                    setTimeout(function () { window.location.href = resp.body.redirect_url; }, 100);
                }
            } else {
                self.showError('Грешка: ' + ((resp.body && resp.body.err) || 'unknown'));
                self.submitting = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Изпрати поръчка'; }
            }
        }).catch(function (e) {
            self.showError('Мрежова грешка.');
            self.submitting = false;
            if (btn) { btn.disabled = false; btn.textContent = 'Изпрати поръчка'; }
            console.error(e);
        });
    };

    // ───────── 2) NavigateChartModal ─────────
    function NavigateChartModal(opts) {
        BaseModal.call(this, opts);
        this.qClass = opts.qClass || 'aim-q2';
        this.topic = opts.topic || 'price_change';
        this.productIds = (opts.product_ids || []).map(function (x) { return parseInt(x, 10); });
    }
    NavigateChartModal.prototype = Object.create(BaseModal.prototype);
    NavigateChartModal.prototype.render = function () {
        var icon = '<path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-7"/>';
        var titleMap = {
            price_change: 'Промяна на цените',
            stock:        'Наличност',
            sales:        'Продажби',
            zombie:       'Залежали'
        };
        var title = titleMap[this.topic] || 'Графика';
        var products = this.productIds.map(findProduct).filter(Boolean);
        var hasData = products.length > 0;

        var body = hasData
            ? '<div class="aim-chart-wrap"><canvas data-role="chart"></canvas></div>'
              + '<div class="aim-row-meta" style="text-align:center">' + products.length + ' артикул(а) · ' + esc(this.topic) + '</div>'
            : '<div class="aim-chart-empty">Няма налични данни за тази графика.<br><small>topic=' + esc(this.topic) + '</small></div>';

        return this.headerHTML(title, 'Само за преглед', icon)
             + '<div class="aim-bd">' + body + '</div>'
             + '<div class="aim-ft">'
             +   '<button class="aim-btn primary" data-aim-close="1">Затвори</button>'
             + '</div>';
    };
    NavigateChartModal.prototype.bind = function () {
        var canvas = this.dialog.querySelector('[data-role="chart"]');
        if (!canvas) return;
        var products = this.productIds.map(findProduct).filter(Boolean);
        if (products.length === 0) return;

        var labels = products.map(function (p) {
            var n = (p.name || '').toString();
            return n.length > 14 ? n.slice(0, 13) + '…' : n;
        });
        var data = products.map(function (p) { return parseFloat(p.price) || 0; });

        if (typeof window.Chart === 'function') {
            try {
                new window.Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: CURRENCY,
                            data: data,
                            backgroundColor: 'rgba(168, 85, 247, 0.55)',
                            borderColor:     'rgba(168, 85, 247, 1)',
                            borderWidth: 1,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
                            y: { ticks: { color: 'rgba(255,255,255,0.7)', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,0.05)' } }
                        }
                    }
                });
                return;
            } catch (e) { console.warn('Chart.js error, falling back:', e); }
        }
        // CSS fallback bar chart (no Chart.js available)
        canvas.outerHTML = this._fallbackBars(labels, data);
    };
    NavigateChartModal.prototype._fallbackBars = function (labels, data) {
        var max = Math.max.apply(null, data.concat([1]));
        var html = '<div style="display:flex;flex-direction:column;gap:6px;padding:4px 0">';
        for (var i = 0; i < labels.length; i++) {
            var pct = Math.max(2, Math.round((data[i] / max) * 100));
            html += '<div style="display:grid;grid-template-columns:90px 1fr 60px;align-items:center;gap:8px;font-size:11px">'
                  +   '<div style="color:rgba(255,255,255,0.7);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(labels[i]) + '</div>'
                  +   '<div style="height:14px;background:rgba(255,255,255,0.05);border-radius:7px;overflow:hidden">'
                  +     '<div style="height:100%;width:' + pct + '%;background:linear-gradient(90deg,rgba(168,85,247,0.4),rgba(168,85,247,0.85));border-radius:7px"></div>'
                  +   '</div>'
                  +   '<div class="price-display" style="text-align:right">' + esc(fmtPrice(data[i])) + '</div>'
                  + '</div>';
        }
        html += '</div>';
        return html;
    };

    // ───────── 3) TransferDraftModal ─────────
    function TransferDraftModal(opts) {
        BaseModal.call(this, opts);
        this.qClass = opts.qClass || 'aim-q4';
        this.fromStoreId = parseInt(opts.from_store_id || (typeof CFG !== 'undefined' && CFG.storeId) || 0, 10);
        this.toStoreId   = parseInt(opts.to_store_id || 0, 10);
        this.insightId   = opts.insight_id || null;
        this.items = (opts.items || []).map(function (it) {
            var p = findProduct(it.product_id);
            return {
                product_id: it.product_id,
                qty: Math.max(1, parseInt(it.qty || 1, 10)),
                name: (p && p.name) || it.name || ('#' + it.product_id),
                checked: true
            };
        });
        this.submitting = false;
    }
    TransferDraftModal.prototype = Object.create(BaseModal.prototype);
    TransferDraftModal.prototype.storeName = function (id) {
        try {
            var stores = (typeof CFG !== 'undefined' && CFG.stores) ? CFG.stores : [];
            for (var i = 0; i < stores.length; i++) if (parseInt(stores[i].id, 10) === parseInt(id, 10)) return stores[i].name;
        } catch (e) { /* noop */ }
        if (typeof CFG !== 'undefined' && parseInt(CFG.storeId, 10) === parseInt(id, 10) && CFG.storeName) return CFG.storeName;
        return id ? ('Магазин #' + id) : '—';
    };
    TransferDraftModal.prototype.render = function () {
        var icon = '<path d="M5 12h14"/><polyline points="12 5 19 12 12 19"/>';
        var rows = this.items.length === 0
            ? '<div class="aim-empty">Няма артикули за прехвърляне.</div>'
            : this.items.map(function (it, idx) {
                return '<label class="aim-row" data-idx="' + idx + '">'
                     +   '<div class="aim-check">'
                     +     '<input type="checkbox" data-role="chk"' + (it.checked ? ' checked' : '') + '>'
                     +     '<div>'
                     +       '<div class="aim-row-nm">' + esc(it.name) + '</div>'
                     +     '</div>'
                     +   '</div>'
                     +   '<div class="aim-qty">'
                     +     '<button type="button" data-act="dec">−</button>'
                     +     '<input type="number" min="1" step="1" value="' + it.qty + '" data-role="qty">'
                     +     '<button type="button" data-act="inc">+</button>'
                     +   '</div>'
                     + '</label>';
            }).join('');

        return this.headerHTML('Чернова прехвърляне', this.items.length + ' артикул(а)', icon)
             + '<div class="aim-bd">'
             +   '<div data-role="err-slot"></div>'
             +   '<div class="aim-stores">'
             +     '<div class="aim-stores-cell"><div class="aim-lbl">От</div>' + esc(this.storeName(this.fromStoreId)) + '</div>'
             +     '<div class="aim-stores-arrow">' + svg('<path d="M5 12h14"/><polyline points="12 5 19 12 12 19"/>') + '</div>'
             +     '<div class="aim-stores-cell"><div class="aim-lbl">Към</div>' + esc(this.storeName(this.toStoreId)) + '</div>'
             +   '</div>'
             +   rows
             + '</div>'
             + '<div class="aim-ft">'
             +   '<button class="aim-btn" data-aim-close="1">Отказ</button>'
             +   '<button class="aim-btn primary" data-role="submit">Създай чернова</button>'
             + '</div>';
    };
    TransferDraftModal.prototype.bind = function () {
        var self = this;
        this.dialog.querySelectorAll('.aim-row').forEach(function (row) {
            var idx = parseInt(row.dataset.idx, 10);
            var qty = row.querySelector('[data-role="qty"]');
            var chk = row.querySelector('[data-role="chk"]');
            row.querySelector('[data-act="dec"]').addEventListener('click', function (e) {
                e.preventDefault();
                self.items[idx].qty = Math.max(1, self.items[idx].qty - 1);
                qty.value = self.items[idx].qty;
            });
            row.querySelector('[data-act="inc"]').addEventListener('click', function (e) {
                e.preventDefault();
                self.items[idx].qty = self.items[idx].qty + 1;
                qty.value = self.items[idx].qty;
            });
            qty.addEventListener('input', function () {
                var v = parseInt(qty.value, 10);
                self.items[idx].qty = (isNaN(v) || v < 1) ? 1 : v;
            });
            qty.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); });
            chk.addEventListener('change', function () { self.items[idx].checked = chk.checked; });
        });
        this.dialog.querySelector('[data-role="submit"]').addEventListener('click', function () { self.submit(); });
    };
    TransferDraftModal.prototype.submit = function () {
        if (this.submitting) return;
        var picked = this.items.filter(function (it) { return it.checked; });
        if (picked.length === 0) { this.showError('Маркирай поне един артикул.'); return; }
        if (!this.fromStoreId || !this.toStoreId || this.fromStoreId === this.toStoreId) {
            this.showError('Невалидни магазини за прехвърляне.'); return;
        }
        this.submitting = true;
        var btn = this.dialog.querySelector('[data-role="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Създаване…'; }

        var self = this;
        postAction('transfer_draft_submit', {
            from_store_id: this.fromStoreId,
            to_store_id:   this.toStoreId,
            insight_id:    this.insightId || '',
            items: picked.map(function (it) { return { product_id: it.product_id, qty: it.qty }; })
        }).then(function (resp) {
            if (resp.body && resp.body.ok) {
                if (typeof self.opts.onSuccess === 'function') self.opts.onSuccess(resp.body);
                self.close();
            } else {
                self.showError('Грешка: ' + ((resp.body && resp.body.err) || 'unknown'));
                self.submitting = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Създай чернова'; }
            }
        }).catch(function (e) {
            self.showError('Мрежова грешка.');
            self.submitting = false;
            if (btn) { btn.disabled = false; btn.textContent = 'Създай чернова'; }
            console.error(e);
        });
    };

    // ───────── 4) DismissModal ─────────
    function DismissModal(opts) {
        BaseModal.call(this, opts);
        this.qClass = opts.qClass || 'aim-q6';
        this.insightId = opts.insight_id || null;
        this.topic = opts.topic || '';
        this.submitting = false;
    }
    DismissModal.prototype = Object.create(BaseModal.prototype);
    DismissModal.prototype.render = function () {
        var icon = '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>';
        return this.headerHTML('Скриване на съвет', this.topic ? ('topic: ' + this.topic) : '', icon)
             + '<div class="aim-bd">'
             +   '<div data-role="err-slot"></div>'
             +   '<p style="font-size:13px;color:rgba(255,255,255,0.75);margin:0 0 12px;line-height:1.4">'
             +     'Потвърдете dismiss-ване на този съвет? Той няма да се показва отново.'
             +   '</p>'
             +   '<label class="aim-field">'
             +     '<span class="aim-lbl">Причина (по желание)</span>'
             +     '<textarea class="aim-textarea" data-role="reason" maxlength="500" '
             +              'placeholder="Защо скриваш този съвет? Помага на AI да се обучи."></textarea>'
             +   '</label>'
             + '</div>'
             + '<div class="aim-ft">'
             +   '<button class="aim-btn" data-aim-close="1">Отказ</button>'
             +   '<button class="aim-btn danger" data-role="submit">Скрий съвета</button>'
             + '</div>';
    };
    DismissModal.prototype.bind = function () {
        var self = this;
        this.dialog.querySelector('[data-role="submit"]').addEventListener('click', function () { self.submit(); });
    };
    DismissModal.prototype.submit = function () {
        if (this.submitting) return;
        if (!this.insightId) { this.showError('Липсва insight_id.'); return; }
        this.submitting = true;
        var btn = this.dialog.querySelector('[data-role="submit"]');
        var reasonEl = this.dialog.querySelector('[data-role="reason"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Скриване…'; }

        var self = this;
        postAction('dismiss', {
            insight_id: this.insightId,
            reason: reasonEl ? reasonEl.value : ''
        }).then(function (resp) {
            if (resp.body && resp.body.ok) {
                if (typeof self.opts.onSuccess === 'function') self.opts.onSuccess(resp.body);
                self.close();
            } else {
                self.showError('Грешка: ' + ((resp.body && resp.body.err) || 'unknown'));
                self.submitting = false;
                if (btn) { btn.disabled = false; btn.textContent = 'Скрий съвета'; }
            }
        }).catch(function (e) {
            self.showError('Мрежова грешка.');
            self.submitting = false;
            if (btn) { btn.disabled = false; btn.textContent = 'Скрий съвета'; }
            console.error(e);
        });
    };

    // ───────── handleAiAction override ─────────
    var _origHandle = window.handleAiAction;
    window.handleAiAction = function (actKey, productId) {
        var act = (window.__aiAct || {})[actKey] || {};
        var intent = act.intent || act.type || 'none';
        var topic = act.topic || '';
        var data = act.data || {};
        var insightId = act.insight_id || data.insight_id || null;
        var qClass = (function () {
            var m = String(actKey || '').match(/^(q[1-6])-/);
            return m ? 'aim-' + m[1] : 'aim-q5';
        })();

        switch (intent) {
            case 'order_draft': {
                var items = (data.items && data.items.length)
                    ? data.items
                    : (productId ? [{ product_id: productId, qty: data.qty || 1 }] : []);
                new OrderDraftModal({
                    qClass: qClass,
                    items: items,
                    supplier_id: data.supplier_id,
                    insight_id: insightId
                }).open();
                break;
            }
            case 'navigate_chart': {
                var pids = (data.product_ids && data.product_ids.length)
                    ? data.product_ids
                    : (productId ? [productId] : []);
                new NavigateChartModal({
                    qClass: qClass,
                    topic: topic || data.topic,
                    product_ids: pids
                }).open();
                break;
            }
            case 'transfer_draft': {
                var titems = (data.items && data.items.length)
                    ? data.items
                    : (productId ? [{ product_id: productId, qty: data.qty || 1 }] : []);
                new TransferDraftModal({
                    qClass: qClass,
                    from_store_id: data.from_store_id || ((typeof CFG !== 'undefined') ? CFG.storeId : 0),
                    to_store_id:   data.to_store_id   || 0,
                    items: titems,
                    insight_id: insightId
                }).open();
                break;
            }
            case 'dismiss': {
                if (!insightId) {
                    console.warn('[AI Action] dismiss without insight_id, falling back');
                    if (typeof _origHandle === 'function') _origHandle(actKey, productId);
                    return;
                }
                new DismissModal({
                    qClass: qClass,
                    insight_id: insightId,
                    topic: topic
                }).open();
                break;
            }
            case 'navigate_product':
            case 'deeplink':
                if (typeof window.openProductDetail === 'function') window.openProductDetail(productId);
                break;
            case 'chat':
                if (typeof window.openChatOverlay === 'function') window.openChatOverlay({ topic: topic, productId: productId });
                else if (typeof window.openProductDetail === 'function') window.openProductDetail(productId);
                break;
            default:
                if (typeof _origHandle === 'function') _origHandle(actKey, productId);
        }
    };

    // Public: expose for testing / debugging
    window.AIBrainModals = {
        Order: OrderDraftModal,
        Chart: NavigateChartModal,
        Transfer: TransferDraftModal,
        Dismiss: DismissModal
    };
})();
