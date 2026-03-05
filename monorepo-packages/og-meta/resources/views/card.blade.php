<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1200">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Ensure exact dimensions for screenshot */
        html, body { margin: 0; padding: 0; width: 1200px; height: 630px; overflow: hidden; }
        /* Inter font via Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <div class="w-[1200px] h-[630px] p-[60px]" style="background-color: {{ $bgColor }}">
        <div class="bg-white w-full h-full rounded-sm p-[60px] flex">
            {{-- Left column: text content --}}
            <div class="flex-1 flex flex-col min-w-0">
                {{-- App name --}}
                <p class="text-gray-400 text-base mb-4">{{ $appName }}</p>

                {{-- Headline (code) --}}
                <h1 class="text-5xl font-bold text-gray-900 leading-tight">{{ $headline }}</h1>

                {{-- Subtitle (amount) --}}
                @if($subtitle)
                    <p class="text-4xl font-bold text-gray-900 mt-1">{{ $subtitle }}</p>
                @endif

                {{-- Status badge --}}
                <div class="mt-4">
                    <span class="inline-block text-white text-sm font-bold px-4 py-2 rounded"
                          style="background-color: {{ $badgeColor }}">
                        {{ strtoupper($status) }}
                    </span>
                </div>

                {{-- Message --}}
                @if($message)
                    <p class="text-gray-500 text-base mt-4 leading-relaxed">{{ $message }}</p>
                @endif

                {{-- Spacer --}}
                <div class="flex-1"></div>

                {{-- Tagline --}}
                @if($tagline)
                    <p class="text-gray-400 text-sm">{{ $tagline }}</p>
                @endif
            </div>

            {{-- Right column: splash HTML (rendered natively) --}}
            @if($splashHtml)
                <div class="w-[400px] flex-shrink-0 flex items-center justify-center ml-6 overflow-hidden">
                    {!! $splashHtml !!}
                </div>
            @endif
        </div>
    </div>
</body>
</html>
