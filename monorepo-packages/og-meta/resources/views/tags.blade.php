{{-- Dynamic Open Graph meta tags for link previews (WhatsApp, iMessage, Viber) --}}
@if(isset($og))
    <meta property="og:title" content="{{ $og['title'] }}">
    <meta property="og:description" content="{{ $og['description'] }}">
    <meta property="og:image" content="{{ $og['image'] }}">
    <meta property="og:url" content="{{ $og['url'] }}">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $og['title'] }}">
    <meta name="twitter:description" content="{{ $og['description'] }}">
    <meta name="twitter:image" content="{{ $og['image'] }}">
@endif
