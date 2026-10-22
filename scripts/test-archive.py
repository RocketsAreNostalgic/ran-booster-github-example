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
    passive = r"""
define('ABSPATH', '/synthetic-only/');
$GLOBALS['hooks'] = [];
function add_action($name, $callback) { $GLOBALS['hooks'][] = $name; }
function current_user_can($capability) { return true; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES); }
if ($argv[2] !== 'missing-core') {
    define('RAN_BOOSTER_PROVIDER_API_VERSION', 15);
    require $argv[3];
}
require $argv[1] . '/ran-booster-github-example.php';
if ($GLOBALS['hooks'] !== ['ran_booster_register_providers', 'admin_notices']) { throw new RuntimeException('Unexpected plugin hooks.'); }
if (class_exists('RAN\\BoosterGitHubProvider\\V1\\GitHubProvider', false)) { throw new RuntimeException('Passive entry executed the provider.'); }
ob_start();
RAN\BoosterGitHubExample\Plugin::availability_notice();
$notice = ob_get_clean();
$expected = $argv[2] === 'missing-dependencies' ? 'dependencies are missing' : 'requires compatible Booster';
if (!str_contains($notice, $expected)) { throw new RuntimeException('Missing host/dependency did not give a bounded unavailable notice.'); }
"""
    for scenario in ['missing-core', 'old-host', 'missing-dependencies']:
        with tempfile.TemporaryDirectory(prefix='ran-example-passive-test-') as directory:
            root = Path(directory)
            bundle.extractall(root)
            plugin = root / 'ran-booster-github-example'
            methods = 'public function begin_native_mutation() {} public function complete_native_mutation() {}' if scenario == 'missing-dependencies' else ''
            context = root / 'context.php'
            context.write_text('<?php namespace RAN\\RepositoryProvider; final class ProviderRegistrationContext {' + methods + '}')
            if scenario == 'missing-dependencies':
                (plugin / 'qualified-provider.json').unlink()
            subprocess.run([os.environ.get('PHP_BINARY', 'php'), '-r', passive, str(plugin), scenario, str(context)], check=True)
    corrupted = r"""
require $argv[1] . '/src/Plugin.php';
$refused = false;
try { RAN\BoosterGitHubExample\Plugin::load_dependencies(); }
catch (RuntimeException $failure) { $refused = str_contains($failure->getMessage(), 'unqualified'); }
if (!$refused || class_exists('RAN\\BoosterGitHubProvider\\V1\\GitHubProvider', false)) { throw new RuntimeException('Unqualified vendored bytes executed.'); }
"""
    for package in ['ran/booster-github-provider', 'ran/wp-release-updater', 'ran/updater-support']:
        with tempfile.TemporaryDirectory(prefix='ran-example-corrupt-test-') as directory:
            root = Path(directory)
            bundle.extractall(root)
            plugin = root / 'ran-booster-github-example'
            with (plugin / 'vendor' / package / 'composer.json').open('a') as metadata:
                metadata.write('\n')
            subprocess.run([os.environ.get('PHP_BINARY', 'php'), '-r', corrupted, str(plugin)], check=True)
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
