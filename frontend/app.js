const root = document.querySelector('#app');
const endpoints = window.APP_CONFIG;
const labels = { prodi: 'Prodi', mahasiswa: 'Mahasiswa' };
let version = 0;
let routeAbort;
let flash = '';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
const alert = (message, type = 'danger') =>
    `<div class="alert alert-${type}" role="${type === 'success' ? 'status' : 'alert'}">${escapeHtml(message)}</div>`;
const link = (path) => `#/${path}`;
const prodiName = (data, id) => data.find((item) => item.id === Number(id))?.nama_prodi ?? `Prodi #${id} (tidak tersedia)`;

class ApiError extends Error {
    constructor(message, status = 0, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

async function api(service, path, { method = 'GET', body, signal } = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 10000);
    const abort = () => controller.abort();
    signal?.addEventListener('abort', abort, { once: true });
    if (signal?.aborted) controller.abort();
    try {
        const response = await fetch(endpoints[service] + '/api/' + path, {
            method,
            headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body ? JSON.stringify(body) : undefined,
            signal: controller.signal,
        });
        const data = await response.json();
        if (!response.ok) throw new ApiError(data.message || 'Permintaan tidak berhasil.', response.status, data.errors);
        return data;
    } catch (error) {
        if (signal?.aborted) throw error;
        if (error instanceof ApiError) throw error;
        throw new ApiError(`Layanan ${labels[service]} tidak dapat dihubungi. Pastikan layanan berjalan, lalu coba lagi.`);
    } finally {
        clearTimeout(timer);
        signal?.removeEventListener('abort', abort);
    }
}

function pagination(meta, entity) {
    if (meta.last_page <= 1) return '';
    const current = meta.current_page;
    const item = (page, label, disabled = false, active = false) =>
        `<li class="page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}">${disabled || active
            ? `<span class="page-link"${active ? ' aria-current="page"' : ''}>${label}</span>`
            : `<a class="page-link" href="${link(entity + '?page=' + page)}">${label}</a>`}</li>`;
    let items = item(current - 1, 'Sebelumnya', current === 1);
    for (let page = Math.max(1, current - 2); page <= Math.min(meta.last_page, current + 2); page++) {
        items += item(page, String(page), false, page === current);
    }
    items += item(current + 1, 'Berikutnya', current === meta.last_page);
    return `<nav class="mt-3" aria-label="Halaman data"><ul class="pagination flex-wrap">${items}</ul></nav>`;
}

async function listing(entity, page, signal) {
    const [result, options] = await Promise.all([
        api(entity, entity + '?page=' + page, { signal }),
        entity === 'mahasiswa' ? api('prodi', 'prodi/options', { signal }).catch((error) => ({ error })) : null,
    ]);
    const isStudent = entity === 'mahasiswa';
    const prodi = options?.data ?? [];
    const rows = result.data.map((item) => `<tr>
        <td>${item.id}</td>
        ${isStudent ? `<td>${escapeHtml(prodiName(prodi, item.id_prodi))}</td><td>${escapeHtml(item.nama)}</td><td>${escapeHtml(item.nim)}</td>`
            : `<td>${escapeHtml(item.nama_prodi)}</td>`}
        <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" aria-label="Edit ${entity} ${escapeHtml(item.nama ?? item.nama_prodi)}" href="${link(entity + '/' + item.id + '/edit')}">Edit</a>
            <a class="btn btn-sm btn-outline-danger" aria-label="Hapus ${entity} ${escapeHtml(item.nama ?? item.nama_prodi)}" href="${link(entity + '/' + item.id + '/hapus')}">Hapus</a>
        </td>
    </tr>`).join('');
    return `${options?.error ? alert('Nama prodi belum dapat dimuat. ' + options.error.message, 'warning') : ''}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h1 class="h4 mb-0">Data ${labels[entity]}</h1>
            <a class="btn btn-primary" href="${link(entity + '/create')}">Tambah ${labels[entity]}</a>
        </div>
        <div class="table-responsive bg-white border rounded">
            <table class="table table-striped table-hover align-middle mb-0">
                <caption class="visually-hidden">Daftar ${entity}</caption>
                <thead class="table-light"><tr><th scope="col">ID</th>
                    ${isStudent ? '<th scope="col">Nama Prodi</th><th scope="col">Nama</th><th scope="col">NIM</th>' : '<th scope="col">Nama Prodi</th>'}
                    <th scope="col" class="text-end">Aksi</th>
                </tr></thead>
                <tbody>${rows || `<tr><td colspan="${isStudent ? 5 : 3}" class="text-center text-secondary py-4">Belum ada data ${entity}.${!isStudent ? ' Silakan tambah prodi terlebih dahulu.' : ''}</td></tr>`}</tbody>
            </table>
        </div>
        ${pagination(result.meta, entity)}`;
}

function input(name, label, value, max) {
    return `<div class="mb-3">
        <label class="form-label" for="${name}">${label}</label>
        <input class="form-control" id="${name}" name="${name}" type="text" value="${escapeHtml(value)}" maxlength="${max}" required aria-describedby="${name}-error">
        <div id="${name}-error" class="invalid-feedback" data-error="${name}"></div>
    </div>`;
}

async function form(entity, id, signal) {
    const [record, options] = await Promise.all([
        id ? api(entity, entity + '/' + id, { signal }) : { data: {} },
        entity === 'mahasiswa' ? api('prodi', 'prodi/options', { signal }).catch((error) => ({ error })) : null,
    ]);
    const item = record.data;
    const blocked = entity === 'mahasiswa' && (!options?.data?.length || options.error);
    let fields = input('nama_prodi', 'Nama Prodi', item.nama_prodi, 100);
    if (entity === 'mahasiswa') {
        const prodi = options?.data ?? [];
        fields = `<div class="mb-3">
            <label class="form-label" for="id_prodi">Prodi</label>
            <select class="form-select" name="id_prodi" id="id_prodi" required aria-describedby="id_prodi-error">
                <option value="">Pilih prodi</option>
                ${prodi.map((entry) => `<option value="${entry.id}"${entry.id === item.id_prodi ? ' selected' : ''}>${escapeHtml(entry.nama_prodi)}</option>`).join('')}
            </select>
            <div id="id_prodi-error" class="invalid-feedback" data-error="id_prodi"></div>
        </div>` + input('nama', 'Nama Mahasiswa', item.nama, 100) + input('nim', 'NIM', item.nim, 30);
    }
    const warning = options?.error ? alert(options.error.message, 'warning') :
        blocked ? `<div class="alert alert-warning" role="alert">Belum ada prodi. <a class="alert-link" href="#/prodi/create">Tambah prodi</a> sebelum menambahkan mahasiswa.</div>` : '';
    return `<h1 class="h4 mb-3">${id ? 'Edit' : 'Tambah'} ${labels[entity]}</h1>
        ${warning}<div id="form-message"></div>
        <div class="card"><div class="card-body">
            <form data-entity="${entity}" data-id="${id ?? ''}" data-operation="save">
                ${fields}
                <button class="btn btn-primary" type="submit"${blocked ? ' disabled' : ''}>Simpan</button>
                <a class="btn btn-outline-secondary" href="${link(entity)}">Batal</a>
            </form>
        </div></div>`;
}

async function confirmDelete(entity, id, signal) {
    const record = await api(entity, entity + '/' + id, { signal });
    const item = record.data;
    let details = [['ID', item.id], ['Nama Prodi', item.nama_prodi]];
    let blocked = false;
    let warning = '';
    if (entity === 'prodi') {
        try {
            const usage = await api('mahasiswa', 'prodi/' + id + '/usage', { signal });
            blocked = usage.data.has_mahasiswa;
            if (blocked) warning = 'Prodi masih memiliki mahasiswa. Pindahkan atau hapus mahasiswa tersebut terlebih dahulu.';
        } catch (error) {
            blocked = true;
            warning = error.message + ' Penghapusan prodi belum dapat dilakukan.';
        }
    } else {
        let name = `Prodi #${item.id_prodi}`;
        try {
            const prodi = await api('prodi', 'prodi/' + item.id_prodi, { signal });
            name = prodi.data.nama_prodi;
        } catch { /* A missing Prodi service must not prevent deleting a mahasiswa. */ }
        details = [['ID', item.id], ['Nama', item.nama], ['NIM', item.nim], ['Prodi', name]];
    }
    return `<h1 class="h4 mb-3">Hapus ${labels[entity]}</h1><div id="form-message"></div>
        <div class="card"><div class="card-body">
            <dl class="row mb-3">${details.map(([key, value]) => `<dt class="col-sm-3">${key}</dt><dd class="col-sm-9">${escapeHtml(value)}</dd>`).join('')}</dl>
            ${blocked ? alert(warning, 'warning') + `<a class="btn btn-outline-secondary" href="${link(entity)}">Kembali</a>` :
                `<p>Yakin ingin menghapus data ini?</p>
                <form data-entity="${entity}" data-id="${id}" data-operation="delete">
                    <button class="btn btn-danger" type="submit">Ya, Hapus</button>
                    <a class="btn btn-outline-secondary" href="${link(entity)}">Batal</a>
                </form>`}
        </div></div>`;
}

async function render() {
    const ownVersion = ++version;
    routeAbort?.abort();
    routeAbort = new AbortController();
    const signal = routeAbort.signal;
    const url = new URL((location.hash.slice(1) || '/mahasiswa'), location.origin);
    const parts = url.pathname.split('/').filter(Boolean);
    const [entity, action, operation] = parts;
    if (!Object.hasOwn(labels, entity)) {
        location.replace('#/mahasiswa');
        return;
    }
    document.querySelectorAll('[data-nav]').forEach((element) => {
        const active = element.dataset.nav === entity;
        element.classList.toggle('active', active);
        if (active) element.setAttribute('aria-current', 'page');
        else element.removeAttribute('aria-current');
    });
    const title = !action ? 'Data' : action === 'create' ? 'Tambah' : operation === 'edit' ? 'Edit' : 'Hapus';
    document.title = `${title} ${labels[entity]} | CRUD Mahasiswa`;
    root.setAttribute('aria-busy', 'true');
    root.innerHTML = '<p class="text-secondary" role="status">Memuat data…</p>';
    try {
        let content;
        if (!action) {
            const page = Math.max(1, Number.parseInt(url.searchParams.get('page'), 10) || 1);
            content = await listing(entity, page, signal);
        } else if (action === 'create' && parts.length === 2) {
            content = await form(entity, null, signal);
        } else if (/^[1-9]\d*$/.test(action) && parts.length === 3 && operation === 'edit') {
            content = await form(entity, action, signal);
        } else if (/^[1-9]\d*$/.test(action) && parts.length === 3 && operation === 'hapus') {
            content = await confirmDelete(entity, action, signal);
        } else {
            content = alert('Halaman tidak ditemukan.') + `<a class="btn btn-outline-secondary" href="${link(entity)}">Kembali</a>`;
        }
        if (ownVersion !== version) return;
        root.innerHTML = (flash ? alert(flash, 'success') : '') + content;
        flash = '';
    } catch (error) {
        if (signal.aborted || ownVersion !== version) return;
        const message = error.status === 404 ? 'Data tidak ditemukan. Data mungkin sudah dihapus.' : error.message;
        root.innerHTML = alert(message) + '<button class="btn btn-primary me-2" data-retry>Coba Lagi</button>' +
            `<a class="btn btn-outline-secondary" href="${link(entity)}">Kembali ke daftar</a>`;
    } finally {
        if (ownVersion === version) {
            root.removeAttribute('aria-busy');
            root.focus({ preventScroll: true });
        }
    }
}

root.addEventListener('click', (event) => {
    if (event.target.closest('[data-retry]')) render();
});

root.addEventListener('submit', async (event) => {
    const target = event.target;
    if (!(target instanceof HTMLFormElement)) return;
    event.preventDefault();
    if (target.dataset.busy) return;
    const ownVersion = version;
    const { entity, id, operation } = target.dataset;
    const button = target.querySelector('button[type="submit"]');
    target.dataset.busy = 'true';
    button.disabled = true;
    button.textContent = operation === 'delete' ? 'Menghapus…' : 'Menyimpan…';
    const message = document.querySelector('#form-message');
    message.innerHTML = '';
    target.querySelectorAll('.is-invalid').forEach((field) => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
    });
    try {
        const body = operation === 'delete' ? undefined : Object.fromEntries(new FormData(target));
        if (body && entity === 'mahasiswa') body.id_prodi = Number(body.id_prodi);
        await api(entity, entity + (id ? '/' + id : ''), {
            method: operation === 'delete' ? 'DELETE' : id ? 'PUT' : 'POST',
            body,
        });
        if (ownVersion !== version) return;
        flash = `${labels[entity]} berhasil ${operation === 'delete' ? 'dihapus' : id ? 'diperbarui' : 'ditambahkan'}.`;
        location.hash = link(entity);
    } catch (error) {
        if (ownVersion !== version) return;
        message.innerHTML = alert(error.status === 422 ? 'Data belum tersimpan. Periksa isian formulir di bawah.' : error.message);
        for (const [field, errors] of Object.entries(error.errors ?? {})) {
            const input = target.elements.namedItem(field);
            const feedback = target.querySelector(`[data-error="${field}"]`);
            if (!input || !feedback) continue;
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            feedback.textContent = errors.join(' ');
        }
        (target.querySelector('.is-invalid') ?? message).focus();
    } finally {
        delete target.dataset.busy;
        button.disabled = false;
        button.textContent = operation === 'delete' ? 'Ya, Hapus' : 'Simpan';
    }
});

for (const element of document.querySelectorAll('[data-docs]')) {
    element.href = endpoints[element.dataset.docs] + '/docs/api';
}
window.addEventListener('hashchange', render);
if (!location.hash) location.replace('#/mahasiswa');
else render();
