{{-- Shared na styling ng dalawang legal page (Privacy Policy at Terms of
     Service). Iisang kopya para hindi mag-drift ang itsura ng dalawa. --}}
<style>
    html {
        scroll-behavior: smooth;
    }

    .legal-body {
        --legal-text: #4f4634;
    }

    .legal-back {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--muted);
        text-decoration: none;
        font-size: 13px;
        margin-bottom: 22px;
        transition: color 0.2s ease;
    }

    .legal-back:hover {
        color: var(--gold);
    }

    /* ── Hero ── */
    .legal-hero {
        background: linear-gradient(135deg, var(--sand), var(--cream));
        border: 1px solid var(--border);
        border-radius: 22px;
        padding: 42px 44px;
        margin-bottom: 34px;
    }

    .legal-eyebrow {
        font-size: 13px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--gold);
        font-weight: 600;
        margin-bottom: 12px;
    }

    .legal-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(30px, 4vw, 44px);
        font-weight: 600;
        color: var(--stone);
        margin: 0 0 14px;
        line-height: 1.15;
    }

    .legal-sub {
        font-size: 15px;
        color: var(--muted);
        line-height: 1.75;
        max-width: 640px;
        margin: 0;
    }

    .legal-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 26px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        font-size: 12.5px;
        color: var(--muted);
    }

    .legal-meta span {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .legal-meta i {
        color: var(--gold);
        font-size: 13px;
    }

    /* ── Layout ── */
    .legal-wrap {
        display: grid;
        grid-template-columns: 232px 1fr;
        gap: 40px;
        align-items: start;
        padding-bottom: 90px;
    }

    .legal-toc {
        position: sticky;
        top: 96px;
    }

    .toc-title {
        font-size: 13px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: var(--muted);
        font-weight: 600;
        margin: 0 0 14px 12px;
    }

    .toc-link {
        display: block;
        padding: 7px 12px;
        border-left: 2px solid transparent;
        color: var(--muted);
        text-decoration: none;
        font-size: 13px;
        line-height: 1.45;
        transition: color 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    }

    .toc-link:hover {
        color: var(--stone);
    }

    .toc-link.active {
        color: var(--gold);
        border-left-color: var(--gold);
        background: rgba(184, 148, 63, 0.08);
        font-weight: 500;
    }

    .toc-switch {
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid var(--border);
    }

    .toc-switch a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: var(--stone);
        text-decoration: none;
        font-weight: 500;
    }

    .toc-switch a:hover {
        color: var(--gold);
    }

    /* ── Document ── */
    .legal-doc {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 42px 46px;
        box-shadow: 0 10px 34px rgba(44, 36, 22, 0.05);
    }

    .legal-doc section {
        scroll-margin-top: 100px;
    }

    .legal-doc section + section {
        margin-top: 38px;
        padding-top: 34px;
        border-top: 1px solid var(--border);
    }

    .legal-doc h2 {
        font-family: 'Playfair Display', serif;
        font-size: 22px;
        font-weight: 600;
        color: var(--stone);
        margin: 0 0 14px;
        line-height: 1.3;
    }

    .legal-doc h2 .num {
        color: var(--gold);
        font-size: 15px;
        font-family: 'Jost', sans-serif;
        font-weight: 600;
        margin-right: 10px;
        vertical-align: 3px;
    }

    .legal-doc h3 {
        font-size: 14px;
        font-weight: 600;
        color: var(--stone);
        margin: 22px 0 8px;
        letter-spacing: 0.2px;
    }

    .legal-doc p,
    .legal-doc li {
        font-size: 14.5px;
        line-height: 1.85;
        color: var(--legal-text, #4f4634);
    }

    .legal-doc p {
        margin: 0 0 12px;
    }

    .legal-doc ul {
        margin: 0 0 14px;
        padding-left: 20px;
    }

    .legal-doc li {
        margin-bottom: 7px;
    }

    .legal-doc li::marker {
        color: var(--gold);
    }

    .legal-doc strong {
        color: var(--stone);
        font-weight: 600;
    }

    .legal-doc a {
        color: var(--gold);
        text-decoration: none;
        border-bottom: 1px solid rgba(184, 148, 63, 0.35);
    }

    .legal-doc a:hover {
        border-bottom-color: var(--gold);
    }

    /* ── Callout ── */
    .legal-note {
        background: var(--sand);
        border-left: 3px solid var(--gold);
        border-radius: 0 12px 12px 0;
        padding: 15px 18px;
        margin: 16px 0;
    }

    .legal-note p:last-child {
        margin-bottom: 0;
    }

    .legal-note.plain {
        border-left-color: var(--border);
    }

    /* ── Tables ── */
    .legal-table-wrap {
        overflow-x: auto;
        margin: 16px 0 18px;
        border: 1px solid var(--border);
        border-radius: 14px;
    }

    .legal-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
        min-width: 460px;
    }

    .legal-table th {
        text-align: left;
        font-size: 10.5px;
        letter-spacing: 1.4px;
        text-transform: uppercase;
        color: var(--muted);
        font-weight: 600;
        padding: 12px 16px;
        background: var(--sand);
        white-space: nowrap;
    }

    .legal-table td {
        padding: 12px 16px;
        border-top: 1px solid var(--border);
        color: var(--legal-text, #4f4634);
        line-height: 1.65;
        vertical-align: top;
    }

    .legal-table td strong {
        color: var(--stone);
    }

    .legal-table code {
        font-size: 12.5px;
        background: var(--sand);
        border-radius: 5px;
        padding: 2px 6px;
        color: var(--stone);
    }

    /* ── Contact card + doc switch ── */
    .legal-contact {
        margin-top: 40px;
        background: var(--stone);
        border-radius: 18px;
        padding: 28px 30px;
        color: rgba(255, 255, 255, 0.72);
    }

    .legal-contact h2 {
        color: #fff;
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        margin: 0 0 10px;
    }

    .legal-contact p {
        font-size: 14px;
        line-height: 1.8;
        color: rgba(255, 255, 255, 0.72);
        margin: 0 0 14px;
    }

    .legal-contact-rows {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 28px;
        font-size: 13.5px;
    }

    .legal-contact-rows span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .legal-contact-rows i {
        color: var(--gold-light);
    }

    .legal-contact-rows a {
        color: var(--gold-light);
        text-decoration: none;
        border-bottom: 1px solid rgba(212, 170, 90, 0.35);
    }

    .legal-doc-switch {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 22px;
    }

    .legal-doc-switch a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 18px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #fff;
        color: var(--stone);
        font-size: 13.5px;
        font-weight: 500;
        text-decoration: none;
        transition: border-color 0.2s ease, color 0.2s ease;
    }

    .legal-doc-switch a:hover {
        border-color: var(--gold);
        color: var(--gold);
    }

    /* ── Responsive ── */
    @media (max-width: 900px) {
        .legal-hero {
            padding: 32px 26px;
        }

        .legal-wrap {
            grid-template-columns: minmax(0, 1fr);
            gap: 26px;
        }

        .legal-toc {
            position: static;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 16px;
        }

        .toc-title {
            margin-left: 0;
        }

        .toc-links {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .toc-link {
            border-left: none;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 11px;
            font-size: 12.5px;
        }

        .toc-link.active {
            border-color: var(--gold);
        }

        .legal-doc {
            padding: 28px 22px;
        }
    }
</style>
