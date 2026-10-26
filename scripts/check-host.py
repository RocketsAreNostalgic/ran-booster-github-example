#!/usr/bin/env python3
"""Verify the source host and installed runtime copies before host-backed checks."""
import hashlib
import json
import os
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parent.parent


def git(host: Path, *arguments: str) -> str:
    return subprocess.check_output(
        ['git', '-C', str(host), *arguments], text=True, stderr=subprocess.PIPE
    ).strip()


def check_host(host: Path, contract: dict, qualification: dict) -> None:
    host = host.resolve(strict=True)
    if Path(git(host, 'rev-parse', '--show-toplevel')).resolve() != host:
        raise ValueError('Core must be the root of its own exact Git checkout')
    if git(host, 'rev-parse', 'HEAD') != contract['commit']:
        raise ValueError('Core revision differs from host-contract.json')
    if git(host, 'rev-parse', 'HEAD^{tree}') != contract['tree']:
        raise ValueError('Core tree differs from host-contract.json')
    if git(host, 'status', '--porcelain', '--untracked-files=no'):
        raise ValueError('Core tracked source must be unchanged')
    if git(host, 'ls-files', '--others', '--exclude-standard', '--', 'RAN', 'tests'):
        raise ValueError('Core source/test roots contain untracked files')
    for record in git(host, 'ls-tree', '-rz', 'HEAD').split('\0'):
        if not record:
            continue
        metadata, relative = record.split('\t', 1)
        mode, kind, digest = metadata.split(' ')
        path = host / relative
        if kind != 'blob' or mode == '120000' or path.resolve() != path or not path.is_file():
            raise ValueError('Core tracked source must contain regular exact files')
        contents = path.read_bytes()
        actual_digest = hashlib.sha1(b'blob ' + str(len(contents)).encode() + b'\0' + contents).hexdigest()
        if actual_digest != digest:
            raise ValueError('Core tracked bytes differ from the pinned Git tree')
    lock_bytes = (host / 'composer.lock').read_bytes()
    if hashlib.sha256(lock_bytes).hexdigest() != contract['composer_lock_sha256']:
        raise ValueError('Core lock differs from the qualified revision')
    lock = json.loads(lock_bytes)
    installed = json.loads((host / 'vendor/composer/installed.json').read_text())
    expected = {package['name']: package for package in lock['packages']}
    actual = {package['name']: package for package in installed['packages']}
    if installed['dev'] or set(expected) != set(actual):
        raise ValueError('Core needs exactly its locked production dependencies')
    for name, package in expected.items():
        if actual[name]['version'] != package['version'] or actual[name]['source']['reference'] != package['source']['reference']:
            raise ValueError('Core installed dependency identity differs from its lock')
    for name, package in qualification['packages'].items():
        if expected[name]['source']['reference'] != package['reference']:
            raise ValueError('Core and Example require different runtime dependency revisions')
        for relative, digest in package['files'].items():
            path = host / 'vendor' / name / relative
            if path.resolve() != path or not path.is_file() or hashlib.sha256(path.read_bytes()).hexdigest() != digest:
                raise ValueError('Core and Example runtime dependency bytes differ')
    bootstrap = (host / 'ran-booster.php').read_text()
    if "define( 'RAN_BOOSTER_PROVIDER_API_VERSION', %d );" % contract['provider_api'] not in bootstrap:
        raise ValueError('Core Provider API differs from the fixture contract')
    if not (host / 'vendor/autoload.php').is_file():
        raise ValueError('Core production autoloader is missing')


if __name__ == '__main__':
    try:
        location = os.environ.get('RAN_BOOSTER_CORE_PATH')
        if not location:
            raise ValueError('Set RAN_BOOSTER_CORE_PATH to the pinned Core checkout')
        check_host(
            Path(location), json.loads((ROOT / 'host-contract.json').read_text()),
            json.loads((ROOT / 'qualified-provider.json').read_text())
        )
    except (ValueError, OSError, KeyError, subprocess.CalledProcessError) as failure:
        raise SystemExit('check-host: ' + str(failure)) from failure
    print('Exact Core revision, clean source, locked production graph and matching runtime bytes: passed.')
