#!/usr/bin/env python3
"""Validate release payloads and immutable SVN tags. No publishing operations."""
import argparse
import hashlib
import json
import pathlib
import re
import shutil
import stat
import struct
import subprocess
import tempfile
import zipfile

ROOT_FILES = {'LICENSE', 'joshternet.php', 'uninstall.php', 'readme.txt', 'screenshot-1.png', 'screenshot-2.png'}
INCLUDES = {'class-joshternet-' + name + '.php' for name in ('admin', 'button', 'endpoint', 'origin')}
BUTTONS = {name + '.png' for name in ('join-the-joshternet', 'undeclared', 'verified-josh', 'verified-non-josh')}
EXPECTED = ROOT_FILES | {'includes/' + name for name in INCLUDES} | {'assets/buttons/' + name for name in BUTTONS}
APPROVED_SHA = '951ff1ffb1c5fec4f318415ac6039fab2ac1cbaf33a191f61ed7605eb83a02c4'


def require(condition, message):
    if not condition:
        raise ValueError(message)


def version(value):
    require(re.fullmatch(r'\d+\.\d+\.\d+', value), 'Expected numeric X.Y.Z version')
    return value


def manifest(directory):
    root = pathlib.Path(directory)
    require(root.is_dir(), 'Payload directory missing')
    require(not any(p.is_symlink() for p in root.rglob('*')), 'Symlinks are forbidden')
    return {p.relative_to(root).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
            for p in sorted(root.rglob('*')) if p.is_file()}


def validate_payload(directory, release_version):
    root = pathlib.Path(directory)
    files = manifest(root)
    require(set(files) == EXPECTED, 'Unexpected/missing package files: ' + str(sorted(set(files) ^ EXPECTED)))
    plugin = (root / 'joshternet.php').read_text()
    readme = (root / 'readme.txt').read_text()
    for pattern, text in ((r'Version:\s*([^\s]+)', plugin),
                          (r"define\( 'JOSHTERNET_VERSION', '([^']+)' \);", plugin),
                          (r'^Stable tag:\s*(\S+)', readme)):
        match = re.search(pattern, text, re.M)
        require(match and match.group(1) == release_version, 'Version mismatch')
    require(readme.startswith('=== Joshternet ===\n'), 'Invalid readme title')
    require('== External services ==' in readme, 'Missing external service disclosure')
    for link in ('https://joshternet.org/api/button-state', 'https://joshternet.org/terms/', 'https://joshternet.org/privacy/'):
        require(link in readme, 'Missing disclosure: ' + link)
    captions = readme.split('== Screenshots ==', 1)[1].split('==', 1)[0]
    require(re.findall(r'^(\d+)\. ', captions, re.M) == ['1', '2'], 'Screenshot captions must match assets')
    return files


def extract(archive, destination, release_version, approved=False):
    data = pathlib.Path(archive).read_bytes()
    if approved:
        require(hashlib.sha256(data).hexdigest() == APPROVED_SHA, 'Approved ZIP checksum mismatch')
        require(release_version == '0.1.1', 'Approved mode only supports 0.1.1')
    with zipfile.ZipFile(archive) as z:
        seen = set()
        for item in z.infolist():
            path = pathlib.PurePosixPath(item.filename)
            require(not path.is_absolute() and '..' not in path.parts and '\\' not in item.filename,
                    'Unsafe ZIP path')
            require(path.parts and path.parts[0] == 'joshternet', 'Wrong ZIP root')
            require(item.filename not in seen, 'Duplicate ZIP member')
            seen.add(item.filename)
            mode = item.external_attr >> 16
            require(not stat.S_ISLNK(mode), 'ZIP symlink forbidden')
            if not item.is_dir():
                require(path.relative_to('joshternet').as_posix() in EXPECTED, 'Unexpected ZIP file')
            else:
                require(path.as_posix() in {'joshternet', 'joshternet/includes', 'joshternet/assets', 'joshternet/assets/buttons'}, 'Unexpected ZIP directory')
        require(not pathlib.Path(destination).exists(), 'Extraction destination already exists')
        z.extractall(destination)
    return validate_payload(pathlib.Path(destination) / 'joshternet', release_version)


def compare(directory, other, subset=False):
    files = manifest(directory)
    if subset:
        root = pathlib.Path(other)
        require(all((root / name).is_file() and hashlib.sha256((root / name).read_bytes()).hexdigest() == digest
                    for name, digest in files.items()), 'Test source differs from staged payload')
    else:
        require(files == manifest(other), 'Payload file contents differ')


def readme_fixed_sections(text):
    headings = list(re.finditer(r'^== ([^\n]+) ==$', text, re.M))
    require(headings, 'Missing readme sections')
    names = [heading.group(1) for heading in headings]
    require(len(names) == len(set(names)), 'Duplicate readme sections')
    header = text.split('\n\n', 1)[0]
    # Directory contributor capitalization may be corrected without changing identities.
    header = re.sub(r'^(Contributors:)(.*)$', lambda match: match.group(1) + match.group(2).lower(), header, flags=re.M)
    fixed = {'header': header}
    for index, heading in enumerate(headings):
        name = heading.group(1)
        end = headings[index + 1].start() if index + 1 < len(headings) else len(text)
        if name not in ('Description', 'Frequently Asked Questions'):
            fixed[name] = text[heading.start():end]
    return fixed


def approved_listing(directory, listing_source, release_version):
    require(release_version == '0.1.1', 'Approved listing mode only supports 0.1.1')
    approved = validate_payload(directory, release_version)
    proposed = validate_payload(listing_source, release_version)
    require({name: digest for name, digest in approved.items() if name != 'readme.txt'} ==
            {name: digest for name, digest in proposed.items() if name != 'readme.txt'},
            'Approved plugin files differ; only directory readme messaging may change')
    destination = pathlib.Path(directory) / 'readme.txt'
    source = pathlib.Path(listing_source) / 'readme.txt'
    require(readme_fixed_sections(destination.read_text()) == readme_fixed_sections(source.read_text()),
            'Readme changes exceed directory summary, description, and FAQ messaging')
    shutil.copyfile(source, destination)
    return validate_payload(directory, release_version)


def assets(directory, readme):
    root = pathlib.Path(directory)
    sizes = {'icon-128x128.png': (128, 128), 'icon-256x256.png': (256, 256),
             'banner-772x250.png': (772, 250), 'banner-1544x500.png': (1544, 500),
             'screenshot-1.png': None, 'screenshot-2.png': None}
    require({p.name for p in root.iterdir()} == set(sizes), 'Unexpected/missing directory assets')
    for name, size in sizes.items():
        data = (root / name).read_bytes()
        require(data[:8] == b'\x89PNG\r\n\x1a\n', 'Expected PNG: ' + name)
        dimensions = struct.unpack('>II', data[16:24])
        require(size is None or dimensions == size, 'Incorrect dimensions: ' + name)
        limit = 1024 * 1024 * (1 if name.startswith('icon') else 4 if name.startswith('banner') else 10)
        require(len(data) <= limit, 'Oversize asset: ' + name)
    captions = pathlib.Path(readme).read_text().split('== Screenshots ==', 1)[1].split('==', 1)[0]
    require(re.findall(r'^(\d+)\. ', captions, re.M) == ['1', '2'], 'Screenshot captions differ')


def svn_preflight(directory, release_version):
    url = 'https://plugins.svn.wordpress.org/joshternet/'
    listing = subprocess.check_output(['svn', 'list', '--non-interactive', url + 'tags/'], text=True)
    if release_version + '/' not in listing.splitlines():
        published = [tuple(map(int, name.rstrip('/').split('.')))
                     for name in listing.splitlines()
                     if re.fullmatch(r'\d+\.\d+\.\d+/', name)]
        require(not published or tuple(map(int, release_version.split('.'))) > max(published),
                'New SVN tag must be newer than all published versions')
        print('SVN tag absent; eligible for deployment')
        return
    with tempfile.TemporaryDirectory() as temporary:
        exported = pathlib.Path(temporary) / 'tag'
        subprocess.run(['svn', 'export', '--non-interactive', url + 'tags/' + release_version, str(exported)], check=True)
        compare(directory, exported)
    print('SVN tag already exists and is identical; recovery only')


def plugin_check_results(text):
    output = text.strip()
    if output == 'Success: Checks complete. No errors found.':
        return []
    findings = json.loads(output)
    require(isinstance(findings, list) and not findings, 'Plugin Check reported findings; publication blocked')
    return findings


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['extract', 'validate', 'compare', 'assets', 'svn-preflight', 'plugin-check-results', 'approved-listing'])
    parser.add_argument('path')
    parser.add_argument('--version', default='0.1.1', type=version)
    parser.add_argument('--destination')
    parser.add_argument('--other')
    parser.add_argument('--approved', action='store_true')
    parser.add_argument('--source', action='store_true')
    parser.add_argument('--manifest')
    args = parser.parse_args()
    if args.command == 'extract':
        require(args.destination, 'Destination required')
        result = extract(args.path, args.destination, args.version, args.approved)
    elif args.command == 'validate':
        result = validate_payload(args.path, args.version)
    elif args.command == 'compare':
        compare(args.path, args.other, args.source)
        result = manifest(args.path)
    elif args.command == 'approved-listing':
        result = approved_listing(args.path, args.other, args.version)
    elif args.command == 'assets':
        assets(args.path, args.other)
        result = manifest(args.path)
    elif args.command == 'plugin-check-results':
        result = plugin_check_results(pathlib.Path(args.path).read_text())
    else:
        svn_preflight(args.path, args.version)
        result = manifest(args.path)
    if args.manifest:
        pathlib.Path(args.manifest).write_text(json.dumps(result, indent=2) + '\n')
    print('Validated ' + args.command)


if __name__ == '__main__':
    main()
