@extends('layouts/contentNavbarLayout')
@section('title', 'Dashboard - Analytics')

@php
use Illuminate\Support\Str;
@endphp

@section('content')
<div class="row gy-4">
    <!-- OpenRouter Balance Card -->
    @if($hasOpenRouterKey)
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial bg-primary rounded shadow-xs">
                            <i class="icon-base ri ri-money-dollar-circle-line icon-24px"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <p class="mb-0">OpenRouter Balance</p>
                        <h5 class="mb-0">
                            @if($openRouterBalance !== null)
                                ${{ number_format($openRouterBalance, 2) }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <!--/ OpenRouter Balance Card -->

    <!-- OpenRouter Models Count Card -->
    @if($hasOpenRouterKey)
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial bg-success rounded shadow-xs">
                            <i class="icon-base ri ri-brain-line icon-24px"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <p class="mb-0">OpenRouter Models</p>
                        <h5 class="mb-0">
                            @if($openRouterModelsCount !== null)
                                {{ $openRouterModelsCount }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <!--/ OpenRouter Models Count Card -->

    <!-- Local Models Count Card -->
    @if($hasLocalUrl)
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial bg-info rounded shadow-xs">
                            <i class="icon-base ri ri-server-line icon-24px"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <p class="mb-0">Local Models</p>
                        <h5 class="mb-0">
                            @if($localModelsCount !== null)
                                {{ $localModelsCount }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <!--/ Local Models Count Card -->

    <!-- Included PDFs Count Card -->
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <div class="avatar-initial bg-danger rounded shadow-xs">
                            <i class="icon-base ri ri-file-pdf-line icon-24px"></i>
                        </div>
                    </div>
                    <div class="ms-3">
                        <p class="mb-0">Included PDFs</p>
                        <h5 class="mb-0">{{ $includedPdfsCount }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--/ Included PDFs Count Card -->

    <!-- Projects Table Card - Full Width -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-folder-line me-2"></i>Projects
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Owner</th>
                                <th>Studies Count</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $project)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="icon-base ri ri-folder-line me-2 text-primary"></i>
                                        <a href="{{ route('projects.show', $project) }}" class="text-primary fw-semibold" style="text-decoration: none;">
                                            {{ $project->name }}
                                        </a>
                                    </div>
                                </td>
                                <td>{{ $project->description ? Str::limit($project->description, 100) : 'N/A' }}</td>
                                <td>{{ $project->user->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-info">{{ $project->studies_count }}</span>
                                </td>
                                <td>{{ $project->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-icon btn-label-primary" title="View Project">
                                        <i class="icon-base ri ri-eye-line"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No projects found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!--/ Projects Table Card -->

    <!-- Selected Models Card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="icon-base ri ri-checkbox-circle-line me-2"></i>Selected Models for Use
                </h5>
                <a href="{{ route('ai-models') }}" class="btn btn-sm btn-primary">
                    <i class="icon-base ri ri-settings-line me-1"></i>Manage Models
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Model ID</th>
                                <th>Price Input</th>
                                <th>Price Output</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($selectedModels as $model)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="icon-base ri ri-brain-line me-2 text-primary"></i>
                                        <span>{{ $model->name }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if($model->type === 'openrouter')
                                        <span class="badge bg-success">OpenRouter</span>
                                    @elseif($model->type === 'local')
                                        <span class="badge bg-info">Local</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $model->type }}</span>
                                    @endif
                                </td>
                                <td>{{ $model->model_id ?? 'N/A' }}</td>
                                <td>
                                    @if($model->price_input !== null)
                                        ${{ number_format($model->price_input * 1000000, 2) }}/M
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    @if($model->price_output !== null)
                                        ${{ number_format($model->price_output * 1000000, 2) }}/M
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    No models selected. <a href="{{ route('ai-models') }}">Go to AI Models</a> to add models.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!--/ Selected Models Card -->
</div>
@endsection
