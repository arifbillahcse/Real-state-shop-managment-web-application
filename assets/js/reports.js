// ============================================
// Reports & Analytics
// ============================================

let dailyChart = null;

// ---- Helpers ----
function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}
function num(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

// ---- Quick date ranges ----
function setRange(type) {
    const today = new Date();
    let from, to = today;

    switch (type) {
        case 'today':
            from = today; break;
        case 'week': {
            from = new Date(today);
            from.setDate(today.getDate() - today.getDay());
            break;
        }
        case 'month':
            from = new Date(today.getFullYear(), today.getMonth(), 1); break;
        case 'year':
            from = new Date(today.getFullYear(), 0, 1); break;
        default:
            from = today;
    }
    document.getElementById('rFrom').value = from.toISOString().slice(0, 10);
    document.getElementById('rTo').value   = to.toISOString().slice(0, 10);
    loadReport();
}

// ---- Load report ----
function loadReport() {
    const from = document.getElementById('rFrom').value;
    const to   = document.getElementById('rTo').value;
    if (!from || !to) { showToast('তারিখ নির্বাচন করুন', 'warning'); return; }
    if (from > to)    { showToast('শুরুর তারিখ শেষ তারিখের পরে হতে পারে না', 'warning'); return; }

    document.getElementById('reportLoading').classList.remove('d-none');
    document.getElementById('reportContent').classList.add('d-none');

    fetch(`${BASE_URL}/api/get_report.php?from=${from}&to=${to}`)
        .then(r => r.json())
        .then(res => {
            document.getElementById('reportLoading').classList.add('d-none');
            document.getElementById('reportContent').classList.remove('d-none');
            if (res.success) renderReport(res.data);
            else showToast(res.message, 'danger');
        })
        .catch(() => {
            document.getElementById('reportLoading').classList.add('d-none');
            document.getElementById('reportContent').classList.remove('d-none');
            showToast('রিপোর্ট লোড করতে সমস্যা হয়েছে', 'danger');
        });
}

function renderReport(d) {
    // --- Summary cards ---
    const ss = d.sales_summary || {};
    document.getElementById('sumTotalSales').textContent = fmt(ss.total);
    document.getElementById('sumSaleCount').textContent  = (ss.sale_count || 0) + 'টি বিক্রয়';
    document.getElementById('sumPayments').textContent   = fmt((d.payments || {}).total_collected);
    document.getElementById('sumDue').textContent        = fmt(ss.due);
    document.getElementById('sumProfit').textContent     = fmt((d.profit || {}).profit);

    // --- Daily sales chart ---
    renderDailyChart(d.daily_sales || []);

    // --- Top products ---
    renderTopProducts(d.top_products || []);

    // --- Customer dues ---
    renderDues(d.customer_dues || []);

    // --- Stock valuation ---
    renderStock(d.stock || { rows: [], totals: {} });

    // --- Purchase summary ---
    const pu = d.purchase || {};
    document.getElementById('purchaseCount').textContent = (pu.purchase_count || 0) + 'টি';
    document.getElementById('purchaseCost').textContent  = fmt(pu.total_cost);

    // --- Profit ---
    const pr = d.profit || {};
    document.getElementById('profitRevenue').textContent = fmt(pr.revenue);
    document.getElementById('profitCost').textContent    = fmt(pr.cost);
    document.getElementById('profitNet').textContent     = fmt(pr.profit);
}

function renderDailyChart(daily) {
    const labels  = daily.map(r => r.sale_date);
    const totals  = daily.map(r => parseFloat(r.total));
    const paid    = daily.map(r => parseFloat(r.paid));

    if (dailyChart) dailyChart.destroy();
    dailyChart = new Chart(document.getElementById('dailySalesChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'বিক্রয় (৳)', data: totals,
                    borderColor: 'rgba(230,57,70,1)',
                    backgroundColor: 'rgba(230,57,70,.1)',
                    fill: true, tension: .3
                },
                {
                    label: 'পরিশোধ (৳)', data: paid,
                    borderColor: 'rgba(40,167,69,1)',
                    backgroundColor: 'rgba(40,167,69,.08)',
                    fill: true, tension: .3
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => '৳' + v.toLocaleString() } } }
        }
    });
}


function renderTopProducts(list) {
    const tbody = document.getElementById('topProductsBody');
    const map   = { rod: 'রড', cement: 'সিমেন্ট' };
    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">এই সময়ে কোনো বিক্রয় নেই</td></tr>';
        return;
    }
    tbody.innerHTML = list.map((p, i) => `
        <tr>
            <td>
                <span class="badge bg-${p.product_type === 'rod' ? 'danger' : 'primary'} me-1">${map[p.product_type] || ''}</span>
                ${esc(p.product_name)}
            </td>
            <td class="text-end">${num(p.total_qty)} ${esc(p.unit)}</td>
            <td class="text-end fw-semibold">${fmt(p.total_revenue)}</td>
        </tr>
    `).join('');
}

function renderDues(list) {
    const tbody = document.getElementById('duesBody');
    const badge = document.getElementById('duesTotalBadge');
    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i>কোনো বাকি নেই</td></tr>';
        badge.textContent = fmt(0);
        return;
    }
    let total = 0;
    tbody.innerHTML = list.map(c => {
        total += parseFloat(c.total_due);
        return `
        <tr>
            <td>${esc(c.customer_name)}
                ${c.phone ? `<span class="text-muted small d-block">${esc(c.phone)}</span>` : ''}
            </td>
            <td class="text-end"><span class="badge bg-danger">${fmt(c.total_due)}</span></td>
        </tr>`;
    }).join('');
    badge.textContent = fmt(total);
}

function renderStock(stock) {
    const tbody  = document.getElementById('stockBody');
    const map    = { rod: 'রড', cement: 'সিমেন্ট' };
    const rows   = stock.rows || [];
    const totals = stock.totals || {};

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">কোনো পণ্য নেই</td></tr>';
    } else {
        tbody.innerHTML = rows.map(r => `
            <tr class="${parseFloat(r.current_stock) <= 0 ? 'table-warning' : ''}">
                <td>${esc(r.product_name)}</td>
                <td><span class="badge bg-${r.product_type === 'rod' ? 'danger' : 'primary'}">${map[r.product_type] || ''}</span></td>
                <td class="text-end">${num(r.current_stock)} ${esc(r.unit)}</td>
                <td class="text-end">${fmt(r.buy_price)}</td>
                <td class="text-end">${fmt(r.stock_cost)}</td>
                <td class="text-end text-success">${fmt(r.stock_value)}</td>
            </tr>
        `).join('');
    }
    document.getElementById('stockCostTotal').textContent  = fmt(totals.cost);
    document.getElementById('stockValueTotal').textContent = fmt(totals.value);
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', loadReport);
