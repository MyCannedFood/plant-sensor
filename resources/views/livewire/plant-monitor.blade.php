<div wire:poll.5s="refresh">
    <a href="{{ route('overview') }}" class="back-link">
        <i data-lucide="arrow-left" class="icon"></i> Overview
    </a>

    <div class="mon-head">
        @if ($editing)
            <div class="mon-title">
                <h2>Edit plant</h2>
                <p>Update the room details below.</p>
            </div>
        @else
            <div class="mon-title">
                <h2>{{ $this->plant->name }}</h2>
                <p>{{ $this->plant->location }} · {{ $this->plant->species }}</p>
            </div>
            <div class="mon-actions">
                <button type="button" class="btn-ghost" wire:click="startEdit"><i data-lucide="pencil" class="icon"></i> Edit</button>
                <button type="button" class="btn-danger-ghost" wire:click="requestDelete"><i data-lucide="trash-2" class="icon"></i> Delete</button>
            </div>
        @endif
    </div>

    @if ($editing)
        <div class="panel form-panel" wire:key="plant-edit-form">
            <div class="form-grid">
                <label class="t-field">
                    <h4>Name</h4>
                    <input type="text" wire:model="plantDetails.name" maxlength="60" autofocus>
                    @error('plantDetails.name') <span class="field-error">{{ $message }}</span> @enderror
                </label>
                <label class="t-field">
                    <h4>Species</h4>
                    <input type="text" wire:model="plantDetails.species" maxlength="120">
                </label>
                <label class="t-field">
                    <h4>Location</h4>
                    <input type="text" wire:model="plantDetails.location" maxlength="80">
                </label>
                <label class="t-field form-wide">
                    <h4>Notes</h4>
                    <textarea wire:model="plantDetails.notes" rows="3" maxlength="1000" placeholder="Care tips, anything to remember…"></textarea>
                </label>
            </div>
            <div class="t-actions">
                <button type="button" class="save-btn" wire:click="savePlantDetails">Save changes</button>
                <button type="button" class="btn-ghost" wire:click="cancelEdit">Cancel</button>
                <span class="save-confirm mono"><i data-lucide="check" class="icon"></i> Saved</span>
            </div>
        </div>
    @endif

    @if ($confirmingDelete)
        <div class="delete-confirm" wire:key="delete-confirm">
            <div>
                <p class="delete-title">Delete {{ $this->plant->name }}?</p>
                <p class="delete-copy">This permanently removes
                    <b>{{ $this->deleteSummary['devices'] }}</b> @choice('device|devices', $this->deleteSummary['devices']),
                    <b>{{ $this->deleteSummary['readings'] }}</b> @choice('reading|readings', $this->deleteSummary['readings']) and
                    <b>{{ $this->deleteSummary['alerts'] }}</b> @choice('alert|alerts', $this->deleteSummary['alerts']).
                    This cannot be undone.</p>
            </div>
            <div class="t-actions">
                <button type="button" class="btn-danger" wire:click="destroyPlant">Delete plant</button>
                <button type="button" class="btn-ghost" wire:click="cancelDelete">Cancel</button>
            </div>
        </div>
    @endif

    <div class="stat-grid">
        @foreach ($this->metricCards() as $card)
            <article class="stat-card flag-{{ $card['status'] }}">
                <div class="stat-top">
                    <p class="stat-label">{{ $card['label'] }}</p>
                    <span class="stat-tag">{{ $card['status_label'] }}</span>
                </div>
                <p class="stat-value">{{ $card['display'] }}<span class="unit">{{ $card['unit'] }}</span></p>
                <p class="stat-range">band <b>{{ $card['band'] }}</b> {{ $card['unit'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Trend</h3>
                <p class="panel-sub">Measurement history with threshold band</p>
            </div>
            <div class="panel-controls">
                <div class="segmented" role="group" aria-label="Metric">
                    @foreach ($this->metricOptions() as $option)
                        <button type="button" wire:click="setMetric('{{ $option['value'] }}')" class="{{ $option['active'] ? 'active' : '' }}">{{ $option['short'] }}</button>
                    @endforeach
                </div>
                <div class="segmented" role="group" aria-label="Range">
                    @foreach (['1h' => '1H', '24h' => '24H', '7d' => '7D'] as $value => $label)
                        <button type="button" wire:click="setRange('{{ $value }}')" class="{{ $this->range === $value ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
        <div wire:ignore id="trendChart" data-config="{{ json_encode($this->chartConfig(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}"></div>
    </div>

    <div class="group-grid">
        <div class="panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Devices</h3>
                    <p class="panel-sub">Nodes in this room</p>
                </div>
            </div>
            @foreach ($this->deviceRows() as $device)
                <div class="dev-row">
                    <div>
                        <div class="dev-name">{{ $device['name'] }}</div>
                        <div class="dev-sat">
                            @foreach ($device['badges'] as $badge)
                                <span class="sensor-badge">{{ $badge }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="dev-meta">
                        <span class="dev-chip-status {{ $device['online'] ? 'text-on' : 'text-off' }}">
                            <span class="dot-inline"></span>{{ $device['status_word'] }}
                        </span>
                        <span class="dev-lastseen">last seen <b class="mono">{{ $device['last_seen'] }}</b></span>
                        <span class="dev-readings">{{ $device['readings_today'] }} readings today</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <h3 class="panel-title">Recent alerts</h3>
                    <p class="panel-sub">Latest events on this node</p>
                </div>
                <a href="{{ route('alerts') }}" class="alert-link">View all <i data-lucide="arrow-right" class="icon"></i></a>
            </div>
            @forelse ($this->recentAlerts() as $alert)
                <div class="alert-item sev-{{ $alert['severity_key'] }}">
                    <span class="alert-time">{{ $alert['time'] }}</span>
                    <div>
                        <p class="alert-msg"><span class="alert-dot"></span>{{ $alert['message'] }}</p>
                        <p class="alert-meta">{{ $alert['meta'] }}</p>
                    </div>
                </div>
            @empty
                <p class="empty-note">No recent events — all clear.</p>
            @endforelse
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Threshold settings</h3>
                <p class="panel-sub">Alerts fire when a measurement crosses these bounds</p>
            </div>
        </div>

        <div wire:ignore>
            <div class="threshold-grid">
                @foreach ($this->metricOptions() as $option)
                    <div class="t-field">
                        <h4>{{ $option['label'] }} <span class="t-unit">· {{ $option['unit'] }}</span></h4>
                        <div class="t-inputs">
                            <label>Min
                                <input type="number" step="{{ $option['step'] }}" wire:model.defer="thresholdForm.{{ $option['value'] }}.min">
                            </label>
                            <label>Max
                                <input type="number" step="{{ $option['step'] }}" wire:model.defer="thresholdForm.{{ $option['value'] }}.max">
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="t-actions">
                <button type="button" class="save-btn" wire:click="saveThresholds">Save thresholds</button>
                <span class="save-confirm mono" id="saveConfirm"><i data-lucide="check" class="icon"></i> Saved</span>
            </div>
        </div>
    </div>
</div>