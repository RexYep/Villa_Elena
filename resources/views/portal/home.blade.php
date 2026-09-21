@extends('layouts.portal')

@section('title', $resortName . ' — Private Pool Resort in Pansol, Calamba')
@section('bare')@endsection

@push('styles')
    <style>
        :root {
            --nav-h: 80px;
        }

        html {
            scroll-behavior: smooth;
        }

        /* The nav is fixed, so an anchor jump parks the section heading
           underneath it. Every in-page link and the availability check's
           scroll land below the bar instead. */
        section[id],
        div[id] {
            scroll-margin-top: calc(var(--nav-h) + 12px);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--stone);
            line-height: 1.6;
        }

        /* ════════════════════════════════════════
                                               NAVIGATION
                                            ════════════════════════════════════════ */
        /* portal.css styles a different `.nav` (the public shell) as a flex row
           with 40px side padding. Inherited here, it made .nav-inner a
           content-sized flex item inside a second 40px gutter: 80px lost per
           side, so at 960px the nav was 994px wide and Sign Up / Sign Out ran
           off the right edge. display/padding reset so .nav-inner is the only
           layout box, as this page intends. */
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--nav-h);
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: none;
            backdrop-filter: none;
            display: block;
            padding: 0;
        }

        .nav.scrolled {
            background: rgba(44, 36, 22, 0.98);
            backdrop-filter: blur(16px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
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

        /* Desktop nav links. Scoped under .nav because portal.css's ≤900px
           rule for the customer topnav also matches .nav-links — position:fixed,
           column, dark panel — which showed this list as a permanently open
           dropdown between 769 and 900px. */
        .nav .nav-links {
            display: flex;
            position: static;
            flex-direction: row;
            align-items: center;
            gap: 4px;
            list-style: none;
            margin: 0;
            padding: 0;
            background: none;
            box-shadow: none;
            max-height: none;
            overflow: visible;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.75);
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
            background: rgba(255, 255, 255, 0.1);
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
            color: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: transparent;
        }

        .nav-btn-ghost:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.7);
            background: rgba(255, 255, 255, 0.08);
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

        .hamburger.open span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }

        .hamburger.open span:nth-child(2) {
            opacity: 0;
            transform: translateX(-8px);
        }

        .hamburger.open span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
        }

        /* Mobile menu */
        .mobile-menu {
            display: none;
            position: fixed;
            top: var(--nav-h);
            left: 0;
            right: 0;
            background: rgba(44, 36, 22, 0.98);
            backdrop-filter: blur(20px);
            padding: 20px 24px 28px;
            z-index: 999;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .mobile-menu.open {
            display: block;
        }

        .mobile-menu a {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            padding: 14px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            transition: color 0.2s;
        }

        .mobile-menu a:hover {
            color: var(--gold-light);
        }

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

        /* Sign Out is a POST form, so it gets the same pill as the links. */
        .mobile-menu .mobile-auth form {
            flex: 1;
            margin: 0;
        }

        .mobile-menu .mobile-auth button {
            width: 100%;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
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
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            animation: pulse 15s infinite ease-in-out;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.7;
            }

            50% {
                opacity: 1;
            }
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

        .hero-promo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 20px 9px 14px;
            margin-bottom: 22px;
            border-radius: 999px;
            background: rgba(212, 170, 90, 0.15);
            border: 1px solid rgba(212, 170, 90, 0.45);
            color: var(--gold-light);
            font-size: 13.5px;
            letter-spacing: .3px;
            backdrop-filter: blur(6px);
        }

        .hero-promo .promo-amount {
            background: var(--gold);
            color: var(--stone);
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .5px;
            padding: 3px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .promo-band {
            background: linear-gradient(135deg, #2c2416 0%, #4a3d2a 100%);
            padding: 34px 20px;
        }

        .promo-band-inner {
            max-width: 1180px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: stretch;
            justify-content: center;
        }

        .promo-card {
            flex: 1 1 300px;
            max-width: 420px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 20px 24px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(212, 170, 90, 0.3);
        }

        .promo-card .promo-badge {
            flex-shrink: 0;
            background: var(--gold);
            color: var(--stone);
            font-family: 'Playfair Display', serif;
            font-size: 19px;
            font-weight: 700;
            line-height: 1;
            padding: 12px 14px;
            border-radius: 11px;
            text-align: center;
            white-space: nowrap;
        }

        .promo-card h4 {
            font-family: 'Playfair Display', serif;
            font-size: 21px;
            color: #fff;
            margin: 0 0 5px;
        }

        .promo-card p {
            color: rgba(255, 255, 255, 0.72);
            font-size: 13.5px;
            line-height: 1.55;
            margin: 0 0 7px;
            font-weight: 300;
        }

        .promo-card .promo-meta {
            color: var(--gold-light);
            font-size: 11.5px;
            letter-spacing: .6px;
            text-transform: uppercase;
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
            color: rgba(255, 255, 255, 0.75);
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
            box-shadow: 0 12px 30px rgba(184, 148, 63, 0.4);
        }

        .btn-hero-ghost {
            border: 1.5px solid rgba(255, 255, 255, 0.45);
            color: #fff;
            padding: 15px 36px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-hero-ghost:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.8);
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
            font-size: 14px;
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
        #properties {
            background: var(--cream);
        }

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
            top: 18px;
            left: 18px;
            background: rgba(44, 36, 22, 0.8);
            color: #fff;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 0.5px;
            backdrop-filter: blur(8px);
        }

        .prop-status-badge {
            position: absolute;
            top: 18px;
            right: 18px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-available {
            background: #d1fae5;
            color: #10b981;
        }

        .status-occupied {
            background: #fee2e2;
            color: #ef4444;
        }

        .prop-body {
            padding: 26px 28px 28px;
        }

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

        .prop-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .prop-amenities {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .amenity-tag {
            background: var(--sand);
            color: var(--muted);
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 13px;
            font-family: 'Jost', sans-serif;
            letter-spacing: .3px;
            font-weight: 500;
            text-align: center;
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

        .prop-price-night {
            font-size: 13px;
            color: var(--muted);
        }

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

        .villa-showcase-img-wrap {
            position: relative;
            min-height: 420px;
            background: var(--sand);
        }

        .villa-showcase-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            inset: 0;
        }

        .villa-showcase-badge {
            position: absolute;
            top: 22px;
            left: 22px;
            background: rgba(44, 36, 22, 0.85);
            color: #fff;
            padding: 7px 18px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: .5px;
            backdrop-filter: blur(8px);
        }

        .villa-showcase-body {
            padding: 44px 44px 40px;
            display: flex;
            flex-direction: column;
        }

        .villa-showcase-name {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 12px;
        }

        .villa-showcase-desc {
            font-size: 14.5px;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .villa-showcase-meta {
            display: flex;
            gap: 20px;
            font-size: 13.5px;
            color: var(--muted);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .villa-showcase-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .villa-showcase-amenities {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 24px;
        }

        /* Rooms status strip */
        .rooms-status-strip {
            background: var(--sand);
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 26px;
        }

        .rooms-status-title {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .rooms-status-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .room-pill {
            font-size: 12.5px;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fff;
            border: 1px solid var(--border);
        }

        .room-pill .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-available {
            background: #10b981;
        }

        .dot-occupied {
            background: #f59e0b;
        }

        .dot-maintenance {
            background: #ef4444;
        }

        .villa-showcase-footer {
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .villa-showcase-price {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 700;
        }

        .villa-showcase-price span {
            font-size: 14px;
            font-weight: 400;
            color: var(--muted);
            font-family: 'Jost', sans-serif;
        }

        .btn-book-showcase {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 16px 32px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all .3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-book-showcase:hover {
            background: var(--gold);
            color: var(--stone);
            transform: translateY(-2px);
        }

        .btn-book-showcase.unavailable {
            background: var(--border);
            color: var(--muted);
            cursor: not-allowed;
            pointer-events: none;
        }

        @media (max-width: 900px) {
            .villa-showcase {
                grid-template-columns: 1fr;
            }

            .villa-showcase-img-wrap {
                min-height: 280px;
            }

            .villa-showcase-body {
                padding: 32px 26px;
            }

            .villa-showcase-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
            }

            .btn-book-showcase {
                justify-content: center;
            }
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

        #amenities .section-eyebrow {
            color: var(--gold-light);
        }

        #amenities .section-title {
            color: #fff;
        }

        #amenities .section-desc {
            color: rgba(255, 255, 255, 0.55);
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-top: 52px;
        }

        .amenity-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 36px 28px;
            text-align: center;
            transition: all 0.35s ease;
        }

        .amenity-card:hover {
            background: rgba(184, 148, 63, 0.12);
            border-color: rgba(184, 148, 63, 0.35);
            transform: translateY(-6px);
        }

        .amenity-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(184, 148, 63, 0.15);
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
            color: rgba(255, 255, 255, 0.45);
            line-height: 1.5;
        }

        /* ════════════════════════════════════════
                                               ROOM TOUR SECTION
                                            ════════════════════════════════════════ */
        #room-tour {
            background: var(--cream);
            padding: 0;
        }

        #room-tour .section {
            padding: 100px 40px;
        }

        .room-tour-note {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(196, 103, 58, 0.07);
            border: 1px solid rgba(196, 103, 58, 0.18);
            border-radius: 14px;
            padding: 14px 20px;
            margin: 8px 0 44px;
            font-size: 14px;
            color: var(--stone);
        }

        .room-tour-note i {
            color: var(--terracotta);
            font-size: 18px;
            flex-shrink: 0;
        }

        .room-tour-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 28px;
        }

        .room-card {
            background: var(--sand);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border);
        }

        .room-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(44, 36, 22, 0.12);
        }

        .room-card-img-wrap {
            position: relative;
            aspect-ratio: 4/3;
            overflow: hidden;
            background: var(--border);
        }

        .room-card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.6s ease;
        }

        .room-card:hover .room-card-img-wrap img {
            transform: scale(1.08);
        }

        .room-card-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 44px;
            color: #c9bfa8;
            background: var(--border);
        }

        .room-card-status {
            position: absolute;
            top: 14px;
            right: 14px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: .3px;
            padding: 6px 13px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(6px);
            color: #fff;
        }

        .room-card-status .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #fff;
        }

        .status-available {
            background: rgba(16, 185, 129, 0.85);
        }

        .status-occupied {
            background: rgba(245, 158, 11, 0.85);
        }

        .status-maintenance {
            background: rgba(239, 68, 68, 0.85);
        }

        .room-card-body {
            padding: 20px 22px 24px;
        }

        .room-card-name {
            font-family: 'Playfair Display', serif;
            font-size: 19px;
            font-weight: 600;
            color: var(--stone);
            margin-bottom: 4px;
        }

        .room-card-meta {
            font-size: 13px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 600px) {
            .room-tour-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 18px;
            }
        }

        /* ════════════════════════════════════════
                                               GALLERY SECTION
                                            ════════════════════════════════════════ */
        #gallery {
            background: var(--sand);
            padding: 0;
        }

        #gallery .section {
            padding: 80px 40px;
        }

        /* Prev/next live in the header row, level with the heading, so the
           photo itself stays clear of controls. */
        .gallery-controls {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
        }

        .gallery-nav {
            width: 46px;
            height: 46px;
            border: none;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.25s ease, transform 0.25s ease;
        }

        .gallery-nav.prev {
            background: var(--cream);
            color: var(--stone);
            box-shadow: 0 12px 24px -14px rgba(44, 36, 22, 0.5);
        }

        .gallery-nav.prev:hover {
            background: #fff;
            transform: translateY(-2px);
        }

        .gallery-nav.next {
            background: var(--gold);
            color: #fff;
            box-shadow: 0 12px 24px -14px rgba(184, 148, 63, 0.9);
        }

        .gallery-nav.next:hover {
            background: var(--gold-light);
            transform: translateY(-2px);
        }

        /* One photo at a time, auto-advancing slideshow */
        .gallery-slideshow {
            position: relative;
            aspect-ratio: 16 / 9;
            /* Full-width 16:9 is ~675px tall, more than a laptop screen shows
               under the nav; cap it so photo, caption and dots fit at once. */
            max-height: 76vh;
            border-radius: 32px;
            overflow: hidden;
            background: var(--stone);
            box-shadow: 0 30px 50px -34px rgba(44, 36, 22, 0.6);
        }

        .gallery-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            cursor: zoom-in;
            transition: opacity 0.7s ease-out, visibility 0.7s ease-out;
        }

        .gallery-slide.active {
            opacity: 1;
            visibility: visible;
            z-index: 1;
        }

        /* Each photo settles in from a slight zoom while it is on screen.
           4.5s against the 5s interval, so it finishes before the next cut. */
        .gallery-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transform: scale(1.06);
            transition: transform 4.5s ease-out;
        }

        .gallery-slide.active img {
            transform: scale(1);
        }

        .gallery-overlay {
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
            display: flex;
            align-items: flex-end;
            padding: 32px;
            background: linear-gradient(to top,
                    rgba(44, 36, 22, 0.62) 0%,
                    rgba(44, 36, 22, 0.12) 45%,
                    transparent 70%);
        }

        .gallery-count {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            background: rgba(253, 251, 247, 0.85);
            color: var(--stone);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-variant-numeric: tabular-nums;
        }

        .gallery-caption {
            margin-top: 12px;
            font-family: 'Playfair Display', serif;
            font-size: clamp(22px, 2.6vw, 32px);
            font-weight: 600;
            line-height: 1.15;
            color: #fff;
        }

        .gallery-desc {
            margin: 6px 0 0;
            max-width: 44ch;
            font-size: 14.5px;
            font-weight: 300;
            line-height: 1.55;
            color: rgba(255, 255, 255, 0.86);
        }

        /* Dots sit under the frame; the current one stretches into a pill. */
        .gallery-dots {
            margin-top: 22px;
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .gallery-dot {
            width: 10px;
            height: 10px;
            padding: 0;
            border: none;
            border-radius: 999px;
            background: rgba(44, 36, 22, 0.15);
            cursor: pointer;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .gallery-dot:hover {
            background: rgba(44, 36, 22, 0.3);
        }

        .gallery-dot.active {
            width: 32px;
            background: var(--gold);
        }

        .gallery-nav:focus-visible,
        .gallery-dot:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 3px;
        }

        @media (max-width: 768px) {

            /* `#gallery .section` outranks the shared ≤768px `.section`
               rule, so the gallery kept its 40px desktop gutter on phones
               and the frame shrank to ~260px. */
            #gallery .section {
                padding: 64px 20px;
            }

            .gallery-slideshow {
                aspect-ratio: 16 / 10;
                border-radius: 24px;
            }

            .gallery-overlay {
                padding: 20px;
            }

            .gallery-desc {
                font-size: 13px;
            }
        }

        /* 16:10 at phone width leaves ~220px of height, too little for the
           pill, title and description together. */
        @media (max-width: 480px) {
            .gallery-slideshow {
                aspect-ratio: 4 / 3;
                border-radius: 20px;
            }

            .gallery-overlay {
                padding: 16px;
            }
        }

        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .lightbox.open {
            display: flex;
        }

        .lightbox-img {
            max-width: 90vw;
            max-height: 85vh;
            border-radius: 12px;
            object-fit: contain;
        }

        .lightbox-close {
            position: absolute;
            top: 24px;
            right: 24px;
            color: #fff;
            font-size: 32px;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.12);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .lightbox-close:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ════════════════════════════════════════
                                               ABOUT SECTION
                                            ════════════════════════════════════════ */
        #about {
            background: var(--cream);
            padding: 0;
        }

        #about .section {
            padding: 100px 40px;
        }

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
            top: 0;
            left: 0;
            width: 75%;
            height: 400px;
            border-radius: 24px;
            object-fit: cover;
            box-shadow: 0 30px 70px rgba(44, 36, 22, 0.18);
        }

        .about-img-accent {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 55%;
            height: 280px;
            border-radius: 20px;
            object-fit: cover;
            box-shadow: 0 20px 50px rgba(44, 36, 22, 0.15);
            border: 6px solid var(--cream);
        }

        .about-badge {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--gold);
            color: var(--stone);
            border-radius: 50%;
            width: 100px;
            height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            line-height: 1.3;
            box-shadow: 0 8px 30px rgba(184, 148, 63, 0.5);
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

        #testimonials .section {
            padding: 100px 40px;
        }

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
            box-shadow: 0 20px 50px rgba(44, 36, 22, 0.1);
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
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--gold);
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author-name {
            font-weight: 600;
            font-size: 15px;
            color: var(--stone);
        }

        .author-location {
            font-size: 14px;
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

        #location .section {
            padding: 100px 40px;
        }

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
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(184, 148, 63, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--gold);
            flex-shrink: 0;
        }

        .location-item-label {
            font-size: 14px;
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

        .location-directions:hover {
            gap: 12px;
        }

        .map-wrap {
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(44, 36, 22, 0.12);
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

        #contact .section {
            padding: 100px 40px;
        }

        #contact .section-eyebrow {
            color: var(--gold-light);
        }

        #contact .section-title {
            color: #fff;
        }

        #contact .section-desc {
            color: rgba(255, 255, 255, 0.5);
        }

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
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(184, 148, 63, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--gold-light);
            flex-shrink: 0;
        }

        .contact-item-label {
            font-size: 13px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 5px;
        }

        .contact-item-value {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.85);
        }

        .contact-item-value a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: color 0.2s;
        }

        .contact-item-value a:hover {
            color: var(--gold-light);
        }

        /* Contact Form */
        .contact-form {}

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 14px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.45);
            margin-bottom: 10px;
            display: block;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 14px;
            padding: 16px 20px;
            font-size: 15px;
            color: #fff;
            font-family: inherit;
            transition: all 0.3s ease;
            resize: none;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.25);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--gold);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 0 4px rgba(184, 148, 63, 0.12);
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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
            color: rgba(255, 255, 255, 0.35);
            font-size: 14px;
            line-height: 1.7;
            max-width: 260px;
            margin-bottom: 24px;
        }

        .footer-col-title {
            font-size: 14px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
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
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--gold-light);
        }

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
            color: rgba(255, 255, 255, 0.28);
        }

        .footer-legal {
            display: flex;
            gap: 24px;
        }

        .footer-legal a {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.28);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-legal a:hover {
            color: rgba(255, 255, 255, 0.6);
        }

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

        .reveal-delay-1 {
            transition-delay: 0.1s;
        }

        .reveal-delay-2 {
            transition-delay: 0.2s;
        }

        .reveal-delay-3 {
            transition-delay: 0.3s;
        }

        .reveal-delay-4 {
            transition-delay: 0.4s;
        }

        /* ════════════════════════════════════════
                                               HERO — BOOKING BAR & TRUST ROW
                                            ════════════════════════════════════════ */
        /* The hero now carries real content (promo, availability check, proof),
           so it grows past one screen instead of clipping. `svh` keeps mobile
           browsers from counting the collapsing address bar as usable height. */
        .hero {
            height: auto;
            min-height: 100vh;
            min-height: 100svh;
            padding: calc(var(--nav-h) + 44px) 20px 92px;
        }

        /* Photo comes from the villa's own primary image when there is one
           (--hero-photo, set inline on the section so it works with the
           Cloudinary disk too); the packaged shot is the fallback. */
        .hero::before {
            background: var(--hero-photo, url('/images/images8.jpg')) center/cover no-repeat;
            opacity: 0.42;
        }

        /* Scrim — the headline and the booking fields have to stay readable
           over whatever photo the admin uploads, which we can't vet. */
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
            background: linear-gradient(180deg,
                    rgba(20, 12, 5, 0.74) 0%,
                    rgba(20, 12, 5, 0.34) 36%,
                    rgba(20, 12, 5, 0.84) 100%);
        }

        .hero-content {
            max-width: 960px;
            width: 100%;
        }

        .hero-promo {
            text-decoration: none;
            transition: background 0.25s ease, border-color 0.25s ease, transform 0.25s ease;
        }

        .hero-promo:hover {
            background: rgba(212, 170, 90, 0.24);
            border-color: rgba(212, 170, 90, 0.75);
            transform: translateY(-2px);
        }

        .hero-sub {
            font-size: 19px;
            max-width: 620px;
            margin-bottom: 34px;
        }

        .hero-cta-row {
            margin-bottom: 30px;
        }

        /* ── Availability check ──────────────────────────────────────
           Submits back to the homepage route, which already filters the
           listing by checkin/slot/guests. */
        .book-bar {
            display: grid;
            /* minmax(0, …) matters: a bare `fr` track keeps an auto minimum,
               and the slot <select>'s longest option is wide enough that the
               three fields ate the whole row and squeezed the submit button
               to zero width. */
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.3fr) minmax(0, 0.7fr) auto;
            gap: 10px;
            align-items: end;
            width: 100%;
            max-width: 880px;
            margin: 0 auto;
            padding: 14px;
            border-radius: 20px;
            text-align: left;
            background: rgba(253, 251, 247, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.28);
        }

        .book-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .book-field label {
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: var(--gold-light);
        }

        .book-field input,
        .book-field select {
            width: 100%;
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            color: #fff;
            background: rgba(26, 16, 9, 0.55);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 12px 14px;
            appearance: none;
        }

        .book-field select option {
            color: var(--stone);
            background: #fff;
        }

        .book-field input:focus,
        .book-field select:focus {
            outline: 2px solid var(--gold-light);
            outline-offset: 1px;
            border-color: transparent;
        }

        /* The native picker glyph renders near-black, i.e. invisible here. */
        .book-field input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1) opacity(0.75);
            cursor: pointer;
        }

        /* Not `.btn-check` — that is Bootstrap's hidden toggle-input helper
           (position:absolute; clip:rect(0,0,0,0)), and Bootstrap is in the
           portal bundle, so the submit button vanished. */
        .btn-availability {
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            background: var(--stone);
            border-radius: 12px;
            padding: 13px 26px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.25s ease, transform 0.25s ease;
        }

        .btn-availability:hover {
            background: #453a24;
            transform: translateY(-2px);
        }

        .book-bar-note {
            grid-column: 1 / -1;
            margin: 2px 2px 0;
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.62);
        }

        .hero-trust {
            list-style: none;
            margin: 30px 0 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px 34px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.72);
        }

        .hero-trust li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hero-trust i {
            font-size: 15px;
            color: var(--gold-light);
        }

        .hero-trust strong {
            color: #fff;
            font-weight: 600;
        }

        .hero-scroll {
            position: absolute;
            bottom: 26px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            line-height: 1;
            font-size: 22px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.5);
            animation: heroScroll 2.4s ease-in-out infinite;
        }

        .hero-scroll:hover {
            color: var(--gold-light);
        }

        @keyframes heroScroll {

            0%,
            100% {
                transform: translate(-50%, 0);
            }

            50% {
                transform: translate(-50%, 7px);
            }
        }

        /* ════════════════════════════════════════
                                               PROMO BAND
                                            ════════════════════════════════════════ */
        .promo-card {
            text-decoration: none;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .promo-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.28);
            border-color: rgba(212, 170, 90, 0.65);
        }

        /* ════════════════════════════════════════
                                               HIGHLIGHTS STRIP
                                            ════════════════════════════════════════ */
        .highlights {
            background: var(--cream);
            border-bottom: 1px solid var(--border);
        }

        .highlights-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 46px 40px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .highlight {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 0 22px;
        }

        .highlight+.highlight {
            border-left: 1px solid var(--border);
        }

        .highlight i {
            font-size: 22px;
            line-height: 1.25;
            color: var(--gold);
            flex-shrink: 0;
        }

        .highlight-title {
            font-size: 15.5px;
            font-weight: 600;
            color: var(--stone);
            margin-bottom: 4px;
        }

        .highlight-text {
            font-size: 13.5px;
            line-height: 1.6;
            color: var(--muted);
        }

        /* ════════════════════════════════════════
                                               CLOSING BOOKING CTA
                                            ════════════════════════════════════════ */
        .final-cta {
            position: relative;
            background: var(--stone);
            overflow: hidden;
        }

        .final-cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('/images/night-view.jpg') center/cover no-repeat;
            opacity: 0.32;
        }

        .final-cta::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(105deg,
                    rgba(20, 12, 5, 0.92) 0%,
                    rgba(20, 12, 5, 0.62) 58%,
                    rgba(20, 12, 5, 0.38) 100%);
        }

        .final-cta-inner {
            position: relative;
            z-index: 2;
            max-width: 1280px;
            margin: 0 auto;
            padding: 96px 40px;
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 48px;
            align-items: center;
        }

        .final-cta-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(30px, 3.4vw, 44px);
            font-weight: 600;
            line-height: 1.15;
            color: #fff;
            margin-bottom: 16px;
        }

        .final-cta-text {
            max-width: 46ch;
            font-size: 16px;
            font-weight: 300;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.72);
        }

        .final-cta-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .final-cta-price {
            font-family: 'Playfair Display', serif;
            font-size: 14.5px;
            color: var(--gold-light);
        }

        .final-cta-price strong {
            display: block;
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }

        .btn-final {
            background: var(--gold);
            color: var(--stone);
            padding: 17px 40px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-final:hover {
            background: var(--gold-light);
            transform: translateY(-3px);
            box-shadow: 0 14px 34px rgba(184, 148, 63, 0.4);
        }

        .final-cta-call {
            font-size: 14.5px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.8);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .final-cta-call:hover {
            color: var(--gold-light);
        }

        /* ── Keyboard focus, everywhere a guest can act ── */
        .btn-hero-primary:focus-visible,
        .btn-hero-ghost:focus-visible,
        .btn-availability:focus-visible,
        .btn-final:focus-visible,
        .btn-book-showcase:focus-visible,
        .btn-see-more-reviews:focus-visible,
        .hero-promo:focus-visible,
        .promo-card:focus-visible,
        .hero-scroll:focus-visible,
        .final-cta-call:focus-visible {
            outline: 2px solid var(--gold-light);
            outline-offset: 3px;
        }

        /* ════════════════════════════════════════
                                               RESPONSIVE
                                            ════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .about-grid {
                grid-template-columns: 1fr;
                gap: 50px;
            }

            .about-img-stack {
                height: 380px;
            }

            .location-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .contact-grid {
                grid-template-columns: 1fr;
                gap: 50px;
            }

            .footer-top {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }

            .highlights-inner {
                grid-template-columns: repeat(2, 1fr);
                row-gap: 30px;
            }

            /* Items 1 and 3 start a row, so they lose the divider. */
            .highlight:nth-child(odd) {
                border-left: none;
            }

            .final-cta-inner {
                grid-template-columns: 1fr;
                gap: 34px;
                padding: 80px 32px;
            }
        }

        @media (max-width: 900px) {
            .book-bar {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            }

            .btn-availability {
                grid-column: 1 / -1;
                justify-content: center;
            }
        }

        /* The full nav needs 994px as a guest and 1034px signed in (My Bookings
           + Sign Out are wider than Sign In + Sign Up). Tighter gutters and link
           padding bring that to ~850 / ~890px, so a 960px Surface Pro keeps the
           full bar; below 940px it becomes the hamburger. */
        @media (max-width: 1100px) {
            .nav-inner {
                padding: 0 24px;
            }

            .nav .nav-links {
                gap: 2px;
            }

            .nav-links a {
                padding: 8px 10px;
            }

            .nav-right {
                gap: 8px;
            }

            .nav-btn {
                padding: 10px 18px;
            }
        }

        @media (max-width: 940px) {
            .nav .nav-links {
                display: none;
            }

            .hamburger {
                display: flex;
            }

            .nav-right .nav-btn,
            .nav-right form {
                display: none;
            }

            .nav-inner {
                padding: 0 20px;
            }
        }

        /* Opened on a narrow window, then widened past the breakpoint. */
        @media (min-width: 941px) {
            .mobile-menu.open {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .section {
                padding: 80px 20px 100px;
            }

            .properties-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .hero-title {
                font-size: clamp(42px, 9vw, 72px);
            }

            .amenities-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .about-stats {
                gap: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .footer-top {
                grid-template-columns: 1fr;
                gap: 36px;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }

            .hero-cta-row {
                flex-direction: column;
                align-items: center;
            }

            .hero {
                padding: calc(var(--nav-h) + 32px) 18px 64px;
            }

            .hero-sub {
                font-size: 16.5px;
                margin-bottom: 26px;
            }

            .hero-cta-row .btn-hero-primary,
            .hero-cta-row .btn-hero-ghost {
                width: 100%;
                max-width: 320px;
                text-align: center;
            }

            .hero-trust {
                gap: 10px 20px;
                font-size: 13px;
            }

            /* No room for it once the hero stacks, and it would sit on the
               booking bar rather than below it. */
            .hero-scroll {
                display: none;
            }

            .highlights-inner {
                grid-template-columns: 1fr;
                padding: 34px 20px;
                row-gap: 24px;
            }

            .highlight {
                padding: 0;
            }

            .highlight+.highlight {
                border-left: none;
            }

            .final-cta-inner {
                padding: 70px 20px;
            }

            .btn-final {
                width: 100%;
                justify-content: center;
            }

            .final-cta-actions {
                align-self: stretch;
            }
        }

        @media (max-width: 560px) {
            .book-bar {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 480px) {
            .amenities-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Motion here is decoration, never information — drop all of it when
           the guest has asked their OS for less. */
        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            .reveal {
                opacity: 1;
                transform: none;
                transition: none;
            }

            .hero-glow,
            .hero-scroll {
                animation: none;
            }

            .gallery-slide img,
            .gallery-slide.active img {
                transform: none;
            }

            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
            }
        }

        @media (hover: none) and (pointer: coarse) {
            .hamburger {
                min-width: 44px;
                min-height: 44px;
                align-items: center;
                justify-content: center;
            }

            .gallery-nav,
            .gallery-nav.prev,
            .gallery-nav.next {
                width: 44px;
                height: 44px;
            }

            .gallery-dot {
                position: relative;
            }

            .gallery-dot::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 44px;
                height: 44px;
                transform: translate(-50%, -50%);
            }
        }
    </style>
@endpush

@section('content')

    @php
        // $featuredVilla ay ang villa row na HINDI dumaan sa date filter, kaya
        // buo pa rin ang hero, highlights at closing CTA kahit booked na ang
        // petsang tiningnan ng bisita. Ang availability mismo ay ipinapakita
        // ng showcase sa ibaba, na $properties ang batayan.
        $heroPromo = $promos->first();
        $heroSlot = in_array(request('slot'), array_keys(\App\Models\Booking::SLOTS)) ? request('slot') : 'day';
        $heroPhoto = $featuredVilla?->primaryImage?->url;
        $heroBookUrl =
            $featuredVilla && $allowOnlineBooking
                ? route('portal.property', $featuredVilla) .
                    '?' .
                    http_build_query(array_filter([
                        'checkin' => request('checkin'),
                        'slot' => $heroSlot,
                        'guests' => request('guests'),
                    ]))
                : '#properties';
    @endphp

    {{-- ══════════════════════════════════
     NAVIGATION
══════════════════════════════════ --}}
    <nav class="nav" id="mainNav">
        <div class="nav-inner">
            <a href="{{ route('home') }}" class="nav-brand">Villa <em>Elena</em></a>

            <ul class="nav-links">
                <li><a href="#hero">Home</a></li>
                <li><a href="#properties">The Villa</a></li>
                <li><a href="#amenities">Amenities</a></li>
                <li><a href="#room-tour">Rooms</a></li>
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
        <a href="#amenities" class="mobile-link">Amenities</a>
        <a href="#room-tour" class="mobile-link">Rooms</a>
        <a href="#gallery" class="mobile-link">Gallery</a>
        <a href="#about" class="mobile-link">About Us</a>
        <a href="#contact" class="mobile-link">Contact</a>
        <div class="mobile-auth">
            @auth
                <a href="{{ route('customer.home') }}" class="nav-btn nav-btn-ghost text-center">My Bookings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-btn nav-btn-ghost">Sign Out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="nav-btn nav-btn-ghost text-center">Sign In</a>
                <a href="{{ route('register') }}" class="nav-btn nav-btn-gold text-center">Sign Up</a>
            @endauth
        </div>
    </div>

    {{-- ══════════════════════════════════
     HERO SECTION
══════════════════════════════════ --}}
    <section class="hero" id="hero" @if ($heroPhoto) style="--hero-photo:url('{{ $heroPhoto }}')" @endif>
        <div class="hero-bg-pattern"></div>
        <div class="hero-glow"></div>

        <div class="hero-content">
            @if ($heroPromo)
                <a href="#promos" class="hero-promo">
                    <span class="promo-amount">{{ $heroPromo->value_label }}</span>
                    <span>
                        {{ $heroPromo->label ?: 'Seasonal offer' }}@if ($heroPromo->expiry_date)
                            &middot; until {{ $heroPromo->expiry_date->format('M j') }}
                        @endif
                    </span>
                </a>
            @endif

            <h1 class="hero-title">Villa Elena<br><em>Private Pool Resort</em></h1>
            <p class="hero-sub">
                {{ $resortDesc ?: 'Book the whole villa in Pansol, Calamba — pool, rooms, kitchen and grill, yours alone for a day or a night.' }}
            </p>

            <div class="hero-cta-row">
                <a href="{{ $heroBookUrl }}" class="btn-hero-primary">
                    {{ $allowOnlineBooking ? 'Book the villa' : 'See rates' }}
                </a>
                <a href="#room-tour" class="btn-hero-ghost">Look inside</a>
            </div>

            {{-- Feeds the checkin/slot/guests filter the homepage already
                 applies; results land in the villa showcase below. --}}
            <form method="GET" action="{{ route('home') }}" class="book-bar" id="availabilityForm">
                <div class="book-field">
                    <label for="heroCheckin">Date</label>
                    <input type="date" id="heroCheckin" name="checkin" value="{{ request('checkin') }}"
                        min="{{ now()->toDateString() }}" required>
                </div>
                <div class="book-field">
                    <label for="heroSlot">Slot</label>
                    <select id="heroSlot" name="slot">
                        @foreach (\App\Models\Booking::SLOTS as $key => $slotInfo)
                            <option value="{{ $key }}" @selected($heroSlot === $key)>{{ $slotInfo['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="book-field">
                    <label for="heroGuests">Guests</label>
                    <select id="heroGuests" name="guests">
                        @for ($g = 1; $g <= 30; $g++)
                            <option value="{{ $g }}" {{ (int) request('guests', 1) === $g ? 'selected' : '' }}>
                                {{ $g }} {{ $g === 1 ? 'guest' : 'guests' }}
                            </option>
                        @endfor
                    </select>
                </div>
                <button type="submit" class="btn-availability">
                    <i class="bi bi-search"></i> Check the date
                </button>
                <p class="book-bar-note">One booking takes the whole villa, so a slot is either open or it isn't.</p>
            </form>

            <ul class="hero-trust">
                @if ($guestRating > 0)
                    <li><i class="bi bi-star-fill"></i> <strong>{{ number_format($guestRating, 1) }}</strong> guest
                        rating</li>
                @endif
                @if ($rooms->count())
                    <li><i class="bi bi-door-open-fill"></i> <strong>{{ $rooms->count() }}</strong> air-conditioned
                        rooms</li>
                @endif
                @if ($guestsServed > 0)
                    <li><i class="bi bi-people-fill"></i> <strong>{{ number_format($guestsServed) }}</strong> guests
                        hosted</li>
                @endif
                <li><i class="bi bi-shield-check"></i> No other groups on site</li>
            </ul>
        </div>

        <a href="#properties" class="hero-scroll" aria-label="Skip to the villa"><i class="bi bi-chevron-down"></i></a>
    </section>

    {{-- ══════════════════════════════════
     SEASONAL PROMOS
══════════════════════════════════ --}}
    @if ($promos->isNotEmpty())
        <section class="promo-band" id="promos">
            <div class="promo-band-inner">
                @foreach ($promos as $promo)
                    <a href="#properties" class="promo-card">
                        <div class="promo-badge">{{ $promo->value_label }}</div>
                        <div>
                            <h4>{{ $promo->label }}</h4>
                            @if ($promo->description)
                                <p>{{ $promo->description }}</p>
                            @endif
                            <div class="promo-meta">
                                @if ($promo->isUpcoming())
                                    For stays {{ $promo->start_date->format('M d') }}@if ($promo->expiry_date)
                                        – {{ $promo->expiry_date->format('M d, Y') }}
                                    @endif
                                @else
                                    {{ $promo->expiry_date ? 'Until ' . $promo->expiry_date->format('M d, Y') : 'Ongoing' }}
                                @endif
                                @if ($promo->applies_to !== 'all')
                                    &middot; {{ $promo->slot_label }}
                                @endif

                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ══════════════════════════════════
     QUICK HIGHLIGHTS
══════════════════════════════════ --}}
    <section class="highlights" aria-label="What a Villa Elena booking includes">
        <div class="highlights-inner">
            <div class="highlight">
                <i class="bi bi-house-lock-fill"></i>
                <div>
                    <div class="highlight-title">The whole villa</div>
                    <p class="highlight-text">One group at a time. The pool, rooms and kitchen are yours for the slot —
                        nobody else is booked in.</p>
                </div>
            </div>
            <div class="highlight">
                <i class="bi bi-clock-history"></i>
                <div>
                    <div class="highlight-title">Day or night slot</div>
                    {{-- Joined in PHP: a Blade loop leaves whitespace around the
                         separators, which shows up as "5:00 PM) , or". --}}
                    <p class="highlight-text">
                        {{ implode(', or ', array_column(\App\Models\Booking::SLOTS, 'label')) }}.
                    </p>
                </div>
            </div>
            <div class="highlight">
                <i class="bi bi-people-fill"></i>
                <div>
                    <div class="highlight-title">
                        {{ $featuredVilla?->max_capacity ? 'Room for ' . $featuredVilla->max_capacity : 'Built for groups' }}
                    </div>
                    <p class="highlight-text">Family days, barkada getaways and small celebrations, with parking on
                        site.</p>
                </div>
            </div>
            <div class="highlight">
                <i class="bi bi-{{ $allowOnlineBooking ? 'qr-code-scan' : 'chat-dots-fill' }}"></i>
                <div>
                    <div class="highlight-title">{{ $allowOnlineBooking ? 'Reserve online' : 'Reserve by message' }}
                    </div>
                    <p class="highlight-text">
                        {{ $allowOnlineBooking ? 'Pick a date, pay the deposit by QR Ph from any bank or e-wallet app, and the slot is held.' : 'Online booking is paused right now — call or message us and we will hold your date.' }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════
     VILLA SHOWCASE SECTION
══════════════════════════════════ --}}
    <section id="properties">
        <div class="section">
            <div class="section-header">
                <div>
                    <div class="section-eyebrow reveal">One Villa. One Group</div>
                    <div class="section-title reveal reveal-delay-1">Your Own Private Escape</div>
                    <p class="section-desc reveal reveal-delay-2">A private pool resort in Pansol, Calamba. Perfect for
                        family days, barkada getaways and small celebrations.</p>
                </div>
            </div>

            {{-- Active Filters --}}
            @if (request()->hasAny(['checkin', 'guests']) && request()->filled('checkin'))
                <div
                    style="background:rgba(184,148,63,0.08);border:1px solid rgba(184,148,63,0.2);border-radius:16px;padding:14px 20px;margin-bottom:40px;display:flex;align-items:center;gap:12px;font-size:14px;flex-wrap:wrap;">
                    <i class="bi bi-funnel-fill" style="color:var(--gold);"></i>
                    <span>Showing results for</span>
                    <strong>{{ \Carbon\Carbon::parse(request('checkin'))->format('M j, Y') }} ·
                        {{ request('slot') === 'night' ? 'Night (7PM–6AM)' : 'Day (8AM–5PM)' }}</strong>
                    @if (request('guests'))
                        <span>· {{ request('guests') }} guests</span>
                    @endif
                    <a href="{{ route('home') }}"
                        style="margin-left:auto;color:var(--terracotta);font-weight:500;text-decoration:none;">Clear all</a>
                </div>
            @endif

            @php $villa = $properties->first(); @endphp

            @if (!$villa)
                <div class="empty-state">
                    <i class="bi bi-house-slash"></i>
                    <p class="mb-12" style="font-size:20px;font-weight:500;color:var(--stone);">Villa Elena is not
                        available on the selected date.</p>
                    <p>Try a different date, or check the calendar on the Villa's page.</p>
                    <a href="{{ route('home') }}" style="color:var(--gold);margin-top:20px;display:inline-block;">Clear
                        the filter →</a>
                </div>
            @else
                @php
                    $amenities = is_array($villa->amenities)
                        ? $villa->amenities
                        : json_decode($villa->amenities ?? '[]', true);
                    $showAmenities = array_slice($amenities ?? [], 0, 6);
                    $checkin = request('checkin');
                    $guests = request('guests', 2);
                    $slot = in_array(request('slot'), array_keys(\App\Models\Booking::SLOTS)) ? request('slot') : 'day';
                @endphp

                <div class="villa-showcase reveal">
                    <div class="villa-showcase-img-wrap">
                        @if ($villa->primaryImage)
                            <img src="{{ $villa->primaryImage->url }}" class="villa-showcase-img"
                                alt="{{ $villa->property_name }}" loading="lazy" decoding="async">
                        @else
                            <div
                                style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:80px;color:#ddd;">
                                <i class="bi bi-house"></i>
                            </div>
                        @endif
                        <span class="villa-showcase-badge"><i class="bi bi-shield-check"></i> Exclusive Use</span>
                    </div>

                    <div class="villa-showcase-body">
                        <div class="villa-showcase-name">{{ $villa->property_name }}</div>
                        @if ($villa->description)
                            <p class="villa-showcase-desc">{{ $villa->description }}</p>
                        @endif
                        <div class="villa-showcase-meta">
                            <span><i class="bi bi-people-fill"></i> Up to {{ $villa->max_capacity }} guests</span>
                            @if ($villa->floor_area_sqm)
                                <span><i class="bi bi-arrows-angle-expand"></i> {{ $villa->floor_area_sqm }} m²</span>
                            @endif
                            @if ($rooms->count())
                                <span><i class="bi bi-door-open"></i> {{ $rooms->count() }} rooms</span>
                            @endif
                        </div>

                        @if (count($showAmenities))
                            <div class="villa-showcase-amenities">
                                @foreach ($showAmenities as $amenity)
                                    <span class="amenity-tag">{{ ucwords(str_replace('_', ' ', $amenity)) }}</span>
                                @endforeach
                                @if (count($amenities) > 6)
                                    <span class="amenity-tag">+{{ count($amenities) - 6 }} more</span>
                                @endif
                            </div>
                        @endif

                        <div class="villa-showcase-footer">
                            <div class="villa-showcase-price">
                                ₱{{ number_format($villa->base_price, 0) }} <span>/ package</span>
                                <div
                                    style="font-size:12px;font-family:'Jost',sans-serif;font-weight:400;margin-top:6px;line-height:1.7;color:var(--muted);">
                                    <div>&#x2022; <strong style="color:var(--stone);font-weight:600;">Regular:</strong>
                                        Mon–Thu &amp; Sun after 6PM</div>
                                    @if ($villa->weekend_price && $villa->weekend_price != $villa->base_price)
                                        <div>&#x2022; <strong style="color:var(--stone);font-weight:600;">Peak:</strong>
                                            ₱{{ number_format($villa->weekend_price, 0) }} &middot; Fri, Sat &amp; Sun
                                            before 6PM</div>
                                    @else
                                        <div>&#x2022; Same rate applies all week</div>
                                    @endif
                                </div>
                            </div>
                            @if ($villa->status !== 'maintenance' && $allowOnlineBooking)
                                <a href="{{ route('portal.property', $villa) }}?checkin={{ $checkin }}&slot={{ $slot }}&guests={{ $guests }}"
                                    class="btn-book-showcase">
                                    <i class="bi bi-calendar-check"></i>
                                    {{ $checkin ? 'Reserve Now' : 'Book Now' }}
                                </a>
                            @elseif (!$allowOnlineBooking)
                                <span class="btn-book-showcase unavailable">
                                    <i class="bi bi-telephone"></i> Contact Us to Book
                                </span>
                            @endif

                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ══════════════════════════════════
     AMENITIES SECTION
══════════════════════════════════ --}}
    <section id="amenities">
        <div class="section">
            <div class="section-header">
                <div>
                    <div class="section-eyebrow reveal">Villa Features</div>
                    <div class="section-title reveal reveal-delay-1">Everything is<br>Yours</div>
                    <p class="section-desc reveal reveal-delay-2" style="color:rgba(255,255,255,0.5);">From the private
                        pool to the kitchen and entertainment spaces, everything comes with your whole-villa booking.</p>
                </div>
            </div>

            <div class="amenities-grid">
                <div class="amenity-card reveal reveal-delay-1">
                    <div class="amenity-icon"><i class="bi bi-water"></i></div>
                    <div class="amenity-name">Swimming Pool</div>
                    <div class="amenity-desc">Adult and kiddie pools, exclusively yours for the whole booking</div>
                </div>
                <div class="amenity-card reveal reveal-delay-2">
                    <div class="amenity-icon"><i class="bi bi-wifi"></i></div>
                    <div class="amenity-name">WiFi</div>
                    <div class="amenity-desc">Free internet access throughout your stay</div>
                </div>
                <div class="amenity-card reveal reveal-delay-3">
                    <div class="amenity-icon"><i class="bi bi-snow2"></i></div>
                    <div class="amenity-name">All Air Conditioned Rooms</div>
                    <div class="amenity-desc">Every room in the villa is air-conditioned</div>
                </div>
                <div class="amenity-card reveal reveal-delay-1">
                    <div class="amenity-icon"><i class="bi bi-mic-fill"></i></div>
                    <div class="amenity-name">Videoke</div>
                    <div class="amenity-desc">Sing the night away on the in-house videoke</div>
                </div>
                <div class="amenity-card reveal reveal-delay-2">
                    <div class="amenity-icon"><i class="bi bi-fire"></i></div>
                    <div class="amenity-name">Griller Station</div>
                    <div class="amenity-desc">An outdoor grill ready for your barbecue</div>
                </div>
                <div class="amenity-card reveal reveal-delay-3">
                    <div class="amenity-icon"><i class="bi bi-thermometer-snow"></i></div>
                    <div class="amenity-name">Refrigerator</div>
                    <div class="amenity-desc">Keep your food and drinks cold for the whole stay</div>
                </div>
                <div class="amenity-card reveal reveal-delay-1">
                    <div class="amenity-icon"><i class="bi bi-egg-fried"></i></div>
                    <div class="amenity-name">Double Burner Gas Stove</div>
                    <div class="amenity-desc">A two-burner stove for cooking your own meals</div>
                </div>
                <div class="amenity-card reveal reveal-delay-2">
                    <div class="amenity-icon"><i class="bi bi-car-front-fill"></i></div>
                    <div class="amenity-name">Parking Area</div>
                    <div class="amenity-desc">On-site parking space for your vehicles</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════
     ROOM TOUR SECTION
══════════════════════════════════ --}}
    @if ($rooms->count())
        <section id="room-tour">
            <div class="section">
                <div class="section-header">
                    <div>
                        <div class="section-eyebrow reveal">Inside the Villa</div>
                        <div class="section-title reveal reveal-delay-1">Explore the<br>Rooms</div>
                        <p class="section-desc reveal reveal-delay-2">A closer look at each room inside Villa Elena — all
                            included in your one whole-villa booking.</p>
                    </div>
                </div>

                <div class="room-tour-grid">
                    @foreach ($rooms as $room)
                        @php
                            $roomImage = $room->images->first();
                        @endphp
                        <div class="room-card reveal">
                            <div class="room-card-img-wrap">
                                @if ($roomImage)
                                    <img src="{{ $roomImage->url }}"
                                        alt="{{ $room->property_name ?: 'Room at Villa Elena' }}" loading="lazy"
                                        decoding="async">
                                @else
                                    <div class="room-card-img-placeholder"><i class="bi bi-door-closed"></i></div>
                                @endif
                            </div>
                            <div class="room-card-body">
                                <div class="room-card-name">{{ $room->property_name }}</div>
                                @if ($room->floor_area_sqm)
                                    <div class="room-card-meta"><i class="bi bi-arrows-angle-expand"></i>
                                        {{ $room->floor_area_sqm }} m²</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════════════════════════════
     GALLERY SECTION
══════════════════════════════════ --}}
    <section id="gallery">
        <div class="section">
            <div class="section-header">
                <div>
                    <div class="section-eyebrow reveal">Visual Journey</div>
                    <div class="section-title reveal reveal-delay-1">Captured Moments</div>
                    <p class="section-desc reveal reveal-delay-2">A glimpse into the beauty that awaits you at Villa Elena.
                    </p>
                </div>
                <div class="gallery-controls">
                    <button type="button" class="gallery-nav prev" aria-controls="gallerySlideshow"
                        aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="gallery-nav next" aria-controls="gallerySlideshow"
                        aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>

            @php
                $galleryShots = [
                    [
                        'src' => 'images/view-12.jpg',
                        'alt' => 'Night view of the villa',
                        'caption' => 'Night View',
                        'desc' => 'The  outstanding view of resort at night .',
                    ],
                    [
                        'src' => 'images/view-inside.jpg',
                        'alt' => 'Inside view',
                        'caption' => 'Inside View',
                        'desc' => 'Shaded tables beside the pool, with a slide for the kids and the rooms a few steps away.',
                    ],
                    [
                        'src' => 'images/kitchen1.png',
                        'alt' => 'Kitchen area',
                        'caption' => 'Kitchen Area',
                        'desc' => 'A double sink, two-burner gas stove and refrigerator for cooking your own meals.',
                    ],
                    [
                        'src' => 'images/pool2.jpg',
                        'alt' => 'Resort pool view',
                        'caption' => 'Panoramic Resort Pool View',
                        'desc' => 'The main pool and its slide, with the raised kiddie pool at the far end.',
                    ],
                    [
                        'src' => 'images/terrace1.png',
                        'alt' => 'Terrace',
                        'caption' => 'Terrace',
                        'desc' => 'A covered sitting area with a wooden sofa set, out of the sun.',
                    ],
                    [
                        'src' => 'images/karaoke2.jpeg',
                        'alt' => 'Karaoke room',
                        'caption' => 'Karaoke',
                        'desc' => 'The in-house videoke, with speakers and a songbook ready to go.',
                    ],
                    [
                        'src' => 'images/images10.jpg',
                        'alt' => 'Dining area',
                        'caption' => 'Dining Area',
                        'desc' => 'One long table under the woven lamps, with seats for the whole group.',
                    ],
                ];
                $galleryTotal = str_pad(count($galleryShots), 2, '0', STR_PAD_LEFT);
            @endphp

            <div class="gallery-slideshow" id="gallerySlideshow" data-interval="5000">
                @foreach ($galleryShots as $i => $shot)
                    <div class="gallery-slide{{ $i === 0 ? ' active' : '' }}" onclick="openLightbox(this)">
                        <img src="{{ asset($shot['src']) }}" alt="{{ $shot['alt'] }}" loading="lazy"
                            decoding="async">
                        <div class="gallery-overlay">
                            <div>
                                <span class="gallery-count">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }} /
                                    {{ $galleryTotal }}</span>
                                <div class="gallery-caption">{{ $shot['caption'] }}</div>
                                <p class="gallery-desc">{{ $shot['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="gallery-dots">
                @foreach ($galleryShots as $i => $shot)
                    <button type="button" class="gallery-dot{{ $i === 0 ? ' active' : '' }}"
                        aria-controls="gallerySlideshow" aria-label="Show {{ $shot['caption'] }}"
                        @if ($i === 0) aria-current="true" @endif></button>
                @endforeach
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
                    <img src="{{ asset('images/pool-view.jpg') }}" class="about-img-main"
                        alt="The pool area at Villa Elena" loading="lazy" decoding="async">
                    <img src="{{ asset('images/night-view.jpg') }}" class="about-img-accent" alt="Villa Elena at night"
                        loading="lazy" decoding="async">
                    <div class="about-badge">
                        <strong>6+</strong>
                        Years of<br>Excellence
                    </div>
                </div>

                <div class="about-text">
                    <div class="section-eyebrow reveal">Our Story</div>
                    <div class="section-title reveal reveal-delay-1">The Whole Villa,<br>Just for You</div>
                    <p class="reveal reveal-delay-2 text-muted-theme"
                        style="font-size:16px;line-height:1.8;font-weight:300;margin-bottom:18px;">
                        Villa Elena Private Pool Resort is a place in Pansol, Calamba made for families and groups of
                        friends who want to enjoy their time together in a space they can call their own. When you book
                        Villa Elena, the resort is yours for your stay.
                    </p>
                    <p class="reveal reveal-delay-3 text-muted-theme"
                        style="font-size:16px;line-height:1.8;font-weight:300;">
                        You have your own pool, rooms, kitchen, griller and shared spaces — giving your group the freedom to
                        swim, eat, relax and spend time together without sharing the place with other guests. Whether it is
                        a family getaway, a barkada weekend or a simple celebration, Villa Elena gives you the space to make
                        the most of your time together.
                    </p>

                    <div class="about-stats reveal reveal-delay-4">
                        <div class="stat-item">
                            <div class="stat-num">{{ $rooms->count() }}</div>
                            <div class="stat-label">Private Rooms</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-num">{{ $guestRating > 0 ? number_format($guestRating, 1) . '★' : 'New' }}
                            </div>
                            <div class="stat-label">Guest Rating</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-num">{{ number_format($guestsServed) }}</div>
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
                <p style="font-size:16px;max-width:500px;margin:14px auto 0;font-weight:300;line-height:1.7;"
                    class="reveal reveal-delay-2 text-muted-theme">
                    Authentic reviews from guests who've experienced the Villa Elena difference.
                </p>
            </div>

            <div class="testimonials-grid">
                @forelse($reviews as $review)
                    <div class="testimonial-card reveal reveal-delay-{{ $loop->iteration }}">
                        <div class="testimonial-quote">"</div>
                        <div class="testimonial-stars">
                            {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                        <p class="testimonial-text">
                            "{{ \Illuminate\Support\Str::limit($review->content, 180) }}"
                        </p>
                        <div class="testimonial-author">
                            <div class="author-avatar">
                                @if ($review->user?->profile_image_url)
                                    <img src="{{ $review->user->profile_image_url }}"
                                        alt="Photo of {{ $review->user->full_name ?? 'the guest' }}" loading="lazy"
                                        decoding="async" width="46" height="46">
                                @else
                                    {{ strtoupper(substr($review->user->full_name ?? 'G', 0, 1)) }}
                                @endif
                            </div>
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
                            <a href="https://maps.app.goo.gl/sLsqnY5bL31FdFUV7" target="_blank"
                                class="location-directions">
                                Get Directions <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="location-item reveal reveal-delay-2">
                        <div class="location-icon"><i class="bi bi-signpost-2-fill"></i></div>
                        <div>
                            <div class="location-item-label">Getting Here</div>
                            <div class="location-item-value">Exit SLEX at Calamba, then head toward Los Baños<br>along the
                                Pansol stretch of the national highway.</div>
                        </div>
                    </div>

                    <div class="location-item reveal reveal-delay-3">
                        <div class="location-icon"><i class="bi bi-clock-fill"></i></div>
                        <div>
                            <div class="location-item-label">Booking Slots</div>
                            <div class="location-item-value">
                                @foreach (\App\Models\Booking::SLOTS as $slotInfo)
                                    {{ $slotInfo['label'] }}@if (!$loop->last)
                                        <br>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="location-item reveal reveal-delay-4">
                        <div class="location-icon"><i class="bi bi-headset"></i></div>
                        <div>
                            <div class="location-item-label">Assistance</div>
                            <div class="location-item-value">Open every day<br>Call or message us for arrival
                                arrangements</div>
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
                    Whether you're planning a romantic escape or a grand celebration — our team is ready to craft your
                    perfect stay.
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
                                <a href="{{ $facebookUrl ?: '#' }}" target="_blank"
                                    rel="noopener">{{ $facebookUrl ? 'Visit our Facebook page' : 'Not yet set' }}</a>
                            </div>
                        </div>
                    </div>
                    <div class="contact-item reveal reveal-delay-4">
                        <div class="contact-icon"><i class="bi bi-tiktok"></i></div>
                        <div>
                            <div class="contact-item-label">TikTok</div>
                            <div class="contact-item-value">
                                <a href="{{ $tiktokUrl ?: '#' }}" target="_blank"
                                    rel="noopener">{{ $tiktokUrl ? 'Visit our Tiktok account' : 'Not yet set' }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Contact Form --}}
                <div class="contact-form reveal reveal-delay-2">
                    @if (session('contact_success'))
                        <div class="alert alert-success">{{ session('contact_success') }}</div>
                    @endif
                    @if (session('contact_error'))
                        <div class="alert alert-danger">{{ session('contact_error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif
                    <form method="POST" action="{{ route('portal.contact.send') }}" id="contactForm">
                        @csrf
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Your Name</label>
                                <input type="text" name="name" class="form-input" value="{{ old('name') }}"
                                    placeholder="Juan dela Cruz" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-input" value="{{ old('email') }}"
                                    placeholder="juan@example.com" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-input" value="{{ old('subject') }}"
                                placeholder="Booking inquiry, special request...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-input" rows="5"
                                placeholder="Tell us how we can help you plan the perfect getaway..." required minlength="10">{{ old('message') }}</textarea>
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
     CLOSING BOOKING CTA
══════════════════════════════════ --}}
    <section class="final-cta" id="book">
        <div class="final-cta-inner">
            <div>
                <h2 class="final-cta-title">Pick a date and the villa is yours</h2>
                <p class="final-cta-text">
                    One group books at a time, so the slot you take is off the calendar for everyone else. Choose a day
                    or a night, settle the deposit, and the rest of the place comes with it.
                </p>
            </div>

            <div class="final-cta-actions">
                @if ($featuredVilla)
                    <div class="final-cta-price">
                        <strong>₱{{ number_format($featuredVilla->base_price, 0) }}</strong>
                        per slot, Mon–Thu
                    </div>
                @endif

                @if ($allowOnlineBooking && $featuredVilla)
                    <a href="{{ $heroBookUrl }}" class="btn-final">
                        <i class="bi bi-calendar-check"></i> Book the villa
                    </a>
                @else
                    <a href="#contact" class="btn-final">
                        <i class="bi bi-chat-dots"></i> Message us to book
                    </a>
                @endif

                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $resortPhone) }}" class="final-cta-call">
                    <i class="bi bi-telephone-fill"></i> {{ $resortPhone }}
                </a>
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
                        A private pool resort in Pansol, Calamba — the whole villa exclusively yours, one group at a
                        time.<br> {{ $resortAddress }}.
                    </p>
                </div>

                {{-- Quick Links --}}
                <div>
                    <div class="footer-col-title">Explore</div>
                    <ul class="footer-links">
                        <li><a href="#hero">Home</a></li>
                        <li><a href="#properties">The Villa</a></li>
                        <li><a href="#amenities">Amenities</a></li>
                        <li><a href="#room-tour">Rooms</a></li>
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

                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-copy">
                    © {{ date('Y') }} Villa Elena Resort. All rights reserved.
                </div>
                <div class="footer-legal">
                    <a href="{{ route('portal.privacy') }}">Privacy Policy</a>
                    <a href="{{ route('portal.terms') }}">Terms of Service</a>
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
        }, {
            threshold: 0.12
        });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

        /* ── Gallery slideshow (one photo at a time) ──
           Prev/next sit in the section header and the dots under the frame,
           so they're looked up on the section, not inside the stage. */
        const gallerySlideshow = (function() {
            const section = document.getElementById('gallery');
            const stage = document.getElementById('gallerySlideshow');
            if (!section || !stage) return null;

            const slides = Array.from(stage.querySelectorAll('.gallery-slide'));
            const dots = Array.from(section.querySelectorAll('.gallery-dot'));
            const delay = parseInt(stage.dataset.interval, 10) || 5000;
            if (slides.length < 2) return null;

            let current = 0;
            let timer = null;
            let inView = true;

            function show(index) {
                const next = (index + slides.length) % slides.length;
                if (next === current) return;
                slides[current].classList.remove('active');
                dots[current].classList.remove('active');
                dots[current].removeAttribute('aria-current');
                current = next;
                slides[current].classList.add('active');
                dots[current].classList.add('active');
                dots[current].setAttribute('aria-current', 'true');
                play();
            }

            function stop() {
                clearTimeout(timer);
                timer = null;
            }

            function play() {
                stop();
                if (!inView) return;
                timer = setTimeout(() => show(current + 1), delay);
            }

            section.querySelector('.gallery-nav.prev').addEventListener('click', () => show(current - 1));
            section.querySelector('.gallery-nav.next').addEventListener('click', () => show(current + 1));
            dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));

            stage.addEventListener('mouseenter', stop);
            stage.addEventListener('mouseleave', play);
            document.addEventListener('visibilitychange', () => document.hidden ? stop() : play());

            // Only run while the gallery is actually on screen
            new IntersectionObserver(entries => {
                inView = entries[0].isIntersecting;
                inView ? play() : stop();
            }, {
                threshold: 0.25
            }).observe(stage);

            play();
            return {
                play,
                stop
            };
        })();

        /* ── Gallery lightbox ── */
        function openLightbox(item) {
            gallerySlideshow?.stop();
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
            gallerySlideshow?.play();
        }
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeLightbox();
        });

        /* ── Availability check ──
           The form is a plain GET back to the homepage, so the answer arrives
           as a fresh page. Land the guest on the result instead of the hero
           they just submitted from. A GET form drops the fragment from its
           action, so there is no native anchor to ride; scroll-margin-top on
           the section keeps the heading clear of the fixed nav either way.
           Instant, not smooth: a smooth scroll started at load gets cancelled
           by the lazy images settling and stops partway down the hero. */
        @if (request()->filled('checkin'))
            window.addEventListener('load', () => {
                requestAnimationFrame(() => {
                    document.getElementById('properties')
                        ?.scrollIntoView({ behavior: 'auto', block: 'start' });
                });
            });
        @endif
    </script>
@endpush

