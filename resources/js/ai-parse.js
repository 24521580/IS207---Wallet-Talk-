const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const config = window.Vinoi || {};
const form = document.getElementById('ai-form');
const textarea = document.getElementById('ai-text');
const submitBtn = document.getElementById('ai-submit');
const loading = document.getElementById('ai-loading');
const inputError = document.getElementById('ai-input-error');
const resultWrap = document.getElementById('ai-result');
const resultTitle = document.getElementById('ai-result-title');
const list = document.getElementById('tx-list');
const template = document.getElementById('tx-card-template');
const unresolved = document.getElementById('ai-unresolved');
const statusLine = document.getElementById('ai-status');
const summary = document.getElementById('ai-summary');
const emptyHint = document.getElementById('tx-empty');
const confirmForm = document.getElementById('confirm-form');
const confirmBtn = document.getElementById('confirm-submit');

const money = new Intl.NumberFormat('vi-VN');

function showError(message) {
    inputError.textContent = message;
    inputError.classList.remove('hidden');
}

function clearError() {
    inputError.textContent = '';
    inputError.classList.add('hidden');
}

function showStatus(message) {
    if (!message) {
        statusLine.classList.add('hidden');
        return;
    }

    statusLine.textContent = message;
    statusLine.classList.remove('hidden');
}

function categoriesFor(type) {
    return (config.categories || []).filter((item) => item.type === type);
}

function fillCategorySelect(select, type, selectedId) {
    const options = categoriesFor(type);
    select.innerHTML = options
        .map((item) => `<option value="${item.id}" ${String(item.id) === String(selectedId) ? 'selected' : ''}>${item.name}</option>`)
        .join('');
}

function addCard(data = {}) {
    const node = template.content.firstElementChild.cloneNode(true);
    const typeSelect = node.querySelector('.js-type');
    const categorySelect = node.querySelector('.js-category');
    const note = node.querySelector('.js-note');
    const amount = node.querySelector('.js-amount');
    const date = node.querySelector('.js-date');
    const source = node.querySelector('.js-source');

    typeSelect.value = data.type || 'expense';
    note.value = data.note || '';
    amount.value = data.amount || '';
    date.value = data.date || new Date().toISOString().slice(0, 10);
    source.value = data.source || 'manual';
    fillCategorySelect(categorySelect, typeSelect.value, data.category_id);

    typeSelect.addEventListener('change', () => {
        fillCategorySelect(categorySelect, typeSelect.value);
        refreshSummary();
    });
    amount.addEventListener('input', refreshSummary);

    node.querySelector('.js-remove').addEventListener('click', () => {
        node.remove();
        refreshIndexes();
    });

    list.appendChild(node);
    refreshIndexes();
}

function refreshSummary() {
    updateSummary([...list.children]);
}

function refreshIndexes() {
    const cards = [...list.children];

    cards.forEach((card, index) => {
        card.querySelector('.js-note').name = `transactions[${index}][note]`;
        card.querySelector('.js-amount').name = `transactions[${index}][amount]`;
        card.querySelector('.js-type').name = `transactions[${index}][type]`;
        card.querySelector('.js-date').name = `transactions[${index}][transaction_date]`;
        card.querySelector('.js-category').name = `transactions[${index}][category_id]`;
        card.querySelector('.js-source').name = `transactions[${index}][source]`;
        card.querySelector('.js-index').textContent = `Khoản ${index + 1}`;

        const isAi = card.querySelector('.js-source').value === 'ai';
        card.querySelector('.js-source-badge').textContent = isAi ? 'AI đề xuất' : 'Nhập thủ công';
    });

    const count = cards.length;
    resultTitle.textContent = count > 0 ? `AI đã nhận diện ${count} giao dịch` : 'Chưa có giao dịch nào';
    emptyHint.classList.toggle('hidden', count > 0);
    confirmBtn.disabled = count === 0;
    confirmBtn.classList.toggle('opacity-50', count === 0);

    updateSummary(cards);
}

// Tổng thu/chi tạm tính của danh sách đang chờ xác nhận.
function updateSummary(cards) {
    let income = 0;
    let expense = 0;

    cards.forEach((card) => {
        const amount = Number(card.querySelector('.js-amount').value || 0);
        if (!Number.isFinite(amount)) return;

        if (card.querySelector('.js-type').value === 'income') {
            income += amount;
        } else {
            expense += amount;
        }
    });

    if (cards.length === 0) {
        summary.classList.add('hidden');
        summary.classList.remove('flex');
        return;
    }

    summary.innerHTML = `
        <span class="chip">${cards.length} giao dịch</span>
        <span class="chip amount-out">Chi ${money.format(expense)} ₫</span>
        <span class="chip amount-in">Thu ${money.format(income)} ₫</span>
    `;
    summary.classList.remove('hidden');
    summary.classList.add('flex');
}

document.querySelectorAll('.example-chip').forEach((chip) => {
    chip.addEventListener('click', () => {
        textarea.value = chip.textContent.replaceAll('“', '').replaceAll('”', '').trim();
    });
});

document.getElementById('add-manual')?.addEventListener('click', () => {
    resultWrap.classList.remove('hidden');
    addCard({ source: 'manual', type: 'expense' });
});

form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearError();
    showStatus('');
    const text = textarea.value.trim();
    if (!text) {
        showError('Vui lòng nhập nội dung chi tiêu.');
        return;
    }

    submitBtn.disabled = true;
    loading.classList.remove('hidden');
    loading.classList.add('flex');

    try {
        const response = await fetch(config.parseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ text }),
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            showError(payload.message || 'Không thể kết nối với AI. Vui lòng thử lại hoặc nhập thủ công.');
            resultWrap.classList.remove('hidden');
            return;
        }

        list.innerHTML = '';
        payload.data.transactions.forEach((item) => addCard(item));
        resultWrap.classList.remove('hidden');
        showStatus(payload.message);

        if (payload.data.unresolved?.length) {
            unresolved.classList.remove('hidden');
            unresolved.textContent = `Có ${payload.data.unresolved.length} đoạn chưa chắc chắn. Hãy kiểm tra lại hoặc thêm khoản thủ công.`;
        } else {
            unresolved.classList.add('hidden');
            unresolved.textContent = '';
        }

        if (payload.data.demo) {
            resultTitle.textContent = `${resultTitle.textContent} (Demo AI Mode)`;
        }
    } catch (error) {
        showError('Không thể kết nối với AI. Vui lòng thử lại hoặc nhập thủ công.');
        resultWrap.classList.remove('hidden');
    } finally {
        submitBtn.disabled = false;
        loading.classList.add('hidden');
        loading.classList.remove('flex');
    }
});

// Chặn submit hai lần khi lưu nhiều giao dịch.
let saving = false;
confirmForm?.addEventListener('submit', (event) => {
    if (saving) {
        event.preventDefault();
        return;
    }

    saving = true;
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Đang lưu…';
});
