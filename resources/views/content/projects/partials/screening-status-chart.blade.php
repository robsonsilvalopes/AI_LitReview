{{--
    Status distribution donut, with a percentage tile per status and a stacked bar.
    Screening decisions (Include / Exclude / N/A):
        @include('content.projects.partials.screening-status-chart', ['statusSummary' => ['include' => n, 'exclude' => n, 'na' => n]])
    Any other statuses: pass 'statusItems' (label, count, color = Bootstrap color name, icon) and optionally
    'chartTitle' and 'chartScope' (the subtitle reads "<total> studies <chartScope>").
--}}
@php
    $ssItems = collect($statusItems ?? [
        ['label' => 'Include', 'count' => $statusSummary['include'] ?? 0, 'color' => 'success', 'icon' => 'ri-checkbox-circle-line'],
        ['label' => 'Exclude', 'count' => $statusSummary['exclude'] ?? 0, 'color' => 'danger', 'icon' => 'ri-close-circle-line'],
        ['label' => 'N/A', 'count' => $statusSummary['na'] ?? 0, 'color' => 'secondary', 'icon' => 'ri-question-line'],
    ])->map(fn ($item) => array_merge($item, ['count' => (int) $item['count']]))->all();
    $ssTotal = array_sum(array_column($ssItems, 'count'));
    $ssPct = fn ($n) => $ssTotal > 0 ? number_format($n / $ssTotal * 100, 1) : '0.0';
    $ssTileCol = 'col-sm-' . max(3, intdiv(12, max(1, count($ssItems))));
    $ssChartItems = array_map(fn ($i) => ['label' => $i['label'], 'count' => $i['count'], 'color' => $i['color']], $ssItems);
@endphp

@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss', 'resources/assets/vendor/libs/apex-charts/apexcharts.js'])

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ $chartTitle ?? 'Decision Distribution' }}</h5>
                <small class="text-muted">{{ $ssTotal }} {{ $ssTotal === 1 ? 'study' : 'studies' }} {{ $chartScope ?? 'in this screening' }}</small>
            </div>
            <div class="card-body">
                <div class="row align-items-center g-4">
                    <div class="col-md-5 col-lg-4">
                        <div id="screening-status-chart" style="min-height: 240px;"></div>
                    </div>
                    <div class="col-md-7 col-lg-8">
                        <div class="row g-3">
                            @foreach($ssItems as $item)
                            <div class="{{ $ssTileCol }}">
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
                            @foreach($ssItems as $item)
                            <div class="progress-bar bg-{{ $item['color'] }}" role="progressbar" style="width: {{ $item['count'] / $ssTotal * 100 }}%" title="{{ $item['label'] }}"></div>
                            @endforeach
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

    const items = @json($ssChartItems);
    const counts = items.map(i => i.count);
    const total = counts.reduce((a, b) => a + b, 0);
    const css = getComputedStyle(document.documentElement);
    const color = (name, fallback) => (css.getPropertyValue(name) || '').trim() || fallback;
    const fallbackColors = { success: '#56ca00', danger: '#ff4c51', secondary: '#8a8d93', warning: '#ffb400', info: '#16b1ff', primary: '#8c57ff' };

    new ApexCharts(el, {
        chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
        series: total > 0 ? counts : [1],
        labels: total > 0 ? items.map(i => i.label) : ['No studies'],
        colors: total > 0
            ? items.map(i => color('--bs-' + i.color, fallbackColors[i.color] || '#8a8d93'))
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
