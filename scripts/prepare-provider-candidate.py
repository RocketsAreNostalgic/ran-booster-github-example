#!/usr/bin/env python3
"""Build an explicitly unpublished Example/Core consumer assembly from exact Git source."""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parent.parent
PROVIDER = 'ran/booster-github-provider'


def run(command, cwd=None):
    subprocess.run([str(item) for item in command], cwd=cwd, check=True)


def git(root, *arguments):
    return subprocess.check_output(['git', '-C', str(root), *arguments], text=True).strip()


def read(path):
    return json.loads(path.read_text())


def write(path, value):
    path.write_text(json.dumps(value, indent=2) + '\n')


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def checkout(root, expected):
    root = root.resolve(strict=True)
    if re.fullmatch('[a-f0-9]{40}', expected) is None or git(root, 'rev-parse', '--show-toplevel') != str(root) or git(root, 'rev-parse', 'HEAD') != expected:
        raise ValueError('An exact standalone Git checkout and matching SHA are required')
    if git(root, 'status', '--porcelain', '--untracked-files=all'):
        raise ValueError('Candidate input checkout must be clean')
    for record in git(root, 'ls-tree', '-rz', 'HEAD').split('\0'):
        if not record:
            continue
        metadata, name = record.split('\t', 1)
        mode, kind, reference = metadata.split(' ')
        path = root / name
        if kind != 'blob' or mode == '120000' or path.is_symlink() or not path.is_file():
            raise ValueError('Candidate source must contain regular tracked files')
        contents = path.read_bytes()
        if hashlib.sha1(b'blob ' + str(len(contents)).encode() + b'\0' + contents).hexdigest() != reference:
            raise ValueError('Candidate tracked bytes differ from the exact commit')
    return {'commit': expected, 'tree': git(root, 'rev-parse', 'HEAD^{tree}'), 'source_root': str(root)}


def clone(source, sha, destination):
    run(['git', 'clone', '--quiet', '--no-hardlinks', source, destination])
    run(['git', 'checkout', '--quiet', '--detach', sha], cwd=destination)


def frozen(before, after):
    for section in ('packages', 'packages-dev'):
        original = {p['name']: p for p in before[section] if p['name'] != PROVIDER}
        current = {p['name']: p for p in after[section] if p['name'] != PROVIDER}
        if original != current:
            raise ValueError('Candidate preparation changed an unrelated locked package')


def host_contract(host, identity, example):
    contract = read(example / 'host-contract.json')
    if identity['commit'] != contract['commit'] or identity['tree'] != contract['tree'] or digest(host / 'composer.lock') != contract['composer_lock_sha256']:
        raise ValueError('The source host must match the Example pinned host contract')


def fresh_output(output, roots):
    if output.exists() or output.is_symlink() or any(output.is_relative_to(root) for root in roots):
        raise ValueError('Candidate output must be new and outside all input source roots')


def composer(root, *arguments):
    executable = os.environ.get('COMPOSER_BINARY') or shutil.which('composer')
    if not executable:
        raise ValueError('Composer is required')
    run([os.environ.get('PHP_BINARY', 'php'), executable, *arguments], cwd=root)


def install_candidate(root, provider, sha, production_only):
    lock = read(root / 'composer.lock')
    install = ['install', '--no-interaction', '--prefer-dist', '--no-progress', '--no-scripts', '--no-plugins']
    if production_only:
        install.append('--no-dev')
    composer(root, *install)
    document = read(root / 'composer.json')
    document['repositories'].insert(0, {'type': 'vcs', 'url': str(provider), 'only': [PROVIDER]})
    document['require'][PROVIDER] = 'dev-candidate#' + sha
    write(root / 'composer.json', document)
    update = ['update', PROVIDER, '--minimal-changes', '--prefer-source', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins']
    if production_only:
        update.append('--no-dev')
    composer(root, *update)
    current = read(root / 'composer.lock')
    frozen(lock, current)
    package = next(p for p in current['packages'] if p['name'] == PROVIDER)
    installed = next(p for p in read(root / 'vendor/composer/installed.json')['packages'] if p['name'] == PROVIDER)
    if package['version'] != 'dev-candidate' or package['source']['reference'] != sha or installed['source'] != package['source'] or installed['installation-source'] != 'source':
        raise ValueError('Composer did not install the exact genuine candidate Git package')
    candidate = root / 'vendor' / PROVIDER
    if git(candidate, 'rev-parse', 'HEAD') != sha:
        raise ValueError('Installed candidate Git revision differs')
    for relative in ['composer.json', *[p.relative_to(provider).as_posix() for p in (provider / 'src').rglob('*.php')]]:
        if (candidate / relative).is_symlink() or (candidate / relative).read_bytes() != (provider / relative).read_bytes():
            raise ValueError('Installed Provider bytes differ from candidate source')


def host_archive(host, output, marker):
    files = {}
    entries = [line.strip() for line in (host / 'release-files.txt').read_text().splitlines() if line.strip() and not line.startswith('#')]
    for entry in entries:
        if entry == 'ran-booster-release.json':
            continue  # This is a source-host fixture, not a fabricated published Core release.
        path = PurePosixPath(entry)
        if path.is_absolute() or '..' in path.parts:
            raise ValueError('Unsafe Core runtime allowlist')
        source = host / entry
        for item in sorted(source.rglob('*')) if source.is_dir() else [source]:
            if item.is_file():
                files[item.relative_to(host).as_posix()] = item
    policy = read(host / 'runtime-packaging-policy.json')
    for package in policy['packages']:
        base = host / package['archive_root']
        for surface in package['surfaces']:
            source = base / surface['path']
            for item in sorted(source.rglob('*')) if source.is_dir() else [source]:
                if item.is_file():
                    files[item.relative_to(host).as_posix()] = item
    with zipfile.ZipFile(output, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
        for name, source in sorted(files.items()):
            if source.is_symlink() or not source.is_file():
                raise ValueError('Candidate host runtime must contain regular files')
            info = zipfile.ZipInfo('ran-booster/' + name, (1980, 1, 1, 0, 0, 0))
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            bundle.writestr(info, source.read_bytes())
        bundle.writestr('ran-booster/candidate-proof.json', json.dumps(marker, indent=2) + '\n')


def prepare(provider_root, provider_sha, host_root, host_sha, output, example_root=ROOT):
    provider_root = provider_root.resolve(strict=True)
    host_root = host_root.resolve(strict=True)
    example_root = example_root.resolve(strict=True)
    identities = {'provider': checkout(provider_root, provider_sha), 'host': checkout(host_root, host_sha), 'example': checkout(example_root, git(example_root, 'rev-parse', 'HEAD'))}
    host_contract(host_root, identities['host'], example_root)
    if read(provider_root / 'composer.json')['name'] != PROVIDER:
        raise ValueError('Candidate checkout is not the GitHub Provider package')
    fresh_output(output, (provider_root, host_root, example_root))
    output.mkdir(parents=True)
    provider = output / 'provider'
    clone(provider_root, provider_sha, provider)
    run(['git', 'branch', 'candidate', provider_sha], cwd=provider)
    example = output / 'example'
    host = output / 'host'
    clone(example_root, identities['example']['commit'], example)
    clone(host_root, host_sha, host)
    install_candidate(example, provider, provider_sha, False)
    install_candidate(host, provider, provider_sha, True)
    marker = {'mode': 'unpublished-provider-candidate', 'provider_commit': provider_sha, 'example_commit': identities['example']['commit'], 'host_commit': host_sha}
    write(example / 'candidate-proof.json', marker)
    qualifier = [os.environ.get('PHP_BINARY', 'php'), ROOT / 'scripts/qualify-dependencies.php', '--candidate-provider=' + provider_sha, '--candidate-root=' + str(example)]
    run(qualifier)
    run([*qualifier, '--check'])
    spec = importlib.util.spec_from_file_location('candidate_build', ROOT / 'scripts/build.py')
    builder = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(builder)
    artifacts = output / 'artifacts'
    artifacts.mkdir()
    archive = builder.build(example, provider_sha)
    example_archive = artifacts / archive.name
    shutil.copyfile(archive, example_archive)
    core_archive = artifacts / ('ran-booster-source-candidate-' + provider_sha + '.zip')
    host_archive(host, core_archive, marker)
    receipt = {'schema': 'candidate-provider-consumer-proof', 'schema_version': 1, 'candidate': True, 'published': False, **identities}
    for key, source, artifact in [('example', example, example_archive), ('host', host, core_archive)]:
        receipt[key].update(source_root=str(source), archive=str(artifact), sha256=digest(artifact))
    receipt['provider'].update(source_root=str(provider), manifest_sha256=digest(provider / 'composer.json'))
    write(output / 'receipt.json', receipt)
    env = os.environ.copy()
    env['RAN_PROVIDER_CONSUMER_ASSEMBLY'] = str(output)
    subprocess.run([os.environ.get('PHP_BINARY', 'php'), str(ROOT / 'tests/candidate-package.php')], env=env, check=True)
    return receipt


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--provider-root', type=Path, required=True)
    parser.add_argument('--provider-sha', required=True)
    parser.add_argument('--host-root', type=Path, required=True)
    parser.add_argument('--host-sha', required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--example-root', type=Path, default=ROOT)
    args = parser.parse_args()
    try:
        result = prepare(args.provider_root, args.provider_sha, args.host_root, args.host_sha, args.output.resolve(), args.example_root)
    except (ValueError, OSError, KeyError, subprocess.CalledProcessError) as failure:
        parser.exit(1, 'candidate preparation: ' + str(failure) + '\n')
    print(json.dumps(result, indent=2))
