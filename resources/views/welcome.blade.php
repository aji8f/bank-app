<!DOCTYPE html>
<html lang="en">
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
        @keyframes slideIn { from { transform: translateX(110%); opacity:0; } to { transform: translateX(0); opacity:1; } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Toast -->
<div id="toast" class="hidden fixed top-4 right-4 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-xl text-white text-sm font-medium toast">
    <span id="toast-icon"></span>
    <span id="toast-msg"></span>
</div>

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-lg">B</div>
            <div>
                <h1 class="text-lg font-bold text-gray-900 leading-none">Simple Banking</h1>
                <p class="text-xs text-gray-400">AWS Cloud Test Dashboard</p>
            </div>
        </div>
        <div id="health-badge" class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
            <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span> Checking...
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8 space-y-8">

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Users</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="stat-users">—</p>
        </div>
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Total Dana</p>
            <p class="text-xl font-bold text-gray-900 mt-1" id="stat-balance">—</p>
        </div>
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Transaksi</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="stat-tx">—</p>
        </div>
        <div class="bg-white rounded-xl p-5 card border border-gray-100">
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">API Status</p>
            <p class="text-2xl font-bold mt-1" id="stat-status">—</p>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid md:grid-cols-2 gap-6">

        <!-- Accounts List -->
        <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                <h2 class="font-semibold text-gray-800">Akun Nasabah</h2>
                <button onclick="loadUsers()" class="text-xs text-blue-500 hover:text-blue-700 font-medium">↺ Refresh</button>
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
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Dari</label>
                    <select id="tf-from" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Pilih pengirim...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Ke</label>
                    <select id="tf-to" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Pilih penerima...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Jumlah</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">Rp</span>
                        <input id="tf-amount" type="number" min="1000" placeholder="50000"
                            class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <button onclick="doTransfer()" id="tf-btn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition-colors">
                    → Transfer
                </button>
                <div id="tf-result" class="hidden rounded-lg p-3 text-sm"></div>
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
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Jumlah</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">Rp</span>
                        <input id="dp-amount" type="number" min="1000" placeholder="100000"
                            class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                </div>
                <button onclick="doDeposit()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 rounded-lg text-sm transition-colors">
                    + Deposit
                </button>
                <div id="dp-result" class="hidden rounded-lg p-3 text-sm"></div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-50">
                <h2 class="font-semibold text-gray-800">Riwayat Transaksi</h2>
            </div>
            <div class="p-6 space-y-3">
                <div class="flex gap-2">
                    <select id="tx-user" class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                        <option value="">Pilih user...</option>
                    </select>
                    <button onclick="loadTransactions()" class="px-4 py-2.5 bg-gray-800 hover:bg-gray-900 text-white text-sm rounded-lg font-medium transition-colors">
                        Lihat
                    </button>
                </div>
                <div id="tx-list" class="space-y-2 max-h-64 overflow-y-auto pr-1"></div>
            </div>
        </div>

    </div>

    <!-- Endpoints -->
    <div class="bg-white rounded-xl border border-gray-100 card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-50">
            <h2 class="font-semibold text-gray-800">API Endpoints</h2>
            <p class="text-xs text-gray-400 mt-0.5">Klik untuk copy URL</p>
        </div>
        <div class="p-6">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3" id="endpoint-list"></div>
        </div>
    </div>

</main>

<script>
const BASE = '/api';
let allUsers = [];

function toast(msg, type = 'success') {
    const t = document.getElementById('toast');
    const colors = { success: 'bg-green-600', error: 'bg-red-500', info: 'bg-blue-600' };
    const icons  = { success: '✓', error: '✗', info: 'ℹ' };
    t.className = `fixed top-4 right-4 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-xl text-white text-sm font-medium toast ${colors[type]}`;
    document.getElementById('toast-icon').textContent = icons[type];
    document.getElementById('toast-msg').textContent  = msg;
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 3500);
}

function rupiah(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

function timeAgo(d) {
    const s = Math.floor((Date.now() - new Date(d)) / 1000);
    if (s < 60) return `${s}d lalu`;
    if (s < 3600) return `${Math.floor(s/60)}m lalu`;
    if (s < 86400) return `${Math.floor(s/3600)}j lalu`;
    return new Date(d).toLocaleDateString('id-ID');
}

async function checkHealth() {
    try {
        const r = await fetch(`${BASE}/health`);
        const d = await r.json();
        const ok = d.status === 'ok';
        const badge = document.getElementById('health-badge');
        badge.innerHTML = `<span class="w-2 h-2 rounded-full ${ok ? 'bg-green-400' : 'bg-red-400'} inline-block"></span> ${ok ? 'API Online' : 'API Error'}`;
        badge.className = `flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium ${ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`;
        const ss = document.getElementById('stat-status');
        ss.textContent = ok ? '✓ Online' : '✗ Error';
        ss.className   = `text-xl font-bold mt-1 ${ok ? 'text-green-600' : 'text-red-500'}`;
    } catch {
        document.getElementById('health-badge').innerHTML = `<span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> Offline`;
    }
}

async function loadUsers() {
    try {
        const r = await fetch(`${BASE}/users`);
        const d = await r.json();
        allUsers = d.users || [];

        const total = allUsers.reduce((s, u) => s + Number(u.balance), 0);
        document.getElementById('stat-users').textContent   = allUsers.length;
        document.getElementById('stat-balance').textContent = rupiah(total);

        document.getElementById('users-list').innerHTML = allUsers.map(u => `
            <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm flex-shrink-0">
                        ${u.name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-800">${u.name}</p>
                        <p class="text-xs text-gray-400">${u.email}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-800">${rupiah(u.balance)}</p>
                    <p class="text-xs text-gray-400">ID #${u.id}</p>
                </div>
            </div>
        `).join('');

        const opts = allUsers.map(u => `<option value="${u.id}">${u.name} (#${u.id})</option>`).join('');
        ['tf-from','tf-to','dp-user','tx-user'].forEach(id => {
            const sel = document.getElementById(id);
            const cur = sel.value;
            sel.innerHTML = `<option value="">Pilih...</option>${opts}`;
            if (cur) sel.value = cur;
        });

        // Load tx count from first user
        loadTxCount(allUsers[0]?.id);
    } catch {
        document.getElementById('users-list').innerHTML = `<div class="px-6 py-8 text-center text-red-400 text-sm">Gagal memuat data</div>`;
    }
}

async function loadTxCount(uid) {
    if (!uid) return;
    try {
        const r = await fetch(`${BASE}/transactions/${uid}`);
        const d = await r.json();
        document.getElementById('stat-tx').textContent = d.total ?? '—';
    } catch {}
}

async function doTransfer() {
    const from   = document.getElementById('tf-from').value;
    const to     = document.getElementById('tf-to').value;
    const amount = document.getElementById('tf-amount').value;
    const result = document.getElementById('tf-result');
    const btn    = document.getElementById('tf-btn');

    if (!from || !to || !amount) { toast('Lengkapi semua field', 'error'); return; }
    if (from === to) { toast('Pengirim dan penerima tidak boleh sama', 'error'); return; }

    btn.textContent = '⟳ Memproses...'; btn.disabled = true;
    result.className = 'hidden';

    try {
        const r = await fetch(`${BASE}/transfer`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ from: +from, to: +to, amount: +amount })
        });
        const d = await r.json();
        result.classList.remove('hidden');

        if (r.ok) {
            result.className = 'rounded-lg p-3 text-sm bg-green-50 text-green-700 border border-green-200';
            result.innerHTML = `<strong>✓ Transfer berhasil!</strong><br>Saldo pengirim: <strong>${rupiah(d.from_balance)}</strong> &nbsp;|&nbsp; Penerima: <strong>${rupiah(d.to_balance)}</strong>`;
            toast('Transfer berhasil!', 'success');
            loadUsers();
        } else {
            result.className = 'rounded-lg p-3 text-sm bg-red-50 text-red-700 border border-red-200';
            result.innerHTML = `<strong>✗ ${d.error}</strong> <span class="text-xs opacity-60">(${d.code})</span>`;
            toast(d.error, 'error');
        }
    } catch {
        toast('Koneksi error', 'error');
    } finally {
        btn.textContent = '→ Transfer'; btn.disabled = false;
    }
}

async function doDeposit() {
    const user_id = document.getElementById('dp-user').value;
    const amount  = document.getElementById('dp-amount').value;
    const result  = document.getElementById('dp-result');

    if (!user_id || !amount) { toast('Lengkapi semua field', 'error'); return; }

    try {
        const r = await fetch(`${BASE}/deposit`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ user_id: +user_id, amount: +amount })
        });
        const d = await r.json();
        result.classList.remove('hidden');

        if (r.ok) {
            result.className = 'rounded-lg p-3 text-sm bg-green-50 text-green-700 border border-green-200';
            result.innerHTML = `✓ Deposit berhasil! Saldo baru: <strong>${rupiah(d.balance)}</strong>`;
            toast('Deposit berhasil!', 'success');
            loadUsers();
        } else {
            result.className = 'rounded-lg p-3 text-sm bg-red-50 text-red-700 border border-red-200';
            result.innerHTML = `✗ ${d.error}`;
            toast(d.error, 'error');
        }
    } catch {
        toast('Koneksi error', 'error');
    }
}

async function loadTransactions() {
    const uid  = document.getElementById('tx-user').value;
    const list = document.getElementById('tx-list');
    if (!uid) { toast('Pilih user dulu', 'info'); return; }

    list.innerHTML = `<div class="text-center text-gray-400 text-sm py-4">Memuat...</div>`;

    try {
        const r = await fetch(`${BASE}/transactions/${uid}`);
        const d = await r.json();
        const txs = d.transactions || [];

        if (!txs.length) {
            list.innerHTML = `<div class="text-center text-gray-400 text-sm py-4">Belum ada transaksi</div>`;
            return;
        }

        const typeColor = { transfer: 'text-orange-500', deposit: 'text-green-600', withdrawal: 'text-red-500' };
        const typeIcon  = { transfer: '↔', deposit: '↓', withdrawal: '↑' };

        list.innerHTML = txs.map(tx => `
            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                <div>
                    <p class="text-xs font-semibold ${typeColor[tx.type] || 'text-gray-600'}">${typeIcon[tx.type] || ''} ${tx.type}</p>
                    <p class="text-xs text-gray-400 mt-0.5">${tx.note || '—'}</p>
                    <p class="text-xs text-gray-300 mt-0.5">${timeAgo(tx.created_at)}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-800">${rupiah(tx.amount)}</p>
                    <span class="text-xs px-1.5 py-0.5 rounded-full ${tx.status === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'}">${tx.status}</span>
                </div>
            </div>
        `).join('');
    } catch {
        list.innerHTML = `<div class="text-center text-red-400 text-sm py-4">Gagal memuat transaksi</div>`;
    }
}

function renderEndpoints() {
    const eps = [
        { method: 'GET',  path: '/api/health',            color: 'bg-green-50 border-green-200',   badge: 'bg-green-500',   desc: 'Health check & DB ping' },
        { method: 'GET',  path: '/api/users',             color: 'bg-blue-50 border-blue-200',     badge: 'bg-blue-500',    desc: 'Daftar semua nasabah' },
        { method: 'GET',  path: '/api/balance/{id}',      color: 'bg-blue-50 border-blue-200',     badge: 'bg-blue-500',    desc: 'Cek saldo per user' },
        { method: 'POST', path: '/api/transfer',          color: 'bg-orange-50 border-orange-200', badge: 'bg-orange-500',  desc: 'Transfer antar nasabah' },
        { method: 'POST', path: '/api/deposit',           color: 'bg-purple-50 border-purple-200', badge: 'bg-purple-500',  desc: 'Top-up saldo akun' },
        { method: 'GET',  path: '/api/transactions/{id}', color: 'bg-gray-50 border-gray-200',     badge: 'bg-gray-500',    desc: 'Riwayat transaksi' },
    ];

    document.getElementById('endpoint-list').innerHTML = eps.map(ep => `
        <div class="rounded-xl border p-4 ${ep.color} cursor-pointer hover:shadow-sm transition-all" onclick="copyUrl('${ep.path}')">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs font-bold px-2 py-0.5 rounded-full text-white ${ep.badge}">${ep.method}</span>
                <code class="text-xs text-gray-600 font-mono truncate">${ep.path}</code>
            </div>
            <p class="text-xs text-gray-500">${ep.desc}</p>
        </div>
    `).join('');
}

function copyUrl(path) {
    navigator.clipboard.writeText(window.location.origin + path);
    toast('URL disalin ke clipboard!', 'info');
}

(async () => {
    renderEndpoints();
    await checkHealth();
    await loadUsers();
    setInterval(checkHealth, 30000);
})();
</script>
</body>
</html>
