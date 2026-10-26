#!/usr/bin/env python3
"""Assemble a deterministic plugin ZIP from Composer's installed runtime graph."""
from __future__ import annotations

import hashlib
import json
import os
import re
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parent.parent
SLUG = 'ran-booster-github-example'
RUNTIME = {'ran/booster-github-provider', 'ran/updater-support', 'ran/wp-release-updater'}


def build() -> Path:
    lock = json.loads((ROOT / 'composer.lock').read_text())
    if {p['name'] for p in lock['packages']} != RUNTIME:
        raise RuntimeError('Unexpected production package graph')
    php = os.environ.get('PHP_BINARY', 'php')
    composer = os.environ.get('COMPOSER_BINARY', 'composer')
    subprocess.run([php, str(ROOT / 'scripts/qualify-dependencies.php'), '--check'], check=True)
    for source in [ROOT / 'src', ROOT / 'vendor']:
        if any(path.is_symlink() for path in source.rglob('*')):
            raise RuntimeError('Symlinked source or dependency trees are not distributable')
    header = (ROOT / (SLUG + '.php')).read_text()
    version = re.search(r'^ \* Version: ([0-9]+\.[0-9]+\.[0-9]+)$', header, re.MULTILINE)
    if version is None:
        raise RuntimeError('A single WordPress plugin version is required')
    with tempfile.TemporaryDirectory(prefix='ran-example-build-') as directory:
        stage = Path(directory) / SLUG
        stage.mkdir()
        for name in ['composer.json', 'composer.lock', 'ran-booster-github-example.php', 'uninstall.php', 'ran-booster-github-example.gh-replacement.json', 'qualified-provider.json', 'README.md', 'LICENSE']:
            source = ROOT / name
            if not source.is_file() or source.is_symlink():
                raise RuntimeError(f'Unowned build source: {name}')
            shutil.copyfile(source, stage / name)
        shutil.copytree(ROOT / 'src', stage / 'src')
        shutil.copytree(ROOT / 'languages', stage / 'languages')
        shutil.copytree(ROOT / 'vendor', stage / 'vendor')
        env = os.environ.copy()
        env['COMPOSER_HOME'] = str(Path(directory) / 'composer-home')
        env['COMPOSER_DISABLE_NETWORK'] = '1'
        env['COMPOSER_ROOT_VERSION'] = version.group(1)
        command = [composer] if not composer.endswith('.phar') else [php, composer]
        subprocess.run(command + ['install', '--working-dir=' + str(stage), '--no-dev', '--no-scripts', '--no-plugins', '--no-interaction', '--prefer-dist', '--quiet'], env=env, check=True)
        installed = json.loads((stage / 'vendor/composer/installed.json').read_text())
        if {p['name'] for p in installed['packages']} != RUNTIME or installed['dev']:
            raise RuntimeError('The archive would include development packages')
        for name in RUNTIME:
            package = stage / 'vendor' / name
            keep = {'src', 'composer.json', 'LICENSE', 'LICENSE.md', 'LICENSE.txt'}
            if name == 'ran/booster-github-provider':
                keep.add('languages')
            if name == 'ran/wp-release-updater':
                keep.update({'bootstrap.php', 'runtime.php', 'runtime-copy.json'})
            for child in package.iterdir():
                if child.name not in keep:
                    shutil.rmtree(child) if child.is_dir() else child.unlink()
        bin_dir = stage / 'vendor/bin'
        if bin_dir.exists():
            shutil.rmtree(bin_dir)
        output = ROOT / 'dist' / (SLUG + '.zip')
        output.parent.mkdir(exist_ok=True)
        with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for path in sorted(stage.rglob('*')):
                if path.is_symlink():
                    raise RuntimeError('Symlinks are forbidden in plugin archives')
                if not path.is_file():
                    continue
                relative = path.relative_to(stage)
                if any(part in {'.git', 'tests', 'node_modules', '.env'} for part in relative.parts):
                    raise RuntimeError(f'Unexpected distributable path: {relative}')
                info = zipfile.ZipInfo(SLUG + '/' + relative.as_posix(), (1980, 1, 1, 0, 0, 0))
                info.create_system = 3
                info.external_attr = 0o100644 << 16
                info.compress_type = zipfile.ZIP_DEFLATED
                archive.writestr(info, path.read_bytes(), compresslevel=9)
        digest = hashlib.sha256(output.read_bytes()).hexdigest()
        (output.with_suffix('.zip.sha256')).write_text(digest + '  ' + output.name + '\n')
        print(str(output) + '\nsha256 ' + digest)
        return output


if __name__ == '__main__':
    build()
