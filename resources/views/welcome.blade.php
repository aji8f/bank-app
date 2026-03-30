<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Banking — Cloud Test Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', -apple-system, sans-serif; }
        .card { transition: box-shadow 0.2s; }
        .card:hover { box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .toast { animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .4; } }
        .spin { animation: spin 1s linear infinite; display: inline-block; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Toast -->
<div id="toast" class="hidden fixed top-4 right-4 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-xl text-white text-sm font-medium toast max-w-xs">
    <span id="toast-icon" class="text-base flex-shrink-0"></span>
    <span id="toast-msg"></span>
</div>

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-lg select-none">B</div>
            <div>
                <h1 class="text-lg font-bold text-gray-900 leading-none">Simple Banking</h1>
                <p class="text-xs text-gray-400">AWS Cloud Test Dashboard</p>
            </div>
        </div>
        <div id="health-badge" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
            <span class="w-2 h-2 rounded-full bg-gray-300 pulse-dot inline-block"></span> Checking...
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8 space-y-8">

    <!-- Stats Bar -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total User</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="stat-users">—</p>
        </div>
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Dana</p>
            <p class="text-xl font-bold text-gray-900 mt-1 leading-tight" id="stat-balance">—</p>
        </div>
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Transaksi</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="stat-tx">—</p>
        </div>
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">API Status</p>
            <p class="text-xl font-bold mt-1" id="stat-status">—</p>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid md:grid-cols-2 gap-6">

        <!-- Akun Nasabah -->
        <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">Akun Nasabah</h2>
                <button onclick="loadUsers()" class="text-xs text-blue-500 hover:text-blue-700 font-medium transition-colors">↺ Refresh</button>
            </div>
            <div id="users-list" class="divide-y divide-gray-50">
                <div class="px-6 py-8 text-center text-gray-400 text-sm">Memuat data...</div>
            </div>
        </div>

        <!-- Transfer -->
        <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50">
                <h2 class="font-semibold text-gray-800">Transfer Dana</h2>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Dari (pengirim)</label>
                    <select id="tf-from" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Pilih pengirim...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Ke (penerima)</label>
                    <select id="tf-to" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Pilih penerima...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Jumlah (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium pointer-events-none">Rp</span>
                        <input id="tf-amount" type="number" min="1000" step="1000" placeholder="50000"
                            class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <button id="tf-btn" onclick="doTransfer()"
                    class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-medium py-2.5 rounded-lg text-sm transition-colors">
                    → Transfer
                </button>
                <div id="tf-result" class="hidden rounded-lg p-3 text-sm leading-relaxed"></div>
            </div>
        </div>

        <!-- Deposit -->
        <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50">
                <h2 class="font-semibold text-gray-800">Deposit</h2>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">User</label>
                    <select id="dp-user" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 bg-white">
                        <option value="">Pilih user...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Jumlah (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium pointer-events-none">Rp</span>
                        <input id="dp-amount" type="number" min="1000" step="1000" placeholder="100000"
                            class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
                <button id="dp-btn" onclick="doDeposit()"
                    class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white font-medium py-2.5 rounded-lg text-sm transition-colors">
                    + Deposit
                </button>
                <div id="dp-result" class="hidden rounded-lg p-3 text-sm leading-relaxed"></div>
            </div>
        </div>

        <!-- Riwayat Transaksi -->
        <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50">
                <h2 class="font-semibold text-gray-800">Riwayat Transaksi</h2>
            </div>
            <div class="p-6 space-y-3">
                <div class="flex gap-2">
                    <select id="tx-user" class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Pilih user...</option>
                    </select>
                    <button onclick="loadTransactions()"
                        class="px-4 py-2.5 bg-gray-800 hover:bg-gray-900 text-white text-sm rounded-lg font-medium transition-colors whitespace-nowrap">
                        Lihat
                    </button>
                </div>
                <div id="tx-count" class="hidden text-xs text-gray-400 pb-1"></div>
                <div id="tx-list" class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    <div class="text-center text-gray-300 text-sm py-8">Pilih user untuk melihat transaksi</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Check Balance Panel -->
    <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-50">
            <h2 class="font-semibold text-gray-800">Cek Saldo</h2>
        </div>
        <div class="p-6 flex flex-col sm:flex-row gap-3">
            <select id="bal-user" class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Pilih user...</option>
            </select>
            <button onclick="checkBalance()"
                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg font-medium transition-colors whitespace-nowrap">
                Cek Saldo
            </button>
            <div id="bal-result" class="hidden sm:flex items-center gap-2 px-4 py-2.5 bg-blue-50 border border-blue-200 rounded-lg text-sm font-semibold text-blue-800"></div>
        </div>
    </div>

    <!-- API Endpoints -->
    <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-50">
            <h2 class="font-semibold text-gray-800">API Endpoints</h2>
            <p class="text-xs text-gray-400 mt-0.5">Klik untuk copy URL • Total <span id="ep-count">7</span> endpoint</p>
        </div>
        <div class="p-6">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3" id="endpoint-list"></div>
        </div>
    </div>

</main>

<script>
const BASE = '/api';
let allUsers = [];

// ─── Toast ────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
    const t = document.getElementById('toast');
    const cfg = {
        success: { bg: 'bg-green-600', icon: '✓' },
        error:   { bg: 'bg-red-500',   icon: '✗' },
        info:    { bg: 'bg-blue-600',  icon: 'ℹ' },
        warn:    { bg: 'bg-amber-500', icon: '⚠' },
    };
    const c = cfg[type] || cfg.info;
    t.className = `fixed top-4 right-4 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-xl text-white text-sm font-medium toast max-w-xs ${c.bg}`;
    document.getElementById('toast-icon').textContent = c.icon;
    document.getElementById('toast-msg').textContent  = msg;
    t.classList.remove('hidden');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.add('hidden'), 3500);
}

// ─── Format helpers ───────────────────────────────────────────────────────
function rupiah(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

function timeAgo(dateStr) {
    const s = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (s < 60)    return `${s}s lalu`;          // FIX: was "${s}d lalu"
    if (s < 3600)  return `${Math.floor(s / 60)}m lalu`;
    if (s < 86400) return `${Math.floor(s / 3600)}j lalu`;
    return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function setBtnLoading(id, loading, text) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.disabled   = loading;
    btn.textContent = text;
}

// ─── Health Check ─────────────────────────────────────────────────────────
async function checkHealth() {
    try {
        const r = await fetch(`${BASE}/health`);
        const d = await r.json();
        const ok = d.status === 'ok' && d.db === 'connected';
        const badge = document.getElementById('health-badge');
        badge.innerHTML = `<span class="w-2 h-2 rounded-full ${ok ? 'bg-green-400' : 'bg-red-400'} inline-block"></span> ${ok ? 'API Online' : (d.db === 'disconnected' ? 'DB Error' : 'API Error')}`;
        badge.className = `flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium ${ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`;
        const ss = document.getElementById('stat-status');
        ss.textContent = ok ? '✓ Online' : '✗ Error';
        ss.className   = `text-xl font-bold mt-1 ${ok ? 'text-green-600' : 'text-red-500'}`;
    } catch {
        const badge = document.getElementById('health-badge');
        badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> Offline`;
        badge.className = 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-red-50 text-red-700';
        document.getElementById('stat-status').textContent = '✗ Offline';
        document.getElementById('stat-status').className   = 'text-xl font-bold mt-1 text-red-500';
    }
}

// ─── Stats ────────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const r = await fetch(`${BASE}/stats`);
        const d = await r.json();
        document.getElementById('stat-tx').textContent = d.total_transactions ?? '—';
    } catch {}
}

// ─── Load Users ───────────────────────────────────────────────────────────
async function loadUsers() {
    const list = document.getElementById('users-list');
    list.innerHTML = `<div class="px-6 py-8 text-center text-gray-400 text-sm"><span class="spin">⟳</span> Memuat...</div>`;

    try {
        const r = await fetch(`${BASE}/users`);
        const d = await r.json();
        allUsers = d.users || [];   // FIX: backend returns 'users', not 'data'

        const total = allUsers.reduce((s, u) => s + Number(u.balance), 0);
        document.getElementById('stat-users').textContent   = allUsers.length;
        document.getElementById('stat-balance').textContent = rupiah(total);

        if (!allUsers.length) {
            list.innerHTML = `<div class="px-6 py-8 text-center text-gray-400 text-sm">Belum ada user. Jalankan: <code class="bg-gray-100 px-1 rounded">php artisan db:seed</code></div>`;
            return;
        }

        list.innerHTML = allUsers.map(u => `
            <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm flex-shrink-0 select-none">
                        ${u.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">${escHtml(u.name)}</p>
                        <p class="text-xs text-gray-400">${escHtml(u.email)}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-800">${rupiah(u.balance)}</p>
                    <p class="text-xs text-gray-400">ID #${u.id}</p>
                </div>
            </div>
        `).join('');

        // Populate all selects
        const opts = allUsers.map(u => `<option value="${u.id}">${escHtml(u.name)} (#${u.id})</option>`).join('');
        ['tf-from', 'tf-to', 'dp-user', 'tx-user', 'bal-user'].forEach(id => {
            const sel = document.getElementById(id);
            const cur = sel.value;
            sel.innerHTML = `<option value="">Pilih...</option>${opts}`;
            if (cur) sel.value = cur;
        });

    } catch (e) {
        list.innerHTML = `<div class="px-6 py-8 text-center text-red-400 text-sm">Gagal memuat data. Cek koneksi database.</div>`;
    }
}

// ─── Check Balance ────────────────────────────────────────────────────────
async function checkBalance() {
    const uid    = document.getElementById('bal-user').value;
    const result = document.getElementById('bal-result');
    if (!uid) { toast('Pilih user dulu', 'warn'); return; }

    result.classList.add('hidden');
    try {
        const r = await fetch(`${BASE}/balance/${uid}`);
        const d = await r.json();

        if (r.ok) {
            result.innerHTML = `<span class="text-blue-400">💰</span> ${escHtml(d.name)}: <strong>${rupiah(d.balance)}</strong>`;
            result.classList.remove('hidden');
        } else {
            toast(d.error || 'User tidak ditemukan', 'error');
        }
    } catch {
        toast('Koneksi error', 'error');
    }
}

// ─── Transfer ─────────────────────────────────────────────────────────────
async function doTransfer() {
    const from   = document.getElementById('tf-from').value;
    const to     = document.getElementById('tf-to').value;
    const amount = document.getElementById('tf-amount').value;
    const result = document.getElementById('tf-result');

    if (!from || !to || !amount) { toast('Lengkapi semua field', 'warn'); return; }
    if (from === to) { toast('Pengirim dan penerima tidak boleh sama', 'error'); return; }
    if (Number(amount) <= 0) { toast('Jumlah harus lebih dari 0', 'error'); return; }

    setBtnLoading('tf-btn', true, '⟳ Memproses...');
    result.classList.add('hidden');

    try {
        const r = await fetch(`${BASE}/transfer`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body:    JSON.stringify({ from: +from, to: +to, amount: +amount }),
        });
        const d = await r.json();
        result.classList.remove('hidden');

        if (r.ok) {
            // FIX: backend now returns flat from_balance / to_balance
            const fromName = allUsers.find(u => u.id == from)?.name || `#${from}`;
            const toName   = allUsers.find(u => u.id == to)?.name   || `#${to}`;
            result.className = 'rounded-lg p-3 text-sm leading-relaxed bg-green-50 text-green-800 border border-green-200';
            result.innerHTML = `
                <div class="font-semibold mb-1">✓ Transfer berhasil — ${rupiah(d.amount)}</div>
                <div class="text-xs space-y-0.5 text-green-700">
                    <div>📤 ${escHtml(fromName)}: <strong>${rupiah(d.from_balance)}</strong></div>
                    <div>📥 ${escHtml(toName)}: <strong>${rupiah(d.to_balance)}</strong></div>
                    <div class="text-green-500 mt-1">TX #${d.transaction_id}</div>
                </div>`;
            toast('Transfer berhasil!', 'success');
            document.getElementById('tf-amount').value = '';
            loadUsers();
            loadStats();
        } else {
            result.className = 'rounded-lg p-3 text-sm leading-relaxed bg-red-50 text-red-700 border border-red-200';
            result.innerHTML = `<strong>✗ ${escHtml(d.error)}</strong> <span class="text-xs opacity-60">(${d.code})</span>`;
            toast(d.error, 'error');
        }
    } catch {
        toast('Koneksi error', 'error');
    } finally {
        setBtnLoading('tf-btn', false, '→ Transfer');
    }
}

// ─── Deposit ──────────────────────────────────────────────────────────────
async function doDeposit() {
    const user_id = document.getElementById('dp-user').value;
    const amount  = document.getElementById('dp-amount').value;
    const result  = document.getElementById('dp-result');

    if (!user_id || !amount) { toast('Lengkapi semua field', 'warn'); return; }
    if (Number(amount) <= 0) { toast('Jumlah harus lebih dari 0', 'error'); return; }

    setBtnLoading('dp-btn', true, '⟳ Memproses...');
    result.classList.add('hidden');

    try {
        const r = await fetch(`${BASE}/deposit`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body:    JSON.stringify({ user_id: +user_id, amount: +amount }),
        });
        const d = await r.json();
        result.classList.remove('hidden');

        if (r.ok) {
            // FIX: backend now returns 'balance' key
            result.className = 'rounded-lg p-3 text-sm leading-relaxed bg-green-50 text-green-800 border border-green-200';
            result.innerHTML = `
                <div class="font-semibold mb-1">✓ Deposit berhasil!</div>
                <div class="text-xs text-green-700">
                    Saldo baru <strong>${escHtml(d.name)}</strong>: <strong>${rupiah(d.balance)}</strong>
                    <span class="text-green-500 ml-2">TX #${d.transaction_id}</span>
                </div>`;
            toast('Deposit berhasil!', 'success');
            document.getElementById('dp-amount').value = '';
            loadUsers();
            loadStats();
        } else {
            result.className = 'rounded-lg p-3 text-sm leading-relaxed bg-red-50 text-red-700 border border-red-200';
            result.innerHTML = `<strong>✗ ${escHtml(d.error)}</strong> <span class="text-xs opacity-60">(${d.code})</span>`;
            toast(d.error, 'error');
        }
    } catch {
        toast('Koneksi error', 'error');
    } finally {
        setBtnLoading('dp-btn', false, '+ Deposit');
    }
}

// ─── Transactions ─────────────────────────────────────────────────────────
async function loadTransactions() {
    const uid   = document.getElementById('tx-user').value;
    const list  = document.getElementById('tx-list');
    const count = document.getElementById('tx-count');
    if (!uid) { toast('Pilih user dulu', 'warn'); return; }

    list.innerHTML = `<div class="text-center text-gray-400 text-sm py-4"><span class="spin">⟳</span> Memuat...</div>`;
    count.classList.add('hidden');

    try {
        const r = await fetch(`${BASE}/transactions/${uid}`);
        const d = await r.json();

        if (!r.ok) {
            list.innerHTML = `<div class="text-center text-red-400 text-sm py-4">${escHtml(d.error)}</div>`;
            return;
        }

        const txs = d.transactions || [];   // FIX: backend returns 'transactions', not 'data'

        if (!txs.length) {
            list.innerHTML = `<div class="text-center text-gray-300 text-sm py-8">Belum ada transaksi</div>`;
            return;
        }

        count.textContent = `${d.total} transaksi untuk ${escHtml(d.name)}`;
        count.classList.remove('hidden');

        const typeCfg = {
            transfer:   { color: 'text-orange-600', icon: '↔', label: 'Transfer' },
            deposit:    { color: 'text-green-600',  icon: '↓',  label: 'Deposit' },
            withdrawal: { color: 'text-red-500',    icon: '↑',  label: 'Tarik' },
        };

        list.innerHTML = txs.map(tx => {
            const cfg = typeCfg[tx.type] || { color: 'text-gray-500', icon: '·', label: tx.type };
            const isDebit = tx.direction === 'debit';
            return `
            <div class="flex items-start justify-between p-3 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="font-bold ${cfg.color}">${cfg.icon}</span>
                        <span class="text-xs font-semibold ${cfg.color}">${cfg.label}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded-full ${isDebit ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'}">${isDebit ? 'Debit' : 'Kredit'}</span>
                    </div>
                    <p class="text-xs text-gray-500 truncate">${escHtml(tx.note || '—')}</p>
                    <p class="text-xs text-gray-300 mt-0.5">${timeAgo(tx.created_at)}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-semibold ${isDebit ? 'text-red-600' : 'text-green-600'}">${isDebit ? '-' : '+'}${rupiah(tx.amount)}</p>
                    <span class="text-xs px-1.5 py-0.5 rounded-full ${tx.status === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'}">${tx.status}</span>
                </div>
            </div>`;
        }).join('');

    } catch {
        list.innerHTML = `<div class="text-center text-red-400 text-sm py-4">Gagal memuat transaksi</div>`;
    }
}

// ─── Endpoint Cards ───────────────────────────────────────────────────────
function renderEndpoints() {
    const eps = [
        { method: 'GET',  path: '/api/health',            color: 'bg-green-50 border-green-200',   badge: 'bg-green-500',   desc: 'Health check + DB status' },
        { method: 'GET',  path: '/api/stats',             color: 'bg-teal-50 border-teal-200',     badge: 'bg-teal-500',    desc: 'Statistik agregat sistem' },
        { method: 'GET',  path: '/api/users',             color: 'bg-blue-50 border-blue-200',     badge: 'bg-blue-500',    desc: 'Daftar semua nasabah' },
        { method: 'GET',  path: '/api/balance/{id}',      color: 'bg-blue-50 border-blue-200',     badge: 'bg-blue-500',    desc: 'Cek saldo per user' },
        { method: 'POST', path: '/api/transfer',          color: 'bg-orange-50 border-orange-200', badge: 'bg-orange-500',  desc: 'Transfer antar nasabah' },
        { method: 'POST', path: '/api/deposit',           color: 'bg-purple-50 border-purple-200', badge: 'bg-purple-500',  desc: 'Top-up saldo akun' },
        { method: 'GET',  path: '/api/transactions/{id}', color: 'bg-gray-50 border-gray-200',     badge: 'bg-gray-500',    desc: 'Riwayat transaksi user' },
    ];

    document.getElementById('ep-count').textContent = eps.length;
    document.getElementById('endpoint-list').innerHTML = eps.map(ep => `
        <div class="rounded-xl border p-4 ${ep.color} cursor-pointer hover:shadow-sm transition-all group" onclick="copyUrl('${ep.path}')">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-bold px-2 py-0.5 rounded-full text-white ${ep.badge} flex-shrink-0">${ep.method}</span>
                <code class="text-xs text-gray-600 font-mono truncate">${ep.path}</code>
            </div>
            <p class="text-xs text-gray-500">${ep.desc}</p>
            <p class="text-xs text-gray-300 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">Klik untuk copy</p>
        </div>
    `).join('');
}

function copyUrl(path) {
    navigator.clipboard.writeText(window.location.origin + path)
        .then(() => toast('URL disalin!', 'info'))
        .catch(() => toast(window.location.origin + path, 'info'));
}

// ─── XSS helper ──────────────────────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Init ─────────────────────────────────────────────────────────────────
(async () => {
    renderEndpoints();
    await Promise.all([checkHealth(), loadUsers(), loadStats()]);
    setInterval(checkHealth, 30000);
})();
</script>
</body>
</html>
