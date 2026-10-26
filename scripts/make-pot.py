#!/usr/bin/env python3
"""Generate/check the runtime catalogue with WP-CLI's maintained gettext parser."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parent.parent
TARGET = ROOT / 'languages/ran-booster-github-example.pot'


def generate(destination: Path, all_domains: bool) -> bytes:
    executable = os.environ.get('WP_CLI_BINARY') or shutil.which('wp')
    if not executable:
        raise ValueError('WP-CLI 2.12.0 is required')
    command = [os.environ.get('PHP_BINARY', 'php'), '-d', 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED', executable]
    if subprocess.check_output(command + ['--version'], text=True).strip() != 'WP-CLI 2.12.0':
        raise ValueError('WP-CLI 2.12.0 is required')
    result = subprocess.run(command + [
        'i18n', 'make-pot', str(ROOT), str(destination),
        '--ignore-domain' if all_domains else '--domain=ran-booster-github-example',
        '--include=ran-booster-github-example.php,uninstall.php,src',
        '--skip-block-json', '--skip-theme-json',
        '--package-name=RAN Booster — GitHub Example',
        '--headers=' + json.dumps({
            'Project-Id-Version': 'RAN Booster GitHub Example',
            'POT-Creation-Date': '2026-10-25T00:00:00+00:00',
            'Report-Msgid-Bugs-To': 'https://github.com/RocketsAreNostalgic/ran-booster-github-example/issues',
        }),
    ], check=True, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    if 'Warning:' in result.stdout:
        raise ValueError('Gettext extraction reported a warning: ' + result.stdout)
    return destination.read_bytes()


if __name__ == '__main__':
    if sys.argv[1:] not in ([], ['--check']):
        raise SystemExit('Usage: make-pot.py [--check]')
    with tempfile.TemporaryDirectory(prefix='ran-example-i18n-') as directory:
        temporary = Path(directory)
        expected = generate(temporary / 'expected.pot', False)
        all_domains = generate(temporary / 'all.pot', True)
        normalize = lambda content: b'\n'.join(line for line in content.splitlines() if not line.startswith(b'"X-Domain:'))
        if normalize(expected) != normalize(all_domains):
            raise SystemExit('Every runtime gettext string must use ran-booster-github-example')
        if '--check' in sys.argv:
            if not TARGET.is_file() or TARGET.read_bytes() != expected:
                raise SystemExit('Runtime catalogue is stale; run composer i18n:pot')
        else:
            TARGET.parent.mkdir(exist_ok=True)
            TARGET.write_bytes(expected)
    print('Runtime gettext catalogue and text domains: passed.')
