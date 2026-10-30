#!/usr/bin/env bash
# Exercise the ordinary Example ZIP against the unmodified published Core ZIP.
set -euo pipefail
repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)
test -n "${RUNNER_TEMP:-}"
test -n "${RAN_EXAMPLE_CORE_ARCHIVE:-}"
test -n "${RAN_BOOSTER_CORE_PATH:-}"
test "${RAN_EXAMPLE_CONSUMER_PROOF:-}" != 1
python3 "$repo_root/scripts/check-host.py"
python3 "$repo_root/scripts/check-core-archive.py" "$RAN_EXAMPLE_CORE_ARCHIVE"
php "$repo_root/scripts/qualify-dependencies.php" --check
python3 "$repo_root/scripts/release.py" archive
receipt_root=$(mktemp -d "$RUNNER_TEMP/ran-example-published-consumer.XXXXXX")
trap 'rm -rf "$receipt_root"' EXIT HUP INT TERM
export RAN_CONSUMER_RECEIPT="$receipt_root/receipt.json"
python3 - "$repo_root" "$RAN_EXAMPLE_CORE_ARCHIVE" "$RAN_BOOSTER_CORE_PATH" "$RAN_CONSUMER_RECEIPT" <<'PYRECEIPT'
import hashlib,json,pathlib,sys
root,archive,host,output=map(pathlib.Path,sys.argv[1:])
contract=json.loads((root/'host-contract.json').read_text())
lock=json.loads((root/'composer.lock').read_text())
provider=next(p for p in lock['packages'] if p['name']=='ran/booster-github-provider')
assert not provider['version'].startswith('dev-')
provider_root=root/'vendor/ran/booster-github-provider'
digest=lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
receipt={
    'candidate':False,'published':False,'published_host':True,
    'host':{'source_root':str(host.resolve()),'archive':str(archive.resolve()),
            'sha256':digest(archive),'commit':contract['commit'],'tag':contract['tag']},
    'example':{'source_root':str(root),'archive':str(root/'dist/ran-booster-github-example.zip'),
               'sha256':digest(root/'dist/ran-booster-github-example.zip')},
    'provider':{'source_root':str(provider_root),'commit':provider['source']['reference'],
                'manifest_sha256':digest(provider_root/'composer.json')},
}
output.write_text(json.dumps(receipt,indent=2)+'\n')
PYRECEIPT
export RAN_EXAMPLE_PUBLISHED_CONSUMER_PROOF=1
bash "$repo_root/scripts/release-install-smoke.sh"
