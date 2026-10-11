<div>
    @session('notice')
        <div class="notice-banner"><i data-lucide="check" class="icon"></i> {{ $value }}</div>
    @endsession

    <div class="view-head">
        <div>
            <h1 class="view-title">Overview</h1>
            <p class="view-sub">All monitored plants, latest conditions and status. Click a plant to inspect it.</p>
        </div>
        <div class="view-actions">
            <span class="save-confirm mono"><i data-lucide="check" class="icon"></i> Saved</span>
            <button type="button" class="add-btn" wire:click="showPlantForm">
                <i data-lucide="plus" class="icon"></i> Add plant
            </button>
        </div>
    </div>

    @if ($showForm)
        <div class="panel form-panel" wire:key="plant-form">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">New plant</h3>
                    <p class="panel-sub">Register a room so a device can start reporting on it.</p>
                </div>
            </div>
            <div class="form-grid">
                <label class="t-field">
                    <h4>Name</h4>
                    <input type="text" wire:model="plantForm.name" maxlength="60" placeholder="e.g. Monstera" autofocus>
                    @error('plantForm.name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="t-field">
                    <h4>Species</h4>
                    <input type="text" wire:model="plantForm.species" maxlength="120" placeholder="e.g. Monstera deliciosa">
                </label>
                <label class="t-field">
                    <h4>Location</h4>
                    <input type="text" wire:model="plantForm.location" maxlength="80" placeholder="e.g. Living Room">
                </label>
                <label class="t-field form-wide">
                    <h4>Notes</h4>
                    <textarea wire:model="plantForm.notes" rows="3" maxlength="1000" placeholder="Care tips, anything to remember…"></textarea>
                </label>
            </div>
            <div class="t-actions">
                <button type="button" class="save-btn" wire:click="savePlant">Save plant</button>
                <button type="button" class="btn-ghost" wire:click="cancelPlantForm">Cancel</button>
            </div>
        </div>
    @endif

    <div class="plant-grid">
        @forelse ($rooms as $room)
            <a href="{{ route('plants.monitor', $room['id']) }}" class="plant-card">
                <div class="card-top">
                    <div>
                        <h3>{{ $room['name'] }}</h3>
                        <p class="species">{{ $room['species'] }}</p>
                        <p class="location">{{ $room['location'] }}</p>
                    </div>
                    <span class="alert-count-chip {{ $room['alert_count'] ? 'has-alerts' : '' }}">
                        @if ($room['alert_count'])
                            <i data-lucide="triangle-alert" class="icon"></i> {{ $room['alert_count'] }}
                        @else
                            <i data-lucide="check" class="icon"></i> ok
                        @endif
                    </span>
                </div>

                <div class="metric-row">
                    @foreach ($room['metrics'] as $metric)
                        <div class="metric-chip flag-{{ $metric['status'] }}">
                            <span class="m-label">{{ $metric['label'] }}</span>
                            <span class="m-value">
                                {{ $metric['display'] }}<span class="m-unit">{{ $metric['unit'] }}</span>
                                @if ($metric['trend'] === 'up')
                                    <span class="m-trend up"><i data-lucide="arrow-up-right" class="icon"></i></span>
                                @elseif ($metric['trend'] === 'down')
                                    <span class="m-trend down"><i data-lucide="arrow-down-right" class="icon"></i></span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="card-foot">
                    <span>
                        <span class="status-dot {{ $room['alive'] ? 'status-online' : 'status-offline' }}"></span>
                        {{ $room['readings_today'] }} readings today
                    </span>
                    <span class="mono">updated {{ $room['last_seen_text'] }}</span>
                </div>
            </a>
        @empty
            <div class="add-cta">
                <i data-lucide="plus" class="icon"></i>
                <h3>No plants yet</h3>
                <p>Add your first plant to start monitoring a room.</p>
                <button type="button" class="add-btn" wire:click="showPlantForm">Add plant</button>
            </div>
        @endforelse
    </div>
</div>