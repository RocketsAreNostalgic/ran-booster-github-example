#!/usr/bin/env python3
"""Exercise the archive admission guard with mismatched and corrupt fixtures."""
import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parent.parent
spec = importlib.util.spec_from_file_location('core_archive', ROOT / 'scripts/check-core-archive.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class CoreArchiveTest(unittest.TestCase):
    def test_rejects_wrong_digest_pin_and_invalid_zip(self):
        with tempfile.TemporaryDirectory() as directory:
            archive = Path(directory) / 'fixture.zip'
            marker = {'schema': 'ran-booster-core-release', 'schema_version': 1,
                      'version': '1.0.0-beta.39', 'commit': 'a' * 40}
            with zipfile.ZipFile(archive, 'w') as bundle:
                bundle.writestr('ran-booster/ran-booster-release.json', json.dumps(marker))
            contract = {'archive_sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
                        'fixture_version': marker['version'], 'commit': marker['commit']}
            module.check(archive, contract)
            for key, value in [('archive_sha256', '0' * 64), ('commit', 'b' * 40),
                               ('fixture_version', '1.0.0-beta.40')]:
                altered = copy.deepcopy(contract)
                altered[key] = value
                with self.assertRaises(ValueError):
                    module.check(archive, altered)
            archive.write_bytes(b'not a zip')
            contract['archive_sha256'] = hashlib.sha256(archive.read_bytes()).hexdigest()
            with self.assertRaises(zipfile.BadZipFile):
                module.check(archive, contract)


if __name__ == '__main__':
    unittest.main()
