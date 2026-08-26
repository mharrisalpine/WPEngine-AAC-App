#!/bin/bash
set -euo pipefail

site="https://wondrous-marshallleeharris.wpcomstaging.com"
user="${1:?username required}"
pass="${2:?password required}"
redirect="${3:?redirect url required}"

tmpdir="$(mktemp -d)"
cj="$tmpdir/cookies.txt"
login_html="$tmpdir/login.html"
resp="$tmpdir/resp.html"

curl -s -c "$cj" -b "$cj" "$site/wp-login.php" -o "$login_html"

challenge="$(grep '&nbsp; + &nbsp;' "$login_html" | head -n 1 || true)"
a="$(printf '%s' "$challenge" | grep -oE '[0-9]+' | sed -n '1p')"
b="$(printf '%s' "$challenge" | grep -oE '[0-9]+' | sed -n '2p')"
sum=$((a + b))
proof="$(grep 'name="jetpack_protect_answer" value=' "$login_html" | head -n 1 | cut -d'"' -f6)"

curl -s -L -c "$cj" -b "$cj" \
  --data-urlencode "log=$user" \
  --data-urlencode "pwd=$pass" \
  --data-urlencode "jetpack_protect_num=$sum" \
  --data-urlencode "jetpack_protect_answer=$proof" \
  --data-urlencode "wp-submit=Log In" \
  --data-urlencode "redirect_to=$redirect" \
  --data-urlencode "testcookie=1" \
  "$site/wp-login.php" -o "$resp"

cat "$resp" >&2
printf '%s\n' "$cj"
