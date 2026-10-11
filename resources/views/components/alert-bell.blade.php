<a href="{{ route('alerts') }}" class="bell">
    <i data-lucide="bell" class="icon"></i> Alerts
    @if ($count > 0)
        <span class="bell-count">{{ $count }}</span>
    @endif
</a>