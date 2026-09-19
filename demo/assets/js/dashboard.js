// ============================================
// Dashboard
// pages/dashboard.php rendered its stats server-side; here the
// same numbers come from the mock API endpoint get_dashboard.php.
// ============================================

function dashEsc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function dashMoney(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

document.getElementById('todayLabel').textContent =
    new Date().toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric', weekday: 'long'
    });

fetch(BASE_URL + '/api/get_dashboard.php')
    .then(r => r.json())
    .then(res => {
        if (!res.success) return showToast(res.message, 'danger');
        renderDashboard(res.data);
    })
    .catch(() => showToast('ডেটা লোড করতে সমস্যা হয়েছে', 'danger'));

function renderDashboard(d) {
    document.getElementById('statTodayTotal').textContent = dashMoney(d.today_total);
    document.getElementById('statTodayPaid').textContent  = dashMoney(d.today_paid);
    document.getElementById('statTotalDue').textContent   = dashMoney(d.total_due);
    document.getElementById('statStockValue').textContent = dashMoney(d.stock_value);
    document.getElementById('statTodayCount').textContent = d.today_count;

    renderLowStock(d.low_stock);
    renderChart(d.chart);
}

function renderLowStock(items) {
    const alertBox = document.getElementById('lowStockAlert');
    const panel    = document.getElementById('lowStockPanel');

    if (!items.length) {
        alertBox.innerHTML = '';
        panel.innerHTML    = '<p class="text-center text-muted py-4 small">সব পণ্যের স্টক ঠিক আছে।</p>';
        return;
    }

    alertBox.innerHTML = `
        <div class="alert alert-warning alert-dismissible fade show mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>${items.length}টি পণ্যের স্টক কম!</strong>
            ${items.map(i => `<span class="badge bg-danger ms-1">${dashEsc(i.product_name)} (${i.current_stock} ${dashEsc(i.unit)})</span>`).join('')}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;

    panel.innerHTML = `
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>পণ্য</th><th>স্টক</th><th>মিনিমাম</th></tr></thead>
            <tbody>
                ${items.map(i => `
                    <tr>
                        <td>${dashEsc(i.product_name)}</td>
                        <td class="low-stock">${i.current_stock} ${dashEsc(i.unit)}</td>
                        <td>${i.min_stock} ${dashEsc(i.unit)}</td>
                    </tr>`).join('')}
            </tbody>
        </table>`;
}

function renderChart(rows) {
    const labels  = rows.map(r => {
        const [y, m, day] = r.sale_date.split('-').map(Number);
        return new Date(y, m - 1, day)
            .toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' });
    });
    const amounts = rows.map(r => parseFloat(r.total) || 0);

    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'বিক্রয় (৳)',
                data: amounts,
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
