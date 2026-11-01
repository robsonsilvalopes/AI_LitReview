{{--
    Screening decision distribution (Include / Exclude / N/A)
    Usage: @include('content.projects.partials.screening-status-chart', ['statusSummary' => ['include' => n, 'exclude' => n, 'na' => n]])
--}}
@php
    $ssInclude = (int) ($statusSummary['include'] ?? 0);
    $ssExclude = (int) ($statusSummary['exclude'] ?? 0);
    $ssNa = (int) ($statusSummary['na'] ?? 0);
    $ssTotal = $ssInclude + $ssExclude + $ssNa;
    $ssPct = fn ($n) => $ssTotal > 0 ? number_format($n / $ssTotal * 100, 1) : '0.0';
    $ssItems = [
        ['label' => 'Include', 'count' => $ssInclude, 'color' => 'success', 'icon' => 'ri-checkbox-circle-line'],
        ['label' => 'Exclude', 'count' => $ssExclude, 'color' => 'danger', 'icon' => 'ri-close-circle-line'],
        ['label' => 'N/A', 'count' => $ssNa, 'color' => 'secondary', 'icon' => 'ri-question-line'],
    ];
@endphp

@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss', 'resources/assets/vendor/libs/apex-charts/apexcharts.js'])

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Decision Distribution</h5>
                <small class="text-muted">{{ $ssTotal }} {{ $ssTotal === 1 ? 'study' : 'studies' }} in this screening</small>
            </div>
            <div class="card-body">
                <div class="row align-items-center g-4">
                    <div class="col-md-5 col-lg-4">
                        <div id="screening-status-chart" style="min-height: 240px;"></div>
                    </div>
                    <div class="col-md-7 col-lg-8">
                        <div class="row g-3">
                            @foreach($ssItems as $item)
                            <div class="col-sm-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <span class="avatar-initial rounded bg-label-{{ $item['color'] }}">
                                            <i class="icon-base ri {{ $item['icon'] }} ri-24px"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="small text-muted">{{ $item['label'] }}</div>
                                        <h5 class="mb-0">{{ $ssPct($item['count']) }}%</h5>
                                        <small class="text-muted">{{ $item['count'] }} {{ $item['count'] === 1 ? 'study' : 'studies' }}</small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @if($ssTotal > 0)
                        <div class="progress mt-4" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: {{ $ssInclude / $ssTotal * 100 }}%" title="Include"></div>
                            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $ssExclude / $ssTotal * 100 }}%" title="Exclude"></div>
                            <div class="progress-bar bg-secondary" role="progressbar" style="width: {{ $ssNa / $ssTotal * 100 }}%" title="N/A"></div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('screening-status-chart');
    if (!el || typeof ApexCharts === 'undefined') return;

    const counts = [{{ $ssInclude }}, {{ $ssExclude }}, {{ $ssNa }}];
    const total = counts.reduce((a, b) => a + b, 0);
    const css = getComputedStyle(document.documentElement);
    const color = (name, fallback) => (css.getPropertyValue(name) || '').trim() || fallback;

    new ApexCharts(el, {
        chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
        series: total > 0 ? counts : [1],
        labels: total > 0 ? ['Include', 'Exclude', 'N/A'] : ['No studies'],
        colors: total > 0
            ? [color('--bs-success', '#56ca00'), color('--bs-danger', '#ff4c51'), color('--bs-secondary', '#8a8d93')]
            : [color('--bs-gray-200', '#e9ecef')],
        stroke: { width: 2, colors: [color('--bs-paper-bg', '#fff')] },
        dataLabels: {
            enabled: total > 0,
            formatter: (val) => val.toFixed(1) + '%'
        },
        legend: { show: true, position: 'bottom', labels: { colors: color('--bs-body-color', '#666') } },
        tooltip: {
            enabled: total > 0,
            fillSeriesColor: false,
            y: { formatter: (val) => val + (val === 1 ? ' study' : ' studies') + ' (' + (val / total * 100).toFixed(1) + '%)' }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        value: { color: color('--bs-heading-color', '#333'), fontSize: '22px', fontWeight: 600 },
                        total: {
                            show: true,
                            label: 'Studies',
                            color: color('--bs-secondary-color', '#888'),
                            formatter: () => String(total)
                        }
                    }
                }
            }
        }
    }).render();
});
</script>
