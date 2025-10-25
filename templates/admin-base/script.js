(() => {
  const CLIENT_ID = document.documentElement.dataset.client;
  const API_BASE = '../../api.php';
  const UPLOAD_BASE = '../../';
  const BRAND_DATA = window.__CLIENT_BRANDING__ || {};

  const loginOverlay = document.getElementById('loginOverlay');
  const loginForm = document.getElementById('loginForm');
  const loginError = document.getElementById('loginError');
  const mainContent = document.getElementById('mainContent');
  const logoutBtn = document.getElementById('logoutBtn');
  const brandHeading = document.getElementById('brandHeading');
  const confirmOverlay = document.getElementById('confirmOverlay');
  const confirmMsg = document.getElementById('confirmMsg');
  const confirmOk = document.getElementById('confirmOk');
  const confirmCancel = document.getElementById('confirmCancel');
  const deleteOverlay = document.getElementById('deleteOverlay');
  const deleteKeyInput = document.getElementById('deleteKeyInput');
  const deleteKeyError = document.getElementById('deleteKeyError');
  const deleteKeyOk = document.getElementById('deleteKeyOk');
  const deleteKeyCancel = document.getElementById('deleteKeyCancel');
  const toastContainer = document.getElementById('toast-container');

  const searchInput = document.getElementById('searchInput');
  const searchAlert = document.getElementById('search-alert');
  const resultsSearch = document.getElementById('results-search');
  const uploadForm = document.getElementById('form-upload');
  const uploadWarning = document.getElementById('uploadWarning');
  const consultFilterInput = document.getElementById('consultFilterInput');
  const resultsList = document.getElementById('results-list');
  const codeInput = document.getElementById('codeInput');
  const suggestions = document.getElementById('suggestions');
  const resultsCode = document.getElementById('results-code');

  const btnDoSearch = document.getElementById('btnDoSearch');
  const btnClearSearch = document.getElementById('btnClearSearch');
  const btnClearConsult = document.getElementById('btnClearConsult');
  const btnDownloadCsv = document.getElementById('btnDownloadCsv');
  const btnDownloadPdfs = document.getElementById('btnDownloadPdfs');
  const btnSearchCode = document.getElementById('btnSearchCode');

  const DELETION_KEY = '0101';

  let fullList = [];
  let pendingDeleteId = null;
  let intervalId = null;
  let initialized = false;

  function withClient(url) {
    const [base, hash] = url.split('#');
    const separator = base.includes('?') ? '&' : '?';
    const newUrl = `${base}${separator}client=${encodeURIComponent(CLIENT_ID)}`;
    return hash ? `${newUrl}#${hash}` : newUrl;
  }

  function fileUrl(path) {
    if (!path) return '#';
    return path.startsWith('uploads/') ? `${UPLOAD_BASE}${path}` : path;
  }

  function appendClient(formData) {
    if (!formData.has('client')) {
      formData.append('client', CLIENT_ID);
    }
    return formData;
  }

  async function fetchJson(url, options = {}) {
    const opts = Object.assign({ credentials: 'include' }, options);
    const response = await fetch(url, opts);
    if (!response.ok) {
      const text = await response.text();
      throw new Error(text || `HTTP ${response.status}`);
    }
    const contentType = response.headers.get('content-type');
    if (contentType && contentType.includes('application/json')) {
      return response.json();
    }
    return {};
  }

  function apiGet(action, params = {}) {
    const search = new URLSearchParams(params);
    search.set('action', action);
    search.set('client', CLIENT_ID);
    return fetchJson(`${API_BASE}?${search.toString()}`);
  }

  function apiPost(action, formData) {
    const fd = formData instanceof FormData ? formData : new FormData();
    if (!fd.has('action')) {
      fd.append('action', action);
    } else {
      fd.set('action', action);
    }
    appendClient(fd);
    return fetchJson(API_BASE, { method: 'POST', body: fd });
  }

  function toast(message, duration = 3000) {
    const element = document.createElement('div');
    element.className = 'toast';
    element.innerHTML = `<span>${message}</span><button type="button" aria-label="Cerrar">×</button>`;
    element.querySelector('button').onclick = () => element.remove();
    toastContainer.appendChild(element);
    setTimeout(() => element.remove(), duration);
  }

  function startPolling(callback) {
    stopPolling();
    intervalId = window.setInterval(callback, 60000);
  }

  function stopPolling() {
    if (intervalId !== null) {
      window.clearInterval(intervalId);
      intervalId = null;
    }
  }

  function renderDocuments(items, containerId, hideActions) {
    const container = document.getElementById(containerId);
    if (!items || !items.length) {
      container.innerHTML = '<p class="text-gray-500">No hay documentos.</p>';
      return;
    }
    container.innerHTML = items.map((doc) => {
      const codes = (doc.codes || []).join('\n');
      const link = fileUrl(doc.path || '');
      return `
        <div class="border rounded p-4 bg-gray-50">
          <div class="flex justify-between flex-wrap gap-3">
            <div class="min-w-[200px]">
              <h3 class="font-semibold text-lg">${doc.name}</h3>
              <p class="text-gray-600">${doc.date}</p>
              <p class="text-gray-600 text-sm break-all">Archivo: ${doc.path}</p>
              <a href="${link}" target="_blank" class="text-indigo-600 underline">Ver PDF</a>
            </div>
            <div class="button-group text-right">
              ${hideActions ? '' : `
                <button data-action="edit" data-id="${doc.id}" class="btn btn--warning px-2 py-1 text-lg">Editar</button>
                <button data-action="delete" data-id="${doc.id}" class="btn btn--primary px-2 py-1 text-lg">Eliminar</button>
              `}
              <button data-action="toggle" data-id="${doc.id}" class="btn btn--secondary px-2 py-1 text-lg">Ver Códigos</button>
            </div>
          </div>
          <pre id="codes${doc.id}" class="mt-2 p-2 bg-white rounded hidden">${codes}</pre>
        </div>
      `;
    }).join('');
  }

  function clearSearch() {
    searchInput.value = '';
    searchAlert.innerText = '';
    resultsSearch.innerHTML = '';
  }

  function clearCode() {
    resultsCode.innerHTML = '';
  }

  function clearConsultFilter() {
    consultFilterInput.value = '';
    resultsList.innerHTML = '';
  }

  function downloadCsv() {
    let csv = 'Código,Documento\n';
    fullList.forEach((doc) => {
      (doc.codes || []).forEach((code) => {
        csv += `${code},${doc.name}\n`;
      });
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'documentos.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  function downloadPdfs() {
    window.location.href = withClient(`${API_BASE}?action=download_pdfs`);
  }

  function toggleCodes(button) {
    const id = button.dataset.id;
    const pre = document.getElementById(`codes${id}`);
    if (!pre) return;
    if (pre.classList.contains('hidden')) {
      pre.classList.remove('hidden');
      button.textContent = 'Ocultar Códigos';
      stopPolling();
    } else {
      pre.classList.add('hidden');
      button.textContent = 'Ver Códigos';
      startPolling(refreshDocuments);
    }
  }

  async function deleteDoc(id) {
    try {
      const response = await apiGet('delete', { id });
      toast(response.message || response.error || 'Operación completada');
      await refreshDocuments();
    } catch (error) {
      toast(error.message || 'No se pudo eliminar el documento');
    }
  }

  async function editDoc(id) {
    const doc = fullList.find((item) => item.id === id);
    if (!doc) return;
    document.querySelector('[data-tab="tab-upload"]').click();
    document.getElementById('docId').value = doc.id;
    document.getElementById('name').value = doc.name;
    document.getElementById('date').value = doc.date;
    document.getElementById('codes').value = (doc.codes || []).join('\n');
  }

  async function doSearch() {
    const raw = searchInput.value.trim();
    if (!raw) return;
    const codes = [...new Set(raw.split(/\r?\n/).map((line) => line.trim().split(/\s+/)[0]).filter(Boolean))];
    const fd = new FormData();
    fd.append('codes', codes.join('\n'));
    try {
      const data = await apiPost('search', fd);
      const found = new Set(data.flatMap((item) => item.codes || []));
      const missing = codes.filter((code) => !found.has(code));
      searchAlert.innerText = missing.length ? `No encontrados: ${missing.join(', ')}` : '';
      resultsSearch.innerHTML = '';
      renderDocuments(data, 'results-search', true);
    } catch (error) {
      toast('No se pudo realizar la búsqueda');
    }
  }

  async function doCodeSearch() {
    const code = codeInput.value.trim();
    if (!code) return;
    const fd = new FormData();
    fd.append('code', code);
    try {
      const data = await apiPost('search_by_code', fd);
      resultsCode.innerHTML = '';
      if (!data.length) {
        resultsCode.innerHTML = '<p class="text-gray-500">No hay documentos.</p>';
        return;
      }
      renderDocuments(data, 'results-code', true);
    } catch (error) {
      toast('No se pudo buscar el código');
    }
  }

  function doConsultFilter() {
    const term = consultFilterInput.value.trim().toLowerCase();
    const filtered = fullList.filter((doc) => {
      const name = doc.name ? doc.name.toLowerCase() : '';
      const path = doc.path ? doc.path.toLowerCase() : '';
      return name.includes(term) || path.includes(term);
    });
    renderDocuments(filtered, 'results-list', false);
  }

  async function refreshDocuments() {
    try {
      const response = await apiGet('list', { page: 1, per_page: 0 });
      fullList = response.data || [];
      const activeTab = document.querySelector('.tab.active');
      if (activeTab && activeTab.dataset.tab === 'tab-list') {
        const term = consultFilterInput.value.trim();
        if (term) {
          doConsultFilter();
        } else {
          renderDocuments(fullList, 'results-list', false);
        }
      }
    } catch (error) {
      toast('No se pudo obtener la lista de documentos');
    }
  }

  function bindTabs() {
    document.querySelectorAll('.tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach((item) => item.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach((item) => item.classList.add('hidden'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.remove('hidden');

        if (tab.dataset.tab === 'tab-list') {
          refreshDocuments();
          startPolling(refreshDocuments);
        } else {
          stopPolling();
        }

        if (tab.dataset.tab === 'tab-search') {
          clearSearch();
        }
        if (tab.dataset.tab === 'tab-code') {
          clearCode();
        }
      });
    });
    const firstTab = document.querySelector('.tab.active');
    if (firstTab) {
      firstTab.click();
    }
  }

  function bindDocumentActions() {
    resultsList.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      const id = parseInt(button.dataset.id, 10);
      if (!id) return;
      if (button.dataset.action === 'edit') {
        editDoc(id);
      } else if (button.dataset.action === 'delete') {
        pendingDeleteId = id;
        deleteOverlay.classList.remove('hidden');
        deleteKeyInput.value = '';
        deleteKeyError.classList.add('hidden');
        deleteKeyInput.focus();
      } else if (button.dataset.action === 'toggle') {
        toggleCodes(button);
      }
    });

    resultsSearch.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      if (button.dataset.action === 'toggle') {
        toggleCodes(button);
      }
    });

    resultsCode.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      if (button.dataset.action === 'toggle') {
        toggleCodes(button);
      }
    });
  }

  function bindUploadForm() {
    uploadForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const fileInput = document.getElementById('file');
      const file = fileInput.files[0];
      if (file && file.size > 10 * 1024 * 1024) {
        uploadWarning.classList.remove('hidden');
        return;
      }
      uploadWarning.classList.add('hidden');

      const formData = new FormData(uploadForm);
      const action = formData.get('id') ? 'edit' : 'upload';

      const codes = (formData.get('codes') || '').toString()
        .split(/\r?\n/)
        .map((value) => value.trim())
        .filter(Boolean)
        .sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
      formData.set('codes', codes.join('\n'));

      try {
        const response = await apiPost(action, formData);
        toast(response.message || response.error || 'Operación completada');
        uploadForm.reset();
        document.getElementById('docId').value = '';
        await refreshDocuments();
      } catch (error) {
        toast('No se pudo guardar el documento');
      }
    });
  }

  function bindSearchInputs() {
    btnDoSearch.addEventListener('click', doSearch);
    btnClearSearch.addEventListener('click', clearSearch);
    btnClearConsult.addEventListener('click', clearConsultFilter);
    btnDownloadCsv.addEventListener('click', downloadCsv);
    btnDownloadPdfs.addEventListener('click', downloadPdfs);
    btnSearchCode.addEventListener('click', doCodeSearch);

    consultFilterInput.addEventListener('input', doConsultFilter);

    let timeoutId;
    codeInput.addEventListener('input', () => {
      window.clearTimeout(timeoutId);
      const term = codeInput.value.trim();
      if (!term) {
        suggestions.classList.add('hidden');
        return;
      }
      timeoutId = window.setTimeout(async () => {
        try {
          const suggestionList = await apiGet('suggest', { term });
          if (!suggestionList.length) {
            suggestions.classList.add('hidden');
            return;
          }
          suggestions.innerHTML = suggestionList
            .map((code) => `<div class="py-1 px-2 hover:bg-gray-100 cursor-pointer" data-code="${code}">${code}</div>`)
            .join('');
          suggestions.classList.remove('hidden');
        } catch (error) {
          suggestions.classList.add('hidden');
        }
      }, 200);
    });

    suggestions.addEventListener('touchmove', (event) => event.stopPropagation());
    suggestions.addEventListener('click', (event) => {
      const code = event.target.dataset.code;
      if (!code) return;
      codeInput.value = code;
      suggestions.classList.add('hidden');
      doCodeSearch();
    });
    codeInput.addEventListener('blur', () => {
      window.setTimeout(() => suggestions.classList.add('hidden'), 100);
    });
  }

  async function confirmDialog(message) {
    confirmMsg.textContent = message;
    confirmOverlay.classList.remove('hidden');
    return new Promise((resolve) => {
      const handle = (result) => {
        confirmOverlay.classList.add('hidden');
        confirmOk.removeEventListener('click', onOk);
        confirmCancel.removeEventListener('click', onCancel);
        resolve(result);
      };
      const onOk = () => handle(true);
      const onCancel = () => handle(false);
      confirmOk.addEventListener('click', onOk);
      confirmCancel.addEventListener('click', onCancel);
    });
  }

  function bindDeletionFlow() {
    deleteKeyOk.addEventListener('click', async () => {
      if (deleteKeyInput.value !== DELETION_KEY) {
        deleteKeyError.classList.remove('hidden');
        deleteKeyInput.value = '';
        deleteKeyInput.focus();
        return;
      }
      deleteKeyError.classList.add('hidden');
      deleteOverlay.classList.add('hidden');
      const confirmed = await confirmDialog('¿Eliminar este documento?');
      if (!confirmed) return;
      if (pendingDeleteId) {
        await deleteDoc(pendingDeleteId);
        pendingDeleteId = null;
      }
    });

    deleteKeyCancel.addEventListener('click', () => {
      deleteOverlay.classList.add('hidden');
      deleteKeyInput.value = '';
      deleteKeyError.classList.add('hidden');
    });
  }

  function applyBranding(branding) {
    if (!branding) return;
    if (branding.name && brandHeading) {
      brandHeading.textContent = branding.name;
    }
  }

  async function initApp() {
    if (initialized) return;
    initialized = true;

    applyBranding(BRAND_DATA);
    bindTabs();
    bindDocumentActions();
    bindUploadForm();
    bindSearchInputs();
    bindDeletionFlow();

    deleteOverlay.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        deleteOverlay.classList.add('hidden');
      }
    });

    await refreshDocuments();
    startPolling(refreshDocuments);
  }

  async function handleLogin(event) {
    event.preventDefault();
    const formData = new FormData(loginForm);
    try {
      const response = await apiPost('login', formData);
      loginError.classList.add('hidden');
      loginOverlay.classList.add('hidden');
      mainContent.classList.remove('hidden');
      applyBranding(response.branding || BRAND_DATA);
      await initApp();
      await refreshDocuments();
      startPolling(refreshDocuments);
    } catch (error) {
      loginError.classList.remove('hidden');
    }
  }

  async function handleLogout() {
    try {
      await apiPost('logout');
    } catch (error) {
      // ignore
    }
    stopPolling();
    fullList = [];
    pendingDeleteId = null;
    mainContent.classList.add('hidden');
    loginOverlay.classList.remove('hidden');
  }

  async function checkSession() {
    try {
      const response = await apiGet('session');
      if (response.logged_in) {
        loginOverlay.classList.add('hidden');
        mainContent.classList.remove('hidden');
        await initApp();
        return;
      }
    } catch (error) {
      // ignore
    }
    loginOverlay.classList.remove('hidden');
    mainContent.classList.add('hidden');
  }

  loginForm.addEventListener('submit', handleLogin);
  logoutBtn.addEventListener('click', handleLogout);

  checkSession();
})();
