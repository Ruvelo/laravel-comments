#!/usr/bin/env bash
# Regenerates the images in art/ from the demo thread. Run it after changing
# the views or the demo content, then commit art/.
#
# Needs PHP (with the dev dependencies installed), Python 3 and a Chrome or
# Chromium binary: set CHROME=/path/to/chrome if it isn't on the PATH.
set -euo pipefail
cd "$(dirname "$0")/.."

CHROME=${CHROME:-$(command -v chromium || command -v chromium-browser || command -v google-chrome || command -v chrome-headless-shell || true)}
[ -x "$CHROME" ] || { echo "Chrome not found; set CHROME=/path/to/chrome" >&2; exit 1; }

rm -rf build
php demo/build.php build/site build/shots
mkdir -p art build/serve

# Headless Chrome on a bare Linux box has no emoji font; borrow Noto Color
# Emoji for the screenshots only (the demo itself uses the reader's own).
# It's fetched once and served locally so every subset loads in time.
mkdir -p build/serve/emoji
ua='Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'
curl -sSf -A "$ua" 'https://fonts.googleapis.com/css2?family=Noto+Color+Emoji' > build/serve/emoji/remote.css
i=0; : > build/serve/emoji/emoji.css
while IFS= read -r line; do
    if [[ $line =~ url\((https://[^\)]+)\) ]]; then
        i=$((i+1)); curl -sSf -o "build/serve/emoji/$i.woff2" "${BASH_REMATCH[1]}"
        line=${line/${BASH_REMATCH[1]}//emoji/$i.woff2}
    fi
    echo "$line" >> build/serve/emoji/emoji.css
done < build/serve/emoji/remote.css
emoji='<link rel="stylesheet" href="/emoji/emoji.css"><style>body{font-family:ui-sans-serif,system-ui,sans-serif,"Noto Color Emoji"}html .comments{--comments-sans:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif,"Noto Color Emoji"}</style>'
find build/shots -name index.html -exec sed -i -e "s#</head>#${emoji}</head>#" -e "s# autofocus##g" {} +
ln -sfn ../site build/serve/laravel-comments
ln -sfn ../shots build/serve/shots
cp demo/banner.html demo/ruvelo-mark.svg build/serve/

port=8920
python3 -m http.server "$port" --bind 127.0.0.1 --directory build/serve >/dev/null 2>&1 &
server=$!
trap 'kill $server' EXIT
sleep 1

shot() { # name, path, width,height, [extra chrome flags]
    "$CHROME" --headless --no-sandbox --hide-scrollbars --force-device-scale-factor=2 \
        --virtual-time-budget=15000 ${4:-} --window-size="$3" \
        --screenshot="art/$1.png" "http://127.0.0.1:$port/$2" 2>/dev/null
    echo "art/$1.png"
}

shot screenshot-thread     shots/thread/     1280,1100
shot screenshot-page       shots/page/       1280,900
shot screenshot-reply      shots/reply/      1280,1000
shot screenshot-moderation shots/moderation/ 1280,900
shot screenshot-dark       shots/thread/     1280,1100 --blink-settings=preferredColorScheme=0
shot screenshot-mobile     shots/thread/     390,844

# The banner frames the thread screenshot, so it goes last.
mkdir -p build/site/art && cp art/screenshot-thread.png build/site/art/
shot banner banner.html 1280,640
