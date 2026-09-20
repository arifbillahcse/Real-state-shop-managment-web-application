// ============================================
// Dashboard
// pages/dashboard.php rendered its stats server-side; here the same
// numbers come from the mock endpoint get_dashboard.php. Which cards
// appear depends on the signed-in role, exactly as the PHP does.
// ============================================

function dashEsc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function dashMoney(n) {
    return parseFloat(n || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

function trimZeros(n) { return String(parseFloat(n || 0)); }

const PAY_LABEL = { cash: 'নগদ', credit: 'বাকি', mobile_banking: 'মো.ব্যাং', cheque: 'চেক' };

document.getElementById('todayLabel').textContent =
    new Date().toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric', weekday: 'long'
    });

fetch(BASE_URL + '/api/get_dashboard.php')
    .then(r => r.json())
    .then(res => {
        if (!res.success) return showToast(res.message, 'danger');
        renderCards(res.data);
        renderLowStock(res.data.low_stock);
        renderChart(res.data.chart);
        renderRecent(res.data.recent_sales);
        if (typeof runCountUps === 'function') runCountUps();
    })
    .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));

function card(colClass, label, value, sub, icon, tone, valueClass = '') {
    return `
    <div class="${colClass}">
        <div class="card stat-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-muted small mb-1">${label}</p>
                    <h5 class="fw-bold mb-0 ${valueClass}" data-countup="${value}" data-suffix=" ৳">${dashMoney(value)}</h5>
                    <small class="text-muted">${sub}</small>
                </div>
                <div class="stat-icon bg-${tone} bg-opacity-10 text-${tone}">
                    <i class="bi ${icon}"></i>
                </div>
            </div>
        </div>
    </div>`;
}

function renderCards(d) {
    // Staff see two cards, everyone else four — matching pages/dashboard.php
    const col = d.is_staff ? 'col-6 col-md-6' : 'col-6 col-md-3';
    let html = card(col, 'আজকের বিক্রয়', d.today_total,
        d.today_count + 'টি লেনদেন', 'bi-cart-check', 'danger');

    if (!d.is_staff) {
        html += card(col, 'আজকের পেমেন্ট আদায়', d.today_payments_total,
            d.today_payments_count + 'টি পেমেন্ট', 'bi-cash-stack', 'success', 'text-success');
        html += card(col, 'মোট বাকি', d.total_due,
            'সকল কাস্টমার', 'bi-wallet2', 'warning', 'text-danger');
    }

    html += card(col, 'স্টক মূল্য', d.stock_value,
        d.branch_name ? dashEsc(d.branch_name) : 'বর্তমান স্টক', 'bi-boxes', 'info');

    document.getElementById('statCards').innerHTML = html;
}

function renderLowStock(items) {
    const panel = document.getElementById('lowStockPanel');

    if (!items.length) {
        panel.innerHTML = '<p class="text-center text-muted py-4 small">সব পণ্যের স্টক ঠিক আছে।</p>';
        return;
    }

    panel.innerHTML = `
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>পণ্য</th><th>স্টক</th><th>মিনিমাম</th></tr></thead>
            <tbody>
                ${items.map(i => `
                    <tr>
                        <td>${dashEsc(i.product_name)}</td>
                        <td class="low-stock">${trimZeros(i.current_stock)} ${dashEsc(i.unit)}</td>
                        <td>${trimZeros(i.min_stock)} ${dashEsc(i.unit)}</td>
                    </tr>`).join('')}
            </tbody>
        </table>
        </div>`;
}

function renderRecent(rows) {
    const panel = document.getElementById('recentSalesPanel');

    if (!rows.length) {
        panel.innerHTML = '<p class="text-center text-muted py-4 small">এখনো কোনো বিক্রয় নেই।</p>';
        return;
    }

    panel.innerHTML = `
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>ইনভয়েস</th><th>তারিখ</th><th>কাস্টমার</th><th>পেমেন্ট</th>
                    <th class="text-end">মোট</th><th class="text-end">পরিশোধ</th><th class="text-end">বাকি</th>
                </tr>
            </thead>
            <tbody>
                ${rows.map(s => `
                    <tr>
                        <td class="fw-semibold">${dashEsc(s.invoice_number)}</td>
                        <td>${dashEsc(s.sale_date)}</td>
                        <td>${dashEsc(s.customer_name)}</td>
                        <td><span class="badge bg-secondary">${PAY_LABEL[s.payment_method] || dashEsc(s.payment_method)}</span></td>
                        <td class="text-end">${dashMoney(s.total_amount)}</td>
                        <td class="text-end text-success">${dashMoney(s.paid_amount)}</td>
                        <td class="text-end ${s.due_amount > 0 ? 'text-danger fw-semibold' : ''}">${dashMoney(s.due_amount)}</td>
                    </tr>`).join('')}
            </tbody>
        </table>`;
}

function renderChart(rows) {
    const labels = rows.map(r => {
        const [y, m, d] = r.sale_date.split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' });
    });

    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'বিক্রয় (৳)',
                data: rows.map(r => parseFloat(r.total) || 0),
                backgroundColor: 'rgba(230,57,70,.75)',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '৳' + v.toLocaleString() } }
            }
        }
    });
}
