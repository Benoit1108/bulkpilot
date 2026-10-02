"""Checks that every test docs/ACCEPTANCE.md cites exists (run by `make qa`).

References, as the document writes them:
- `Some/PathTest.php::testName` — a PHPUnit test under api/tests/ (the path may start with api/tests/);
- `::testName` — the same, in the file cited last;
- a title « … » (three words at least; « … » marks words left out) — a Playwright or Vitest test whose
  title holds those words, on one line of an e2e or Vitest file.
A renamed or deleted test then fails the QA instead of leaving a dead reference.
"""
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
DOC = ROOT / 'docs' / 'ACCEPTANCE.md'
PHP_ROOT = ROOT / 'api' / 'tests'
E2E = sorted((ROOT / 'pwa' / 'e2e').rglob('*.spec.ts'))
VITEST = sorted((ROOT / 'pwa' / 'test').rglob('*.test.ts'))

text = DOC.read_text(encoding='utf-8')
errors = []
php_file = None
front_file = None
token = re.compile(r'`([^`]+)`|«\s*([^»]+?)\s*»')
for number, line in enumerate(text.splitlines(), 1):
    for code, title in token.findall(line):
        if code:
            match = re.fullmatch(r'(?:api/tests/)?([\w/]+\.php)?::(test\w+)', code)
            if match:
                if match.group(1):
                    php_file = PHP_ROOT / match.group(1)
                if php_file is None:
                    errors.append(f'{number}: {code} — which file?')
                elif not php_file.is_file():
                    errors.append(f'{number}: {php_file.relative_to(ROOT)} does not exist')
                elif not re.search(rf'function {match.group(2)}\(', php_file.read_text(encoding='utf-8')):
                    errors.append(f'{number}: {php_file.relative_to(ROOT)} has no {match.group(2)}()')
                continue
            if re.fullmatch(r'[\w/.-]+\.php', code):
                php_file = PHP_ROOT / code.removeprefix('api/tests/')
                if not php_file.is_file():
                    errors.append(f'{number}: {code} does not exist')
                continue
            if re.fullmatch(r'[\w/.-]+\.(spec|test)\.ts', code):
                candidates = [path for path in E2E + VITEST if path.as_posix().endswith(code.removeprefix('pwa/'))]
                if not candidates:
                    errors.append(f'{number}: {code} does not exist')
                    front_file = None
                else:
                    front_file = candidates[0]
                continue
        # A title is three words at least (« plus tard » is a button, not a test); « … » stands for
        # the words left out of a long one.
        if title and len(title.split()) >= 3:
            parts = [part.strip() for part in title.split('…') if part.strip()]
            def holds(path: pathlib.Path) -> bool:
                content = path.read_text(encoding='utf-8')
                return any(all(part in line for part in parts) for line in content.splitlines())
            pool = ([front_file] if front_file else []) + E2E + VITEST
            if not any(holds(path) for path in pool):
                errors.append(f'{number}: no e2e or Vitest test titled « {title.strip()} »')

for error in errors:
    print(f'docs/ACCEPTANCE.md:{error}')
print(f'ACCEPTANCE.md: {len(errors)} dead reference(s).' if errors else 'ACCEPTANCE.md: every reference is a test.')
sys.exit(1 if errors else 0)
