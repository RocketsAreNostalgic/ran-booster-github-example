#!/usr/bin/env python3
"""Exercise host identity refusals using a disposable synthetic Git checkout."""
import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('example_host', ROOT / 'scripts/check-host.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def invoke(host: Path, *arguments: str) -> str:
    return subprocess.check_output(['git', '-C', str(host), *arguments], text=True, stderr=subprocess.PIPE).strip()


def refused(host: Path, contract: dict, qualification: dict, message: str) -> None:
    try:
        module.check_host(host, contract, qualification)
    except (ValueError, OSError, KeyError):
        return
    raise AssertionError('Host guard accepted ' + message)


with tempfile.TemporaryDirectory(prefix='ran-example-host-contract-') as directory:
    host = Path(directory).resolve()
    (host / 'RAN').mkdir()
    (host / 'tests').mkdir()
    package = {'name': 'ran/booster-github-provider', 'version': 'v1.0.0-beta.20', 'source': {'reference': 'a' * 40}}
    lock = json.dumps({'packages': [package]}).encode()
    (host / 'composer.lock').write_bytes(lock)
    bootstrap = "<?php define( 'RAN_BOOSTER_PROVIDER_API_VERSION', 15 );\n"
    (host / 'ran-booster.php').write_text(bootstrap)
    invoke(host, 'init', '-q')
    invoke(host, 'add', 'composer.lock', 'ran-booster.php')
    invoke(host, '-c', 'user.name=Example fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-qm', 'fixture')
    runtime = host / 'vendor/ran/booster-github-provider'
    runtime.mkdir(parents=True)
    runtime_file = runtime / 'composer.json'
    runtime_file.write_text('{}\n')
    metadata = host / 'vendor/composer/installed.json'
    metadata.parent.mkdir(parents=True)
    installed = {'dev': False, 'packages': [package]}
    metadata.write_text(json.dumps(installed))
    (host / 'vendor/autoload.php').write_text('<?php // synthetic autoloader\n')
    contract = {
        'commit': invoke(host, 'rev-parse', 'HEAD'), 'tree': invoke(host, 'rev-parse', 'HEAD^{tree}'),
        'composer_lock_sha256': hashlib.sha256(lock).hexdigest(), 'provider_api': 15,
    }
    qualification = {'packages': {package['name']: {
        'reference': 'a' * 40, 'files': {'composer.json': hashlib.sha256(runtime_file.read_bytes()).hexdigest()}
    }}}
    module.check_host(host, contract, qualification)
    for field, value in [('commit', 'b' * 40), ('tree', 'b' * 40), ('composer_lock_sha256', 'b' * 64), ('provider_api', 14)]:
        modified = {**contract, field: value}
        refused(host, modified, qualification, field)
    (host / 'ran-booster.php').write_text(bootstrap + '// changed\n')
    refused(host, contract, qualification, 'dirty tracked source')
    invoke(host, 'update-index', '--assume-unchanged', 'ran-booster.php')
    refused(host, contract, qualification, 'modified tracked bytes hidden from git status')
    invoke(host, 'update-index', '--no-assume-unchanged', 'ran-booster.php')
    (host / 'ran-booster.php').write_text(bootstrap)
    (host / 'tests/extra.php').write_text('<?php // untracked executable source\n')
    refused(host, contract, qualification, 'untracked test code')
    (host / 'tests/extra.php').unlink()
    metadata.write_text(json.dumps({**installed, 'dev': True}))
    refused(host, contract, qualification, 'development dependency installation')
    metadata.write_text(json.dumps(installed))
    other = copy.deepcopy(qualification)
    other['packages'][package['name']]['reference'] = 'b' * 40
    refused(host, contract, other, 'different dependency reference')
    runtime_file.write_text('{}\n// corrupt\n')
    refused(host, contract, qualification, 'different runtime bytes')
    runtime_file.unlink()
    refused(host, contract, qualification, 'missing runtime bytes')
    outside = host / 'unowned.json'
    outside.write_text('{}\n')
    runtime_file.symlink_to(outside)
    refused(host, contract, qualification, 'symlinked runtime bytes')

print('Host guard: positive fixture and 12 identity/source/graph/byte refusal controls passed.')
