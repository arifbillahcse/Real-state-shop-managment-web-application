/* global BASE_URL, CAN_WRITE */

let searchTimer = null;

function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ── Load notes ──────────────────────────────────────────────────────────────
function loadNotes(search) {
    const list = document.getElementById('notesList');
    list.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></div>';

    const url = BASE_URL + '/api/get_free_notes.php' + (search ? '?search=' + encodeURIComponent(search) : '');
    fetch(url)
        .then(r => r.json())
        .then(res => {
            if (res.success) renderNotes(res.notes);
            else list.innerHTML = `<div class="alert alert-danger">${esc(res.message)}</div>`;
        })
        .catch(() => {
            list.innerHTML = '<div class="alert alert-danger">ডেটা লোড করতে সমস্যা হয়েছে।</div>';
        });
}

function renderNotes(notes) {
    const list = document.getElementById('notesList');

    if (!notes.length) {
        list.innerHTML = `
        <div class="text-center py-5 text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
            কোনো নোট পাওয়া যায়নি
        </div>`;
        return;
    }

    list.innerHTML = notes.map(n => `
    <div class="card shadow-sm mb-3 note-card" id="note-${n.id}">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <span class="fw-bold text-primary">
                            <i class="bi bi-person-circle me-1"></i>${esc(n.customer_name)}
                        </span>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-calendar3 me-1"></i>${esc(n.note_date)}
                        </span>
                        <span class="text-muted small">
                            <i class="bi bi-pencil me-1"></i>${esc(n.author)}
                        </span>
                    </div>
                    <div class="note-text" style="white-space:pre-wrap;line-height:1.6">${esc(n.note)}</div>
                </div>
                ${CAN_WRITE ? `
                <button class="btn btn-sm btn-outline-danger flex-shrink-0" onclick="deleteNote(${n.id})" title="মুছুন">
                    <i class="bi bi-trash"></i>
                </button>` : ''}
            </div>
        </div>
    </div>`).join('');
}

// ── Submit ───────────────────────────────────────────────────────────────────
function submitNote(e) {
    e.preventDefault();
    const name = document.getElementById('nCustomerName').value.trim();
    const note = document.getElementById('nText').value.trim();
    const date = document.getElementById('nDate').value;
    if (!name || !note) return;

    const btn = document.getElementById('noteSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>সংরক্ষণ হচ্ছে...';

    fetch(BASE_URL + '/api/add_free_note.php', {
        method: 'POST',
        body:   new URLSearchParams({ customer_name: name, note, note_date: date })
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>সংরক্ষণ করুন';
        if (res.success) {
            document.getElementById('noteForm').reset();
            document.getElementById('nDate').value = new Date().toISOString().slice(0, 10);
            document.getElementById('charCount').textContent = '0';
            showToast(res.message, 'success');
            loadNotes(document.getElementById('searchInput').value.trim());
        } else {
            showToast(res.message, 'danger');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>সংরক্ষণ করুন';
        showToast('সমস্যা হয়েছে। আবার চেষ্টা করুন।', 'danger');
    });
}

// ── Delete ───────────────────────────────────────────────────────────────────
function deleteNote(id) {
    if (!confirm('এই নোটটি মুছে ফেলবেন?')) return;

    fetch(BASE_URL + '/api/delete_free_note.php', {
        method: 'POST',
        body:   new URLSearchParams({ id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const card = document.getElementById('note-' + id);
            if (card) card.remove();
            showToast(res.message, 'success');
            if (!document.querySelector('.note-card')) {
                document.getElementById('notesList').innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-1 d-block mb-2 opacity-25"></i>
                    কোনো নোট পাওয়া যায়নি
                </div>`;
            }
        } else {
            showToast(res.message, 'danger');
        }
    })
    .catch(() => showToast('মুছতে সমস্যা হয়েছে।', 'danger'));
}

// ── Search ───────────────────────────────────────────────────────────────────
function clearSearch() {
    document.getElementById('searchInput').value = '';
    loadNotes('');
}

document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const val = this.value.trim();
    searchTimer = setTimeout(() => loadNotes(val), 350);
});

// ── Char counter ─────────────────────────────────────────────────────────────
const nText = document.getElementById('nText');
if (nText) {
    nText.addEventListener('input', () => {
        document.getElementById('charCount').textContent = nText.value.length;
    });
}

// Initial load
loadNotes('');
