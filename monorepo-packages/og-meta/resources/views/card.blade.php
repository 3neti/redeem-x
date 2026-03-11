<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1200">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { margin: 0; padding: 0; width: 1200px; height: 630px; overflow: hidden; }
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <div class="w-[1200px] h-[630px] p-[48px]" style="background-color: {{ $bgColor }}">
        <div class="bg-white w-full h-full rounded-lg flex flex-col items-center justify-center relative overflow-hidden">

            {{-- App name — always anchored top-left --}}
            <p class="absolute top-8 left-10 text-gray-300 text-sm tracking-wider uppercase z-10">{{ $appName }}</p>

            @if(!empty($splashHtml))
                {{-- ============================================================ --}}
                {{-- OVERRIDE: Splash HTML (message text, splash content, fallback) --}}
                {{-- ============================================================ --}}
                <div class="absolute inset-0 flex items-center justify-center p-16">
                    {!! $splashHtml !!}
                </div>

                {{-- Bottom anchors: code + amount + status --}}
                <div class="absolute bottom-6 left-10 right-10 flex items-end justify-between z-10">
                    <div class="flex items-baseline gap-4">
                        <span class="text-3xl font-black text-gray-900/40 tracking-wider">{{ $headline }}</span>
                        @if($subtitle)
                            <span class="text-2xl font-semibold text-gray-500/40">{{ $subtitle }}</span>
                        @endif
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full text-white" style="background-color: {{ $badgeColor }}">{{ $status }}</span>
                </div>

            @elseif(!empty($overlayImage))
                {{-- ============================================================ --}}
                {{-- OVERRIDE: Overlay image (unfurled URL og:image)               --}}
                {{-- ============================================================ --}}
                <img
                    src="data:image/jpeg;base64,{{ $overlayImage }}"
                    alt=""
                    class="absolute inset-0 w-full h-full object-cover"
                />
                {{-- Gradient scrim for legibility --}}
                <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-black/60 to-transparent"></div>

                {{-- Bottom anchors over the scrim --}}
                <div class="absolute bottom-6 left-10 right-10 flex items-end justify-between z-10">
                    <div class="flex items-baseline gap-4">
                        <span class="text-3xl font-black text-white tracking-wider">{{ $headline }}</span>
                        @if($subtitle)
                            <span class="text-2xl font-semibold text-white/70">{{ $subtitle }}</span>
                        @endif
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full text-white" style="background-color: {{ $badgeColor }}">{{ $status }}</span>
                </div>

            @elseif($qrDataUri)
                {{-- ============================================================ --}}
                {{-- QR code landing layout (no code query param)                  --}}
                {{-- ============================================================ --}}
                <img src="{{ $qrDataUri }}" alt="QR" class="w-[280px] h-[280px]">
                @if($subtitle)
                    <p class="text-4xl font-semibold text-gray-500 mt-6 tracking-tight">{{ $subtitle }}</p>
                @endif

            @else
                {{-- ============================================================ --}}
                {{-- DEFAULT: Voucher code + amount + badges                       --}}
                {{-- ============================================================ --}}
                <div class="flex items-center justify-center gap-10">
                    <div class="flex gap-2">
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                    </div>
                    <h1 class="text-[148px] font-black text-gray-900 tracking-wider leading-none">{{ $headline }}</h1>
                    <div class="flex gap-2">
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                    </div>
                </div>

                @if($subtitle)
                    <p class="text-7xl font-semibold text-gray-600 mt-6 tracking-tight">{{ $subtitle }}</p>
                @endif

                <div class="flex items-center gap-8 mt-10">
                    @if($typeBadge)
                        <span class="inline-block text-gray-500 text-[40px] font-bold px-12 py-4 rounded-full bg-gray-100 uppercase tracking-wider">
                            {{ $typeBadge }}
                        </span>
                    @endif
                    @if($payeeBadge)
                        <span class="inline-block text-white text-[40px] font-bold px-12 py-4 rounded-full bg-gray-700 uppercase tracking-wider">
                            {{ $payeeBadge }}
                        </span>
                    @endif
                </div>
            @endif

        </div>
    </div>
</body>
</html>
