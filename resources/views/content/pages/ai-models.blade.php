@extends('layouts/contentNavbarLayout')

@section('title', 'AI Models')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables/css/dataTables.bootstrap5.min.css') }}">
@endsection

@section('page-style')
<style>
  .ai-models-wrapper .table-responsive {
    overflow-x: hidden;
  }
  .ai-models-wrapper table.dataTable {
    width: 100% !important;
  }
  .ai-model-meta {
    color: var(--bs-text-muted);
    font-size: 0.8125rem;
  }
  .ai-model-desc {
    color: var(--bs-body-color);
    opacity: 0.85;
    word-break: break-word;
    overflow-wrap: anywhere;
  }
  .ai-model-name {
    font-weight: 600;
    word-break: break-word;
    overflow-wrap: anywhere;
  }
  .ai-model-context {
    color: var(--bs-text-muted);
    font-size: 0.8125rem;
    white-space: nowrap;
  }
  .ai-model-pricing {
    font-size: 0.8125rem;
    color: var(--bs-body-color);
  }
  #aiModelsTable td,
  #availableModelsTable td {
    white-space: normal;
    word-break: break-word;
    overflow-wrap: anywhere;
  }
  #aiModelsTable th:first-child,
  #aiModelsTable td:first-child {
    width: 160px;
    min-width: 160px;
  }
  #aiModelsTable th:last-child,
  #aiModelsTable td:last-child {
    width: 160px;
    min-width: 160px;
  }
  #availableModelsTable th:first-child,
  #availableModelsTable td:first-child {
    width: 160px;
    min-width: 160px;
  }
  #availableModelsTable th:last-child,
  #availableModelsTable td:last-child {
    width: 80px;
    min-width: 80px;
  }
  .use-openrouter-model {
    white-space: nowrap;
  }
</style>
@endsection

@section('vendor-script')
@endsection

@section('content')
<div class="row ai-models-wrapper">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h2 class="card-title mb-1">AI Models</h2>
            <div class="text-muted">OpenRouter catalog</div>
          </div>
        </div>

        <div class="alert alert-info {{ empty($hasOpenRouterKey) ? '' : 'd-none' }}" id="aiModelsKeyNotice">
          No API key configured for OpenRouter. Configure it in
          <a href="{{ route('settings') }}" class="alert-link">Settings</a>.
        </div>
        <div class="alert alert-danger d-none" id="aiModelsError"></div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <h5 class="mb-2">Available models for use</h5>
        <div class="table-responsive">
          <table class="table" id="availableModelsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Price</th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <h5 class="mb-0">Local models</h5>
          <button type="button" class="btn btn-sm btn-outline-primary" id="refreshLocalModels">Refresh</button>
        </div>
        <div class="alert alert-info d-none" id="localModelsNotice">
          <i class="icon-base ri ri-information-line me-2"></i>
          Configure your Ollama URL in <a href="{{ route('settings') }}" class="alert-link">Settings</a>.
        </div>
        <div class="alert alert-danger d-none" id="localModelsError"></div>
        <div class="table-responsive">
          <table class="table" id="localModelsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Size</th>
                <th>Modified</th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <h5 class="mb-0">OpenRouter models</h5>
          <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" id="aiModelsSort" style="width: 180px;">
              <option value="popularity">Sort: Popularity</option>
              <option value="name">Sort: Name</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary" id="refreshAiModels">Refresh</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table" id="aiModelsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name / Description / Context</th>
                <th>Pricing</th>
                <th></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="availableModelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Model Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><strong>Name:</strong> <span id="availableModelName"></span></div>
        <div class="mb-2"><strong>Model ID:</strong> <span id="availableModelId"></span></div>
        <div class="mb-2"><strong>Type:</strong> <span id="availableModelType"></span></div>
        <div class="mb-3"><strong>Pricing:</strong> <span id="availableModelPricing"></span></div>
        <div class="mb-1 fw-semibold">Model JSON</div>
        <pre class="bg-light p-2 rounded small" id="availableModelJson" style="max-height: 320px; overflow: auto;"></pre>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  (function() {
    'use strict';

    const tableEl = document.getElementById('aiModelsTable');
    const availableTableEl = document.getElementById('availableModelsTable');
    const localTableEl = document.getElementById('localModelsTable');
    const errorEl = document.getElementById('aiModelsError');
    const keyNoticeEl = document.getElementById('aiModelsKeyNotice');
    const refreshBtn = document.getElementById('refreshAiModels');
    const sortEl = document.getElementById('aiModelsSort');
    let dataTable = null;
    let availableTable = null;
    let localTable = null;

    function loadScript(src) {
      return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
          resolve();
          return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });
    }

    function setError(message) {
      if (keyNoticeEl && keyNoticeEl.classList.contains('d-none')) {
        keyNoticeEl.classList.add('d-none');
      }
      if (!message) {
        errorEl.classList.add('d-none');
        errorEl.textContent = '';
        return;
      }
      errorEl.textContent = message;
      errorEl.classList.remove('d-none');
    }

    function setLocalError(message) {
      const localModelsError = document.getElementById('localModelsError');
      const localModelsNotice = document.getElementById('localModelsUrlNotice');
      if (localModelsNotice && !localModelsNotice.classList.contains('d-none')) {
        localModelsNotice.classList.add('d-none');
      }
      if (!message) {
        if (localModelsError) {
          localModelsError.classList.add('d-none');
          localModelsError.textContent = '';
        }
        return;
      }
      if (localModelsError) {
        localModelsError.textContent = message;
        localModelsError.classList.remove('d-none');
      }
    }

    async function ensureDataTables() {
      if (!window.jQuery) {
        await loadScript("{{ asset('assets/vendor/libs/jquery/jquery.min.js') }}");
      }
      if (!window.jQuery) {
        setError('jQuery failed to load.');
        throw new Error('jQuery not available');
      }
      if (!$.fn.DataTable) {
        try {
          await loadScript("{{ asset('assets/vendor/libs/datatables/js/jquery.dataTables.min.js') }}");
          await loadScript("{{ asset('assets/vendor/libs/datatables/js/dataTables.bootstrap5.min.js') }}");
        } catch (e) {
          setError('DataTables failed to load.');
          throw e;
        }
      }
      if (!$.fn.DataTable) {
        setError('DataTables is not loaded. Please refresh the page.');
        throw new Error('DataTables not available');
      }
    }

    function formatPricing(value) {
      if (value === null || value === undefined || value === '') return '-';
      const num = Number(value);
      if (Number.isNaN(num)) return value;
      const perMillion = num * 1000000;
      return `$${perMillion.toFixed(2)}/M`;
    }

    function getProviderLabel(model) {
      const id = model.id || '';
      if (id.includes('/')) {
        return id.split('/')[0];
      }
      return 'provider';
    }

    function initDataTable() {
      if (dataTable) return;
      dataTable = $(tableEl).DataTable({
        data: [],
        autoWidth: false,
        order: [[4, 'desc']],
        pageLength: 25,
        pagingType: 'full_numbers',
        language: {
          paginate: {
            first: '<i class="icon-base ri ri-skip-left-line"></i>',
            last: '<i class="icon-base ri ri-skip-right-line"></i>',
            next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
            previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>'
          }
        },
        columns: [
          { data: 'id' },
          {
            data: null,
            render: function(data) {
              const name = data.name || 'Unknown';
              const description = data.description || 'No description';
              const context = data.context_length ? `${data.context_length} context` : 'Context: -';
              const provider = getProviderLabel(data);
              return `
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div class="ai-model-name">${name}</div>
                  <div class="ai-model-context">${context}</div>
                </div>
                <div class="ai-model-desc">${description}</div>
                <div class="ai-model-meta">by ${provider}</div>
              `;
            }
          },
          {
            data: null,
            render: function(data) {
              const pricing = data.pricing || {};
              const prompt = formatPricing(pricing.prompt ?? pricing.input ?? '');
              const completion = formatPricing(pricing.completion ?? pricing.output ?? '');
              return `
                <div class="ai-model-pricing">input: ${prompt}</div>
                <div class="ai-model-pricing">output: ${completion}</div>
              `;
            }
          },
          {
            data: null,
            orderable: false,
            searchable: false,
            render: function(data) {
              return `
                <button type="button" class="btn btn-sm btn-primary use-openrouter-model" data-id="${data.id}">
                  Use this model
                </button>
              `;
            }
          }
          ,
          {
            data: 'popularity',
            visible: false,
            searchable: false
          }
        ]
      });
    }

    function initAvailableTable() {
      if (availableTable) return;
      availableTable = $(availableTableEl).DataTable({
        data: [],
        autoWidth: false,
        order: [[0, 'asc']],
        pageLength: 10,
        pagingType: 'full_numbers',
        language: {
          paginate: {
            first: '<i class="icon-base ri ri-skip-left-line"></i>',
            last: '<i class="icon-base ri ri-skip-right-line"></i>',
            next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
            previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>'
          }
        },
        columns: [
          { data: 'model_id' },
          {
            data: null,
            render: function(data) {
              return `<a href="javascript:void(0);" class="available-model-link" data-id="${data.id}">${data.name}</a>`;
            }
          },
          {
            data: null,
            render: function(data) {
              const input = formatPricing(data.price_input ?? '');
              const output = formatPricing(data.price_output ?? '');
              return `
                <div class="ai-model-pricing">input: ${input}</div>
                <div class="ai-model-pricing">output: ${output}</div>
              `;
            }
          },
          {
            data: null,
            orderable: false,
            searchable: false,
            render: function(data) {
              return `
                <button type="button" class="btn btn-sm btn-icon btn-danger delete-available-model" data-id="${data.id}">
                  <i class="icon-base ri ri-delete-bin-line"></i>
                </button>
              `;
            }
          }
        ]
      });
    }

    function initLocalTable() {
      if (localTable) return;
      localTable = $(localTableEl).DataTable({
        data: [],
        autoWidth: false,
        order: [[0, 'asc']],
        pageLength: 10,
        pagingType: 'full_numbers',
        language: {
          paginate: {
            first: '<i class="icon-base ri ri-skip-left-line"></i>',
            last: '<i class="icon-base ri ri-skip-right-line"></i>',
            next: '<i class="icon-base ri ri-arrow-right-s-line"></i>',
            previous: '<i class="icon-base ri ri-arrow-left-s-line"></i>'
          }
        },
        columns: [
          { data: 'model_id' },
          { data: 'name' },
          { data: 'size' },
          { data: 'modified_at' },
          {
            data: null,
            orderable: false,
            searchable: false,
            render: function(data) {
              return `
                <button type="button" class="btn btn-sm btn-primary use-local-model" data-id="${data.id}" data-name="${data.name}">
                  Use this model
                </button>
              `;
            }
          }
        ]
      });
    }

    async function loadModels() {
      setError('');
      refreshBtn.disabled = true;
      refreshBtn.textContent = 'Loading...';
      try {
        await ensureDataTables();
        initDataTable();
        initAvailableTable();
        initLocalTable();
        const response = await fetch('{{ route('ai-models.data') }}', {
          headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) {
          setError(`Failed to load models (HTTP ${response.status}).`);
          return;
        }
        let payload = null;
        try {
          payload = await response.json();
        } catch (e) {
          setError('Invalid response from server. Please try again.');
          return;
        }
        if (payload.error) {
          if (payload.error.toLowerCase().includes('api key') && keyNoticeEl) {
            keyNoticeEl.classList.remove('d-none');
            errorEl.classList.add('d-none');
          } else {
            setError(payload.error);
          }
        }
        dataTable.clear();
        if (Array.isArray(payload.data)) {
          dataTable.rows.add(payload.data);
        } else if (!payload.error) {
          setError('No data returned from server.');
        }
        dataTable.draw();
        await loadAvailableModels();
        await loadLocalModels();
      } catch (error) {
        setError('Failed to load models. Please try again.');
      } finally {
        refreshBtn.disabled = false;
        refreshBtn.textContent = 'Refresh';
      }
    }

    async function loadLocalModels() {
      const localModelsError = document.getElementById('localModelsError');
      const localModelsNotice = document.getElementById('localModelsNotice');
      
      try {
        await ensureDataTables();
        initLocalTable();
        
        // Use AbortController to timeout the request if it takes too long
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2000); // 2 second timeout
        
        const response = await fetch('{{ route('ai-models.local') }}', {
          signal: controller.signal,
          headers: { 'Accept': 'application/json' }
        });
        clearTimeout(timeoutId);
        
        if (!response.ok) {
          localModelsError.textContent = `Failed to load local models (HTTP ${response.status}).`;
          localModelsError.classList.remove('d-none');
          localModelsNotice.classList.add('d-none');
          return;
        }
        
        const payload = await response.json();
        
        if (payload.error) {
          localModelsError.textContent = payload.error;
          localModelsError.classList.remove('d-none');
          localModelsNotice.classList.add('d-none');
          localTable.clear();
          localTable.draw();
          return;
        }
        
        localModelsError.classList.add('d-none');
        localModelsNotice.classList.add('d-none');
        localTable.clear();
        
        if (Array.isArray(payload.data)) {
          localTable.rows.add(payload.data);
        }
        localTable.draw();
      } catch (error) {
        if (error.name === 'AbortError') {
          setLocalError('Request timed out. Ollama may not be accessible. Please check your Ollama URL in Settings.');
        } else {
          setLocalError('Failed to load local models. Please check your Ollama URL in Settings.');
        }
      }
    }

    async function loadAvailableModels() {
      const response = await fetch('{{ route('ai-models.available.index') }}', {
        headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) {
        return;
      }
      const payload = await response.json();
      availableTable.clear();
      if (Array.isArray(payload.data)) {
        availableTable.rows.add(payload.data);
      }
      availableTable.draw();
    }

    async function addOpenRouterModel(model) {
      const pricing = model.pricing || {};
      const payload = {
        model_id: model.id,
        name: model.name || model.id,
        model_json: model.raw || model,
        price_input: pricing.prompt ?? pricing.input ?? null,
        price_output: pricing.completion ?? pricing.output ?? null,
        type: 'openrouter'
      };
      const response = await fetch('{{ route('ai-models.available.store') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (data.success) {
        if (typeof window.showToast === 'function') {
          window.showToast('Model added to available list.', 'success');
        }
        await loadAvailableModels();
      } else {
        if (typeof window.showToast === 'function') {
          window.showToast('Failed to add model.', 'error');
        }
      }
    }

    async function deleteAvailableModel(id) {
      const response = await fetch(`{{ url('/ai-models/available') }}/${id}`, {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
      });
      if (response.ok) {
        await loadAvailableModels();
      }
    }

    async function showAvailableModel(id) {
      const response = await fetch(`{{ url('/ai-models/available') }}/${id}`, {
        headers: { 'Accept': 'application/json' }
      });
      if (!response.ok) return;
      const payload = await response.json();
      const model = payload.data;
      document.getElementById('availableModelName').textContent = model.name || '-';
      document.getElementById('availableModelId').textContent = model.model_id || '-';
      document.getElementById('availableModelType').textContent = model.type || '-';
      const input = formatPricing(model.price_input ?? '');
      const output = formatPricing(model.price_output ?? '');
      document.getElementById('availableModelPricing').textContent = `input: ${input} | output: ${output}`;
      document.getElementById('availableModelJson').textContent = JSON.stringify(model.model_json || {}, null, 2);
      const modal = new bootstrap.Modal(document.getElementById('availableModelModal'));
      modal.show();
    }

    refreshBtn.addEventListener('click', loadModels);
    const refreshLocalBtn = document.getElementById('refreshLocalModels');
    if (refreshLocalBtn) {
      refreshLocalBtn.addEventListener('click', loadLocalModels);
    }
    
    if (sortEl) {
      sortEl.addEventListener('change', function() {
        if (!dataTable) return;
        if (sortEl.value === 'name') {
          dataTable.order([1, 'asc']).draw();
        } else {
          dataTable.order([4, 'desc']).draw();
        }
      });
    }

    tableEl.addEventListener('click', function(event) {
      const button = event.target.closest('.use-openrouter-model');
      if (!button) return;
      const modelId = button.getAttribute('data-id');
      const row = dataTable.rows().data().toArray().find(item => item.id === modelId);
      if (row) {
        addOpenRouterModel(row);
      }
    });

    availableTableEl.addEventListener('click', function(event) {
      const deleteBtn = event.target.closest('.delete-available-model');
      if (deleteBtn) {
        deleteAvailableModel(deleteBtn.getAttribute('data-id'));
        return;
      }
      const link = event.target.closest('.available-model-link');
      if (link) {
        showAvailableModel(link.getAttribute('data-id'));
      }
    });

    localTableEl.addEventListener('click', function(event) {
      const button = event.target.closest('.use-local-model');
      if (!button) return;
      const modelId = button.getAttribute('data-id');
      const modelName = button.getAttribute('data-name');
      const row = localTable.rows().data().toArray().find(item => item.id === modelId);
      if (row) {
        addLocalModel(row);
      }
    });

    async function addLocalModel(model) {
      const payload = {
        model_id: model.id,
        name: model.name || model.id,
        model_json: model.raw || model,
        price_input: null,
        price_output: null,
        type: 'local'
      };
      const response = await fetch('{{ route('ai-models.available.store') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (data.success) {
        if (typeof window.showToast === 'function') {
          window.showToast('Model added to available list.', 'success');
        }
        await loadAvailableModels();
      } else {
        if (typeof window.showToast === 'function') {
          window.showToast('Failed to add model.', 'error');
        }
      }
    }

    // Load models after a short delay to avoid blocking page load
    setTimeout(() => {
      loadModels();
    }, 500);
  })();
</script>
@endsection
