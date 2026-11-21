@extends('layouts/contentNavbarLayout')

@section('title', 'Settings')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title mb-0">API Settings</h5>
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    @csrf
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="icon-base ri ri-information-line me-2"></i>
                                <strong>OpenRouter API Key:</strong> Enter your OpenRouter API key to enable AI features. 
                                You can get your API key from <a href="https://openrouter.ai/keys" target="_blank" class="alert-link">OpenRouter</a>.
                            </div>
                        </div>
                        <div class="col-12 form-control-validation">
                            <div class="form-password-toggle">
                                <div class="input-group input-group-merge">
                                    <div class="form-floating form-floating-outline">
                                        <input type="password" class="form-control @error('openrouter_api_key') is-invalid @enderror" 
                                               id="openrouter_api_key" name="openrouter_api_key" 
                                               value="" 
                                               placeholder="sk-or-v1-..." 
                                               autocomplete="off" />
                                        <label for="openrouter_api_key">OpenRouter API Key</label>
                                    </div>
                                    <span class="input-group-text cursor-pointer" id="toggleApiKey">
                                        <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                                    </span>
                                </div>
                                @error('openrouter_api_key')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted mt-1">
                                    @if($user->openrouter_api_key)
                                        <span class="text-success"><i class="icon-base ri ri-check-line me-1"></i>API key is configured</span> - Enter a new key to update it.
                                    @else
                                        Enter your OpenRouter API key to enable AI features.
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title mb-0">Local Models</h5>
            </div>
            <div class="card-body">
                <form id="localModelsForm">
                    @csrf
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="icon-base ri ri-information-line me-2"></i>
                                <strong>Ollama URL:</strong> Enter the URL of your local Ollama instance to enable local AI models.
                            </div>
                        </div>
                        <div class="col-12 form-control-validation">
                            <div class="form-floating form-floating-outline">
                                <input type="url" class="form-control @error('ollama_url') is-invalid @enderror" 
                                       id="ollama_url" name="ollama_url" 
                                       value="{{ $user->ollama_url ?? 'http://localhost:11434/' }}" 
                                       placeholder="http://localhost:11434/" />
                                <label for="ollama_url">Ollama URL</label>
                            </div>
                            @error('ollama_url')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted mt-1">
                                Default: http://localhost:11434/
                            </small>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                    </div>
                </form>
            </div>
        </div>
        
        @php
            $literatureKeys = [
                ['field' => 'ieee_api_key', 'service' => 'IEEE Xplore', 'label' => 'IEEE Xplore API key',
                 'use' => 'Runs search strings on IEEE Xplore (Studies → Search databases).',
                 'get' => 'https://developer.ieee.org/', 'get_label' => 'developer.ieee.org', 'required' => 'Required for IEEE Xplore searches'],
                ['field' => 'scopus_api_key', 'service' => 'Scopus', 'label' => 'Scopus API key',
                 'use' => 'Runs search strings on Scopus. Works from the institution network or with the institutional token below.',
                 'get' => 'https://dev.elsevier.com/', 'get_label' => 'dev.elsevier.com', 'required' => 'Required for Scopus searches'],
                ['field' => 'scopus_insttoken', 'service' => 'Scopus', 'label' => 'Scopus institutional token',
                 'use' => 'Lets Scopus answer outside the institution network; the library requests it from Elsevier.',
                 'get' => 'https://dev.elsevier.com/support.html', 'get_label' => 'Elsevier developer support', 'required' => 'Optional'],
                ['field' => 'pubmed_api_key', 'service' => 'PubMed', 'label' => 'NCBI (PubMed) API key',
                 'use' => 'Raises the PubMed search rate limit (searches work without it).',
                 'get' => 'https://account.ncbi.nlm.nih.gov/settings/', 'get_label' => 'NCBI account settings', 'required' => 'Optional'],
                ['field' => 'core_api_key', 'service' => 'CORE', 'label' => 'CORE API key',
                 'use' => 'Downloads repository copies in Final Selection → Find open-access PDFs.',
                 'get' => 'https://core.ac.uk/services/api#form', 'get_label' => 'core.ac.uk', 'required' => 'Optional; without it CORE is skipped'],
            ];
        @endphp
        <div class="card mb-6">
            <div class="card-header">
                <h5 class="card-title mb-0">Literature databases &amp; open-access PDFs</h5>
            </div>
            <div class="card-body">
                <form id="literatureApiForm" autocomplete="off">
                    <div class="alert alert-info">
                        <i class="icon-base ri ri-information-line me-2"></i>
                        Keys for <strong>Studies → Search databases</strong> (IEEE Xplore, Scopus, PubMed; OpenAlex needs none) and for
                        <strong>Final Selection → Find open-access PDFs</strong> (Unpaywall, Crossref, CORE). They are saved encrypted to your account
                        and never shown again: leave a field empty to keep the saved key. The same values can also be entered in those windows.
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-floating form-floating-outline">
                                <input type="email" class="form-control" id="unpaywall_email" name="unpaywall_email" maxlength="255"
                                       value="{{ $user->unpaywall_email }}" placeholder="you@university.edu" />
                                <label for="unpaywall_email">Contact e-mail (Unpaywall, Crossref)</label>
                            </div>
                            <small class="form-text text-muted mt-1">
                                The <a href="https://unpaywall.org/products/api" target="_blank" rel="noopener">Unpaywall API</a> requires a contact e-mail, and Crossref answers faster with one.
                                Leave it empty to search open-access PDFs without Unpaywall.
                            </small>
                        </div>

                        @foreach($literatureKeys as $key)
                        @php $saved = !empty($user->{$key['field']}); @endphp
                        <div class="col-md-6">
                            <div class="form-password-toggle">
                                <div class="input-group input-group-merge">
                                    <div class="form-floating form-floating-outline">
                                        <input type="password" class="form-control" id="{{ $key['field'] }}" data-secret-field="{{ $key['field'] }}"
                                               maxlength="255" autocomplete="off"
                                               placeholder="{{ $saved ? 'Saved; leave empty to keep it' : 'Paste the key' }}" />
                                        <label for="{{ $key['field'] }}">{{ $key['label'] }}</label>
                                    </div>
                                    <span class="input-group-text cursor-pointer" data-toggle-secret="{{ $key['field'] }}">
                                        <i class="icon-base ri ri-eye-off-line icon-20px"></i>
                                    </span>
                                </div>
                            </div>
                            <small class="form-text text-muted mt-1 d-block">
                                @if($saved)
                                    <span class="text-success"><i class="icon-base ri ri-check-line me-1"></i>Key saved</span> ·
                                @else
                                    <span>{{ $key['required'] }}</span> ·
                                @endif
                                {{ $key['use'] }}
                                Get it at <a href="{{ $key['get'] }}" target="_blank" rel="noopener">{{ $key['get_label'] }}</a>.
                            </small>
                            @if($saved)
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="clear_{{ $key['field'] }}" data-clear-field="{{ $key['field'] }}">
                                <label class="form-check-label small" for="clear_{{ $key['field'] }}">Remove the saved key</label>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">About OpenRouter</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    <strong>OpenRouter</strong> provides unified access to multiple AI models through a single API. 
                    With your API key, you can use various AI models for your projects.
                </p>
                <ul class="mb-0">
                    <li>Access to multiple AI models (GPT-4, Claude, Llama, etc.)</li>
                    <li>Simple, unified API interface</li>
                    <li>Pay-as-you-go pricing</li>
                    <li>Secure and reliable</li>
                </ul>
                <div class="mt-4">
                    <a href="https://openrouter.ai" target="_blank" class="btn btn-outline-primary">
                        <i class="icon-base ri ri-external-link-line me-2"></i>
                        Visit OpenRouter
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const settingsForm = document.getElementById('settingsForm');
    const apiKeyInput = document.getElementById('openrouter_api_key');
    const toggleApiKey = document.getElementById('toggleApiKey');
    
    // Toggle password visibility
    if (toggleApiKey) {
        toggleApiKey.addEventListener('click', function() {
            const type = apiKeyInput.getAttribute('type') === 'password' ? 'text' : 'password';
            apiKeyInput.setAttribute('type', type);
            
            const icon = toggleApiKey.querySelector('i');
            if (type === 'password') {
                icon.classList.remove('ri-eye-line');
                icon.classList.add('ri-eye-off-line');
            } else {
                icon.classList.remove('ri-eye-off-line');
                icon.classList.add('ri-eye-line');
            }
        });
    }
    
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const ollamaUrlInput = document.getElementById('ollama_url');
            const formData = {
                openrouter_api_key: apiKeyInput.value.trim(),
                ollama_url: ollamaUrlInput ? ollamaUrlInput.value.trim() || 'http://localhost:11434/' : 'http://localhost:11434/',
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            // Only send if value is provided
            if (!formData.openrouter_api_key) {
                // Allow clearing the key
                if (!confirm('Are you sure you want to clear the API key?')) {
                    return;
                }
            }

            const submitButton = settingsForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch('{{ route("settings.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    openrouter_api_key: formData.openrouter_api_key || null,
                    ollama_url: formData.ollama_url
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Network response was not ok');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Settings updated successfully!');
                    // Clear the input after successful save
                    apiKeyInput.value = '';
                    // Reload page to show updated status
                    window.location.reload();
                } else {
                    alert('Error updating settings: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating settings: ' + error.message);
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }
    
    // Literature databases & open-access PDFs form
    const literatureApiForm = document.getElementById('literatureApiForm');
    if (literatureApiForm) {
        literatureApiForm.querySelectorAll('[data-toggle-secret]').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = document.getElementById(toggle.dataset.toggleSecret);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                const icon = toggle.querySelector('i');
                icon.classList.toggle('ri-eye-line', show);
                icon.classList.toggle('ri-eye-off-line', !show);
            });
        });

        literatureApiForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const emailInput = document.getElementById('unpaywall_email');
            if (emailInput.value.trim() && !emailInput.checkValidity()) {
                alert('Enter a valid contact e-mail.');
                return;
            }

            const body = { unpaywall_email: emailInput.value.trim(), clear: [] };
            literatureApiForm.querySelectorAll('[data-secret-field]').forEach(input => {
                if (input.value.trim()) body[input.dataset.secretField] = input.value.trim();
            });
            literatureApiForm.querySelectorAll('[data-clear-field]').forEach(box => {
                if (box.checked) body.clear.push(box.dataset.clearField);
            });

            const submitButton = literatureApiForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch('{{ route("settings.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            })
            .then(response => response.json().then(data => {
                if (!response.ok || !data.success) {
                    const message = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Unknown error');
                    throw new Error(message);
                }
                return data;
            }))
            .then(() => {
                alert('Settings updated successfully!');
                window.location.reload();
            })
            .catch(error => alert('Error updating settings: ' + error.message))
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }

    // Local Models Form
    const localModelsForm = document.getElementById('localModelsForm');
    const ollamaUrlInput = document.getElementById('ollama_url');
    
    if (localModelsForm) {
        localModelsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                ollama_url: ollamaUrlInput.value.trim() || 'http://localhost:11434/',
                _token: document.querySelector('meta[name="csrf-token"]').content
            };

            const submitButton = localModelsForm.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch('{{ route("settings.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': formData._token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ollama_url: formData.ollama_url
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Network response was not ok');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Settings updated successfully!');
                    // Reload page to show updated status
                    window.location.reload();
                } else {
                    alert('Error updating settings: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating settings: ' + error.message);
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }
});
</script>
@endsection
