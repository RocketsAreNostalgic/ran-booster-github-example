#!/usr/bin/env python3
"""Reject unsupported inline PHP execution; maintained PHP belongs in checked files.

Use Python's AST and shell lexical tokens only. This does not evaluate variables,
interpret scripts, extract PHP or create a second analyzer. Fixture strings passed
to write_text remain data; passing PHP code to a process is an execution boundary.
"""
import ast
from pathlib import Path
import re
import shlex
import sys

EXCLUDED_ROOTS = {'vendor', 'dist', '.git', '.phpstan', '.phpunit.cache'}
PROCESS_FUNCTIONS = {'run', 'Popen', 'call', 'check_call', 'check_output',
                     'getoutput', 'getstatusoutput', 'system', 'popen',
                     'execv', 'execve', 'execl', 'execlp', 'execvp', 'execvpe'}
INLINE_LONG_FLAGS = {'--run', '--process-code', '--process-begin', '--process-end'}


def inline_flag(value):
    return (value.split('=', 1)[0] in INLINE_LONG_FLAGS
            or (value.startswith('-') and not value.startswith('--')
                and any(character in value[1:] for character in 'rRBE')))


def php_executable(value):
    name = value.rsplit('/', 1)[-1].lower()
    return bool(re.fullmatch(r'php(?:\d+(?:\.\d+)*)?', name)
                or re.fullmatch(r'\$\{?php(?:_binary)?\}?', name))


def shell_source(source):
    lexer = shlex.shlex(source, posix=True, punctuation_chars=';&|<>()\n')
    lexer.whitespace = ' \t\r'
    tokens = list(lexer)
    for position, token in enumerate(tokens):
        if not php_executable(token):
            continue
        end = next((index for index in range(position + 1, len(tokens))
                    if any(character in tokens[index] for character in ';\n|&')), len(tokens))
        arguments = tokens[position + 1:end]
        if any(inline_flag(argument) for argument in arguments):
            raise ValueError('inline PHP code flags require a maintained, directly checked PHP file')
        has_file = any(argument.lower().endswith(('.php', '.phar')) for argument in arguments)
        informational = any(argument in {'-v', '--version', '-h', '--help', '-i', '--info', '-m', '--modules'}
                            for argument in arguments)
        piped = position > 0 and '|' in tokens[position - 1]
        redirected = any(argument.startswith('<') or argument == '-' for argument in arguments)
        if not has_file and (piped or redirected or (not arguments and not informational)):
            raise ValueError('PHP stdin, pipe and heredoc execution requires a maintained PHP entrypoint')


def call_name(node):
    if isinstance(node, ast.Name):
        return node.id
    if isinstance(node, ast.Attribute):
        return call_name(node.value) + '.' + node.attr
    return ''


def python_source(source):
    tree = ast.parse(source)
    aliases = {}
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            aliases.update((entry.asname or entry.name, entry.name) for entry in node.names)
        elif isinstance(node, ast.ImportFrom) and node.module in {'subprocess', 'os'}:
            aliases.update((entry.asname or entry.name, node.module + '.' + entry.name)
                           for entry in node.names)
    for node in ast.walk(tree):
        if not isinstance(node, ast.Call):
            continue
        name = call_name(node.func)
        first, separator, remainder = name.partition('.')
        name = aliases.get(first, first) + (separator + remainder if separator else '')
        if name.rsplit('.', 1)[-1] not in PROCESS_FUNCTIONS:
            continue
        strings = [entry.value for argument in node.args for entry in ast.walk(argument)
                   if isinstance(entry, ast.Constant) and isinstance(entry.value, str)]
        for value in strings:
            if re.search(r'\s|[|<>;&]', value) and re.search(r'\bphp(?:\d+(?:\.\d+)*)?\b', value, re.IGNORECASE):
                shell_source(value)
        possible_php = any(php_executable(value) for value in strings)
        possible_php = possible_php or any(isinstance(entry, ast.Name) and 'php' in entry.id.lower()
                                           for argument in node.args for entry in ast.walk(argument))
        # A literal code flag is unsupported even when the executable comes from
        # PHP_BINARY. Unrelated tools retain their own flag semantics; this does
        # not evaluate arbitrary command wrappers or infer computed arguments.
        if possible_php and any(inline_flag(value) for value in strings):
            raise ValueError('inline PHP process code flags require a maintained, directly checked PHP file')
        if possible_php and any(keyword.arg in {'input', 'stdin'} for keyword in node.keywords):
            raise ValueError('passing source to PHP stdin requires a maintained PHP entrypoint')
        if possible_php and node.args and isinstance(node.args[0], (ast.List, ast.Tuple)):
            elements = node.args[0].elts
            if len(elements) == 1 or any(isinstance(element, ast.Constant) and element.value == '-'
                                         for element in elements[1:]):
                raise ValueError('PHP stdin execution requires a maintained PHP entrypoint')


def check(root):
    for path in root.rglob('*'):
        relative = path.relative_to(root)
        if relative.parts[0] in EXCLUDED_ROOTS or not path.is_file():
            continue
        extension = path.suffix.lower()
        if extension not in {'.py', '.sh'}:
            header = path.read_bytes().split(b'\n', 1)[0]
            if not header.startswith(b'#!'):
                continue
            if b'python' in header.lower():
                extension = '.py'
            elif re.search(rb'\b(?:bash|dash|zsh|ksh|sh)\b', header.lower()):
                extension = '.sh'
            else:
                continue
        try:
            source = path.read_text()
            (python_source if extension == '.py' else shell_source)(source)
        except (SyntaxError, UnicodeError, ValueError) as failure:
            raise ValueError(str(relative) + ': ' + str(failure)) from failure


if __name__ == '__main__':
    try:
        check(Path(sys.argv[1] if len(sys.argv) > 1 else Path(__file__).resolve().parent.parent).resolve())
    except (OSError, ValueError) as failure:
        sys.exit('Embedded PHP coverage: ' + str(failure))
