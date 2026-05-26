@extends('layouts.frontend')

@section('title', 'Home')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    .home-shell { font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, sans-serif; color: #1A1714; }
    .home-shell .mono { font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace; }

    /* ---- Announcement Ticker ---- */
    .home-ticker {
        margin: 18px auto 0;
        border-radius: 14px;
        background: linear-gradient(90deg, var(--primary-50, #FFF6EE), color-mix(in srgb, var(--primary-100, #FFE7CF) 70%, white));
        border: 1px solid var(--primary-100, #FFE7CF);
        overflow: hidden;
        position: relative;
    }
    .home-ticker-inner { display: flex; align-items: flex-start; min-height: 52px; padding: 12px 16px 12px 18px; gap: 14px; }
    .home-ticker-pill, .home-ticker-controls { margin-top: 2px; }
    .home-ticker-pill {
        display: inline-flex; align-items: center; gap: 8px;
        background: var(--primary-500, #E37715); color: #fff;
        font-size: 11px; font-weight: 700; letter-spacing: .06em;
        padding: 6px 10px; border-radius: 999px; text-transform: uppercase; flex-shrink: 0;
    }
    .home-ticker-pill svg { width: 12px; height: 12px; }
    .home-ticker-stage { position: relative; flex: 1; min-width: 0; }
    .home-ticker-item {
        display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 10px;
        font-size: 14px; line-height: 1.45; color: #2A2520;
        min-width: 0;
        overflow-wrap: anywhere; word-break: break-word;
        animation: home-ticker-fade .4s ease;
    }
    @keyframes home-ticker-fade {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .home-ticker-item strong { color: #1A1714; font-weight: 700; }
    .home-ticker-item .meta { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; color: #8F857B; flex-shrink: 0; }
    .home-ticker-item .sep { width: 4px; height: 4px; border-radius: 99px; background: #B8B0A6; flex-shrink: 0; }
    .home-ticker-item a.cta { margin-left: 6px; font-weight: 600; color: var(--primary-600, #C5640C); text-decoration: underline; flex-shrink: 0; }
    .home-ticker-controls { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
    .home-ticker-dots { display: flex; gap: 4px; margin-right: 4px; }
    .home-ticker-dot {
        width: 6px; height: 6px; border-radius: 99px;
        background: var(--primary-200, #FFCFA0); border: none; padding: 0; cursor: pointer;
        transition: all .2s ease;
    }
    .home-ticker-dot.active { width: 18px; background: var(--primary-500, #E37715); }
    .home-ticker-nav {
        width: 28px; height: 28px; border-radius: 8px; border: none;
        background: transparent; color: #6A6058;
        display: grid; place-items: center; transition: all .15s ease;
    }
    .home-ticker-nav:hover { background: color-mix(in srgb, var(--primary-500, #E37715) 12%, transparent); color: var(--primary-600, #C5640C); }
    .home-ticker-nav svg { width: 14px; height: 14px; }

    /* ---- Section ---- */
    .home-section { padding: 64px 0 24px; }
    .home-section.blog { padding: 56px 0 80px; }
    .home-section-header { display: flex; align-items: end; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
    .home-eyebrow {
        font-family: "JetBrains Mono", ui-monospace, monospace;
        font-size: 12px; letter-spacing: .08em;
        color: var(--primary-600, #C5640C);
        text-transform: uppercase; margin: 0 0 8px; font-weight: 600;
    }
    .home-title { font-size: 32px; font-weight: 700; letter-spacing: -.02em; color: #1A1714; margin: 0; line-height: 1.1; }
    .home-sub { font-size: 15px; color: #6A6058; margin: 6px 0 0; }
    .home-section-link {
        font-size: 14px; font-weight: 600; color: #463F38;
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; border-radius: 10px; transition: all .15s ease;
    }
    .home-section-link:hover { background: #F5F1EB; color: #1A1714; }
    .home-section-link svg { width: 14px; height: 14px; }

    /* ---- Menu grid ---- */
    .home-menu-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    @media (max-width: 980px) { .home-menu-grid { grid-template-columns: 1fr; } }
    .home-menu-card {
        position: relative; display: flex; flex-direction: column; gap: 18px;
        padding: 32px; border-radius: 18px; background: #fff; border: 1px solid #ECE7E0;
        text-align: left; cursor: pointer; min-height: 220px; overflow: hidden;
        transition: transform .25s cubic-bezier(.2,.7,.3,1), border-color .2s ease, box-shadow .25s ease;
        color: inherit; text-decoration: none;
    }
    .home-menu-card::before {
        content: ""; position: absolute; inset: auto -40px -60px auto;
        width: 180px; height: 180px; border-radius: 50%;
        background: var(--primary-50, #FFF6EE);
        opacity: 0; transition: opacity .25s ease, transform .35s cubic-bezier(.2,.7,.3,1);
        transform: scale(.7);
    }
    .home-menu-card:hover { transform: translateY(-3px); border-color: var(--primary-300, #FFB36B); box-shadow: 0 4px 14px rgba(26,23,20,.06), 0 2px 4px rgba(26,23,20,.04); }
    .home-menu-card:hover::before { opacity: 1; transform: scale(1); }
    .home-menu-icon {
        position: relative; width: 64px; height: 64px; border-radius: 16px;
        background: var(--primary-50, #FFF6EE); color: var(--primary-600, #C5640C);
        display: grid; place-items: center; transition: background .2s ease, color .2s ease;
    }
    .home-menu-card:hover .home-menu-icon { background: var(--primary-500, #E37715); color: #fff; }
    .home-menu-icon svg { width: 30px; height: 30px; stroke-width: 1.6; }
    .home-menu-card .home-menu-title { position: relative; font-size: 22px; font-weight: 700; letter-spacing: -.01em; color: #1A1714; margin: 0; }
    .home-menu-card .home-menu-desc { position: relative; font-size: 14.5px; line-height: 1.5; color: #6A6058; margin: 0; max-width: 32ch; }
    .home-menu-card .home-menu-cta {
        position: relative; margin-top: auto; padding-top: 8px;
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13.5px; font-weight: 600; color: var(--primary-600, #C5640C);
        font-family: "JetBrains Mono", ui-monospace, monospace; letter-spacing: .02em;
    }
    .home-menu-card .home-menu-cta svg { width: 14px; height: 14px; transition: transform .2s ease; }
    .home-menu-card:hover .home-menu-cta svg { transform: translateX(3px); }
    .home-menu-num { position: absolute; top: 24px; right: 28px; font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 11px; color: #B8B0A6; letter-spacing: .06em; }

    /* ---- Blog grid ---- */
    .home-blog-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
    @media (max-width: 980px) { .home-blog-grid { grid-template-columns: 1fr; } }
    .home-blog-card { display: flex; flex-direction: column; gap: 16px; cursor: pointer; transition: transform .2s ease; color: inherit; text-decoration: none; }
    .home-blog-card:hover { transform: translateY(-2px); }
    .home-blog-thumb { aspect-ratio: 16 / 10; border-radius: 12px; overflow: hidden; position: relative; background: #F5F1EB; }
    .home-blog-thumb img, .home-blog-thumb svg { width: 100%; height: 100%; display: block; object-fit: cover; }
    .home-blog-tag {
        position: absolute; top: 12px; left: 12px;
        background: rgba(255,255,255,.94); backdrop-filter: blur(8px);
        color: #2A2520; font-family: "JetBrains Mono", ui-monospace, monospace;
        font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
        padding: 5px 9px; border-radius: 6px;
    }
    .home-blog-meta { display: flex; align-items: center; gap: 8px; font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; color: #8F857B; }
    .home-blog-meta .dot { width: 3px; height: 3px; background: #B8B0A6; border-radius: 99px; }
    .home-blog-title { font-size: 19px; font-weight: 700; letter-spacing: -.01em; color: #1A1714; line-height: 1.3; margin: 0; transition: color .15s ease; }
    .home-blog-card:hover .home-blog-title { color: var(--primary-600, #C5640C); }
    .home-blog-excerpt { font-size: 14.5px; line-height: 1.55; color: #6A6058; margin: 0; }
</style>
@endpush

@section('content')
<div class="home-shell">
    @php
        // Build announcements list from /admin/announcements (banner type).
        $tickerItems = ($announcements ?? collect())->map(function ($a) {
            return [
                'id' => $a->id,
                'tag' => strtoupper($a->category ?: 'INFO'),
                'title' => $a->title,
                'body' => $a->content,
                'link_url' => $a->link_url,
                'link_label' => $a->link_label ?: 'Selengkapnya',
                'meta' => optional($a->starts_at ?? $a->created_at)->translatedFormat('d M'),
            ];
        })->values();
    @endphp

    {{-- Announcement Ticker --}}
    @if($tickerItems->count() > 0)
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="home-ticker" x-data="{
                items: {{ $tickerItems->toJson() }},
                i: 0,
                paused: false,
                timer: null,
                start() {
                    if (this.items.length < 2) return;
                    this.stop();
                    this.timer = setInterval(() => { if (!this.paused) this.i = (this.i + 1) % this.items.length; }, 4500);
                },
                stop() { if (this.timer) clearInterval(this.timer); this.timer = null; },
                prev() { this.i = (this.i - 1 + this.items.length) % this.items.length; },
                next() { this.i = (this.i + 1) % this.items.length; }
             }"
             x-init="start()"
             @mouseenter="paused = true" @mouseleave="paused = false">
            <div class="home-ticker-inner">
                <span class="home-ticker-pill">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 8a4 4 0 0 1 0 8"/>
                    </svg>
                    <span x-text="items[i] ? items[i].tag : 'INFO'">PENGUMUMAN</span>
                </span>
                <div class="home-ticker-stage" aria-live="polite">
                    <template x-if="items[i]">
                        <div class="home-ticker-item" :key="'item-' + i">
                            <strong x-text="items[i].title"></strong>
                            <template x-if="items[i].body">
                                <span class="sep"></span>
                            </template>
                            <template x-if="items[i].body">
                                <span x-text="items[i].body"></span>
                            </template>
                            <template x-if="items[i].link_url">
                                <a class="cta" :href="items[i].link_url" x-text="items[i].link_label"></a>
                            </template>
                            <template x-if="items[i].meta">
                                <span class="meta" x-text="'· ' + items[i].meta"></span>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="home-ticker-controls" x-show="items.length > 1">
                    <div class="home-ticker-dots" role="tablist">
                        <template x-for="(item, idx) in items" :key="'d' + item.id">
                            <button class="home-ticker-dot" :class="{ active: idx === i }" @click="i = idx" :aria-label="'Pengumuman ' + (idx + 1)"></button>
                        </template>
                    </div>
                    <button class="home-ticker-nav" @click="prev()" aria-label="Sebelumnya">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button class="home-ticker-nav" @click="next()" aria-label="Berikutnya">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Main Menu --}}
    <section class="home-section">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="home-section-header">
                <div>
                    <p class="home-eyebrow">Main Menu</p>
                    <h2 class="home-title">Selamat datang di {{ \App\Models\Setting::get('site_name', 'Warehouse FTV') }}</h2>
                    <p class="home-sub">Selamat menunaikan ibadah shooting!</p>
                </div>
            </div>
            <div class="home-menu-grid">
                <a href="{{ route('catalog.index') }}" class="home-menu-card">
                    <span class="home-menu-num">01</span>
                    <span class="home-menu-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7h3l2-3h8l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/>
                            <circle cx="12" cy="13" r="4"/>
                        </svg>
                    </span>
                    <h3 class="home-menu-title">Equipment Rental</h3>
                    <p class="home-menu-desc">Pinjam kamera, lensa, audio, lighting & support untuk produksi kampus.</p>
                    <span class="home-menu-cta">
                        Browse catalog
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </span>
                </a>

                <a href="{{ route('frontend.schedule') }}" class="home-menu-card">
                    <span class="home-menu-num">02</span>
                    <span class="home-menu-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                            <line x1="8" y1="3" x2="8" y2="7"/>
                            <line x1="16" y1="3" x2="16" y2="7"/>
                        </svg>
                    </span>
                    <h3 class="home-menu-title">Schedule</h3>
                    <p class="home-menu-desc">Lihat jadwal pengambilan & pengembalian, plus event warehouse minggu ini.</p>
                    <span class="home-menu-cta">
                        Open schedule
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </span>
                </a>

                <a href="{{ route('computers.index') }}" class="home-menu-card">
                    <span class="home-menu-num">03</span>
                    <span class="home-menu-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="13" rx="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                    </span>
                    <h3 class="home-menu-title">Computer Booking</h3>
                    <p class="home-menu-desc">Reservasi unit di Lab Editing. Cek ketersediaan slot per jam.</p>
                    <span class="home-menu-cta">
                        Book a unit
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </span>
                </a>
            </div>
        </div>
    </section>

    {{-- Blog --}}
    @if(($blogPosts ?? collect())->count() > 0)
    <section class="home-section blog">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="home-section-header">
                <div>
                    <p class="home-eyebrow">From the Warehouse</p>
                    <h2 class="home-title">Blog & catatan</h2>
                    <p class="home-sub">Tips, update kebijakan, dan cerita produksi dari tim FTV UPI.</p>
                </div>
                <a href="{{ route('blogs') }}" class="home-section-link">
                    Lihat semua
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
            <div class="home-blog-grid">
                @foreach($blogPosts as $post)
                    @php
                        $tagLabel = 'Blog';
                        try {
                            if (method_exists($post, 'tags')) {
                                $firstTag = $post->tags()->first();
                                if ($firstTag) {
                                    $tagLabel = $firstTag->label ?? $firstTag->name ?? $firstTag->title ?? 'Blog';
                                }
                            }
                        } catch (\Throwable $e) {}
                        $featured = $post->featured_image;
                        if ($featured && !str_starts_with($featured, 'http')) {
                            $featured = \Illuminate\Support\Facades\Storage::url($featured);
                        }
                        $excerpt = $post->description ?: \Illuminate\Support\Str::limit(strip_tags($post->content ?? ''), 140);
                        $readMinutes = max(1, (int) ceil(str_word_count(strip_tags($post->content ?? '')) / 200));
                    @endphp
                    <a href="{{ route('post', $post->slug) }}" class="home-blog-card">
                        <div class="home-blog-thumb">
                            @if($featured)
                                <img src="{{ $featured }}" alt="{{ $post->title }}" loading="lazy">
                            @else
                                <svg viewBox="0 0 320 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                                    <defs>
                                        <pattern id="hp-{{ $post->id }}" width="14" height="14" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                                            <line x1="0" y1="0" x2="0" y2="14" stroke="var(--primary-500, #E37715)" stroke-opacity="0.13" stroke-width="6"/>
                                        </pattern>
                                    </defs>
                                    <rect width="320" height="200" fill="var(--primary-100, #FFE7CF)"/>
                                    <rect width="320" height="200" fill="url(#hp-{{ $post->id }})"/>
                                </svg>
                            @endif
                            <span class="home-blog-tag">{{ $tagLabel }}</span>
                        </div>
                        <div class="home-blog-meta">
                            <span>{{ optional($post->published_at)->translatedFormat('d M Y') }}</span>
                            <span class="dot"></span>
                            <span>{{ $readMinutes }} min read</span>
                        </div>
                        <h3 class="home-blog-title">{{ $post->title }}</h3>
                        @if($excerpt)
                            <p class="home-blog-excerpt">{{ $excerpt }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif
</div>
@endsection
