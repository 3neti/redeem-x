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
        <div class="bg-white w-full h-full rounded-lg flex flex-col items-center justify-center relative">

            {{-- App name — top left --}}
            <p class="absolute top-8 left-10 text-gray-300 text-sm tracking-wider uppercase">{{ $appName }}</p>

            @if($qrDataUri)
                {{-- QR code landing layout --}}
                <img src="{{ $qrDataUri }}" alt="QR" class="w-[280px] h-[280px]">
                @if($subtitle)
                    <p class="text-4xl font-semibold text-gray-500 mt-6 tracking-tight">{{ $subtitle }}</p>
                @endif
            @else
                {{-- Voucher code with parallel bars --}}
                <div class="flex items-center justify-center gap-10">
                    {{-- Left parallel bars --}}
                    <div class="flex gap-2">
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                    </div>
                    <h1 class="text-[148px] font-black text-gray-900 tracking-wider leading-none">{{ $headline }}</h1>
                    {{-- Right parallel bars --}}
                    <div class="flex gap-2">
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                        <div class="w-[6px] h-[140px] bg-gray-400 rounded-sm"></div>
                    </div>
                </div>

                {{-- Amount — centered below code --}}
                @if($subtitle)
                    <p class="text-7xl font-semibold text-gray-600 mt-6 tracking-tight">{{ $subtitle }}</p>
                @endif

                {{-- Badges row — type (left) + payee (right) --}}
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
