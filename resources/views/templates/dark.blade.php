{{-- Dark OG image - bold text on dark background with accent border --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { width: 1200px; height: 630px; }
    </style>
</head>
<body>
    <div style="
        width: 1200px;
        height: 630px;
        background: #18181b;
        padding: 80px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #fafafa;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border-left: 8px solid {{ $accent ?? '#8b5cf6' }};
        border-right: 8px solid {{ $accent ?? '#8b5cf6' }};
    ">
        <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
            @isset($title)
                <h1 id="og-title" style="font-size: 65px; font-weight: 700; line-height: 1.15; letter-spacing: -0.02em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-break: break-word;">
                    {{ $title }}
                </h1>
            @endisset

            @isset($description)
                <p id="og-description" style="font-size: 44px; margin-top: 24px; color: #a1a1aa; line-height: 1.4; max-width: 1000px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-break: break-word;">
                    {{ $description }}
                </p>
            @endisset
        </div>

        <div style="display: flex; margin-top: auto; padding-top: 30px; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center;">
                @isset($avatar)
                    <img
                        src="{{ $avatar }}"
                        style="width: 64px; height: 64px; border-radius: 50%; margin-right: 20px; border: 3px solid #3f3f46;"
                    >
                @endisset

                <div>
                    @isset($author)
                        <p style="font-size: 44px; font-weight: 600; color: #e4e4e7;">{{ $author }}</p>
                    @endisset

                    @isset($date)
                        <p style="font-size: 32px; color: #71717a; margin-top: 4px;">{{ $date }}</p>
                    @endisset
                </div>
            </div>

            @isset($domain)
                <p style="font-size: 36px; color: #52525b;">{{ $domain }}</p>
            @endisset
        </div>
    </div>
    <script>
        function fitText(id, maxLines, maxSize, minSize) {
            const el = document.getElementById(id);
            if (!el) return;
            const savedClamp = el.style.webkitLineClamp;
            el.style.webkitLineClamp = 'unset';
            el.style.fontSize = maxSize + 'px';
            const lineHeight = parseFloat(getComputedStyle(el).lineHeight);
            const maxHeight = Math.ceil(lineHeight * maxLines);
            let size = maxSize;
            while (el.scrollHeight > maxHeight && size > minSize) {
                el.style.fontSize = --size + 'px';
            }
            el.style.webkitLineClamp = String(maxLines);
        }

        const hasDescription = !!document.getElementById('og-description');
        const titleEl = document.getElementById('og-title');
        if (titleEl && !hasDescription) {
            titleEl.style.webkitLineClamp = '4';
        }
        fitText('og-title', hasDescription ? 2 : 4, hasDescription ? 80 : 100, 36);
        fitText('og-description', 2, 44, 24);
    </script>
</body>
</html>
