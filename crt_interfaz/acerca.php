<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acerca del Teatro · Teatro Constitución</title>
    <link rel="icon" href="imagenes_teatro/nat.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">
    
    <style>
        body {
            background-image: url('imagenes_teatro/TeatroNocheSide.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #e8e8e8;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(10, 10, 12, 0.9);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .header-inner {
            width: 100%;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 12px 20px 12px 0;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 20px;
            text-decoration: none;
            color: #ffffff;
            margin-right: auto;
        }
        .brand-logo {
            width: 50px;
            height: 50px;
            gap: 20px;
            margin-left: 20px;
            border-radius: 7px;
            background: none;
            display: grid;
            place-items: center;
            font-weight: 800;
            letter-spacing: 0.1px;
        }
        .brand-logo .logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .brand-name {
            font-weight: 600;
            font-size: 1.55rem;
        }
        .site-nav {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-left: auto;
        }
        .site-nav a {
            color: #e8e8e8;
            text-decoration: none;
            font-weight: 600;
            transition: color .2s ease, transform .2s ease;
        }
        .site-nav a:not(.cta) {
            padding: 8px 10px;
            border-radius: 10px;
            transition: color .2s ease, transform .2s ease, background-color .2s ease, box-shadow .2s ease;
        }
        .site-nav a:not(.cta):hover,
        .site-nav a:not(.cta).active {
            color: #ffffff;
            transform: translateY(-1px);
            background: rgba(148, 163, 184, 0.25);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.6);
        }
        .cta {
            margin-left: 6px;
            padding: 10px 14px;
            border-radius: 8px;
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: #fff !important;
            font-weight: 700;
            box-shadow: 0 8px 18px rgba(15,23,42,.55);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
            border: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(15,23,42,.75);
            filter: brightness(1.03);
        }
        .hamburger {
            display: none;
            background: transparent;
            color: #e8e8e8;
            border: 1px solid rgba(255,255,255,0.15);
            width: 42px;
            height: 42px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            z-index: 1103;
        }
        .nav-backdrop { display: none; }

        @media (min-width: 1101px) {
            .site-nav {
                position: fixed;
                top: 0;
                right: 0;
                left: 0;
                height: 74px;
                z-index: 1001;
                justify-content: flex-end;
                align-items: center;
                padding: 0 24px 0 180px;
                margin: 0;
                background: transparent;
                pointer-events: none;
                box-sizing: border-box;
            }
            .site-nav a { pointer-events: auto; }
            .hamburger { display: none !important; }
        }

        @media (max-width: 1100px) {
            body { background-attachment: scroll; }
            .header-inner { padding: 10px 14px; gap: 10px; }
            .brand { gap: 10px; margin-right: 0; min-width: 0; flex: 1; }
            .brand-logo { width: 40px; height: 40px; margin-left: 0; }
            .brand-name {
                font-size: 0.95rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            #mainNav.site-nav {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: auto;
                width: min(86vw, 320px);
                max-width: 100%;
                height: 100dvh;
                margin: 0;
                display: flex !important;
                flex-direction: column !important;
                flex-wrap: nowrap !important;
                align-items: stretch !important;
                justify-content: flex-start;
                padding: calc(64px + env(safe-area-inset-top, 0px)) 20px 24px;
                gap: 6px;
                background: rgba(8, 12, 22, 0.98);
                transform: translateX(105%);
                transition: transform .28s ease;
                z-index: 1102;
                box-shadow: -12px 0 40px rgba(0, 0, 0, 0.45);
                border-left: 1px solid rgba(255, 255, 255, 0.08);
                overflow-y: auto;
                pointer-events: auto;
            }
            #mainNav.site-nav.open { transform: translateX(0); }
            #mainNav.site-nav a:not(.cta) {
                display: block;
                width: 100%;
                padding: 14px 12px;
                font-size: 1.05rem;
                box-sizing: border-box;
            }
            #mainNav.site-nav .cta { display: none !important; }
            .hamburger { display: inline-flex; margin-left: auto; }
            .nav-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.55);
                z-index: 1101;
            }
            .nav-backdrop.show { display: block; }
            .main-content { padding: 24px 16px 40px; }
        }

        /* Contenido principal */
        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 44px 28px 64px;
        }

        .hero-section {
            text-align: center;
            margin-bottom: 36px;
            /* Solo mover: animar opacity en el padre rompe backdrop-filter del glass card */
            animation: fadeInUpSoft 0.8s ease;
        }

        .hero-section h1 {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 14px;
            color: #ffffff;
            text-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.03em;
        }

        .hero-section p {
            font-size: 1.02rem;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.9);
            max-width: 980px;
            margin: 0 auto;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Tarjetas de información */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                inset 0 1px 1px rgba(255, 255, 255, 0.5),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.8s ease;
            border: 1px solid rgba(255, 255, 255, 0.25);
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
        }

        .info-card:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 
                0 16px 48px rgba(0, 0, 0, 0.15),
                inset 0 1px 1px rgba(255, 255, 255, 0.6),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            border-color: rgba(255, 255, 255, 0.35);
        }

        .info-card-icon {
            font-size: 1.65rem;
            color: #ffffff;
            margin-bottom: 12px;
            opacity: 0.95;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .info-card h3 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #ffffff;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .info-card p {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
            margin-bottom: 0;
            font-size: 0.9rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Sección destacada */
        .featured-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 18px;
            padding: 40px 42px;
            margin-bottom: 32px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            animation: fadeInUp 0.8s ease;
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.12),
                inset 0 1px 1px rgba(255, 255, 255, 0.5),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        /* El contenedor principal del hero: visible de inmediato, sin pelear con opacity */
        .hero-section .featured-section {
            animation: none;
            opacity: 1;
            transform: none;
            text-align: left;
            max-width: 100%;
        }

        .hero-section .featured-section h1 {
            font-size: 1.85rem;
            text-align: center;
            margin-bottom: 14px;
        }

        .hero-section .featured-section p {
            font-size: 0.95rem;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 12px;
            max-width: none;
            text-shadow: none;
        }

        .hero-section .featured-section p:last-child {
            margin-bottom: 0;
        }

        .featured-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
        }

        .featured-section h2 {
            font-size: 1.65rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #ffffff;
            text-align: center;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        }

        .featured-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            align-items: start;
        }

        .featured-text {
            color: #cfd3d7;
            line-height: 1.65;
            font-size: 0.92rem;
        }

        .featured-text ul {
            list-style: none;
            padding: 0;
        }

        .featured-text ul li {
            padding: 6px 0;
            padding-left: 26px;
            position: relative;
        }

        .featured-text ul li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #9ca3af;
            font-weight: bold;
        }

        .valores-section {
            margin-bottom: 32px;
            text-align: center;
        }

        .valores-section > h2 {
            margin: 0 0 8px;
            color: #fff;
            font-size: clamp(1.85rem, 3.5vw, 2.5rem);
            text-shadow: 0 4px 20px rgba(0,0,0,.4);
        }

        .valores-carousel {
            position: relative;
            margin-top: 20px;
            width: 100%;
            max-width: none;
        }

        .valores-viewport {
            position: relative;
            min-height: 200px;
        }

        .valor-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(12px);
            transition: opacity .55s ease, transform .55s ease, visibility .55s;
            pointer-events: none;
            text-align: center;
            padding: 40px 42px;
            animation: none !important;
        }

        .valor-slide.is-active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
            position: relative;
        }

        .valor-slide .info-card-icon {
            font-size: 2.1rem;
            margin-bottom: 16px;
        }

        .valor-slide h3 {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-size: clamp(1.45rem, 2.8vw, 1.85rem);
            font-weight: 600;
            margin-bottom: 12px;
        }

        .valor-slide p {
            font-size: 1.05rem;
            line-height: 1.7;
            max-width: 780px;
            margin: 0 auto;
        }

        .valores-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-top: 22px;
        }

        .valores-nav {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.28);
            background: rgba(0,0,0,.35);
            color: #fff;
            display: grid;
            place-items: center;
            cursor: pointer;
            transition: transform .2s ease, border-color .2s ease, background .2s ease;
        }

        .valores-nav:hover {
            transform: translateY(-2px);
            border-color: rgba(255,255,255,.45);
            background: rgba(0,0,0,.45);
        }

        .valores-dots {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        .valores-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: none;
            padding: 0;
            background: rgba(255,255,255,.35);
            cursor: pointer;
            transition: transform .2s ease, background .2s ease, width .2s ease;
        }

        .valores-dot.is-active {
            background: #fff;
            width: 22px;
            border-radius: 999px;
        }

        @media (prefers-reduced-motion: reduce) {
            .valor-slide {
                transition: none;
            }
        }

        .featured-image {
            text-align: center;
        }

        .featured-image i {
            font-size: 5rem;
            color: #9ca3af;
            opacity: 0.7;
        }

        /* —— Rediseño visual Acerca (fotos reales) —— */
        .acerca-display {
            font-family: "Cormorant Garamond", Georgia, serif;
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        /* Intro grande (solo texto) */
        .acerca-lead {
            text-align: center;
            max-width: 1100px;
            margin: 0 auto;
            padding: 12px 12px 6px;
        }

        .acerca-lead h1 {
            font-size: clamp(2.35rem, 4.8vw, 3.4rem);
            margin: 0 0 26px;
            color: #fff;
            text-shadow: 0 6px 28px rgba(0,0,0,.5);
        }

        .acerca-lead p {
            color: #e4e7eb;
            font-size: clamp(1.05rem, 1.7vw, 1.18rem);
            line-height: 1.85;
            margin: 0 0 18px;
            text-align: left;
        }

        .acerca-lead p:last-child {
            margin-bottom: 0;
        }

        /* Galería: horizontal protagonista + 2 verticales */
        .story-band {
            margin: 36px 0 12px;
        }

        .story-band__head {
            text-align: center;
            margin-bottom: 22px;
        }

        .story-band__head h2 {
            font-size: clamp(1.75rem, 3.2vw, 2.35rem);
            margin: 0 0 10px;
            color: #fff;
            text-align: center;
        }

        .story-band__head p {
            margin: 0 auto;
            max-width: 780px;
            color: #cfd3d7;
            font-size: 1.02rem;
            line-height: 1.65;
        }

        .photo-gallery {
            display: grid;
            gap: 18px;
        }

        .photo-feature {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.22);
            box-shadow: 0 22px 56px rgba(0,0,0,.45);
            aspect-ratio: 21 / 9;
            min-height: 340px;
            background: #0b0d12;
        }

        .photo-feature img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            transition: transform 1s ease;
        }

        .photo-feature:hover img {
            transform: scale(1.03);
        }

        .photo-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .photo-portrait {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.2);
            box-shadow: 0 16px 40px rgba(0,0,0,.35);
            aspect-ratio: 3 / 4;
            min-height: 420px;
            background: #0b0d12;
        }

        .photo-portrait img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            display: block;
            transition: transform .9s ease;
        }

        .photo-portrait:hover img {
            transform: scale(1.05);
        }

        .photo-feature figcaption,
        .photo-portrait figcaption {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 32px 16px 14px;
            background: linear-gradient(180deg, transparent, rgba(0,0,0,.8));
            color: #fff;
            font-size: 0.9rem;
            font-weight: 650;
        }

        .photo-feature figcaption span,
        .photo-portrait figcaption span {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            opacity: .75;
            margin-bottom: 2px;
        }

        .mv-block h3 {
            font-size: 1.55rem;
            color: #fff;
            margin: 0 0 12px;
        }

        .mv-block p {
            color: #cfd3d7;
            font-size: 0.95rem;
            line-height: 1.7;
            margin: 0;
        }

        .reveal-up {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity .7s ease, transform .7s ease;
        }

        .reveal-up.is-in {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 900px) {
            .photo-pair {
                grid-template-columns: 1fr;
            }
            .photo-feature {
                aspect-ratio: 16 / 10;
                min-height: 200px;
            }
            .acerca-lead h1 {
                font-size: 1.85rem;
            }
            .acerca-lead p {
                font-size: 0.95rem;
            }
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding: 12px 0;
        }

        .timeline-item {
            display: flex;
            gap: 14px;
            margin-bottom: 18px;
            position: relative;
        }

        .timeline-year {
            min-width: 72px;
            font-weight: 700;
            font-size: 1.05rem;
            color: #e5e7eb;
        }

        .timeline-content {
            background: rgba(21, 25, 34, 0.6);
            padding: 14px 16px;
            border-radius: 8px;
            flex: 1;
            border-left: 3px solid #9ca3af;
        }

        .timeline-content h4 {
            color: #ffffff;
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .timeline-content p {
            color: #cfd3d7;
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.55;
        }

        /* Sección de ubicación */
        .location-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.06));
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 16px;
            padding: 28px 26px;
            margin-bottom: 28px;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.12),
                inset 0 1px 1px rgba(255, 255, 255, 0.5),
                inset 0 -1px 1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .location-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.7), transparent);
        }

        .location-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-top: 12px;
        }

        .location-info {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .location-info h3 {
            color: #ffffff;
            margin-bottom: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .location-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .location-item:last-child {
            border-bottom: none;
        }

        .location-item i {
            color: #ffffff;
            font-size: 1.1rem;
            margin-top: 3px;
            opacity: 0.9;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .location-item strong {
            color: #ffffff;
            font-weight: 600;
        }

        .map-placeholder {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
            backdrop-filter: blur(15px);
            border-radius: 12px;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.3);
        }

        /* Contenedor del mapa */
        .map-container {
            max-width: 100%;
            margin: 0 auto;
            border-radius: 18px;
            overflow: hidden;
            padding: 8px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.1),
                inset 0 1px 1px rgba(255, 255, 255, 0.4);
        }

        .map-container iframe {
            border-radius: 12px;
            display: block;
            filter: grayscale(0%) contrast(1.05) brightness(1.02);
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUpSoft {
            from {
                transform: translateY(18px);
            }
            to {
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                margin-bottom: 20px;
            }
            .hero-section h1 {
                font-size: 1.55rem;
            }
            .hero-section .featured-section h1 {
                font-size: 1.45rem;
            }
            .featured-section,
            .location-section {
                padding: 24px 18px;
            }
            .valor-slide,
            .info-card {
                padding: 22px 18px;
            }
            .featured-content,
            .location-grid {
                grid-template-columns: 1fr;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .site-footer {
                margin-top: 40px;
            }
        }

        /* Footer */
        .site-footer {
            background: rgba(10,10,12,0.95);
            border-top: 1px solid rgba(255,255,255,0.06);
            color: #cfd3d7;
            margin-top: 80px;
        }
        .footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 28px 28px;
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr 1fr;
            gap: 24px;
        }
        .footer-col h4 {
            color: #ffffff;
            font-size: 1rem;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .footer-col p, .footer-col li, .footer-col a, .footer-col span {
            font-size: 0.95rem;
            color: #cfd3d7;
        }
        .footer-links { list-style: none; padding: 0; margin: 0; }
        .footer-links li { margin: 8px 0; }
        .footer-links a { text-decoration: none; color: #cfd3d7; transition: color .2s ease; }
        .footer-links a:hover { color: #fff; }
        .social { display: flex; gap: 10px; margin-top: 8px; }
        .social a { width: 36px; height: 36px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: #e8e8e8; text-decoration: none; transition: all .2s ease; }
        .social a:hover { color: #fff; border-color: rgba(255,255,255,0.35); transform: translateY(-2px); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.06); padding: 14px 20px; color: #aeb4ba; font-size: 0.9rem; }
        .footer-bottom-inner { max-width: 1280px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .muted { color: #aeb4ba; }

        /* (Tema único) Se mantienen colores neutros/grises, sin cambio de fondo */
        @media (max-width: 1100px){
            .footer-inner { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px){
            .footer-inner { grid-template-columns: 1fr; }
            .footer-bottom-inner { flex-direction: column; align-items: flex-start; }
        }

        /* Celular landscape: hero y tarjetas compactas */
        @media (max-width: 1100px) and (orientation: landscape) and (max-height: 520px) {
            .header-inner { padding: 6px 12px; }
            .brand-logo { width: 32px; height: 32px; margin-left: 0; }
            .brand-name { font-size: 0.82rem; }
            .hamburger { width: 36px; height: 36px; }
            .main-content { padding: 14px 16px 24px; }
            .hero-section { margin-bottom: 12px; }
            .hero-section h1 { font-size: 1.3rem; margin-bottom: 6px; }
            .hero-section p { font-size: 0.85rem; line-height: 1.4; }
            .hero-section .featured-section h1 { font-size: 1.25rem; }
            .featured-section,
            .location-section,
            .info-card { padding: 14px 16px; }
            .site-footer { margin-top: 28px; }
            #mainNav.site-nav {
                width: min(70vw, 280px);
                padding-top: calc(52px + env(safe-area-inset-top, 0px));
            }
        }
    </style>
</head>
<body>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>
    <nav class="site-nav" id="mainNav" aria-label="Principal">
        <a href="index.php">Inicio</a>
        <a href="acerca.php" class="active">Acerca del teatro</a>
        <a href="contacto.php">Contacto / Reservaciones</a>
        <a href="cartelera_cliente.php" class="cta">Ver cartelera completa <i class="bi bi-arrow-right"></i></a>
    </nav>
    <header class="site-header">
        <div class="header-inner">
            <a href="index.php" class="brand" aria-label="Inicio">
                <div class="brand-logo"><img src="imagenes_teatro/nat.png" alt="Teatro Constitución" class="logo-img"></div>
                <div class="brand-name">Teatro Constitución · Apatzingan</div>
            </a>
            <button class="hamburger" id="hamburgerBtn" aria-label="Menú" aria-expanded="false" aria-controls="mainNav">
                <i class="bi bi-list" style="font-size:1.25rem"></i>
            </button>
        </div>
    </header>

    <main class="main-content">
        <!-- Intro grande: solo texto (primera impresión) -->
        <section class="hero-section">
            <div class="featured-section">
                <div class="acerca-lead">
                    <h1 class="acerca-display">Acerca del Teatro Constitución</h1>
                    <p>El Teatro Constitución de Apatzingán, inaugurado el 15 de noviembre de 2021, es un espacio cultural emblemático que nació con la misión de fortalecer la vida artística y comunitaria de la región. Desde su apertura, el Teatro se ha consolidado como un recinto dinámico y versátil, donde se han presentado y producido diversas puestas en escena, conciertos, obras teatrales, espectáculos de danza, exposiciones y actividades culturales que han enriquecido la oferta artística del municipio.</p>
                    <p>Además de albergar producciones locales, nacionales e internacionales, el Teatro Constitución ha sido sede de importantes festivales y encuentros culturales, convirtiéndose en un punto de referencia para la promoción del talento emergente y para la presentación de propuestas de gran calidad. Su programación diversa ha permitido acercar el arte a públicos de todas las edades, posicionándolo como uno de los espacios culturales más relevantes de la región de Apatzingán y sus alrededores.</p>
                </div>
            </div>
        </section>

        <!-- Galería: horizontal protagonista + 2 verticales (sin repetir) -->
        <section class="story-band reveal-up">
            <div class="story-band__head">
                <h2 class="acerca-display">En el escenario</h2>
                <p>Momentos de nuestras funciones: comedia, música y tradición.</p>
            </div>
            <div class="photo-gallery">
                <figure class="photo-feature">
                    <img src="imagenes_teatro/acerca-comedia.jpg" alt="Número cómico en el escenario del teatro" width="1200" height="675" loading="lazy">
                    <figcaption><span>Humor</span> Artes escénicas para todos</figcaption>
                </figure>
                <div class="photo-pair">
                    <figure class="photo-portrait">
                        <img src="imagenes_teatro/acerca-escena.jpg" alt="Artistas en escena con banda en vivo" width="600" height="800" loading="lazy">
                        <figcaption><span>Música</span> Conciertos y producciones</figcaption>
                    </figure>
                    <figure class="photo-portrait">
                        <img src="imagenes_teatro/acerca-catrina.jpg" alt="Interpretación de Catrina en el teatro" width="600" height="800" loading="lazy">
                        <figcaption><span>Identidad</span> Tradición y espectáculo</figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- Tarjetas informativas -->
        <div class="info-grid">
            <div class="info-card reveal-up">
                <div class="info-card-icon"><i class="bi bi-building"></i></div>
                <h3>¿Qué es?</h3>
                <p>Un espacio comprometido con el desarrollo cultural y social, el Teatro Constitución busca ser un puente entre la comunidad y las artes, ofreciendo eventos accesibles, formatos variados y de alto nivel que celebran la identidad, la creatividad y el espíritu de la Tierra Caliente.</p>
            </div>

            <div class="info-card reveal-up">
                <div class="info-card-icon"><i class="bi bi-people-fill"></i></div>
                <h3>Capacidad</h3>
                <p>El Teatro Constitución de Apatzingán cuenta con 420 asientos en el área de la sala de espectáculos, además cuenta con espacios para talleres, proyecciones y eventos comunitarios.</p>
            </div>

            <div class="info-card reveal-up">
                <div class="info-card-icon"><i class="bi bi-award-fill"></i></div>
                <h3>Infraestructura</h3>
                <p>Equipado con tecnología de audio y video de última generación, escenario profesional con maquinaria teatral moderna, camerinos amplios, sistema de aire acondicionado, accesibilidad para personas con movilidad reducida y estacionamiento cercano.</p>
            </div>
        </div>

        <!-- Misión y Visión (sin fotos por ahora; se agregarán después) -->
        <section class="featured-section reveal-up">
            <div class="featured-content">
                <div class="featured-text mv-block">
                    <h3 class="acerca-display">Misión</h3>
                    <p>Brindar a la comunidad de Apatzingán y la región un espacio cultural de excelencia, dedicado a la difusión de las artes escénicas y expresiones artísticas diversas, mediante una programación de calidad, infraestructura adecuada y un servicio profesional, que enriquezca la vida cultural, educativa y social de nuestros visitantes.</p>
                </div>
                <div class="featured-text mv-block">
                    <h3 class="acerca-display">Visión</h3>
                    <p>Consolidarnos como el recinto cultural más representativo de la región Tierra Caliente, reconocido por su calidad artística, su gestión profesional y su compromiso con el desarrollo cultural, convirtiéndonos en un referente estatal y nacional en la promoción del arte, la formación de públicos y el fortalecimiento de la identidad cultural.</p>
                </div>
            </div>
        </section>

        <!-- Valores -->
        <section class="valores-section reveal-up" id="valoresSection">
            <h2 class="acerca-display">Valores</h2>
            <div class="valores-carousel" data-valores-carousel>
                <div class="valores-viewport" aria-live="polite">
                    <article class="info-card valor-slide is-active" data-valor-slide>
                        <div class="info-card-icon"><i class="bi bi-palette-fill"></i></div>
                        <h3>Compromiso Cultural</h3>
                        <p>Fomentamos el acceso a la cultura y las artes como un derecho de todas las personas.</p>
                    </article>

                    <article class="info-card valor-slide" data-valor-slide>
                        <div class="info-card-icon"><i class="bi bi-briefcase-fill"></i></div>
                        <h3>Profesionalismo</h3>
                        <p>Actuamos con excelencia, responsabilidad y ética en cada área del teatro.</p>
                    </article>

                    <article class="info-card valor-slide" data-valor-slide>
                        <div class="info-card-icon"><i class="bi bi-people-fill"></i></div>
                        <h3>Inclusión</h3>
                        <p>Promovemos espacios seguros y accesibles para todas las personas, sin distinción.</p>
                    </article>

                    <article class="info-card valor-slide" data-valor-slide>
                        <div class="info-card-icon"><i class="bi bi-heart-fill"></i></div>
                        <h3>Respeto</h3>
                        <p>Valoramos a nuestro público, artistas, colaboradores y comunidad, manteniendo un ambiente digno y respetuoso.</p>
                    </article>

                    <article class="info-card valor-slide" data-valor-slide>
                        <div class="info-card-icon"><i class="bi bi-shield-check"></i></div>
                        <h3>Transparencia</h3>
                        <p>Operamos con claridad y honestidad en nuestras acciones, procesos y comunicación.</p>
                    </article>

                    <article class="info-card valor-slide" data-valor-slide>
                        <div class="info-card-icon"><i class="bi bi-lightbulb-fill"></i></div>
                        <h3>Innovación</h3>
                        <p>Impulsamos propuestas artísticas contemporáneas y buscamos mejorar continuamente nuestros servicios e instalaciones.</p>
                    </article>

                    <article class="info-card valor-slide" data-valor-slide>
                        <div class="info-card-icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <h3>Identidad y Comunidad</h3>
                        <p>Fortalecemos el sentido de pertenencia regional y promovemos la expresión cultural local.</p>
                    </article>
                </div>

                <div class="valores-controls">
                    <button type="button" class="valores-nav" data-valor-prev aria-label="Valor anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="valores-dots" data-valor-dots role="tablist" aria-label="Valores"></div>
                    <button type="button" class="valores-nav" data-valor-next aria-label="Valor siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </section>

        <!-- Sección de Ubicación -->
        <div class="featured-section">
            <h2 class="acerca-display">Nuestra Ubicación</h2>
            <div class="map-container" id="mapaUbicacion"
                 data-map-src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d4536.513467875127!2d-102.35703837479456!3d19.08093898212465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8431e18b7682ab11%3A0x363dfe9a4d8dd0c0!2sTeatro%20Constituci%C3%B3n%20de%20Apatzing%C3%A1n!5e0!3m2!1ses-419!2smx!4v1761836595548!5m2!1ses-419!2smx"
                 data-map-title="Mapa Teatro Constitución de Apatzingán"
                 style="width:100%;min-height:520px;border:0;border-radius:12px;overflow:hidden;background:rgba(0,0,0,.25);">
                <noscript>
                    <p style="padding:1rem;text-align:center;">
                        <a href="https://www.google.com/maps/search/?api=1&amp;query=Teatro+Constituci%C3%B3n+de+Apatzing%C3%A1n"
                           target="_blank" rel="noopener noreferrer">Ver ubicación en Google Maps</a>
                    </p>
                </noscript>
            </div>
            <script>
            (function () {
                var box = document.getElementById('mapaUbicacion');
                if (!box || !box.getAttribute('data-map-src')) return;
                var f = document.createElement('iframe');
                f.title = box.getAttribute('data-map-title') || 'Mapa';
                f.src = box.getAttribute('data-map-src');
                f.width = '100%';
                f.height = '520';
                f.style.border = '0';
                f.style.display = 'block';
                f.loading = 'lazy';
                f.referrerPolicy = 'no-referrer-when-downgrade';
                f.allowFullscreen = true;
                f.setAttribute('sandbox', 'allow-scripts allow-popups allow-popups-to-escape-sandbox allow-same-origin');
                box.replaceChildren(f);
            })();
            </script>
            <div style="max-width: 920px; margin: 30px auto 0; text-align: center;">
                <div class="location-item" style="border-bottom: none; padding-bottom: 0; justify-content: center;">
                    <i class="bi bi-geo-alt-fill"></i>
                    <div>
                        C. José Sotero de Castañeda 724, Ferrocarril, <br>60690 Apatzingán de la Constitución, Mich.<br>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-col">
                <h4>Teatro Constitución · Apatzingan</h4>
                <p class="muted">Arte escénico, música y cultura para todos. Vive la experiencia teatral.</p>
                <div class="social" aria-label="Redes sociales">
                    <a href="https://www.facebook.com/people/Teatro-Constituci%C3%B3n/100077079712986/" target="_blank" rel="noopener noreferrer" title="Facebook" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="https://www.instagram.com/teatro_constitucion_apatzingan?igsh=YzZ4YmxhcWU2Zmk%3D" target="_blank" rel="noopener noreferrer" title="Instagram" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Enlaces</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="acerca.php">Acerca del teatro</a></li>
                    <li><a href="contacto.php">Contacto / Reservaciones</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contacto</h4>
                <ul class="footer-links">
                    <li><i class="bi bi-telephone"></i> <span>+52 (453) 534 5751</span></li>
                    <li><i class="bi bi-envelope"></i> <span>teatroconstitucion@outlook.es</span></li>
                    <li><i class="bi bi-geo-alt"></i> <span>Apatzingán, Michoacán, México</span></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Horarios</h4>
                <ul class="footer-links">
                    <li><span>Taquilla: Lunes a Viernes 09:00 am –08:00 pm</span></li>
                    <li><span>Funciones: Según cartelera</span></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-inner">
                <div>© <?php echo date('Y'); ?> Teatro Constitución · Apatzingan. Todos los derechos reservados.</div>
                <div class="muted"><a href="terminos.php" style="color: inherit; text-decoration: none;">Términos · Privacidad</a></div>
            </div>
        </div>
    </footer>

    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const mainNav = document.getElementById('mainNav');
        const navBackdrop = document.getElementById('navBackdrop');

        function setNavOpen(open) {
            if (!mainNav || !hamburgerBtn) return;
            mainNav.classList.toggle('open', open);
            hamburgerBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (navBackdrop) {
                navBackdrop.classList.toggle('show', open);
                navBackdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
            const icon = hamburgerBtn.querySelector('i');
            if (icon) {
                icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
                icon.style.fontSize = '1.25rem';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (hamburgerBtn && mainNav) {
                hamburgerBtn.addEventListener('click', () => {
                    setNavOpen(!mainNav.classList.contains('open'));
                });
            }
            if (navBackdrop) {
                navBackdrop.addEventListener('click', () => setNavOpen(false));
            }
            mainNav?.querySelectorAll('a').forEach(a => {
                a.addEventListener('click', () => setNavOpen(false));
            });

            // Animación de aparición al hacer scroll
            const observerOptions = {
                threshold: 0.12,
                rootMargin: '0px 0px -40px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.info-card, .featured-section, .reveal-up, .story-band').forEach(el => {
                if (el.closest('.hero-section')) return;
                if (el.closest('[data-valores-carousel]')) return;
                observer.observe(el);
            });

            // Carrusel de valores (uno a uno)
            (function initValoresCarousel() {
                const root = document.querySelector('[data-valores-carousel]');
                if (!root) return;
                const slides = Array.from(root.querySelectorAll('[data-valor-slide]'));
                const dotsWrap = root.querySelector('[data-valor-dots]');
                const btnPrev = root.querySelector('[data-valor-prev]');
                const btnNext = root.querySelector('[data-valor-next]');
                if (!slides.length || !dotsWrap) return;

                let index = 0;
                let timer = null;
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const intervalMs = 4200;

                slides.forEach((_, i) => {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'valores-dot' + (i === 0 ? ' is-active' : '');
                    dot.setAttribute('aria-label', 'Ir al valor ' + (i + 1));
                    dot.addEventListener('click', () => goTo(i, true));
                    dotsWrap.appendChild(dot);
                });

                const dots = Array.from(dotsWrap.querySelectorAll('.valores-dot'));

                function goTo(i, userAction) {
                    index = (i + slides.length) % slides.length;
                    slides.forEach((s, n) => s.classList.toggle('is-active', n === index));
                    dots.forEach((d, n) => d.classList.toggle('is-active', n === index));
                    if (userAction) restart();
                }

                function next() { goTo(index + 1, false); }
                function prev() { goTo(index - 1, true); }

                function stop() {
                    if (timer) { clearInterval(timer); timer = null; }
                }

                function start() {
                    if (reduceMotion) return;
                    stop();
                    timer = setInterval(next, intervalMs);
                }

                function restart() {
                    stop();
                    start();
                }

                btnPrev?.addEventListener('click', prev);
                btnNext?.addEventListener('click', () => { goTo(index + 1, true); });

                root.addEventListener('mouseenter', stop);
                root.addEventListener('mouseleave', start);
                root.addEventListener('focusin', stop);
                root.addEventListener('focusout', (e) => {
                    if (!root.contains(e.relatedTarget)) start();
                });

                start();
            })();
        });
    </script>
</body>
</html>
