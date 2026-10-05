"""Fails when a new migration removes or tightens what the previous release still uses.

Deployments roll back to the previous image without restoring the database (deploy/deploy.sh,
docs/DEPLOYMENT.md, « Migrations : ajouter, puis retirer »): every migration after the baseline must
only expand the schema. Dropping, renaming or making a column NOT NULL without a default is a
"contract" step, allowed only in a migration that says so with a comment
`// contract: <why the previous release no longer needs it>`, shipped one release after the code
stopped using it.
"""
import pathlib
import re
import sys

MIGRATIONS = pathlib.Path('api/migrations')
# Every migration up to this one predates the rule (phase 10).
BASELINE = 'Version20261002143553'

CONTRACTING = [
    (re.compile(r'\bDROP\s+(TABLE|COLUMN|INDEX|CONSTRAINT|TYPE|SEQUENCE)\b', re.I), 'drops'),
    (re.compile(r'\bRENAME\b', re.I), 'renames'),
    (re.compile(r'\bSET\s+NOT\s+NULL\b', re.I), 'makes a column NOT NULL'),
    (re.compile(r'\bALTER\s+(COLUMN\s+)?\S+\s+TYPE\b', re.I), 'changes a column type'),
]


def up_body(source: str) -> str:
    """The up() method only: down() reverses the migration and may drop anything."""
    match = re.search(r'function\s+up\s*\([^)]*\)[^{]*\{(.*?)\n    \}', source, re.S)
    return match.group(1) if match else source


def main() -> int:
    problems = []
    for path in sorted(MIGRATIONS.glob('Version*.php')):
        if path.stem <= BASELINE:
            continue
        source = path.read_text(encoding='utf-8')
        if re.search(r'//\s*contract:\s*\S', source):
            continue
        body = up_body(source)
        found = [what for pattern, what in CONTRACTING if pattern.search(body)]
        # ADD <column> … NOT NULL in one clause (up to a comma or the end of the statement), without DEFAULT.
        for clause in re.findall(r'\bADD\s+(?:COLUMN\s+)?[^,;\'"]+', body, re.I):
            if re.search(r'\bNOT\s+NULL\b', clause, re.I) and not re.search(r'\bDEFAULT\b', clause, re.I) \
                    and not re.match(r'ADD\s+(CONSTRAINT|PRIMARY|UNIQUE|FOREIGN|INDEX)\b', clause, re.I):
                found.append('adds a NOT NULL column without a default')
                break
        for what in found:
            problems.append(f'{path}: {what}: the previous release may still need it '
                            '(add the column nullable or with a default, drop it one release later '
                            'with a "// contract: <why>" comment)')
    for problem in problems:
        print(problem, file=sys.stderr)
    if not problems:
        print('Migrations: every one after the baseline only expands the schema.')
    return 1 if problems else 0


if __name__ == '__main__':
    sys.exit(main())
