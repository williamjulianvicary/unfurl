{{-- Minimal OG image - large centered title on white --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { width: 1200px; height: 630px; overflow: hidden; }
    </style>
</head>
<body>
    <div style="
        width: 1200px;
        height: 630px;
        background: #ffffff;
        padding: 40px 80px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #111827;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        overflow: hidden;
        border-top: 6px solid {{ $accent ?? '#111827' }};
    ">
        @isset($avatar)
            <img
                src="{{ $avatar }}"
                style="width: 100px; height: 100px; border-radius: 50%; margin-bottom: 28px; flex-shrink: 0;"
            >
        @endisset

        @isset($title)
            <h1 id="og-title" style="font-size: 62px; font-weight: 700; line-height: 1.15; letter-spacing: -0.03em; max-width: 1000px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-break: break-word;">
                {{ $title }}
            </h1>
        @endisset

        @isset($description)
            <p id="og-description" style="font-size: 34px; color: #6b7280; margin-top: 20px; line-height: 1.4; max-width: 900px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-break: break-word;">
                {{ $description }}
            </p>
        @endisset

        @if(isset($author) || isset($domain))
            <div style="display: flex; align-items: center; gap: 16px; margin-top: 32px; font-size: 36px; color: #9ca3af; flex-shrink: 0;">
                @isset($author)
                    <span>{{ $author }}</span>
                @endisset

                @if(isset($author) && isset($domain))
                    <span>&middot;</span>
                @endif

                @isset($domain)
                    <span>{{ $domain }}</span>
                @endisset

                @isset($date)
                    <span>&middot;</span>
                    <span>{{ $date }}</span>
                @endisset
            </div>
        @endif
    </div>

    <script>
        function fitText(id, maxLines, maxSize, minSize) {
            const el = document.getElementById(id);
            if (!el) return;

            // Remove clamp so scrollHeight reflects true content height
            const savedClamp = el.style.webkitLineClamp;
            el.style.webkitLineClamp = 'unset';
            el.style.fontSize = maxSize + 'px';

            const lineHeight = parseFloat(getComputedStyle(el).lineHeight);
            const maxHeight = Math.ceil(lineHeight * maxLines);
            let size = maxSize;

            while (el.scrollHeight > maxHeight && size > minSize) {
                el.style.fontSize = --size + 'px';
            }

            // Restore clamp as safety net
            el.style.webkitLineClamp = String(maxLines);
        }

        const hasDescription = !!document.getElementById('og-description');
        const titleEl = document.getElementById('og-title');
        if (titleEl && !hasDescription) {
            titleEl.style.webkitLineClamp = '4';
        }
        fitText('og-title', hasDescription ? 2 : 4, hasDescription ? 80 : 100, 36);
        fitText('og-description', 2, 36, 30);
    </script>
</body>
</html>
