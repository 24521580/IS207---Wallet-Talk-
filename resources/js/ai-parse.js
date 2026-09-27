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

function addCard(data = {}, budgetWarning = null) {
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

    // Budget warning per-card
    const warningEl = node.querySelector('.js-budget-warning');
    if (budgetWarning && budgetWarning.over_budget && warningEl) {
        const fmt = (n) => new Intl.NumberFormat('vi-VN').format(n);
        warningEl.innerHTML = `
            <strong>⚠ Vượt hạn mức ${budgetWarning.category_name}!</strong>
            <span class="ml-1">Hạn mức: ${fmt(budgetWarning.limit_amount)}đ &middot;
            Đã chi: ${fmt(budgetWarning.current_spending)}đ &middot;
            Giao dịch này: ${fmt(budgetWarning.added_amount)}đ &middot;
            Dự kiến: ${fmt(budgetWarning.projected_spending)}đ
            (vượt ${fmt(budgetWarning.over_amount)}đ)</span>
        `;
        warningEl.classList.remove('hidden');
    }

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
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ text }),
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload || !payload.ok) {
            const errorMsg = (payload && payload.message)
                ? payload.message
                : (response.status === 419 ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.' : 'Không thể kết nối với AI. Vui lòng thử lại hoặc nhập thủ công.');
            showError(errorMsg);
            resultWrap.classList.remove('hidden');
            return;
        }

        list.innerHTML = '';
        payload.data.transactions.forEach((item) => {
            // Map warning theo category_id (key là string từ JSON)
            const warn = payload.budget_warnings
                ? (payload.budget_warnings[String(item.category_id)] || null)
                : null;
            addCard(item, warn);
        });
        resultWrap.classList.remove('hidden');
        showStatus(payload.message);

        // Budget warnings tổng hợp ở đầu section
        const budgetWarningsEl = document.getElementById('budget-warnings');
        if (budgetWarningsEl) {
            const warnings = payload.budget_warnings || {};
            const overItems = Object.values(warnings).filter((w) => w.over_budget);
            if (overItems.length > 0) {
                budgetWarningsEl.innerHTML = overItems.map((w) => {
                    const fmt = (n) => new Intl.NumberFormat('vi-VN').format(n);
                    return `<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/40 dark:bg-red-900/20 dark:text-red-300">
                        <strong>⚠ Chú ý:</strong> Giao dịch này sẽ làm bạn vượt hạn mức <strong>${w.category_name}</strong> tháng này!
                        <span class="ml-1 text-red-700 dark:text-red-400">
                            Hạn mức: ${fmt(w.limit_amount)}đ &middot;
                            Đã chi: ${fmt(w.current_spending)}đ &middot;
                            Dự kiến: ${fmt(w.projected_spending)}đ &middot;
                            Vượt: ${fmt(w.over_amount)}đ
                        </span>
                    </div>`;
                }).join('');
                budgetWarningsEl.classList.remove('hidden');
                budgetWarningsEl.classList.add('grid');
            } else {
                budgetWarningsEl.classList.add('hidden');
                budgetWarningsEl.classList.remove('grid');
                budgetWarningsEl.innerHTML = '';
            }
        }

        if (payload.data.unresolved?.length) {
            unresolved.classList.remove('hidden');
            unresolved.textContent = `Có ${payload.data.unresolved.length} đoạn chưa chắc chắn. Hãy kiểm tra lại hoặc thêm khoản thủ công.`;
        } else {
            unresolved.classList.add('hidden');
            unresolved.textContent = '';
        }

        if (payload.data.demo) {
            resultTitle.textContent = `${resultTitle.textContent} (Demo AI Mode)`;
        } else {
            const provName = payload.data.provider ? payload.data.provider.toUpperCase() : 'LIVE';
            resultTitle.textContent = `${resultTitle.textContent} (AI: ${provName})`;
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

// ─────────────────────────────────────────────────────────────────────────────
// VOICE INPUT MODULE
// Voice chỉ là phương thức nhập liệu. Không thay đổi AI pipeline.
// Không lưu audio. Không tự động gọi AI. Không tự động save.
// ─────────────────────────────────────────────────────────────────────────────

(function initVoiceInput() {
    // ── 1. DOM refs ──────────────────────────────────────────────────────────
    const voiceControls   = document.getElementById('voice-controls');
    const voiceBtn        = document.getElementById('voice-btn');
    const voiceBtnLabel   = document.getElementById('voice-btn-label');
    const voiceIconMic    = document.getElementById('voice-icon-mic');
    const voiceIconStop   = document.getElementById('voice-icon-stop');
    const voiceStatus     = document.getElementById('voice-status');
    const voiceError      = document.getElementById('voice-error');
    const voiceInterimWrap = document.getElementById('voice-interim-wrap');
    const voiceInterimText = document.getElementById('voice-interim-text');
    // textarea và inputError đã được khai báo ở scope ngoài (ai-parse.js)
    // nhưng do IIFE tách scope, truy cập lại qua DOM cho an toàn
    const aiTextarea      = document.getElementById('ai-text');

    // Nếu thiếu bất kỳ element nào → không mount để tránh lỗi
    if (!voiceControls || !voiceBtn || !aiTextarea) return;

    // ── 2. Feature detection ─────────────────────────────────────────────────
    const SpeechRecognition =
        window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
        // Browser không hỗ trợ: hiện controls nhưng disable button + tooltip
        voiceControls.classList.remove('hidden');
        voiceControls.classList.add('flex');
        voiceBtn.disabled = true;
        voiceBtn.title = 'Trình duyệt của bạn chưa hỗ trợ nhập bằng giọng nói. Bạn vẫn có thể nhập bằng bàn phím.';
        voiceBtnLabel.textContent = 'Nói (chưa hỗ trợ)';
        voiceBtn.classList.add('opacity-40', 'cursor-not-allowed');
        return;
    }

    // Browser hỗ trợ → hiện voice controls
    voiceControls.classList.remove('hidden');
    voiceControls.classList.add('flex');

    // ── 3. State ─────────────────────────────────────────────────────────────
    /** @type {'idle'|'listening'|'stopping'} */
    let state = 'idle';

    /** @type {SpeechRecognition|null} */
    let recognition = null;

    // ── 4. Helpers ───────────────────────────────────────────────────────────
    function showVoiceError(msg) {
        if (!voiceError) return;
        voiceError.textContent = msg;
        voiceError.classList.remove('hidden');
    }

    function clearVoiceError() {
        if (!voiceError) return;
        voiceError.textContent = '';
        voiceError.classList.add('hidden');
    }

    function showVoiceStatus(msg) {
        if (!voiceStatus) return;
        if (msg) {
            voiceStatus.textContent = msg;
            voiceStatus.classList.remove('hidden');
        } else {
            voiceStatus.textContent = '';
            voiceStatus.classList.add('hidden');
        }
    }

    function showInterim(text) {
        if (!voiceInterimWrap || !voiceInterimText) return;
        if (text) {
            voiceInterimText.textContent = text;
            voiceInterimWrap.classList.remove('hidden');
        } else {
            voiceInterimText.textContent = '';
            voiceInterimWrap.classList.add('hidden');
        }
    }

    /** Commit final transcript vào textarea (append hoặc set) */
    function commitTranscript(transcript) {
        const trimmed = transcript.trim();
        if (!trimmed) return;

        const current = aiTextarea.value;
        if (current.trim() === '') {
            aiTextarea.value = trimmed;
        } else {
            // Append với khoảng trắng phù hợp
            const needsSpace = !current.endsWith(' ') && !current.endsWith('\n');
            aiTextarea.value = current + (needsSpace ? ' ' : '') + trimmed;
        }

        // Dispatch input event để bất kỳ listener nào trên textarea biết giá trị thay đổi
        aiTextarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // ── 5. UI state transitions ───────────────────────────────────────────────
    function enterListening() {
        state = 'listening';
        voiceBtn.setAttribute('aria-pressed', 'true');
        voiceBtn.classList.remove('voice-btn-idle');
        voiceBtn.classList.add('voice-btn-listening');
        voiceBtn.title = 'Đang nghe... Bấm để dừng';
        voiceBtnLabel.textContent = 'Đang nghe...';
        voiceIconMic.classList.add('hidden');
        voiceIconStop.classList.remove('hidden');
        showVoiceStatus('Đang nghe… hãy nói bằng tiếng Việt');
        clearVoiceError();
    }

    function enterIdle() {
        state = 'idle';
        voiceBtn.setAttribute('aria-pressed', 'false');
        voiceBtn.classList.remove('voice-btn-listening');
        voiceBtn.classList.add('voice-btn-idle');
        voiceBtn.title = 'Nói bằng giọng nói';
        voiceBtnLabel.textContent = 'Nói';
        voiceIconMic.classList.remove('hidden');
        voiceIconStop.classList.add('hidden');
        showVoiceStatus('');
        showInterim('');
    }

    // ── 6. Recognition lifecycle ─────────────────────────────────────────────
    function createRecognition() {
        const r = new SpeechRecognition();
        r.lang = 'vi-VN';
        r.interimResults = true;
        r.continuous = false;
        r.maxAlternatives = 1;

        // Tích lũy final transcript trong phiên này
        // (continuous=false nên thường chỉ có 1 lần onresult cuối)
        let sessionFinalTranscript = '';

        r.onstart = function () {
            enterListening();
        };

        r.onresult = function (event) {
            let interimBuffer = '';
            // Duyệt từ resultIndex để không xử lý lại kết quả cũ
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                const text = result[0].transcript;
                if (result.isFinal) {
                    sessionFinalTranscript += (sessionFinalTranscript ? ' ' : '') + text.trim();
                    interimBuffer = ''; // xóa interim khi có final
                } else {
                    interimBuffer = text;
                }
            }
            // Chỉ cập nhật interim display, KHÔNG chạm vào textarea
            showInterim(interimBuffer);
        };

        r.onend = function () {
            // Commit final transcript (nếu có) vào textarea
            if (sessionFinalTranscript.trim()) {
                commitTranscript(sessionFinalTranscript);
            }
            showInterim('');
            enterIdle();
            recognition = null;
        };

        r.onerror = function (event) {
            const errorMessages = {
                'not-allowed':       'Bạn chưa cấp quyền microphone. Vui lòng cho phép microphone hoặc nhập bằng bàn phím.',
                'permission-denied': 'Bạn chưa cấp quyền microphone. Vui lòng cho phép microphone hoặc nhập bằng bàn phím.',
                'audio-capture':     'Không thể truy cập microphone. Vui lòng kiểm tra thiết bị hoặc nhập bằng bàn phím.',
                'no-speech':         'Không nhận diện được giọng nói. Vui lòng thử lại.',
                'network':           'Lỗi mạng khi nhận diện giọng nói. Vui lòng kiểm tra kết nối internet.',
                'aborted':           '', // user tự stop → không hiện lỗi
                'service-not-allowed': 'Trình duyệt không cho phép dùng giọng nói trên trang này (HTTP). Vui lòng dùng HTTPS.',
            };

            const msg = errorMessages[event.error] ?? 'Không nhận diện được giọng nói. Vui lòng thử lại.';
            if (msg) showVoiceError(msg);

            // onend sẽ được gọi sau onerror → enterIdle() tự chạy
        };

        return r;
    }

    function startListening() {
        clearVoiceError();
        recognition = createRecognition();
        try {
            recognition.start();
        } catch (err) {
            // Có thể throw nếu recognition đang chạy
            showVoiceError('Không thể khởi động microphone. Vui lòng thử lại.');
            enterIdle();
            recognition = null;
        }
    }

    function stopListening() {
        if (recognition) {
            state = 'stopping';
            try {
                recognition.stop();
            } catch (_) {
                // ignore — onend sẽ cleanup
            }
        }
    }

    // ── 7. Button click handler ───────────────────────────────────────────────
    voiceBtn.addEventListener('click', () => {
        if (state === 'idle') {
            startListening();
        } else if (state === 'listening') {
            stopListening();
        }
        // state === 'stopping': bỏ qua click thêm
    });

    // ── 8. Cleanup khi rời trang ─────────────────────────────────────────────
    window.addEventListener('pagehide', () => {
        if (recognition && state === 'listening') {
            try { recognition.abort(); } catch (_) {}
            recognition = null;
        }
    });
})();
