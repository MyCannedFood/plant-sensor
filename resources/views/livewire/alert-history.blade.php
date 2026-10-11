<div>
    <a href="{{ route('overview') }}" class="back-link">
        <i data-lucide="arrow-left" class="icon"></i> Overview
    </a>

    <h1 class="view-title">Alert history</h1>
    <p class="view-sub">Every event across all rooms, newest first.</p>

    <div class="filter-tabs">
        @foreach (['all' => 'All', 'alert' => 'Alert', 'watch' => 'Watch', 'resolved' => 'Resolved'] as $value => $label)
            <button type="button" wire:click="setFilter('{{ $value }}')" class="{{ $this->filter === $value ? 'active' : '' }}">
                {{ $label }} ({{ $counts[$value] }})
            </button>
        @endforeach
    </div>

    <div class="panel alert-list">
        @forelse ($alerts as $alert)
            <div class="alert-item sev-{{ $alert['severity_key'] }}">
                <span class="alert-time">{{ $alert['time'] }}<br>{{ $alert['day'] }}</span>
                <div>
                    <p class="alert-msg"><span class="alert-dot"></span><b>{{ $alert['room'] }}</b> — {{ $alert['message'] }}</p>
                    <p class="alert-meta">{{ $alert['meta'] }} · {{ $alert['severity'] }}</p>
                </div>
            </div>
        @empty
            <p class="empty-note">Nothing here.</p>
        @endforelse
    </div>
</div>