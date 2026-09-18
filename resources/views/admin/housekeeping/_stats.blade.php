    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-val {{ $stats['open_reports'] ? 'alert-val' : '' }}">{{ $stats['open_reports'] }}</div>
            <div class="stat-lbl">Open issue reports</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">{{ $stats['open_tasks'] }}</div>
            <div class="stat-lbl">Open tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-val {{ $stats['overdue'] ? 'alert-val' : '' }}">{{ $stats['overdue'] }}</div>
            <div class="stat-lbl">Overdue tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">{{ $stats['done_week'] }}</div>
            <div class="stat-lbl">Done this week</div>
        </div>
    </div>
