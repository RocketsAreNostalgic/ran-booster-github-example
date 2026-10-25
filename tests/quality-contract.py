#!/usr/bin/env python3
"""Exercise the locked checkers and coverage guards in disposable source copies."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parent.parent
PHP = os.environ.get('PHP_BINARY', 'php')


def run(root, *args, success=True):
    result = subprocess.run([PHP, *args], cwd=root, text=True, capture_output=True)
    if success is not None and (result.returncode == 0) != success:
        raise AssertionError('Unexpected checker result: ' + ' '.join(args) + '\n' + result.stdout + result.stderr)
    return result.stdout


def coverage(root, success=True):
    return run(root, 'scripts/check-coverage.php', success=success)


def messages_for(report, root, path):
    return next((entry['messages'] for name, entry in report['files'].items()
                 if (root / name).resolve() == (root / path).resolve()), [])


with tempfile.TemporaryDirectory(prefix='ran-example-quality-') as directory:
    root = Path(directory).resolve()
    for source in ROOT.iterdir():
        if source.name in {'vendor', 'dist', '.git', '.phpstan', '.phpunit.cache'}:
            continue
        if source.is_dir():
            shutil.copytree(source, root / source.name)
        else:
            shutil.copyfile(source, root / source.name)
    (root / 'vendor').symlink_to(ROOT / 'vendor', target_is_directory=True)
    coverage(root)
    embedded = [
        ('scripts/hidden-php.py', 'import subprocess\nsubprocess.run(["php", "-r", "function ( {"], check=True)\n'),
        ('new-root/helper.py', 'import subprocess as sp\nsp.run([PHP_BINARY, "--run=function ( {"], check=True)\n'),
        ('tests/imported.py', 'from subprocess import run as execute\nexecute(["php", "-r", "function ( {"])\n'),
        ('scripts/stdin.py', 'import subprocess\nsubprocess.run(["php"], input="<?php function ( {", text=True)\n'),
        ('scripts/pipe.py', 'from subprocess import Popen\nPopen(["php"], stdin=PIPE)\n'),
        ('scripts/hidden-php.sh', '#!/bin/sh\nphp -r \'function ( {\'\n'),
        ('scripts/heredoc.sh', '#!/bin/sh\nphp <<\'PHP\'\n<?php function ( {\nPHP\n'),
        ('scripts/stdin.sh', '#!/bin/sh\nprintf \'%s\' \'<?php function ( {\' | php\n'),
        ('scripts/redirect.sh', '#!/bin/sh\nphp < hidden-source\n'),
        ('scripts/helper-command', '#!/usr/bin/env Python3\nimport subprocess\nsubprocess.run(["php", "-r", "function ( {"])\n'),
        ('scripts/executable.JSON', '<?PHP function ( {'),
    ]
    for path, source in embedded:
        target = root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(source)
        coverage(root, success=False)
        run(root, 'scripts/lint.php', success=False)
        target.unlink()
    for path, source in [
        ('scripts/fixture-data.py', 'from pathlib import Path\nPath("fixture.php").write_text(\'<?php function ( {\')\n'),
        ('scripts/quoted-data.sh', '#!/bin/sh\nprintf \'%s\\n\' \'<?php function ( {\'\n'),
        ('scripts/ordinary-process.py', 'import subprocess\nsubprocess.run(["git", "branch", "-r"], check=True)\n'),
        ('scripts/argv-data.py', 'import subprocess\nsubprocess.run(["rg", "php -r", "README.md"])\nsubprocess.run(["rg", "php", "-r"])\n'),
    ]:
        target = root / path
        target.write_text(source)
        coverage(root)
        run(root, 'scripts/lint.php')
        target.unlink()
    print('PASS: Python process aliases, inline PHP flags, stdin and shell heredoc/pipe forms fail both guards; quoted fixture data remains data.')
    paths = ['future.php', 'new-root/nested/Probe.php', 'scripts/new/probe.php',
             'tests/new/Probe.php', 'src/tests/vendor/probe.php', 'src/dist/probe.php',
             'tests/vendor/probe.php']
    for number, path in enumerate(paths):
        target = root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text('''<?php
namespace RAN\\BoosterGitHubExample\\QualityProbe;
final class ProbeNUMBER extends \\RuntimeException {
    public string $badProperty = '';
    public function badOwnedMethod( ?string $badValue ): int {
        return strlen( $badValue );
    }
    public function condition( int $value ): bool {
        return $value === 1;
    }
}
'''.replace('NUMBER', str(number)))
    coverage(root)
    report = json.loads(run(root, 'vendor/bin/phpcs', '-q', '--standard=.phpcs.xml',
                            '--report=json', *paths, success=False))
    for path in paths:
        messages = messages_for(report, root, path)
        sources = {message['source'] for message in messages}
        assert 'WordPress.PHP.YodaConditions.NotYoda' in sources, (path, sources)
        assert any('ValidMethodName' in source for source in sources), (path, sources)
        assert any('ValidVariableName' in source for source in sources), (path, sources)
    output = run(root, 'vendor/bin/phpstan', 'analyse', '--configuration=phpstan.neon',
                 '--no-progress', '--debug', '--error-format=json', '--memory-limit=512M',
                 *paths, success=False)
    report = json.loads(output[output.index('{'):])
    for path in paths:
        assert any(message['identifier'] == 'argument.type'
                   for message in messages_for(report, root, path)), path
    print('PASS: root, new, nested, script, test and dependency/cache-name collisions reach both locked checkers.')
    for path in paths:
        (root / path).unlink()

    # The locked PHPStan Finder omits dot-directories; the independent inventory
    # must expose that omission rather than silently treating a nested cache name
    # as a root cache exemption.
    hidden = root / 'tests/.phpstan/probe.php'
    hidden.parent.mkdir(parents=True, exist_ok=True)
    hidden.write_text('<?php namespace RAN\\BoosterGitHubExample\\QualityProbe;')
    coverage(root, success=False)
    hidden.unlink()

    for path in ['new-command', 'new/template.PHTML', 'new/payload.json', 'new/template.html']:
        target = root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text('<?php function ( {')
        coverage(root, success=False)
        run(root, 'scripts/lint.php', success=False)
        target.unlink()
    print('PASS: malformed extensionless, mixed-case template and metadata-named PHP cannot evade syntax or coverage.')

    xml = (root / '.phpcs.xml').read_text()
    probe = root / 'future.php'
    probe.write_text('<?php $badVariable = 1;')
    for mutation in [
        xml.replace('</ruleset>', '<exclude-pattern>future.php</exclude-pattern></ruleset>'),
        *[xml.replace('</ruleset>', '<rule ref="WordPress.NamingConventions.ValidVariableName"><severity>' + str(severity) + '</severity></rule></ruleset>')
          for severity in [0, 1, 4]],
        xml.replace('</ruleset>', '<arg name="exclude" value="WordPress.NamingConventions.ValidVariableName"/></ruleset>'),
    ]:
        (root / '.phpcs.xml').write_text(mutation)
        weakened = json.loads(run(root, 'vendor/bin/phpcs', '-q', '--standard=.phpcs.xml', '--report=json', success=None))
        assert not any('ValidVariableName' in message['source']
                       for message in messages_for(weakened, root, probe))
        coverage(root, success=False)
    (root / '.phpcs.xml').write_text(xml)
    probe.unlink()

    neon = (root / 'phpstan.neon').read_text()
    for mutation in [neon.replace('level: 8', 'level: 7'),
                     neon.replace('- .\n', '- src\n'),
                     neon.replace('- vendor/*', '- future.php\n\t\t\t- vendor/*'),
                     neon.replace('includes:\n', 'includes:\n\t- inherited.neon\n')]:
        (root / 'phpstan.neon').write_text(mutation)
        (root / 'inherited.neon').write_text('parameters:\n\tlevel: 0\n')
        coverage(root, success=False)
    (root / 'phpstan.neon').write_text(neon)
    (root / 'inherited.neon').unlink()

    for directive in ['phpcs:disable', 'PHPCS:IGNORE WordPress',
                      'phpcs:ignore WordPress.PHP.YodaConditions',
                      'phpcs:ignore WordPress.PHP.YodaConditions.NotYoda, WordPress -- broad mixture',
                      'phpcs:set WordPress.NamingConventions.PrefixAllGlobals prefixes rogue',
                      '@codingStandardsIgnoreFile', '@PHPSTAN-IGNORE-NEXT-LINE']:
        probe.write_text('<?php\n// ' + directive + '\n$badVariable = 1;\n')
        coverage(root, success=False)
    probe.write_text('<?php\n// PHPCS:IGNORE WordPress -- deliberately broad control\n$badVariable = 1;\n')
    suppressed = json.loads(run(root, 'vendor/bin/phpcs', '-q', '--standard=.phpcs.xml', '--report=json', 'future.php', success=None))
    assert not any('ValidVariableName' in message['source']
                   for message in messages_for(suppressed, root, probe))
    probe.unlink()
    coverage(root)
    print('PASS: narrowed selection, inherited/weak rules, broad and case-variant suppressions fail closed.')
