(() => {
  const CLIENT_ID = document.documentElement.dataset.client;
  const API_BASE = '../../api.php';
  const BRAND_DATA = window.__CLIENT_BRANDING__ || {};

  const codeInput = document.getElementById('codeInput');
  const suggestions = document.getElementById('suggestions');
  const results = document.getElementById('results-code');
  const alertBox = document.getElementById('code-alert');
  const btnSearch = document.getElementById('btnCodeSearch');
  const btnClear = document.getElementById('btnCodeClear');
  const btnLegal = document.getElementById('btnLegal');
  const btnCloseLegal = document.getElementById('btnCloseLegal');
  const legalModal = document.getElementById('legalModal');
  const brandTitle = document.getElementById('brandTitle');

  function withClient(url) {
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}client=${encodeURIComponent(CLIENT_ID)}`;
  }

  function fileUrl(path) {
    if (!path) return '#';
    return path.startsWith('uploads/') ? `../../${path}` : path;
  }

  async function fetchJson(url, options = {}) {
    const opts = Object.assign({ credentials: 'include' }, options);
    const response = await fetch(url, opts);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
  }

  function applyBranding() {
    if (BRAND_DATA.name && brandTitle) {
      brandTitle.textContent = BRAND_DATA.name;
    }
  }

  async function fetchSuggestions(term) {
    try {
      const data = await fetchJson(withClient(`${API_BASE}?action=suggest&term=${encodeURIComponent(term)}`));
      if (!data.length) {
        suggestions.style.display = 'none';
        return;
      }
      suggestions.innerHTML = data.map((code) => `<div class="px-4 py-2 cursor-pointer" data-code="${code}">${code}</div>`).join('');
      suggestions.style.display = 'block';
    } catch (error) {
      suggestions.style.display = 'none';
    }
  }

  async function searchByCode(code) {
    const fd = new FormData();
    fd.append('action', 'search_by_code');
    fd.append('client', CLIENT_ID);
    fd.append('code', code);
    const data = await fetchJson(API_BASE, { method: 'POST', body: fd });
    return data;
  }

  function renderResults(items) {
    if (!items.length) {
      results.innerHTML = '<p class="text-gray-500">No hay documentos para este código.</p>';
      return;
    }
    results.innerHTML = items.map((doc) => {
      const link = fileUrl(doc.path || '');
      return `
        <div class="border rounded-lg p-4 bg-white shadow-sm">
          <h3 class="font-semibold text-lg truncate">${doc.name}</h3>
          <p class="text-sm text-gray-600 mt-1 truncate">${doc.date}</p>
          <p class="text-xs text-gray-500 italic mt-0.5 break-all">${doc.path}</p>
          <a href="${link}" target="_blank" class="text-[var(--color-primary)] hover:underline mt-1 inline-block font-semibold">Ver PDF</a>
        </div>
      `;
    }).join('');
  }

  async function handleSearch() {
    const code = codeInput.value.trim();
    alertBox.innerText = '';
    results.innerHTML = '';
    if (!code) {
      return;
    }
    try {
      const data = await searchByCode(code);
      if (!data.length) {
        alertBox.innerText = `No hay documentos con “${code}”.`;
        return;
      }
      renderResults(data);
    } catch (error) {
      alertBox.innerText = 'No se pudo completar la búsqueda.';
    }
  }

  function handleClear() {
    codeInput.value = '';
    alertBox.innerText = '';
    results.innerHTML = '';
    suggestions.style.display = 'none';
  }

  function toggleLegalModal() {
    legalModal.classList.toggle('hidden');
  }

  let timeoutId;
  codeInput.addEventListener('input', () => {
    window.clearTimeout(timeoutId);
    const term = codeInput.value.trim();
    if (!term) {
      suggestions.style.display = 'none';
      return;
    }
    timeoutId = window.setTimeout(() => fetchSuggestions(term), 200);
  });

  suggestions.addEventListener('click', (event) => {
    const code = event.target.dataset.code;
    if (!code) return;
    codeInput.value = code;
    suggestions.style.display = 'none';
    handleSearch();
  });

  codeInput.addEventListener('blur', () => {
    window.setTimeout(() => {
      suggestions.style.display = 'none';
    }, 100);
  });

  btnSearch.addEventListener('click', handleSearch);
  btnClear.addEventListener('click', handleClear);
  btnLegal.addEventListener('click', toggleLegalModal);
  btnCloseLegal.addEventListener('click', toggleLegalModal);

  applyBranding();
})();
