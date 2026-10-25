#!/usr/bin/env python3
"""Inspect a built ZIP and run synthetic standalone uninstall in marked roots."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parent.parent
archive = ROOT / 'dist/ran-booster-github-example.zip'
with zipfile.ZipFile(archive) as bundle:
    names = bundle.namelist()
    assert names == sorted(names) and len(names) == len(set(names))
    assert all(n.startswith('ran-booster-github-example/') and '..' not in n.split('/') for n in names)
    assert not any('/tests/' in n or '/.git/' in n or '/node_modules/' in n for n in names)
    assert all(i.date_time == (1980, 1, 1, 0, 0, 0) for i in bundle.infolist())
    installed = json.loads(bundle.read('ran-booster-github-example/vendor/composer/installed.json'))
    assert {p['name'] for p in installed['packages']} == {'ran/booster-github-provider', 'ran/wp-release-updater', 'ran/updater-support'}
    assert installed['dev'] is False
    catalog = 'languages/ran-booster-github-provider.pot'
    catalog_name = 'ran-booster-github-example/vendor/ran/booster-github-provider/' + catalog
    qualification = json.loads(bundle.read('ran-booster-github-example/qualified-provider.json'))
    expected_catalog_hash = qualification['packages']['ran/booster-github-provider']['files'][catalog]
    assert catalog_name in names
    assert hashlib.sha256(bundle.read(catalog_name)).hexdigest() == expected_catalog_hash
    assert b'X-Domain: ran-booster-github-provider' in bundle.read(catalog_name)
    for scenario in ['missing-core', 'old-host', 'missing-dependencies']:
        with tempfile.TemporaryDirectory(prefix='ran-example-passive-test-') as directory:
            root = Path(directory)
            bundle.extractall(root)
            plugin = root / 'ran-booster-github-example'
            if scenario == 'missing-dependencies':
                (plugin / 'qualified-provider.json').unlink()
            subprocess.run([os.environ.get('PHP_BINARY', 'php'), str(ROOT / 'tests/archive-passive.php'), str(plugin), scenario], check=True)
    for package in ['ran/booster-github-provider', 'ran/wp-release-updater', 'ran/updater-support']:
        with tempfile.TemporaryDirectory(prefix='ran-example-corrupt-test-') as directory:
            root = Path(directory)
            bundle.extractall(root)
            plugin = root / 'ran-booster-github-example'
            with (plugin / 'vendor' / package / 'composer.json').open('a') as metadata:
                metadata.write('\n')
            subprocess.run([os.environ.get('PHP_BINARY', 'php'), str(ROOT / 'tests/archive-corrupt.php'), str(plugin)], check=True)
    for mode in ['fresh', 'preloaded', 'modified-copy', 'wrong-invocation', 'wrong-root']:
        with tempfile.TemporaryDirectory(prefix='ran-example-archive-test-') as directory:
            root = Path(directory)
            (root / 'RAN-EXAMPLE-DISPOSABLE').write_text('Synthetic archive fixture only; no WordPress installation.\n')
            bundle.extractall(root)
            plugin = root / 'ran-booster-github-example'
            shutil.copyfile(plugin / 'vendor/ran/booster-github-provider/src/ReleaseDeployments/WorkflowAssistance/WorkflowAssistanceState.php', root / 'qualified-state.php')
            if mode == 'modified-copy':
                with (root / 'qualified-state.php').open('a') as state_copy:
                    state_copy.write('\n// Deliberately unqualified historical/corrupt runtime bytes.\n')
            subprocess.run([os.environ.get('PHP_BINARY', 'php'), str(ROOT / 'tests/archive-uninstall.php'), str(plugin.resolve()), mode], check=True)
print('Archive paths/runtime graph/normalized metadata; passive missing Core/old host/missing dependencies; corrupt bytes in all three packages refused before loading; standalone namespace cleanup (fresh/compatible preloaded) and refusal (modified copy/wrong invocation/wrong root): passed.')
print('sha256 ' + hashlib.sha256(archive.read_bytes()).hexdigest())
