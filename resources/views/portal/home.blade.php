@extends('layouts.portal')

@section('title', $resortName . ' — Private Island Luxury')
@section('bare')@endsection

@push('styles')
<style>
    :root { --nav-h: 80px; }
    html { scroll-behavior: smooth; }
    body {
        font-family: 'Inter', sans-serif;
        background: var(--cream);
        color: var(--stone);
        line-height: 1.6;
    }

    /* ════════════════════════════════════════
       NAVIGATION
    ════════════════════════════════════════ */
    .nav {
        position: fixed;
        top: 0; left: 0; right: 0;
        height: var(--nav-h);
        z-index: 1000;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        background: none;
        backdrop-filter: none;
    }
    .nav.scrolled {
        background: rgba(44, 36, 22, 0.98);
        backdrop-filter: blur(16px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    .nav-inner {
        max-width: 1280px;
        margin: 0 auto;
        height: 100%;
        display: flex;
        align-items: center;
        padding: 0 40px;
        gap: 8px;
    }
    .nav-brand {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        font-weight: 700;
        color: #fff;
        text-decoration: none;
        letter-spacing: -0.5px;
        flex-shrink: 0;
        margin-right: auto;
    }
    .nav-brand em {
        color: var(--gold-light);
        font-style: italic;
        font-weight: 400;
    }

    /* Desktop nav links */
    .nav-links {
        display: flex;
        align-items: center;
        gap: 4px;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .nav-links a {
        color: rgba(255,255,255,0.75);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 50px;
        transition: all 0.25s ease;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }
    .nav-links a:hover {
        color: #fff;
        background: rgba(255,255,255,0.1);
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
        flex-shrink: 0;
    }
    .nav-btn {
        padding: 10px 22px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-family: inherit;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .nav-btn-ghost {
        color: rgba(255,255,255,0.85);
        border: 1px solid rgba(255,255,255,0.25);
        background: transparent;
    }
    .nav-btn-ghost:hover {
        color: #fff;
        border-color: rgba(255,255,255,0.7);
        background: rgba(255,255,255,0.08);
    }
    .nav-btn-gold {
        background: var(--gold);
        color: var(--stone);
        font-weight: 600;
    }
    .nav-btn-gold:hover {
        background: var(--gold-light);
        transform: translateY(-2px);
    }

    /* Hamburger */
    .hamburger {
        display: none;
        flex-direction: column;
        gap: 5px;
        cursor: pointer;
        padding: 8px;
        border: none;
        background: transparent;
    }
    .hamburger span {
        display: block;
        width: 24px;
        height: 2px;
        background: #fff;
        border-radius: 2px;
        transition: all 0.3s ease;
    }
    .hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
    .hamburger.open span:nth-child(2) { opacity: 0; transform: translateX(-8px); }
    .hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

    /* Mobile menu */
    .mobile-menu {
        display: none;
        position: fixed;
        top: var(--nav-h);
        left: 0; right: 0;
        background: rgba(44, 36, 22, 0.98);
        backdrop-filter: blur(20px);
        padding: 20px 24px 28px;
        z-index: 999;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .mobile-menu.open { display: block; }
    .mobile-menu a {
        display: block;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        font-size: 16px;
        font-weight: 500;
        padding: 14px 0;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        transition: color 0.2s;
    }
    .mobile-menu a:hover { color: var(--gold-light); }
    .mobile-menu .mobile-auth {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }
    .mobile-menu .mobile-auth a {
        border: none;
        padding: 12px 24px;
        border-radius: 50px;
        text-align: center;
        flex: 1;
    }

    /* ════════════════════════════════════════
       HERO SECTION
    ════════════════════════════════════════ */
    .hero {
        height: 100vh;
        min-height: 680px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1a0f06 0%, #2c1a0a 45%, #3d2b12 100%);
        overflow: hidden;
    }
  .hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url('/images/images8.jpg') center/cover no-repeat;
    opacity: 0.30;
    z-index: 1;
}
    .hero-bg-pattern {
        position: absolute;
        inset: 0;
        opacity: 0.05;
        background-image: radial-gradient(circle, #fff 1px, transparent 1px);
        background-size: 40px 40px;
        z-index: 2;
    }
    .hero-glow {
        position: absolute;
        width: 900px;
        height: 900px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(212, 170, 90, 0.22) 0%, transparent 70%);
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1;
        animation: pulse 15s infinite ease-in-out;
    }
    @keyframes pulse {
        0%, 100% { opacity: 0.7; }
        50% { opacity: 1; }
    }

    .hero-content {
        position: relative;
        z-index: 3;
        text-align: center;
        padding: 0 20px;
        max-width: 860px;
    }
    .hero-eyebrow {
        font-size: 13px;
        letter-spacing: 4px;
        text-transform: uppercase;
        color: var(--gold-light);
        margin-bottom: 16px;
        font-weight: 500;
    }
    .hero-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(48px, 8vw, 92px);
        font-weight: 700;
        line-height: 1.05;
        color: #fff;
        margin-bottom: 20px;
    }
    .hero-title em {
        color: var(--gold-light);
        font-style: italic;
    }
    .hero-sub {
        color: rgba(255,255,255,0.75);
        font-size: 18px;
        max-width: 560px;
        margin: 0 auto 50px;
        font-weight: 300;
    }

    /* Hero CTA buttons */
    .hero-cta-row {
        display: flex;
        gap: 14px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 44px;
    }
    .btn-hero-primary {
        background: var(--gold);
        color: var(--stone);
        padding: 16px 36px;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.3s ease;
        letter-spacing: 0.3px;
    }
    .btn-hero-primary:hover {
        background: var(--gold-light);
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(184,148,63,0.4);
    }
    .btn-hero-ghost {
        border: 1.5px solid rgba(255,255,255,0.45);
        color: #fff;
        padding: 15px 36px;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .btn-hero-ghost:hover {
        background: rgba(255,255,255,0.12);
        border-color: rgba(255,255,255,0.8);
        transform: translateY(-3px);
    }

/* ════════════════════════════════════════
       SHARED SECTION STYLES
    ════════════════════════════════════════ */
    .section {
        max-width: 1280px;
        margin: 0 auto;
        padding: 100px 40px 120px;
    }
    .section-eyebrow {
        font-size: 12px;
        letter-spacing: 3px;
        text-transform: uppercase;
        color: var(--gold);
        font-weight: 500;
        margin-bottom: 10px;
    }
    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(34px, 4vw, 48px);
        font-weight: 600;
        color: var(--stone);
        line-height: 1.15;
    }
    .section-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 52px;
        flex-wrap: wrap;
        gap: 16px;
    }
    .section-desc {
        color: var(--muted);
        font-size: 16px;
        max-width: 520px;
        margin-top: 14px;
        font-weight: 300;
        line-height: 1.7;
    }
    .results-count {
        color: var(--muted);
        font-size: 15px;
    }

    /* ════════════════════════════════════════
       PROPERTIES SECTION
    ════════════════════════════════════════ */
    #properties { background: var(--cream); }

    .properties-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 32px;
    }
    .property-card {
        background: #fff;
        border-radius: 24px;
        overflow: hidden;
        border: 1px solid var(--border);
        transition: all 0.4s cubic-bezier(0.4, 0.0, 0.2, 1);
        text-decoration: none;
        color: var(--stone);
        display: block;
    }
    .property-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 30px 70px rgba(44, 36, 22, 0.15);
        border-color: transparent;
    }
    .property-img-wrap {
        position: relative;
        height: 260px;
        overflow: hidden;
        background: var(--sand);
    }
    .property-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }
    .property-card:hover .property-img {
        transform: scale(1.08);
    }
    .prop-type-badge {
        position: absolute;
        top: 18px; left: 18px;
        background: rgba(44,36,22,0.8);
        color: #fff;
        padding: 6px 16px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
        letter-spacing: 0.5px;
        backdrop-filter: blur(8px);
    }
    .prop-status-badge {
        position: absolute;
        top: 18px; right: 18px;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-available { background: #d1fae5; color: #10b981; }
    .status-occupied  { background: #fee2e2; color: #ef4444; }

    .prop-body { padding: 26px 28px 28px; }
    .prop-name {
        font-family: 'Playfair Display', serif;
        font-size: 22px;
        font-weight: 600;
        margin-bottom: 8px;
        line-height: 1.2;
    }
    .prop-meta {
        display: flex;
        gap: 16px;
        font-size: 13.5px;
        color: var(--muted);
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .prop-meta span { display: flex; align-items: center; gap: 5px; }
    .prop-amenities {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 22px;
    }
    .amenity-tag {
        background: var(--sand);
        color: var(--muted);
        padding: 4px 14px;
        border-radius: 50px;
        font-size: 12px;
    }
    .prop-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 18px;
        border-top: 1px solid var(--border);
    }
    .prop-price-val {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        font-weight: 700;
        color: var(--stone);
    }
    .prop-price-night { font-size: 13px; color: var(--muted); }
    .btn-book {
        background: var(--stone);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 12px 26px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-block;
        text-decoration: none;
    }
    .btn-book:hover {
        background: var(--gold);
        color: var(--stone);
        transform: translateY(-2px);
    }
    .btn-book-unavailable {
        background: var(--border);
        color: var(--muted);
        cursor: not-allowed;
    }
    .btn-book-unavailable:hover {
        background: var(--border);
        color: var(--muted);
        transform: none;
    }

    .empty-state {
        text-align: center;
        padding: 100px 40px;
        color: var(--muted);
    }
    .empty-state i {
        font-size: 68px;
        margin-bottom: 24px;
        opacity: 0.4;
        display: block;
    }

    /* ── Villa Showcase (single-property, exclusive rental) ── */
    .villa-showcase {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 0;
        background: #fff;
        border-radius: 28px;
        overflow: hidden;
        border: 1px solid var(--border);
        box-shadow: 0 30px 70px rgba(44, 36, 22, 0.08);
    }
    .villa-showcase-img-wrap { position: relative; min-height: 420px; background: var(--sand); }
    .villa-showcase-img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; }
    .villa-showcase-badge {
        position: absolute; top: 22px; left: 22px;
        background: rgba(44,36,22,0.85); color: #fff;
        padding: 7px 18px; border-radius: 50px; font-size: 12px; font-weight: 500;
        letter-spacing: .5px; backdrop-filter: blur(8px);
    }
    .villa-showcase-body { padding: 44px 44px 40px; display: flex; flex-direction: column; }
    .villa-showcase-name {
        font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700;
        line-height: 1.1; margin-bottom: 12px;
    }
    .villa-showcase-desc { font-size: 14.5px; color: var(--muted); line-height: 1.7; margin-bottom: 20px; }
    .villa-showcase-meta { display: flex; gap: 20px; font-size: 13.5px; color: var(--muted); margin-bottom: 20px; flex-wrap: wrap; }
    .villa-showcase-meta span { display: flex; align-items: center; gap: 6px; }

    .villa-showcase-amenities { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }

    /* Rooms status strip */
    .rooms-status-strip {
        background: var(--sand); border-radius: 16px; padding: 16px 18px; margin-bottom: 26px;
    }
    .rooms-status-title {
        font-size: 12px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase;
        color: var(--muted); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
    }
    .rooms-status-list { display: flex; flex-wrap: wrap; gap: 8px; }
    .room-pill {
        font-size: 12.5px; font-weight: 500; padding: 5px 12px; border-radius: 50px;
        display: inline-flex; align-items: center; gap: 5px; background: #fff; border: 1px solid var(--border);
    }
    .room-pill .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .dot-available { background: #10b981; }
    .dot-occupied { background: #f59e0b; }
    .dot-maintenance { background: #ef4444; }

    .villa-showcase-footer {
        margin-top: auto; display: flex; align-items: center; justify-content: space-between;
        padding-top: 24px; border-top: 1px solid var(--border);
    }
    .villa-showcase-price { font-family: 'Playfair Display', serif; font-size: 30px; font-weight: 700; }
    .villa-showcase-price span { font-size: 14px; font-weight: 400; color: var(--muted); font-family: 'Jost', sans-serif; }
    .btn-book-showcase {
        background: var(--stone); color: #fff; border: none; border-radius: 14px;
        padding: 16px 32px; font-size: 15px; font-weight: 700; cursor: pointer;
        transition: all .3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-book-showcase:hover { background: var(--gold); color: var(--stone); transform: translateY(-2px); }
    .btn-book-showcase.unavailable { background: var(--border); color: var(--muted); cursor: not-allowed; pointer-events: none; }

    @media (max-width: 900px) {
        .villa-showcase { grid-template-columns: 1fr; }
        .villa-showcase-img-wrap { min-height: 280px; }
        .villa-showcase-body { padding: 32px 26px; }
        .villa-showcase-footer { flex-direction: column; align-items: stretch; gap: 16px; }
        .btn-book-showcase { justify-content: center; }
    }

    /* ════════════════════════════════════════
       AMENITIES SECTION
    ════════════════════════════════════════ */
    #amenities {
        background: var(--stone);
        padding: 0;
    }
    #amenities .section {
        padding: 100px 40px;
    }
    #amenities .section-eyebrow { color: var(--gold-light); }
    #amenities .section-title { color: #fff; }
    #amenities .section-desc { color: rgba(255,255,255,0.55); }

    .amenities-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 24px;
        margin-top: 52px;
    }
    .amenity-card {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 20px;
        padding: 36px 28px;
        text-align: center;
        transition: all 0.35s ease;
    }
    .amenity-card:hover {
        background: rgba(184,148,63,0.12);
        border-color: rgba(184,148,63,0.35);
        transform: translateY(-6px);
    }
    .amenity-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: rgba(184,148,63,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 26px;
        color: var(--gold-light);
        transition: all 0.35s ease;
    }
    .amenity-card:hover .amenity-icon {
        background: var(--gold);
        color: var(--stone);
    }
    .amenity-name {
        font-family: 'Playfair Display', serif;
        font-size: 17px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 8px;
    }
    .amenity-desc {
        font-size: 13px;
        color: rgba(255,255,255,0.45);
        line-height: 1.5;
    }

    /* ════════════════════════════════════════
       ROOM TOUR SECTION
    ════════════════════════════════════════ */
    #room-tour {
        background: var(--cream);
        padding: 0;
    }
    #room-tour .section { padding: 100px 40px; }

    .room-tour-note {
        display: flex; align-items: center; gap: 10px;
        background: rgba(196,103,58,0.07); border: 1px solid rgba(196,103,58,0.18);
        border-radius: 14px; padding: 14px 20px; margin: 8px 0 44px; font-size: 14px; color: var(--stone);
    }
    .room-tour-note i { color: var(--terracotta); font-size: 18px; flex-shrink: 0; }

    .room-tour-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 28px;
    }
    .room-card {
        background: var(--sand);
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
        border: 1px solid var(--border);
    }
    .room-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(44,36,22,0.12);
    }
    .room-card-img-wrap {
        position: relative;
        aspect-ratio: 4/3;
        overflow: hidden;
        background: var(--border);
    }
    .room-card-img-wrap img {
        width: 100%; height: 100%; object-fit: cover;
        display: block; transition: transform 0.6s ease;
    }
    .room-card:hover .room-card-img-wrap img { transform: scale(1.08); }
    .room-card-img-placeholder {
        width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
        font-size: 44px; color: #c9bfa8; background: var(--border);
    }
    .room-card-status {
        position: absolute; top: 14px; right: 14px;
        font-size: 11.5px; font-weight: 600; letter-spacing: .3px;
        padding: 6px 13px; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px;
        backdrop-filter: blur(6px);
        color: #fff;
    }
    .room-card-status .dot { width: 7px; height: 7px; border-radius: 50%; background: #fff; }
    .status-available   { background: rgba(16,185,129,0.85); }
    .status-occupied    { background: rgba(245,158,11,0.85); }
    .status-maintenance { background: rgba(239,68,68,0.85); }

    .room-card-body { padding: 20px 22px 24px; }
    .room-card-name {
        font-family: 'Playfair Display', serif;
        font-size: 19px; font-weight: 600; color: var(--stone); margin-bottom: 4px;
    }
    .room-card-meta {
        font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 6px;
    }

    @media (max-width: 600px) {
        .room-tour-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }
    }

    /* ════════════════════════════════════════
       GALLERY SECTION
    ════════════════════════════════════════ */
    #gallery {
        background: var(--sand);
        padding: 0;
    }
    #gallery .section { padding: 100px 40px; }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-template-rows: auto;
        gap: 16px;
        margin-top: 52px;
    }
    .gallery-item {
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        cursor: pointer;
    }
    .gallery-item.tall { grid-row: span 2; }
    .gallery-item.wide { grid-column: span 2; }

    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.6s ease;
        min-height: 220px;
    }
    .gallery-item:hover img { transform: scale(1.06); }
    .gallery-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(44,36,22,0.65), transparent);
        opacity: 0;
        transition: opacity 0.4s ease;
        display: flex;
        align-items: flex-end;
        padding: 24px;
    }
    .gallery-item:hover .gallery-overlay { opacity: 1; }
    .gallery-caption {
        color: #fff;
        font-family: 'Playfair Display', serif;
        font-size: 16px;
        font-weight: 500;
    }

    /* Lightbox */
    .lightbox {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.9);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .lightbox.open { display: flex; }
    .lightbox-img {
        max-width: 90vw;
        max-height: 85vh;
        border-radius: 12px;
        object-fit: contain;
    }
    .lightbox-close {
        position: absolute;
        top: 24px; right: 24px;
        color: #fff;
        font-size: 32px;
        cursor: pointer;
        background: rgba(255,255,255,0.12);
        width: 48px; height: 48px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        transition: background 0.2s;
    }
    .lightbox-close:hover { background: rgba(255,255,255,0.25); }

    /* ════════════════════════════════════════
       ABOUT SECTION
    ════════════════════════════════════════ */
    #about {
        background: var(--cream);
        padding: 0;
    }
    #about .section { padding: 100px 40px; }

    .about-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 80px;
        align-items: center;
    }
    .about-img-stack {
        position: relative;
        height: 520px;
    }
    .about-img-main {
        position: absolute;
        top: 0; left: 0;
        width: 75%;
        height: 400px;
        border-radius: 24px;
        object-fit: cover;
        box-shadow: 0 30px 70px rgba(44,36,22,0.18);
    }
    .about-img-accent {
        position: absolute;
        bottom: 0; right: 0;
        width: 55%;
        height: 280px;
        border-radius: 20px;
        object-fit: cover;
        box-shadow: 0 20px 50px rgba(44,36,22,0.15);
        border: 6px solid var(--cream);
    }
    .about-badge {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        background: var(--gold);
        color: var(--stone);
        border-radius: 50%;
        width: 100px; height: 100px;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        font-weight: 700;
        font-size: 13px;
        text-align: center;
        line-height: 1.3;
        box-shadow: 0 8px 30px rgba(184,148,63,0.5);
        z-index: 2;
    }
    .about-badge strong {
        font-family: 'Playfair Display', serif;
        font-size: 28px;
        display: block;
    }
    .about-text p {
        color: var(--muted);
        font-size: 16px;
        line-height: 1.8;
        margin-bottom: 20px;
        font-weight: 300;
    }
    .about-stats {
        display: flex;
        gap: 36px;
        margin-top: 40px;
        padding-top: 36px;
        border-top: 1px solid var(--border);
    }
    .stat-item {}
    .stat-num {
        font-family: 'Playfair Display', serif;
        font-size: 36px;
        font-weight: 700;
        color: var(--stone);
        line-height: 1;
    }
    .stat-label {
        font-size: 13px;
        color: var(--muted);
        margin-top: 4px;
    }

    /* ════════════════════════════════════════
       TESTIMONIALS SECTION
    ════════════════════════════════════════ */
    #testimonials {
        background: var(--sand);
        padding: 0;
    }
    #testimonials .section { padding: 100px 40px; }

    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 28px;
        margin-top: 52px;
    }
    .testimonial-card {
        background: #fff;
        border-radius: 22px;
        padding: 36px 32px;
        border: 1px solid var(--border);
        position: relative;
        transition: all 0.35s ease;
    }
    .testimonial-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 50px rgba(44,36,22,0.1);
        border-color: transparent;
    }
    .testimonial-quote {
        font-size: 52px;
        color: var(--gold);
        font-family: 'Playfair Display', serif;
        line-height: 1;
        margin-bottom: 12px;
        opacity: 0.5;
    }
    .testimonial-text {
        color: var(--stone);
        font-size: 15px;
        line-height: 1.75;
        font-style: italic;
        margin-bottom: 28px;
    }
    .testimonial-stars {
        color: var(--gold);
        font-size: 14px;
        letter-spacing: 2px;
        margin-bottom: 16px;
    }
    .testimonial-author {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .btn-see-more-reviews {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: transparent;
        color: var(--stone);
        border: 1.5px solid var(--stone);
        padding: 14px 32px;
        border-radius: 50px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .btn-see-more-reviews:hover {
        background: var(--stone);
        color: #fff;
        transform: translateY(-3px);
    }
    .author-avatar {
        width: 46px; height: 46px;
        border-radius: 50%;
        background: var(--sand);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        color: var(--gold);
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        flex-shrink: 0;
    }
    .author-name {
        font-weight: 600;
        font-size: 15px;
        color: var(--stone);
    }
    .author-location {
        font-size: 12px;
        color: var(--muted);
        margin-top: 2px;
    }

    /* ════════════════════════════════════════
       LOCATION SECTION
    ════════════════════════════════════════ */
    #location {
        background: var(--cream);
        padding: 0;
    }
    #location .section { padding: 100px 40px; }

    .location-grid {
        display: grid;
        grid-template-columns: 1fr 1.6fr;
        gap: 60px;
        align-items: start;
    }
    .location-info {}
    .location-item {
        display: flex;
        gap: 18px;
        margin-bottom: 32px;
    }
    .location-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        background: rgba(184,148,63,0.12);
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        color: var(--gold);
        flex-shrink: 0;
    }
    .location-item-label {
        font-size: 12px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: var(--muted);
        font-weight: 500;
        margin-bottom: 4px;
    }
    .location-item-value {
        font-size: 15px;
        color: var(--stone);
        line-height: 1.6;
    }
    .location-directions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 8px;
        color: var(--gold);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: gap 0.2s;
    }
    .location-directions:hover { gap: 12px; }

    .map-wrap {
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(44,36,22,0.12);
        border: 1px solid var(--border);
        height: 400px;
    }
    .map-wrap iframe {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
    }

    /* ════════════════════════════════════════
       CONTACT SECTION
    ════════════════════════════════════════ */
    #contact {
        background: var(--stone);
        padding: 0;
    }
    #contact .section { padding: 100px 40px; }
    #contact .section-eyebrow { color: var(--gold-light); }
    #contact .section-title { color: #fff; }
    #contact .section-desc { color: rgba(255,255,255,0.5); }

    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 70px;
        margin-top: 60px;
    }
    .contact-info {}
    .contact-item {
        display: flex;
        gap: 18px;
        margin-bottom: 36px;
    }
    .contact-icon {
        width: 52px; height: 52px;
        border-radius: 16px;
        background: rgba(184,148,63,0.15);
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        color: var(--gold-light);
        flex-shrink: 0;
    }
    .contact-item-label {
        font-size: 11px;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.4);
        margin-bottom: 5px;
    }
    .contact-item-value {
        font-size: 16px;
        color: rgba(255,255,255,0.85);
    }
    .contact-item-value a {
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        transition: color 0.2s;
    }
    .contact-item-value a:hover { color: var(--gold-light); }

    /* Contact Form */
    .contact-form { }
    .form-group { margin-bottom: 20px; }
    .form-label {
        font-size: 12px;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.45);
        margin-bottom: 10px;
        display: block;
        font-weight: 500;
    }
    .form-input {
        width: 100%;
        background: rgba(255,255,255,0.07);
        border: 1px solid rgba(255,255,255,0.14);
        border-radius: 14px;
        padding: 16px 20px;
        font-size: 15px;
        color: #fff;
        font-family: inherit;
        transition: all 0.3s ease;
        resize: none;
    }
    .form-input::placeholder { color: rgba(255,255,255,0.25); }
    .form-input:focus {
        outline: none;
        border-color: var(--gold);
        background: rgba(255,255,255,0.1);
        box-shadow: 0 0 0 4px rgba(184,148,63,0.12);
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .btn-send {
        width: 100%;
        background: var(--gold);
        color: var(--stone);
        border: none;
        border-radius: 14px;
        padding: 18px 36px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: inherit;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 8px;
    }
    .btn-send:hover {
        background: var(--gold-light);
        transform: translateY(-2px);
    }

    /* ════════════════════════════════════════
       FOOTER
    ════════════════════════════════════════ */
    .footer {
        background: #1a1009;
        padding: 70px 40px 40px;
    }
    .footer-inner {
        max-width: 1280px;
        margin: 0 auto;
    }
    .footer-top {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 50px;
        padding-bottom: 56px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .footer-brand {
        font-family: 'Playfair Display', serif;
        color: var(--gold-light);
        font-size: 30px;
        margin-bottom: 14px;
    }
    .footer-brand em {
        font-style: italic;
        font-weight: 400;
    }
    .footer-tagline {
        color: rgba(255,255,255,0.35);
        font-size: 14px;
        line-height: 1.7;
        max-width: 260px;
        margin-bottom: 24px;
    }
    .footer-col-title {
        font-size: 12px;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: rgba(255,255,255,0.4);
        margin-bottom: 20px;
        font-weight: 500;
    }
    .footer-links {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .footer-links a {
        color: rgba(255,255,255,0.5);
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }
    .footer-links a:hover { color: var(--gold-light); }

    .footer-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 32px;
        flex-wrap: wrap;
        gap: 16px;
    }
    .footer-copy {
        font-size: 13px;
        color: rgba(255,255,255,0.28);
    }
    .footer-legal {
        display: flex;
        gap: 24px;
    }
    .footer-legal a {
        font-size: 13px;
        color: rgba(255,255,255,0.28);
        text-decoration: none;
        transition: color 0.2s;
    }
    .footer-legal a:hover { color: rgba(255,255,255,0.6); }

    /* ════════════════════════════════════════
       ANIMATIONS & SCROLL REVEAL
    ════════════════════════════════════════ */
    .reveal {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.7s ease, transform 0.7s ease;
    }
    .reveal.visible {
        opacity: 1;
        transform: translateY(0);
    }
    .reveal-delay-1 { transition-delay: 0.1s; }
    .reveal-delay-2 { transition-delay: 0.2s; }
    .reveal-delay-3 { transition-delay: 0.3s; }
    .reveal-delay-4 { transition-delay: 0.4s; }

    /* ════════════════════════════════════════
       RESPONSIVE
    ════════════════════════════════════════ */
    @media (max-width: 1024px) {
        .about-grid { grid-template-columns: 1fr; gap: 50px; }
        .about-img-stack { height: 380px; }
        .location-grid { grid-template-columns: 1fr; gap: 40px; }
        .contact-grid { grid-template-columns: 1fr; gap: 50px; }
        .footer-top { grid-template-columns: 1fr 1fr; gap: 40px; }
    }

    @media (max-width: 768px) {
        .nav-links { display: none; }
        .hamburger { display: flex; }
        .nav-right .nav-btn { display: none; }
        .nav-inner { padding: 0 20px; }
.section { padding: 80px 20px 100px; }
        .properties-grid { grid-template-columns: 1fr; gap: 24px; }
        .hero-title { font-size: clamp(42px, 9vw, 72px); }
        .amenities-grid { grid-template-columns: repeat(2, 1fr); }
        .gallery-grid { grid-template-columns: 1fr 1fr; }
        .gallery-item.tall { grid-row: span 1; }
        .gallery-item.wide { grid-column: span 2; }
        .testimonials-grid { grid-template-columns: 1fr; }
        .about-stats { gap: 20px; }
        .form-row { grid-template-columns: 1fr; }
        .footer-top { grid-template-columns: 1fr; gap: 36px; }
        .footer-bottom { flex-direction: column; text-align: center; }
        .hero-cta-row { flex-direction: column; align-items: center; }
    }

    @media (max-width: 480px) {
        .amenities-grid { grid-template-columns: 1fr; }
        .gallery-grid { grid-template-columns: 1fr; }
        .gallery-item.wide { grid-column: span 1; }
    }
</style>
@endpush

@section('content')

{{-- ══════════════════════════════════
     NAVIGATION
══════════════════════════════════ --}}
<nav class="nav" id="mainNav">
    <div class="nav-inner">
        <a href="{{ route('home') }}" class="nav-brand">Villa <em>Elena</em></a>

        <ul class="nav-links">
            <li><a href="#hero">Home</a></li>
            <li><a href="#properties">The Villa</a></li>
            <li><a href="#room-tour">Rooms</a></li>
            <li><a href="#amenities">Amenities</a></li>
            <li><a href="#gallery">Gallery</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>

        <div class="nav-right">
            @auth
                <a href="{{ route('customer.home') }}" class="nav-btn nav-btn-ghost">My Bookings</a>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="nav-btn nav-btn-ghost">Sign Out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="nav-btn nav-btn-ghost">Sign In</a>
                <a href="{{ route('register') }}" class="nav-btn nav-btn-gold">Sign Up</a>
            @endauth
        </div>

        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

{{-- Mobile Menu --}}
<div class="mobile-menu" id="mobileMenu">
    <a href="#hero" class="mobile-link">Home</a>
    <a href="#properties" class="mobile-link">The Villa</a>
    <a href="#room-tour" class="mobile-link">Rooms</a>
    <a href="#amenities" class="mobile-link">Amenities</a>
    <a href="#gallery" class="mobile-link">Gallery</a>
    <a href="#about" class="mobile-link">About Us</a>
    <a href="#contact" class="mobile-link">Contact</a>
    <div class="mobile-auth">
        @auth
            <a href="{{ route('customer.home') }}" class="nav-btn nav-btn-ghost text-center">My Bookings</a>
        @else
            <a href="{{ route('login') }}" class="nav-btn nav-btn-ghost text-center">Sign In</a>
            <a href="{{ route('register') }}" class="nav-btn nav-btn-gold text-center">Sign Up</a>
        @endauth
    </div>
</div>


{{-- ══════════════════════════════════
     HERO SECTION
══════════════════════════════════ --}}
<section class="hero" id="hero">
    <div class="hero-bg-pattern"></div>
    <div class="hero-glow"></div>

    <div class="hero-content">

        <h1 class="hero-title">An Exclusive<br><em>Island Sanctuary</em></h1>
        <p class="hero-sub">{{ $resortDesc ?? 'Experience unparalleled privacy and natural beauty in our handcrafted villas.' }}</p>

{{-- CTA Buttons --}}
        <div class="hero-cta-row">
            <a href="#properties" class="btn-hero-primary">
                <i class="bi bi-calendar-check"></i> Book Now
            </a>
            <a href="#properties" class="btn-hero-ghost">
                Check Availability
            </a>
        </div>
    </div>

    <div style="position:absolute;bottom:40px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,0.4);font-size:12px;letter-spacing:2px;text-align:center;z-index:3;">
        <div>Discover Our Villas</div>
        <div style="width:1px;height:50px;background:linear-gradient(transparent, rgba(255,255,255,0.6), transparent);margin:8px auto;"></div>
    </div>
</section>


{{-- ══════════════════════════════════
     VILLA SHOWCASE SECTION
══════════════════════════════════ --}}
<section id="properties">
    <div class="section">
        <div class="section-header">
            <div>
                <div class="section-eyebrow reveal">Exclusive Private Rental</div>
                <div class="section-title reveal reveal-delay-1">Villa Elena</div>
                <p class="section-desc reveal reveal-delay-2">Welcome to our resort, a place good for vacation and any occasion.</p>
            </div>
        </div>

        {{-- Active Filters --}}
        @if(request()->hasAny(['checkin','guests']) && request()->filled('checkin'))
            <div style="background:rgba(184,148,63,0.08);border:1px solid rgba(184,148,63,0.2);border-radius:16px;padding:14px 20px;margin-bottom:40px;display:flex;align-items:center;gap:12px;font-size:14px;flex-wrap:wrap;">
                <i class="bi bi-funnel-fill" style="color:var(--gold);"></i>
                <span>Showing results for</span>
                <strong>{{ \Carbon\Carbon::parse(request('checkin'))->format('M j, Y') }} · {{ (request('slot') === 'night') ? 'Night (7PM–6AM)' : 'Day (8AM–5PM)' }}</strong>
                @if(request('guests')) <span>· {{ request('guests') }} guests</span> @endif
                <a href="{{ route('home') }}" style="margin-left:auto;color:var(--terracotta);font-weight:500;text-decoration:none;">Clear all</a>
            </div>
        @endif

        @php $villa = $properties->first(); @endphp

        @if(!$villa)
            <div class="empty-state">
                <i class="bi bi-house-slash"></i>
                <p class="mb-12" style="font-size:20px;font-weight:500;color:var(--stone);">Villa Elena is not available on the selected date.</p>
                <p>Try a different date, or check the calendar on the Villa's page..</p>
                <a href="{{ route('home') }}" style="color:var(--gold);margin-top:20px;display:inline-block;">Alisin ang filter →</a>
            </div>
        @else
            @php
                $amenities = is_array($villa->amenities)
                    ? $villa->amenities
                    : json_decode($villa->amenities ?? '[]', true);
                $showAmenities = array_slice($amenities ?? [], 0, 6);
                $checkin = request('checkin');
                $guests  = request('guests', 2);
                $slot    = in_array(request('slot'), array_keys(\App\Models\Booking::SLOTS)) ? request('slot') : 'day';
            @endphp

            <div class="villa-showcase reveal">
                <div class="villa-showcase-img-wrap">
                    @if($villa->primaryImage)
                        <img src="{{ $villa->primaryImage->url }}"
                             class="villa-showcase-img" alt="{{ $villa->property_name }}">
                    @else
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:80px;color:#ddd;">
                            <i class="bi bi-house"></i>
                        </div>
                    @endif
                    <span class="villa-showcase-badge"><i class="bi bi-shield-check"></i> Exclusive Use</span>
                </div>

                <div class="villa-showcase-body">
                    <div class="villa-showcase-name">{{ $villa->property_name }}</div>
                    @if($villa->description)
                        <p class="villa-showcase-desc">{{ $villa->description }}</p>
                    @endif
                    <div class="villa-showcase-meta">
                        <span><i class="bi bi-people-fill"></i> Up to {{ $villa->max_capacity }} guests</span>
                        @if($villa->floor_area_sqm)
                            <span><i class="bi bi-arrows-angle-expand"></i> {{ $villa->floor_area_sqm }} m²</span>
                        @endif
                        @if($rooms->count())
                            <span><i class="bi bi-door-open"></i> {{ $rooms->count() }} rooms</span>
                        @endif
                    </div>

                    @if(count($showAmenities))
                    <div class="villa-showcase-amenities">
                        @foreach($showAmenities as $amenity)
                            <span class="amenity-tag">{{ ucwords(str_replace('_', ' ', $amenity)) }}</span>
                        @endforeach
                        @if(count($amenities) > 6)
                            <span class="amenity-tag">+{{ count($amenities)-6 }} more</span>
                        @endif
                    </div>
                    @endif



                    <div class="villa-showcase-footer">
                        <div class="villa-showcase-price">
                            ₱{{ number_format($villa->base_price, 0) }} <span>/ package</span>
                            <div class="text-muted-theme" style="font-size:11px;font-family:'Jost',sans-serif;font-weight:400;margin-top:2px;line-height:1.5;">
                                * Regular: Mon–Thu &amp; Sun after 6PM &nbsp;|&nbsp;
                                @if($villa->weekend_price && $villa->weekend_price != $villa->base_price)
                                    * Peak: ₱{{ number_format($villa->weekend_price, 0) }} · Fri, Sat &amp; Sun before 6PM
                                @else
                                    * Same rate applies all week
                                @endif
                            </div>
                        </div>
                        @if($villa->status !== 'maintenance')
                            <a href="{{ route('portal.property', $villa) }}?checkin={{ $checkin }}&slot={{ $slot }}&guests={{ $guests }}"
                               class="btn-book-showcase">
                                <i class="bi bi-calendar-check"></i>
                                {{ $checkin ? 'Reserve Now' : 'Book Now' }}
                            </a>
                        @else
                         @endif

                   </div>
                </div>
            </div>
        @endif
    </div>
</section>


{{-- ══════════════════════════════════
     ROOM TOUR SECTION
══════════════════════════════════ --}}
@if($rooms->count())
<section id="room-tour">
    <div class="section">
        <div class="section-header">
            <div>
                <div class="section-eyebrow reveal">Inside the Villa</div>
                <div class="section-title reveal reveal-delay-1">Explore the<br>Rooms</div>
                <p class="section-desc reveal reveal-delay-2">A closer look at each room inside Villa Elena — all included in your one whole-villa booking.</p>
            </div>
        </div>



        <div class="room-tour-grid">
            @foreach($rooms as $room)
                @php
                    $roomImage = $room->images->first();
                @endphp
                <div class="room-card reveal">
                    <div class="room-card-img-wrap">
                        @if($roomImage)
                            <img src="{{ $roomImage->url }}" alt="{{ $room->property_name }}" loading="lazy">
                        @else
                            <div class="room-card-img-placeholder"><i class="bi bi-door-closed"></i></div>
                        @endif
                    </div>
                    <div class="room-card-body">
                        <div class="room-card-name">{{ $room->property_name }}</div>
                        @if($room->floor_area_sqm)
                            <div class="room-card-meta"><i class="bi bi-arrows-angle-expand"></i> {{ $room->floor_area_sqm }} m²</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif


{{-- ══════════════════════════════════
     AMENITIES SECTION
══════════════════════════════════ --}}
<section id="amenities">
    <div class="section">
        <div class="section-header">
            <div>
                <div class="section-eyebrow reveal">Resort Features</div>
                <div class="section-title reveal reveal-delay-1">World-Class<br>Amenities</div>
                <p class="section-desc reveal reveal-delay-2" style="color:rgba(255,255,255,0.5);">Every detail curated for your ultimate comfort and escape from the everyday.</p>
            </div>
        </div>

        <div class="amenities-grid">
            <div class="amenity-card reveal reveal-delay-1">
                <div class="amenity-icon"><i class="bi bi-droplet-fill"></i></div>
                <div class="amenity-name">Infinity Pool</div>
                <div class="amenity-desc">Perched oceanside with breathtaking sunset views</div>
            </div>
            <div class="amenity-card reveal reveal-delay-2">
                <div class="amenity-icon"><i class="bi bi-wifi"></i></div>
                <div class="amenity-name">High-Speed WiFi</div>
                <div class="amenity-desc">Fiber-optic connectivity throughout the resort</div>
            </div>
            <div class="amenity-card reveal reveal-delay-3">
                <div class="amenity-icon"><i class="bi bi-snow2"></i></div>
                <div class="amenity-name">Air Conditioning</div>
                <div class="amenity-desc">Climate-controlled villas for your comfort</div>
            </div>
            <div class="amenity-card reveal reveal-delay-1">
                <div class="amenity-icon"><i class="bi bi-car-front-fill"></i></div>
                <div class="amenity-name">Free Parking</div>
                <div class="amenity-desc">Secure, covered parking for all guests</div>
            </div>
            <div class="amenity-card reveal reveal-delay-2">
                <div class="amenity-icon"><i class="bi bi-building"></i></div>
                <div class="amenity-name">Function Hall</div>
                <div class="amenity-desc">Elegant event space for up to 200 guests</div>
            </div>
            <div class="amenity-card reveal reveal-delay-3">
                <div class="amenity-icon"><i class="bi bi-cup-hot-fill"></i></div>
                <div class="amenity-name">Fine Dining</div>
                <div class="amenity-desc">Farm-to-table cuisine and curated cocktails</div>
            </div>
            <div class="amenity-card reveal reveal-delay-1">
                <div class="amenity-icon"><i class="bi bi-heart-pulse-fill"></i></div>
                <div class="amenity-name">Spa & Wellness</div>
                <div class="amenity-desc">Rejuvenating treatments and yoga sessions</div>
            </div>
            <div class="amenity-card reveal reveal-delay-2">
                <div class="amenity-icon"><i class="bi bi-anchor"></i></div>
                <div class="amenity-name">Water Activities</div>
                <div class="amenity-desc">Kayaking, snorkeling, and island hopping</div>
            </div>
        </div>
    </div>
</section>


{{-- ══════════════════════════════════
     GALLERY SECTION
══════════════════════════════════ --}}
<section id="gallery">
    <div class="section">
        <div class="section-header">
            <div>
                <div class="section-eyebrow reveal">Visual Journey</div>
                <div class="section-title reveal reveal-delay-1">Captured Moments</div>
                <p class="section-desc reveal reveal-delay-2">A glimpse into the beauty that awaits you at Villa Elena.</p>
            </div>
        </div>

        <div class="gallery-grid">
          <div class="gallery-item tall" onclick="openLightbox(this)">
    <img src="{{ asset('images/view1.png') }}" alt="Beachfront villas">
    <div class="gallery-overlay"><div class="gallery-caption">Night View</div></div>
</div>
            <div class="gallery-item" onclick="openLightbox(this)">
                <img src="{{ asset('images/inside-view1.png') }}" alt="Inside View">
                <div class="gallery-overlay"><div class="gallery-caption">Inside View</div></div>
            </div>
            <div class="gallery-item" onclick="openLightbox(this)">
                <img src="{{ asset('images/kitchen1.png') }}" alt="Kitchen">
                <div class="gallery-overlay"><div class="gallery-caption">Kitchen Area</div></div>
            </div>
            <div class="gallery-item wide" onclick="openLightbox(this)">
                <img src="{{ asset('images/pool1.png') }}" alt="Resort View">
                <div class="gallery-overlay"><div class="gallery-caption">Panoramic Resort Pool View</div></div>
            </div>
            <div class="gallery-item" onclick="openLightbox(this)">
                <img src="{{ asset('images/terrace1.png') }}" alt="terrace">
                <div class="gallery-overlay"><div class="gallery-caption">Terrace</div></div>
            </div>
            <div class="gallery-item" onclick="openLightbox(this)">
                <img src="{{ asset('images/karaoke1.png') }}" alt="Karaoke">
                <div class="gallery-overlay"><div class="gallery-caption">Karaoke</div></div>
            </div>
            <div class="gallery-item" onclick="openLightbox(this)">
                <img src="{{ asset('images/images10.jpg') }}" alt="Dinning Area">
                <div class="gallery-overlay"><div class="gallery-caption">Dinning Area</div></div>
            </div>
        </div>
    </div>
</section>

{{-- Lightbox --}}
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()"><i class="bi bi-x"></i></span>
    <img src="" alt="" class="lightbox-img" id="lightboxImg">
</div>


{{-- ══════════════════════════════════
     ABOUT SECTION
══════════════════════════════════ --}}
<section id="about">
    <div class="section">
        <div class="about-grid">
            <div class="about-img-stack reveal">
                <img src="{{ asset('images/pool-view.jpg') }}"
                     class="about-img-main" alt="Resort landscape">
                <img src="{{ asset('images/night-view.jpg') }}"
                     class="about-img-accent" alt="Beach">
                <div class="about-badge">
                    <strong>6+</strong>
                    Years of<br>Excellence
                </div>
            </div>

            <div class="about-text">
                <div class="section-eyebrow reveal">Our Story</div>
                <div class="section-title reveal reveal-delay-1">A Sanctuary<br>Built for You</div>
                <p class="section-desc reveal reveal-delay-2" style="max-width:none;"></p>
                <p class="reveal reveal-delay-2 text-muted-theme" style="font-size:16px;line-height:1.8;font-weight:300;margin-bottom:18px;">
                    Villa Elena was born from a dream — to create an escape where luxury and nature exist in perfect harmony. Nestled along the pristine resort of Calamba, our resort is a testament to the beauty of the Philippines and the warmth of its people.
                </p>
                <p class="reveal reveal-delay-3 text-muted-theme" style="font-size:16px;line-height:1.8;font-weight:300;">
                    Every villa, pathway, and garden has been thoughtfully designed to offer complete immersion in the island's natural splendor — while ensuring every modern comfort is at your fingertips. From the moment you arrive, you are our honored guest.
                </p>

                <div class="about-stats reveal reveal-delay-4">
                    <div class="stat-item">
                        <div class="stat-num">6</div>
                        <div class="stat-label">Private Villas</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">4.7★</div>
                        <div class="stat-label">Guest Rating</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">1k+</div>
                        <div class="stat-label">Happy Guests</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


{{-- ══════════════════════════════════
     TESTIMONIALS SECTION
══════════════════════════════════ --}}
<section id="testimonials">
    <div class="section">
        <div style="text-align:center;margin-bottom:0;">
            <div class="section-eyebrow reveal">Guest Voices</div>
            <div class="section-title reveal reveal-delay-1">What Our Guests Say</div>
            <p style="font-size:16px;max-width:500px;margin:14px auto 0;font-weight:300;line-height:1.7;" class="reveal reveal-delay-2 text-muted-theme">
                Authentic reviews from guests who've experienced the Villa Elena difference.
            </p>
        </div>

        <div class="testimonials-grid">
            @forelse($reviews as $review)
                <div class="testimonial-card reveal reveal-delay-{{ $loop->iteration }}">
                    <div class="testimonial-quote">"</div>
                    <div class="testimonial-stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                    <p class="testimonial-text">
                        "{{ \Illuminate\Support\Str::limit($review->content, 180) }}"
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">{{ strtoupper(substr($review->user->full_name ?? 'G', 0, 1)) }}</div>
                        <div>
                            <div class="author-name">{{ $review->user->full_name ?? 'Guest' }}</div>
                           
                        </div>
                    </div>
                </div>
            @empty
                <div class="testimonial-card" style="grid-column:1/-1;text-align:center;">
                    <p class="testimonial-text" style="font-style:normal;margin-bottom:0;">
                        Be the first to share your experience at Villa Elena!
                    </p>
                </div>
            @endforelse
        </div>

        <div style="text-align:center;margin-top:48px;">
            <a href="{{ route('portal.reviews') }}" class="btn-see-more-reviews">
                See All Reviews <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</section>


{{-- ══════════════════════════════════
     LOCATION SECTION
══════════════════════════════════ --}}
<section id="location">
    <div class="section">
        <div>
            <div class="section-eyebrow reveal">Find Us</div>
            <div class="section-title reveal reveal-delay-1">Getting Here</div>
        </div>

        <div class="location-grid" style="margin-top:52px;">
            <div class="location-info">
                <div class="location-item reveal reveal-delay-1">
                    <div class="location-icon"><i class="bi bi-geo-alt-fill"></i></div>
                    <div>
                        <div class="location-item-label">Address</div>
                        <div class="location-item-value">{{ $resortAddress }}</div>
                        <a href="https://maps.app.goo.gl/sLsqnY5bL31FdFUV7" target="_blank" class="location-directions">
                            Get Directions <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="location-item reveal reveal-delay-2">
                    <div class="location-icon"><i class="bi bi-airplane-fill"></i></div>
                    <div>
                        <div class="location-item-label">Nearest Terminal</div>
                        <div class="location-item-value">Calamba Central Terminal (ENI)<br>30 minutes by private transfer</div>
                    </div>
                </div>

                <div class="location-item reveal reveal-delay-3">
                    <div class="location-icon"><i class="bi bi-clock-fill"></i></div>
                    <div>
                        <div class="location-item-label">Check-in / Check-out</div>
                        <div class="location-item-value">Check-in: 2:00 PM<br>Check-out: 12:00 PM</div>
                    </div>
                </div>

                <div class="location-item reveal reveal-delay-4">
                    <div class="location-icon"><i class="bi bi-headset"></i></div>
                    <div>
                        <div class="location-item-label">Concierge</div>
                        <div class="location-item-value">24/7 Personal Concierge<br>Available for all arrival arrangements</div>
                    </div>
                </div>
            </div>

            <div class="map-wrap reveal reveal-delay-2">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3868.237894451763!2d121.17662927419254!3d14.180839987217551!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd6178faf9d793%3A0xd871a08642165754!2sPansol%20Private%20Pool%20Resort%20In%20Laguna!5e0!3m2!1sen!2sph!4v1776613537909!5m2!1sen!2sph"
                    allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    title="Villa Elena Location">
                </iframe>
            </div>
        </div>
    </div>
</section>


{{-- ══════════════════════════════════
     CONTACT SECTION
══════════════════════════════════ --}}
<section id="contact">
    <div class="section">
        <div>
            <div class="section-eyebrow reveal">Get In Touch</div>
            <div class="section-title reveal reveal-delay-1">We'd Love<br>to Hear From You</div>
            <p class="section-desc reveal reveal-delay-2" style="color:rgba(255,255,255,0.5);">
                Whether you're planning a romantic escape or a grand celebration — our team is ready to craft your perfect stay.
            </p>
        </div>

        <div class="contact-grid">
            {{-- Contact Info --}}
            <div class="contact-info">
                <div class="contact-item reveal reveal-delay-1">
                    <div class="contact-icon"><i class="bi bi-telephone-fill"></i></div>
                    <div>
                        <div class="contact-item-label">Phone</div>
                        <div class="contact-item-value">
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $resortPhone) }}">{{ $resortPhone }}</a>
                        </div>
                    </div>
                </div>
                <div class="contact-item reveal reveal-delay-2">
                    <div class="contact-icon"><i class="bi bi-envelope-fill"></i></div>
                    <div>
                        <div class="contact-item-label">Email</div>
                        <div class="contact-item-value">
                            <a href="mailto:{{ $resortEmail }}">{{ $resortEmail }}</a>
                        </div>
                    </div>
                </div>

                <div class="contact-item reveal reveal-delay-3">
                    <div class="contact-icon"><i class="bi bi-facebook"></i></div>
                    <div>
                        <div class="contact-item-label">Facebook</div>
                        <div class="contact-item-value">
                            <a href="{{ $facebookUrl ?: '#' }}" target="_blank" rel="noopener">{{ $facebookUrl ? 'Visit our page' : 'Not yet set' }}</a>
                        </div>
                    </div>
                </div>
                <div class="contact-item reveal reveal-delay-4">
                    <div class="contact-icon"><i class="bi bi-tiktok"></i></div>
                    <div>
                        <div class="contact-item-label">TikTok</div>
                        <div class="contact-item-value">
                            <a href="{{ $tiktokUrl ?: '#' }}" target="_blank" rel="noopener">{{ $tiktokUrl ? 'Visit our page' : 'Not yet set' }}</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Contact Form --}}
            <div class="contact-form reveal reveal-delay-2">
                @if(session('contact_success'))
                    <div class="alert alert-success">{{ session('contact_success') }}</div>
                @endif
                @if(session('contact_error'))
                    <div class="alert alert-danger">{{ session('contact_error') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('portal.contact.send') }}" id="contactForm">
                    @csrf
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Your Name</label>
                            <input type="text" name="name" class="form-input" value="{{ old('name') }}" placeholder="Juan dela Cruz" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="{{ old('email') }}" placeholder="juan@example.com" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-input" value="{{ old('subject') }}" placeholder="Booking inquiry, special request...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-input" rows="5" placeholder="Tell us how we can help you plan the perfect getaway..." required minlength="10">{{ old('message') }}</textarea>
                    </div>
                    <button type="submit" class="btn-send">
                        <i class="bi bi-send-fill"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>


{{-- ══════════════════════════════════
     FOOTER
══════════════════════════════════ --}}
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-top">
            {{-- Brand --}}
            <div>
                <div class="footer-brand">Villa <em>Elena</em></div>
                <p class="footer-tagline">
                    A private resort sanctuary where luxury meets the untouched beauty of nature. {{ $resortAddress }}.
                </p>
            </div>

            {{-- Quick Links --}}
            <div>
                <div class="footer-col-title">Explore</div>
                <ul class="footer-links">
                    <li><a href="#hero">Home</a></li>
                    <li><a href="#properties">Our Villas</a></li>
                    <li><a href="#room-tour">Rooms</a></li>
                    <li><a href="#amenities">Amenities</a></li>
                    <li><a href="#gallery">Gallery</a></li>
                    <li><a href="#about">About Us</a></li>
                </ul>
            </div>

            {{-- Guest --}}
            <div>
                <div class="footer-col-title">Guests</div>
                <ul class="footer-links">
                    <li><a href="{{ route('login') }}">Sign In</a></li>
                    <li><a href="{{ route('register') }}">Create Account</a></li>
                    @auth
                        <li><a href="{{ route('customer.home') }}">My Bookings</a></li>
                    @endauth
                    <li><a href="#contact">Contact Us</a></li>
                    <li><a href="#location">Find Us</a></li>
                </ul>
            </div>

            {{-- Contact --}}
            <div>
                <div class="footer-col-title">Contact</div>
                <ul class="footer-links">
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $resortPhone) }}">{{ $resortPhone }}</a></li>
                    <li><a href="mailto:{{ $resortEmail }}">{{ $resortEmail }}</a></li>
                    <li style="color:rgba(255,255,255,0.4);font-size:14px;line-height:1.6;">{{ $resortAddress }}</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-copy">
                © {{ date('Y') }} Villa Elena Resort. All rights reserved.
            </div>
            <div class="footer-legal">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>

@endsection

@push('scripts')
<script>
    /* ── Nav: scroll effect ── */
    const nav = document.getElementById('mainNav');
    window.addEventListener('scroll', () => {
        nav.classList.toggle('scrolled', window.scrollY > 50);
    });

    /* ── Nav: hamburger toggle ── */
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobileMenu');

    hamburger?.addEventListener('click', () => {
        hamburger.classList.toggle('open');
        mobileMenu.classList.toggle('open');
    });

    document.querySelectorAll('.mobile-link').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('open');
            mobileMenu.classList.remove('open');
        });
    });

/* ── Scroll reveal ── */
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    /* ── Gallery lightbox ── */
    function openLightbox(item) {
        const img = item.querySelector('img');
        const lightbox = document.getElementById('lightbox');
        document.getElementById('lightboxImg').src = img.src;
        document.getElementById('lightboxImg').alt = img.alt;
        lightbox.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeLightbox();
    });

    /* ── Contact form (optional AJAX hook) ── */
    document.getElementById('contactForm')?.addEventListener('submit', function(e) {
        // If you want to wire up AJAX, prevent default here
        // e.preventDefault();
    });
</script>
@endpush
