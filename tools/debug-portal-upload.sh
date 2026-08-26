#!/bin/bash
set -euo pipefail

BASE_URL='https://wondrous-marshallleeharris.wpcomstaging.com'
USERNAME='marshallleeharris'
PASSWORD='wutangCLAN!!8344'
PLUGIN_ZIP="${1:-wordpress/aac-member-portal.zip}"

tmpdir=$(mktemp -d)
cookie_jar="$tmpdir/cookies.txt"

extract_challenge_token() {
	perl -ne 'print "$1\n" if /name="jetpack_protect_answer" value="([^"]+)"/' | head -n1
}

extract_challenge_sum() {
	perl -0777 -ne 'if (/<label[^>]*for="jetpack_protect_answer"[^>]*>(.*?)<\/label>/s) { @numbers = ($1 =~ /(\d+)/g); print(($numbers[0] + $numbers[1]) . "\n") if @numbers >= 2; }'
}

extract_form_action() {
	perl -ne 'print "$1\n" if /<form action="([^"]+)"/' | head -n1
}

solve_challenge_if_present() {
	local html="$1"
	if [[ "$html" != *"jetpack_protect_process_math_form"* ]]; then
		printf '%s' "$html"
		return
	fi

	local token answer action_url solved
	token=$(printf '%s' "$html" | extract_challenge_token)
	answer=$(printf '%s' "$html" | extract_challenge_sum)
	action_url=$(printf '%s' "$html" | extract_form_action)
	if [[ -z "${action_url:-}" ]]; then
		action_url="$BASE_URL/wp-login.php"
	fi
	if [[ "$action_url" != http* ]]; then
		action_url="${BASE_URL%/}/${action_url#/}"
	fi

	solved=$(curl -s -L -b "$cookie_jar" -c "$cookie_jar" \
		-d "jetpack_protect_num=$answer&jetpack_protect_answer=$token&jetpack_protect_process_math_form=1" \
		"$action_url")

	printf '%s' "$solved"
}

login_page=$(curl -s -c "$cookie_jar" "$BASE_URL/wp-login.php")
login_page=$(solve_challenge_if_present "$login_page")

first_token=$(printf '%s' "$login_page" | extract_challenge_token)
first_answer=$(printf '%s' "$login_page" | extract_challenge_sum)

login_response=$(curl -s -L -b "$cookie_jar" -c "$cookie_jar" \
	--data-urlencode "log=$USERNAME" \
	--data-urlencode "pwd=$PASSWORD" \
	--data-urlencode "jetpack_protect_num=$first_answer" \
	--data-urlencode "jetpack_protect_answer=$first_token" \
	--data-urlencode "rememberme=forever" \
	--data-urlencode "wp-submit=Log In" \
	--data-urlencode "redirect_to=$BASE_URL/wp-admin/" \
	--data-urlencode "testcookie=1" \
	"$BASE_URL/wp-login.php")
login_response=$(solve_challenge_if_present "$login_response")

plugin_page=$(curl -s -L -b "$cookie_jar" -c "$cookie_jar" "$BASE_URL/wp-admin/plugin-install.php?tab=upload")
plugin_page=$(solve_challenge_if_present "$plugin_page")
upload_nonce=$(printf '%s' "$plugin_page" | perl -ne 'print "$1\n" if /name="_wpnonce" value="([^"]+)"/' | head -n1)

upload_response=$(curl -s -L -b "$cookie_jar" -c "$cookie_jar" \
	-F "_wpnonce=$upload_nonce" \
	-F "_wp_http_referer=/wp-admin/plugin-install.php?tab=upload" \
	-F "pluginzip=@$PLUGIN_ZIP;type=application/zip" \
	-F "install-plugin-submit=Install+Now" \
	"$BASE_URL/wp-admin/update.php?action=upload-plugin")
upload_response=$(solve_challenge_if_present "$upload_response")

printf '%s' "$upload_response" > "$tmpdir/upload-response.html"
overwrite_link=$(printf '%s' "$upload_response" | perl -ne 'print "$1\n" if /class="button button-primary update-from-upload-overwrite" href="([^"]+)"/' | head -n1 | perl -MHTML::Entities -pe 'decode_entities($_);')

echo "TMPDIR=$tmpdir"
echo "OVERWRITE_LINK=$overwrite_link"
grep -n 'Replace current with uploaded\|This plugin is already installed\|Plugin updated successfully\|Plugin installed successfully' "$tmpdir/upload-response.html" | sed -n '1,80p' || true

if [[ -n "${overwrite_link:-}" ]]; then
	overwrite_response=$(curl -s -L -b "$cookie_jar" -c "$cookie_jar" "$BASE_URL/wp-admin/$overwrite_link")
	overwrite_response=$(solve_challenge_if_present "$overwrite_response")
	printf '%s' "$overwrite_response" > "$tmpdir/overwrite-response.html"
	echo "Saved overwrite response to $tmpdir/overwrite-response.html"
	grep -n 'Plugin updated successfully\|Plugin installed successfully\|jetpack_protect_process_math_form\|This site is temporarily unavailable' "$tmpdir/overwrite-response.html" | sed -n '1,120p' || true
fi
