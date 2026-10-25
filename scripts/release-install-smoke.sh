#!/usr/bin/env bash
# Install only in a newly created, marked runner-owned WordPress root.
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
test -n "${RUNNER_TEMP:-}"
test -n "${RAN_EXAMPLE_CORE_ARCHIVE:-}"
test -f "$RAN_EXAMPLE_CORE_ARCHIVE"
test -f "$repo_root/dist/ran-booster-github-example.zip"
site_root=$(mktemp -d "$RUNNER_TEMP/ran-example-install.XXXXXX")
test -d "$site_root"
touch "$site_root/RAN-EXAMPLE-DISPOSABLE"
cleanup() {
	if [[ "$site_root" == "$RUNNER_TEMP"/ran-example-install.* && -f "$site_root/RAN-EXAMPLE-DISPOSABLE" ]]; then
		rm -rf "$site_root"
	fi
}
trap cleanup EXIT HUP INT TERM

wp --path="$site_root" core download --version=7.0 --skip-content
wp --path="$site_root" core verify-checksums --version=7.0
wp --path="$site_root" config create --dbname=ran_example_test --dbuser=root --dbpass=disposable --dbhost=127.0.0.1:3306 --skip-check
wp --path="$site_root" core install --url=http://ran-example.invalid --title='Example CI' --admin_user=example-ci --admin_password=disposable-example-ci --admin_email=example@example.invalid --skip-email
wp --path="$site_root" plugin install "$RAN_EXAMPLE_CORE_ARCHIVE" --activate
wp --path="$site_root" plugin install "$repo_root/dist/ran-booster-github-example.zip" --activate
wp --path="$site_root" plugin is-active ran-booster
wp --path="$site_root" plugin is-active ran-booster-github-example
wp --path="$site_root" plugin get ran-booster-github-example --field=version | diff - "$repo_root/version.txt"

export RAN_EXAMPLE_DISPOSABLE=1
for order in \
	'["ran-booster/ran-booster.php","ran-booster-github-example/ran-booster-github-example.php"]' \
	'["ran-booster-github-example/ran-booster-github-example.php","ran-booster/ran-booster.php"]'; do
	wp --path="$site_root" option update active_plugins "$order" --format=json
	RAN_EXAMPLE_EXPECTED_ORDER="$order" wp --path="$site_root" eval-file "$repo_root/tests/release-installed.php" --use-include
done
printf 'Clean WordPress activation and both dependency load orders passed.\n'
