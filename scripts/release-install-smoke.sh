#!/usr/bin/env bash
# Install only in a newly created, marked runner-owned WordPress root.
set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
test -n "${RUNNER_TEMP:-}"
test -n "${RAN_EXAMPLE_CORE_ARCHIVE:-}"
test -f "$RAN_EXAMPLE_CORE_ARCHIVE"
if [[ "${RAN_EXAMPLE_CONSUMER_PROOF:-}" != 1 ]]; then
	python3 "$repo_root/scripts/check-core-archive.py" "$RAN_EXAMPLE_CORE_ARCHIVE"
	test -f "$repo_root/dist/ran-booster-github-example.zip"
fi
site_root=$(mktemp -d "$RUNNER_TEMP/ran-example-install.XXXXXX")
private_root=$(mktemp -d "$RUNNER_TEMP/ran-example-secrets.XXXXXX")
chmod 700 "$private_root"
test -d "$site_root"
touch "$site_root/RAN-EXAMPLE-DISPOSABLE"
printf 'RAN Booster disposable test site' > "$site_root/.ran-booster-disposable-test-site"
cleanup() {
	if [[ "$site_root" == "$RUNNER_TEMP"/ran-example-install.* && -f "$site_root/RAN-EXAMPLE-DISPOSABLE" ]]; then
		rm -rf "$site_root" "$private_root"
	fi
}
trap cleanup EXIT HUP INT TERM

wp --path="$site_root" core download --version=7.0 --skip-content
wp --path="$site_root" core verify-checksums --version=7.0
wp --path="$site_root" config create --dbname="${RAN_EXAMPLE_DB_NAME:-ran_example_test}" --dbuser="${RAN_EXAMPLE_DB_USER:-root}" --dbpass="${RAN_EXAMPLE_DB_PASSWORD-disposable}" --dbhost="${RAN_EXAMPLE_DB_HOST:-127.0.0.1:3306}" --skip-check
wp --path="$site_root" core install --url=http://localhost --title='Example CI' --admin_user=example-ci --admin_password=disposable-example-ci --admin_email=example@example.invalid --skip-email
if [[ "${RAN_EXAMPLE_CONSUMER_PROOF:-}" == 1 || "${RAN_EXAMPLE_PUBLISHED_CONSUMER_PROOF:-}" == 1 ]]; then
	wp --path="$site_root" config set RAN_BOOSTER_ENCRYPTED_SECRETS_DIR "$private_root"
fi
wp --path="$site_root" plugin install "$RAN_EXAMPLE_CORE_ARCHIVE" --activate
if [[ "${RAN_EXAMPLE_CONSUMER_PROOF:-}" == 1 || "${RAN_EXAMPLE_PUBLISHED_CONSUMER_PROOF:-}" == 1 ]]; then
	export RAN_PROVIDER_CONSUMER_DISPOSABLE=1
	export RAN_BOOSTER_WORDPRESS_PATH="$site_root"
	for phase in prepare install activate adopt; do
		wp --path="$site_root" --user=example-ci eval-file "$repo_root/tests/consumer/installed.php" "$phase" --use-include
	done
	for order in \
		'["ran-booster/ran-booster.php","ran-booster-github-example/ran-booster-github-example.php"]' \
		'["ran-booster-github-example/ran-booster-github-example.php","ran-booster/ran-booster.php"]'; do
		wp --path="$site_root" option update active_plugins "$order" --format=json
		RAN_EXAMPLE_EXPECTED_ORDER="$order" wp --path="$site_root" --user=example-ci eval-file "$repo_root/tests/consumer/installed.php" exercise --use-include
		if [[ "${RAN_EXAMPLE_PUBLISHED_CONSUMER_PROOF:-}" == 1 ]]; then
			RAN_EXAMPLE_EXPECTED_ORDER="$order" wp --path="$site_root" --user=example-ci eval-file "$repo_root/tests/consumer/installed.php" refuse --use-include
		fi
	done
	wp --path="$site_root" plugin deactivate ran-booster-github-example
	wp --path="$site_root" --user=example-ci eval-file "$repo_root/tests/consumer/installed.php" restore --use-include
	if [[ "${RAN_EXAMPLE_PUBLISHED_CONSUMER_PROOF:-}" == 1 ]]; then
		printf 'Published Core and ordinary Example ZIP: discovery, verified installation, adoption, protocol-7 composition, both load orders and incompatible-copy refusals passed.\n'
	else
		printf 'Candidate release discovery, verified updater installation, adoption and both load orders passed.\n'
	fi
	exit 0
fi
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
