#!/usr/bin/env python3
"""Exercise the local release boundaries without changing remote release state."""
import copy
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parent.parent
SPEC = importlib.util.spec_from_file_location('release', ROOT / 'scripts/release.py')
release = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(release)


class ReleaseContracts(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='ran-example-release-test-')
        self.root = Path(self.temporary.name)
        for name in ('release-please-config.json', '.release-please-manifest.json', 'version.txt',
                     'ran-booster-github-example.php', 'uninstall.php', 'composer.json', 'composer.lock'):
            shutil.copyfile(ROOT / name, self.root / name)
        shutil.copytree(ROOT / 'src', self.root / 'src')
        self.git('init', '--quiet', '--initial-branch=main')
        self.git('config', 'user.name', 'Release Contract Fixture')
        self.git('config', 'user.email', 'release@example.invalid')
        self.commit('chore: seed release fixture')
        self.base = self.git('rev-parse', 'HEAD')

    def tearDown(self):
        self.temporary.cleanup()

    def git(self, *arguments):
        return release.git(self.root, *arguments)

    def commit(self, message):
        self.git('add', '.')
        self.git('commit', '--quiet', '-m', message)
        return self.git('rev-parse', 'HEAD')

    def write_json(self, name, value):
        (self.root / name).write_text(json.dumps(value, indent=2) + '\n')

    def make_zip(self, extra=None, changed=None):
        output = self.root / 'dist' / (release.SLUG + '.zip')
        output.parent.mkdir(exist_ok=True)
        paths = [release.SLUG + '.php', 'uninstall.php']
        paths.extend(path.relative_to(self.root).as_posix() for path in (self.root / 'src').rglob('*.php'))
        with zipfile.ZipFile(output, 'w') as archive:
            for path in paths:
                contents = (self.root / path).read_bytes()
                archive.writestr(release.SLUG + '/' + path, contents if path != changed else contents + b'\n')
            if extra:
                archive.writestr(extra, b'fixture')
        return output

    def test_configuration_and_version_sources_agree(self):
        self.assertEqual(release.check_config(self.root), (ROOT / 'version.txt').read_text().strip())

    def test_profile_b_must_keep_draft_and_forced_tag(self):
        for setting in ('draft', 'force-tag-creation'):
            config = release.document(self.root / 'release-please-config.json')
            config[setting] = False
            self.write_json('release-please-config.json', config)
            with self.assertRaisesRegex(ValueError, 'Profile B'):
                release.check_config(self.root)
            shutil.copyfile(ROOT / 'release-please-config.json', self.root / 'release-please-config.json')

    def test_mismatched_version_is_rejected(self):
        (self.root / 'version.txt').write_text('99.0.0\n')
        with self.assertRaisesRegex(ValueError, 'must agree'):
            release.check_config(self.root)

    def test_production_lock_uptake_requires_release_driving_title(self):
        lock = release.document(self.root / 'composer.lock')
        lock['packages'][0]['version'] += '-fixture'
        self.write_json('composer.lock', lock)
        head = self.commit('fix(deps): update locked runtime dependency')
        with self.assertRaisesRegex(ValueError, 'production dependency changes'):
            release.classify(self.root, self.base, head, 'build: update locked runtime dependency')
        self.assertTrue(release.classify(self.root, self.base, head, 'fix(deps): update locked runtime dependency'))
        self.assertTrue(release.classify(self.root, self.base, head, 'refactor!: replace the runtime dependency contract'))

    def test_merge_commit_delivery_requires_a_retained_driving_commit(self):
        lock = release.document(self.root / 'composer.lock')
        lock['packages'][0]['version'] += '-fixture'
        self.write_json('composer.lock', lock)
        head = self.commit('build: hidden runtime uptake')
        with self.assertRaisesRegex(ValueError, 'retain a release-driving commit'):
            release.classify(self.root, self.base, head, 'fix(deps): adopt runtime dependency')

    def test_requirement_uptake_and_php_floor_drive_releases(self):
        composer = release.document(self.root / 'composer.json')
        before = copy.deepcopy(composer)
        composer['require']['php'] = '^8.4'
        self.assertTrue(release.significant(before, composer, {'packages': []}, {'packages': []}, []))

    def test_development_only_dependency_does_not_require_release(self):
        lock = release.document(self.root / 'composer.lock')
        lock['packages-dev'][0]['version'] += '-fixture'
        self.write_json('composer.lock', lock)
        head = self.commit('chore(deps): update development dependency')
        self.assertFalse(release.classify(self.root, self.base, head, 'chore(deps): update development dependency'))

    def test_exact_head_is_required(self):
        (self.root / 'README.md').write_text('fixture\n')
        self.commit('docs: add fixture readme')
        with self.assertRaisesRegex(ValueError, 'exact checked-out'):
            release.classify(self.root, self.base, self.base, 'docs: add fixture readme')

    def test_advanced_base_does_not_attribute_its_runtime_changes_to_docs_branch(self):
        self.git('branch', 'docs')
        (self.root / 'src' / 'MainOnly.php').write_text((self.root / 'src' / 'Plugin.php').read_text())
        advanced = self.commit('fix: advance main')
        self.git('checkout', '--quiet', 'docs')
        (self.root / 'README.md').write_text('fixture\n')
        head = self.commit('docs: add readme')
        self.assertFalse(release.classify(self.root, advanced, head, 'docs: add readme'))

    def test_candidate_rejects_source_change(self):
        (self.root / 'src' / 'Plugin.php').write_text((self.root / 'src' / 'Plugin.php').read_text() + '\n')
        head = self.commit('chore(main): release fixture')
        with self.assertRaisesRegex(ValueError, 'only its version sources'):
            release.candidate(self.root, self.base, head)

    def test_archive_requires_exact_owned_php_and_paths(self):
        self.make_zip()
        release.verify_archive(self.root)
        self.make_zip(changed='src/Plugin.php')
        with self.assertRaisesRegex(ValueError, 'differs from source'):
            release.verify_archive(self.root)
        self.make_zip(extra=release.SLUG + '/unexpected.php')
        with self.assertRaisesRegex(ValueError, 'population differs'):
            release.verify_archive(self.root)
        self.make_zip(extra=release.SLUG + '/../escape')
        with self.assertRaisesRegex(ValueError, 'unsafe'):
            release.verify_archive(self.root)

    def test_promotion_preserves_tested_bytes_and_exact_identity(self):
        original = self.make_zip().read_bytes()
        manifest_path = release.promote(self.root, self.base)
        manifest = release.document(manifest_path)
        self.assertEqual(manifest['quality_commit'], self.base)
        self.assertEqual(manifest['source_commit'], self.base)
        self.assertEqual(manifest['repository'], release.REPOSITORY)
        self.assertEqual(len(manifest['assets']), 2)
        self.assertEqual((manifest_path.parent / manifest['assets'][0]['name']).read_bytes(), original)
        for asset in manifest['assets']:
            self.assertEqual(release.hashlib.sha256((manifest_path.parent / asset['name']).read_bytes()).hexdigest(), asset['sha256'])
        (self.root / 'src' / 'Extra.php').write_text((self.root / 'src' / 'Plugin.php').read_text())
        with self.assertRaisesRegex(ValueError, 'untracked runtime'):
            release.promote(self.root, self.base)
        (self.root / 'src' / 'Extra.php').unlink()
        (self.root / 'src' / 'Plugin.php').write_text('modified\n')
        with self.assertRaisesRegex(ValueError, 'modified tracked'):
            release.promote(self.root, self.base)

    def test_workflows_retain_read_only_evidence_and_shared_publisher(self):
        quality = (ROOT / '.github/workflows/quality.yml').read_text()
        publisher = (ROOT / '.github/workflows/release-please.yml').read_text()
        self.assertIn('workflow_dispatch:', quality)
        self.assertIn('edited', quality)
        self.assertIn('composer check', quality)
        self.assertIn('release-install-smoke.sh', quality)
        self.assertIn('needs: [project]', quality)
        self.assertIn('PROJECT_RESULT" = success', quality)
        self.assertNotIn('pull_request_target', quality)
        self.assertNotRegex(quality, r'(?m)^\s+(contents|issues|pull-requests|actions): write$')
        self.assertNotIn('secrets: inherit', publisher)
        self.assertIn('permissions: {}', publisher)
        self.assertIn('workflows: [Quality]', publisher)
        self.assertRegex(publisher, r'release-profile-b\.yml@[0-9a-f]{40}')
        for workflow in (quality, publisher):
            for line in workflow.splitlines():
                if 'uses:' in line:
                    self.assertRegex(line, r'@[0-9a-f]{40}(?:\s|$)')


if __name__ == '__main__':
    unittest.main()
