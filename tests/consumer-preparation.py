#!/usr/bin/env python3
"""Exercise exact-input and frozen-graph boundaries without network or release mutation."""
import copy
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parent.parent
SPEC = importlib.util.spec_from_file_location('candidate', ROOT / 'scripts/prepare-provider-candidate.py')
candidate = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(candidate)


class PreparationControls(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='ran-consumer-prep-control-')
        self.root = Path(self.temporary.name)
        self.git('init', '--quiet')
        self.git('config', 'user.name', 'Candidate test')
        self.git('config', 'user.email', 'candidate@example.invalid')
        (self.root / 'composer.json').write_text('{"name":"ran/booster-github-provider"}\n')
        self.git('add', 'composer.json')
        self.git('commit', '--quiet', '-m', 'test: candidate source')
        self.sha = candidate.git(self.root, 'rev-parse', 'HEAD')

    def tearDown(self):
        self.temporary.cleanup()

    def git(self, *args):
        subprocess.run(['git', '-C', str(self.root), *args], check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

    def test_clean_exact_source_is_accepted(self):
        self.assertEqual(candidate.checkout(self.root, self.sha)['commit'], self.sha)

    def test_wrong_sha_dirty_and_untracked_source_are_rejected(self):
        with self.assertRaisesRegex(ValueError, 'matching SHA'):
            candidate.checkout(self.root, 'a' * 40)
        (self.root / 'composer.json').write_text('changed\n')
        with self.assertRaisesRegex(ValueError, 'must be clean'):
            candidate.checkout(self.root, self.sha)
        self.git('checkout', '--', 'composer.json')
        (self.root / 'extra.php').write_text('fixture data')
        with self.assertRaisesRegex(ValueError, 'must be clean'):
            candidate.checkout(self.root, self.sha)

    def test_hidden_tracked_byte_change_is_rejected(self):
        self.git('update-index', '--assume-unchanged', 'composer.json')
        (self.root / 'composer.json').write_text('hidden change\n')
        with self.assertRaisesRegex(ValueError, 'tracked bytes differ'):
            candidate.checkout(self.root, self.sha)

    def test_only_provider_may_change_in_the_locked_graph(self):
        lock = {'packages': [{'name': candidate.PROVIDER, 'version': 'released'}, {'name': 'ran/updater-support', 'version': 'frozen'}], 'packages-dev': [{'name': 'phpunit/phpunit', 'version': 'frozen'}]}
        changed = copy.deepcopy(lock)
        changed['packages'][0]['version'] = 'dev-candidate'
        candidate.frozen(lock, changed)
        for section in ('packages', 'packages-dev'):
            bad = copy.deepcopy(changed)
            bad[section][-1]['version'] = 'drift'
            with self.assertRaisesRegex(ValueError, 'unrelated locked package'):
                candidate.frozen(lock, bad)

    def test_normal_builder_refuses_candidate_marker(self):
        spec = importlib.util.spec_from_file_location('build', ROOT / 'scripts/build.py')
        builder = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(builder)
        (self.root / 'candidate-proof.json').write_text('{}\n')
        with self.assertRaisesRegex(RuntimeError, 'cannot produce release artifacts'):
            builder.build(self.root)

    def test_release_archive_and_promotion_reject_candidate_assemblies(self):
        spec = importlib.util.spec_from_file_location('release', ROOT / 'scripts/release.py')
        release = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(release)
        (self.root / 'candidate-proof.json').write_text('{}\n')
        with self.assertRaisesRegex(ValueError, 'Candidate assemblies'):
            release.verify_archive(self.root)
        (self.root / 'candidate-proof.json').unlink()
        (self.root / 'composer.lock').write_text(json.dumps({'packages': [{'name': candidate.PROVIDER, 'version': 'dev-candidate'}]}))
        with self.assertRaisesRegex(ValueError, 'Unreleased candidate dependency graphs'):
            release.check_config(self.root)

    def test_host_source_identity_and_lock_must_match_pinned_contract(self):
        (self.root / 'composer.lock').write_text('{}\n')
        identity = {'commit': self.sha, 'tree': 'b' * 40}
        contract = {**identity, 'composer_lock_sha256': candidate.digest(self.root / 'composer.lock')}
        (self.root / 'host-contract.json').write_text(json.dumps(contract))
        candidate.host_contract(self.root, identity, self.root)
        for key in ('commit', 'tree'):
            with self.assertRaisesRegex(ValueError, 'pinned host contract'):
                candidate.host_contract(self.root, {**identity, key: 'c' * 40}, self.root)
        (self.root / 'composer.lock').write_text('changed\n')
        with self.assertRaisesRegex(ValueError, 'pinned host contract'):
            candidate.host_contract(self.root, identity, self.root)

    def test_output_cannot_reuse_or_change_an_input_checkout(self):
        with self.assertRaisesRegex(ValueError, 'outside all input'):
            candidate.fresh_output(self.root / 'new-output', [self.root])
        with self.assertRaisesRegex(ValueError, 'must be new'):
            candidate.fresh_output(self.root, [])


if __name__ == '__main__':
    unittest.main()
