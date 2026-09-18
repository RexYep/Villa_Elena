{{-- Styles ng listahan ng ulat ng guest — ginagamit ng booking detail at ng dashboard (home). --}}
<style>
        .issue-list.with-cta {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .issue-list-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--stone);
            margin-bottom: 4px;
        }

        .issue-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f4efe6;
        }

        .issue-item:last-child {
            border-bottom: none;
        }

        .issue-item > i {
            font-size: 18px;
            color: var(--muted);
            margin-top: 1px;
        }

        .issue-item-main {
            flex: 1;
            min-width: 0;
        }

        .issue-item-title {
            font-size: 14px;
            font-weight: 600;
        }

        .issue-item-desc {
            font-size: 13px;
            color: var(--stone);
            overflow-wrap: anywhere;
        }

        .issue-item-time {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .issue-status {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .ws-pending { background: #fef9c3; color: #a16207; }
        .ws-in-progress { background: #dbeafe; color: #1d4ed8; }
        .ws-completed { background: #dcfce7; color: #15803d; }
        .ws-cancelled { background: #f1f5f9; color: #475569; }
</style>
