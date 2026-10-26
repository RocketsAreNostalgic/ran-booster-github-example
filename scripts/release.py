#!/usr/bin/env python3
"""Check local release inputs and emit Profile B metadata; never publish."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parent.parent
SLUG = 'ran-booster-github-example'
REPOSITORY = 'RocketsAreNostalgic/' + SLUG
SHA = re.compile(r'[0-9a-f]{40}')
VERSION = re.compile(r'(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?')
TITLE = re.compile(r'([a-z][a-z0-9-]*)(?:\([^\r\n()]+\))?(!)?: +\S')
RELEASE_FILES = {'.release-please-manifest.json', 'version.txt', SLUG + '.php', 'CHANGELOG.md'}


def document(path: Path):
    return json.loads(path.read_text())


def git(root: Path, *arguments: str) -> str:
    return subprocess.check_output(['git', '-C', str(root), *arguments], text=True).strip()


def version(root: Path = ROOT) -> str:
    value = (root / 'version.txt').read_text().strip()
    if VERSION.fullmatch(value) is None:
        raise ValueError('version.txt must contain one supported SemVer version')
    header = (root / (SLUG + '.php')).read_text()
    values = re.findall(r'^ \* Version: ([^\r\n]+)$', header, re.MULTILINE)
    if values != [value] or document(root / '.release-please-manifest.json') != {'.': value}:
        raise ValueError('plugin header, version.txt and Release Please manifest must agree')
    marked = re.findall(r'x-release-please-start-version\r?\n(.*?)x-release-please-end', header, re.DOTALL)
    if len(marked) != 1 or re.findall(r'^ \* Version: (.+)$', marked[0], re.MULTILINE) != [value]:
        raise ValueError('the plugin Version header needs its own generic updater block')
    if len(VERSION.findall(marked[0])) != 1:
        raise ValueError('the generic updater block must contain only the plugin version')
    return value


def check_config(root: Path = ROOT) -> str:
    if (root / 'candidate-proof.json').exists():
        raise ValueError('Candidate assemblies cannot qualify for release or promotion')
    if any(package['version'].startswith('dev-') or package['version'].endswith('-dev') for package in document(root / 'composer.lock')['packages']):
        raise ValueError('Unreleased candidate dependency graphs cannot qualify for release or promotion')
    value = version(root)
    config = document(root / 'release-please-config.json')
    package = config['packages']['.']
    if config.get('draft') is not True or config.get('force-tag-creation') is not True or config.get('skip-github-release'):
        raise ValueError('Profile B requires draft releases and force-tag-creation')
    if config['packages'].keys() != {'.'} or package.get('release-type') != 'simple' or package.get('package-name') != SLUG:
        raise ValueError('the simple strategy must manage exactly the root Example component')
    if package.get('include-component-in-tag') is not False or package.get('include-v-in-tag') is not True:
        raise ValueError('release tags must have the v<version> form')
    if package.get('extra-files') != [{'type': 'generic', 'path': SLUG + '.php'}]:
        raise ValueError('the plugin header must be managed by the generic updater')
    if not SHA.fullmatch(config.get('bootstrap-sha', '')):
        raise ValueError('bootstrap-sha must identify the deliberate history boundary')
    visible_types(config)
    return value


def visible_types(config) -> set[str]:
    sections = config['packages']['.']['changelog-sections']
    types = {item['type'] for item in sections if item.get('hidden') is False}
    if not {'fix', 'feat'}.issubset(types):
        raise ValueError('fix and feat must remain visible release-driving classifications')
    return types


def production_graph(lock) -> list:
    return sorted(lock.get('packages', []), key=lambda item: item['name'])


def significant(base_composer, head_composer, base_lock, head_lock, paths) -> bool:
    runtime_keys = ('require', 'autoload', 'type', 'license')
    if any(base_composer.get(key) != head_composer.get(key) for key in runtime_keys):
        return True
    if production_graph(base_lock) != production_graph(head_lock):
        return True
    return any(path.startswith(('src/', 'languages/')) or path in {
        SLUG + '.php', 'uninstall.php', SLUG + '.gh-replacement.json',
        'qualified-provider.json', 'scripts/build.py', 'LICENSE',
    } for path in paths)


def classify(root: Path, base: str, head: str, title: str) -> bool:
    if not SHA.fullmatch(base) or not SHA.fullmatch(head) or git(root, 'rev-parse', 'HEAD') != head:
        raise ValueError('classification requires the exact checked-out PR base/head SHAs')
    common = git(root, 'merge-base', base, head)
    paths = git(root, 'diff', '--name-only', common, head).splitlines()
    read_at = lambda name: json.loads(git(root, 'show', common + ':' + name))
    required = significant(read_at('composer.json'), document(root / 'composer.json'),
                           read_at('composer.lock'), document(root / 'composer.lock'), paths)
    if required:
        match = TITLE.match(title)
        types = visible_types(document(root / 'release-please-config.json'))
        if match is None or (match.group(2) != '!' and match.group(1) not in types):
            raise ValueError('runtime or production dependency changes require a visible Conventional Commit title (normally fix(deps):), or explicit breaking !')
        subjects = git(root, 'log', '--format=%s', common + '..' + head).splitlines()
        driving = [TITLE.match(subject) for subject in subjects]
        if not any(item is not None and (item.group(2) == '!' or item.group(1) in types) for item in driving):
            raise ValueError('a release-significant PR must also retain a release-driving commit for merge-commit delivery')
    return required


def candidate(root: Path, base: str, head: str) -> None:
    if not SHA.fullmatch(base) or not SHA.fullmatch(head) or git(root, 'rev-parse', 'HEAD') != head:
        raise ValueError('candidate validation requires the exact checked-out base/head SHAs')
    common = git(root, 'merge-base', base, head)
    paths = set(git(root, 'diff', '--name-only', common, head).splitlines())
    if not paths or not paths.issubset(RELEASE_FILES):
        raise ValueError('a release candidate may change only its version sources and changelog')
    current = check_config(root)
    previous = json.loads(git(root, 'show', common + ':.release-please-manifest.json'))['.']
    if previous == current:
        raise ValueError('release candidate must change the version')
    before = git(root, 'show', common + ':' + SLUG + '.php')
    after = (root / (SLUG + '.php')).read_text().strip()
    replace = lambda content: re.sub(r'(^ \* Version: )[^\r\n]+', r'\1<version>', content, flags=re.MULTILINE)
    if replace(before) != replace(after):
        raise ValueError('release candidate changed plugin code outside its version header')
    notes = (root / 'CHANGELOG.md').read_text()
    if re.search(r'^## .*' + re.escape(current), notes, re.MULTILINE) is None:
        raise ValueError('release candidate changelog must identify the candidate version')


def verify_archive(root: Path = ROOT) -> Path:
    value = check_config(root)
    source_zip = root / 'dist' / (SLUG + '.zip')
    expected_php = {SLUG + '.php', 'uninstall.php'}
    expected_php.update(path.relative_to(root).as_posix() for path in (root / 'src').rglob('*.php'))
    with zipfile.ZipFile(source_zip) as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise ValueError('archive contains duplicate paths')
        own_php = set()
        for name in names:
            parts = name.split('/')
            if parts[0] != SLUG or any(part in ('', '.', '..') for part in parts):
                raise ValueError('archive contains an unsafe or unowned path')
            relative = '/'.join(parts[1:])
            if relative.endswith('.php') and not relative.startswith('vendor/'):
                own_php.add(relative)
        if own_php != expected_php:
            raise ValueError('archive first-party PHP population differs from maintained runtime source')
        for relative in expected_php:
            source = root / relative
            if source.is_symlink() or archive.read(SLUG + '/' + relative) != source.read_bytes():
                raise ValueError('archive first-party PHP differs from source: ' + relative)
        header = archive.read(SLUG + '/' + SLUG + '.php').decode()
        if re.findall(r'^ \* Version: (.+)$', header, re.MULTILINE) != [value]:
            raise ValueError('tested archive version does not match release inputs')
    return source_zip


def promote(root: Path, source: str) -> Path:
    if not SHA.fullmatch(source) or git(root, 'rev-parse', 'HEAD') != source:
        raise ValueError('promotion metadata requires the exact checked-out Quality commit')
    if git(root, 'status', '--porcelain', '--untracked-files=no'):
        raise ValueError('promotion metadata refuses modified tracked source')
    if git(root, 'ls-files', '--others', '--exclude-standard', '--', 'src', SLUG + '.php', 'uninstall.php', 'languages'):
        raise ValueError('promotion metadata refuses untracked runtime source')
    if os.environ.get('GITHUB_REPOSITORY', REPOSITORY) != REPOSITORY:
        raise ValueError('promotion repository identity does not match the Example')
    value = check_config(root)
    source_zip = verify_archive(root)
    target = root / 'dist' / 'release'
    target.mkdir(exist_ok=True)
    archive = target / f'{SLUG}-{value}.zip'
    shutil.copyfile(source_zip, archive)
    checksum = archive.with_suffix('.zip.sha256')
    checksum.write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n')
    assets = [{'name': path.name, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()} for path in (archive, checksum)]
    manifest = target / 'ran-profile-b-promotion.json'
    manifest.write_text(json.dumps({
        'schema': 'ran-profile-b-promotion', 'schema_version': 1,
        'repository': REPOSITORY, 'quality_commit': source, 'source_commit': source,
        'tag': 'v' + value, 'assets': assets,
    }, indent=2) + '\n')
    if set(path.name for path in target.iterdir()) != {archive.name, checksum.name, manifest.name}:
        raise ValueError('unexpected files in the release artifact directory')
    return manifest


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    sub.add_parser('check')
    sub.add_parser('archive')
    for command in ('classify', 'candidate'):
        child = sub.add_parser(command)
        child.add_argument('--base', required=True)
        child.add_argument('--head', required=True)
    child = sub.add_parser('promote')
    child.add_argument('--source', required=True)
    args = parser.parse_args()
    if args.command == 'check':
        print('Release inputs agree at ' + check_config())
    elif args.command == 'archive':
        print('Archive first-party PHP matches maintained runtime source: ' + str(verify_archive()))
    elif args.command == 'classify':
        print('Release-significant change: ' + str(classify(ROOT, args.base, args.head, os.environ.get('RAN_RELEASE_PR_TITLE', ''))))
    elif args.command == 'candidate':
        candidate(ROOT, args.base, args.head)
        print('Release candidate changes only coherent version/changelog inputs')
    else:
        print(promote(ROOT, args.source))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, KeyError, OSError, subprocess.CalledProcessError) as failure:
        print('release: ' + str(failure), file=sys.stderr)
        sys.exit(1)
