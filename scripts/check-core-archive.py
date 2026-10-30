#!/usr/bin/env python3
"""Check the published Core fixture before installing it."""
import hashlib
import json
from pathlib import Path
import sys
import zipfile

ROOT = Path(__file__).resolve().parent.parent


def check(archive: Path, contract: dict) -> None:
    if hashlib.sha256(archive.read_bytes()).hexdigest() != contract['asset_sha256']:
        raise ValueError('Core fixture archive digest differs from the contract')
    with zipfile.ZipFile(archive) as bundle:
        marker = json.loads(bundle.read('ran-booster/ran-booster-release.json'))
    if marker != {'schema': 'ran-booster-core-release', 'schema_version': 1,
                  'version': contract['tag'].removeprefix('v'), 'commit': contract['commit']}:
        raise ValueError('Core fixture archive provenance differs from the contract')


if __name__ == '__main__':
    try:
        check(Path(sys.argv[1]), json.loads((ROOT / 'host-contract.json').read_text()))
    except (IndexError, OSError, ValueError, KeyError, zipfile.BadZipFile) as failure:
        raise SystemExit('check-core-archive: ' + str(failure)) from failure
    print('Core fixture digest and provenance: passed.')
