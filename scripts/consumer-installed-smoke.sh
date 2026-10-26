#!/usr/bin/env bash
# Install the prepared candidate through the real public release boundary.
set -euo pipefail
repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)
assembly="${RAN_PROVIDER_CONSUMER_ASSEMBLY:?Set the prepared candidate output directory.}"
export RAN_CONSUMER_RECEIPT="$assembly/receipt.json"
test -f "$RAN_CONSUMER_RECEIPT"
python3 - "$RAN_CONSUMER_RECEIPT" <<'PYRECEIPT'
import hashlib,json,pathlib,sys
receipt=json.loads(pathlib.Path(sys.argv[1]).read_text())
for key in ('host','example'):
    artifact=pathlib.Path(receipt[key]['archive'])
    if hashlib.sha256(artifact.read_bytes()).hexdigest()!=receipt[key]['sha256']:
        raise SystemExit('Prepared archive digest mismatch: '+key)
PYRECEIPT
export RAN_EXAMPLE_CORE_ARCHIVE
RAN_EXAMPLE_CORE_ARCHIVE=$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["host"]["archive"])' "$RAN_CONSUMER_RECEIPT")
export RAN_EXAMPLE_CONSUMER_PROOF=1
exec bash "$repo_root/scripts/release-install-smoke.sh"
