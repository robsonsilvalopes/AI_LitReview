{{--
    Floating right-side menu with the review steps of the project page, each marked done or missing.
    Needs $project; uses $steps when the page already computed them. Planning steps scroll to their section of
    the project page (or open it there from another page); phase steps open their page.
--}}
@php
    $steps = $steps ?? app(\App\Services\ProjectSteps::class)->forProject($project);
    $onProjectPage = request()->routeIs('projects.show');
    $currentStep = collect(\App\Services\ProjectSteps::PAGE_STEPS)->first(fn ($step, $route) => request()->routeIs($route));
    $projectUrl = route('projects.show', $project);
    $allSteps = collect($steps)->pluck('steps')->flatten(1);
    $doneSteps = $allSteps->where('done', true)->count();
    $stepsPercent = $allSteps->count() ? round($doneSteps / $allSteps->count() * 100) : 0;
@endphp
<style>
    .project-step-anchor { scroll-margin-top: 6rem; }
    #projectSteps {
        position: fixed; top: 5.5rem; right: 1rem; bottom: 1rem; width: 280px; z-index: 1029;
        display: flex; flex-direction: column; border-radius: .5rem; overflow: hidden;
        box-shadow: 0 .25rem 1rem rgba(46, 38, 61, .16);
    }
    #projectSteps .steps-body { overflow-y: auto; overflow-x: hidden; flex: 1 1 auto; padding: .25rem .5rem .75rem; }
    #projectSteps .step-link > span { min-width: 0; overflow-wrap: anywhere; }
    #projectSteps .steps-group { font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; opacity: .6; margin: .9rem .5rem .35rem; }
    #projectSteps .step-link {
        display: flex; align-items: flex-start; gap: .55rem; padding: .45rem .5rem; border-radius: .375rem;
        color: inherit; text-decoration: none; line-height: 1.25;
    }
    #projectSteps .step-link:hover, #projectSteps .step-link:focus-visible { background: rgba(var(--bs-primary-rgb), .08); }
    #projectSteps .step-link.active { background: rgba(var(--bs-primary-rgb), .14); }
    #projectSteps .step-status { font-size: 1.15rem; line-height: 1; margin-top: .05rem; }
    #projectSteps .step-detail { font-size: .75rem; opacity: .7; display: block; margin-top: .1rem; }
    #projectStepsToggle { position: fixed; right: 1rem; bottom: 1rem; z-index: 1029; display: none; }

    /* Wide screens: fixed panel next to the content, unless collapsed */
    @media (min-width: 1200px) {
        body.project-steps-open .layout-page { padding-right: 300px; }
        body.project-steps-collapsed #projectSteps { display: none; }
        body.project-steps-collapsed #projectStepsToggle { display: inline-flex; }
    }
    /* Narrow screens: a button opens the panel over the content */
    @media (max-width: 1199.98px) {
        #projectSteps { display: none; top: 4.5rem; width: min(320px, calc(100vw - 2rem)); z-index: 1090; }
        body.project-steps-show #projectSteps { display: flex; }
        #projectStepsToggle { display: inline-flex; }
        body.project-steps-show #projectStepsToggle { display: none; }
    }
</style>

<nav id="projectSteps" class="bg-menu-theme" aria-label="Review steps">
    <div class="d-flex align-items-start justify-content-between px-3 pt-3 pb-2 border-bottom">
        <div class="me-2 flex-grow-1" style="min-width: 0;">
            <div class="fw-semibold">Review steps</div>
            @unless($onProjectPage)
            <a href="{{ $projectUrl }}" class="small d-block text-truncate" title="{{ $project->name }}">{{ $project->name }}</a>
            @endunless
            <div class="small opacity-75">{{ $doneSteps }} of {{ $allSteps->count() }} done</div>
            <div class="progress mt-2" style="height: 6px;" role="progressbar" aria-valuenow="{{ $stepsPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Steps done">
                <div class="progress-bar bg-success" style="width: {{ $stepsPercent }}%"></div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-icon btn-text-secondary flex-shrink-0" id="projectStepsClose" title="Hide steps" aria-label="Hide steps">
            <i class="icon-base ri ri-arrow-right-s-line"></i>
        </button>
    </div>
    <div class="steps-body">
        @foreach($steps as $group)
            <div class="steps-group">{{ $group['title'] }}</div>
            @foreach($group['steps'] as $step)
            <a href="{{ $step['anchor'] && !$onProjectPage ? $projectUrl . $step['href'] : $step['href'] }}"
               class="step-link{{ $currentStep === $step['key'] ? ' active' : '' }}" data-step="{{ $step['key'] }}"
               @if($step['anchor']) data-anchor="{{ ltrim($step['href'], '#') }}" @endif
               @if($currentStep === $step['key']) aria-current="page" @endif
               title="{{ $step['done'] ? 'Done' : 'Missing' }}: {{ $step['detail'] }}">
                @if($step['done'])
                    <i class="icon-base ri ri-checkbox-circle-fill text-success step-status" aria-label="Done"></i>
                @else
                    <i class="icon-base ri ri-error-warning-line text-warning step-status" aria-label="Missing"></i>
                @endif
                <span>
                    <i class="icon-base ri {{ $step['icon'] }} me-1 opacity-75"></i>{{ $step['label'] }}
                    <span class="step-detail">{{ $step['detail'] }}</span>
                </span>
            </a>
            @endforeach
        @endforeach
    </div>
</nav>
<button type="button" class="btn btn-primary shadow" id="projectStepsToggle" aria-controls="projectSteps">
    <i class="icon-base ri ri-list-check-3 me-1"></i>Steps
    <span class="badge bg-white text-primary ms-2">{{ $doneSteps }}/{{ $allSteps->count() }}</span>
</button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const panel = document.getElementById('projectSteps');
    if (!panel) return;
    const body = document.body;
    const wide = window.matchMedia('(min-width: 1200px)');
    const storageKey = 'projectStepsCollapsed';
    const read = () => { try { return localStorage.getItem(storageKey) === '1'; } catch (e) { return false; } };
    const write = value => { try { localStorage.setItem(storageKey, value ? '1' : '0'); } catch (e) {} };

    function apply() {
        const collapsed = read();
        body.classList.toggle('project-steps-collapsed', collapsed);
        body.classList.toggle('project-steps-open', !collapsed);
    }
    apply();

    document.getElementById('projectStepsClose').addEventListener('click', function() {
        if (wide.matches) { write(true); apply(); } else { body.classList.remove('project-steps-show'); }
    });
    document.getElementById('projectStepsToggle').addEventListener('click', function() {
        if (wide.matches) { write(false); apply(); } else { body.classList.add('project-steps-show'); }
    });

    // Section links scroll on this page; on narrow screens the panel closes after a click
    panel.querySelectorAll('.step-link').forEach(link => link.addEventListener('click', function(e) {
        const id = link.dataset.anchor;
        if (id) {
            const target = document.getElementById(id);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({behavior: 'smooth', block: 'start'});
                history.replaceState(null, '', '#' + id);
            }
        }
        if (!wide.matches) body.classList.remove('project-steps-show');
    }));

    // Highlight the section in view
    const anchored = @json($onProjectPage) ? Array.from(panel.querySelectorAll('[data-anchor]')) : [];
    if ('IntersectionObserver' in window && anchored.length) {
        const byId = Object.fromEntries(anchored.map(a => [a.dataset.anchor, a]));
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    anchored.forEach(a => a.classList.remove('active'));
                    byId[entry.target.id]?.classList.add('active');
                }
            });
        }, {rootMargin: '-30% 0px -60% 0px'});
        anchored.forEach(a => { const t = document.getElementById(a.dataset.anchor); if (t) observer.observe(t); });
    }
});
</script>
