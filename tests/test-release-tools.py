"""Release safety tests, independent of PHP/WordPress and publishing credentials."""
import importlib.util
import pathlib
import stat
import subprocess
import tempfile
import unittest
import zipfile
from unittest import mock

ROOT = pathlib.Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('release_tools', ROOT / 'bin/release-tools.py')
tools = importlib.util.module_from_spec(spec)
spec.loader.exec_module(tools)


class ReleaseToolsTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = pathlib.Path(self.temporary.name)
        self.payload = self.root / 'payload'
        self.payload.mkdir()
        for name in tools.EXPECTED:
            destination = self.payload / name
            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_bytes((ROOT / name).read_bytes())
        plugin = self.payload / 'joshternet.php'
        plugin.write_text(tools.re.sub(r'(Version:\s*)[^\s]+', r'\g<1>0.1.1', plugin.read_text()))
        plugin.write_text(tools.re.sub(r"(define\( 'JOSHTERNET_VERSION', ')[^']+", r'\g<1>0.1.1', plugin.read_text()))
        readme = self.payload / 'readme.txt'
        readme.write_text(tools.re.sub(r'(Stable tag:\s*)[^\s]+', r'\g<1>0.1.1', readme.read_text()))
        self.baseline = self.root / 'baseline'
        tools.shutil.copytree(self.payload, self.baseline)

    def archive(self, entries=None):
        archive_path = self.root / 'test.zip'
        with zipfile.ZipFile(archive_path, 'w') as archive:
            for path in self.payload.rglob('*'):
                if path.is_file():
                    archive.write(path, 'joshternet/' + path.relative_to(self.payload).as_posix())
            for name, value in entries or []:
                archive.writestr(name, value)
        return archive_path

    def test_plugin_check_clean_results(self):
        self.assertEqual(tools.plugin_check_results('Success: Checks complete. No errors found.\n'), [])
        self.assertEqual(tools.plugin_check_results('[]'), [])

    def test_plugin_check_warnings_errors_and_bad_output_block(self):
        for text in ('[{"type":"WARNING","code":"example"}]',
                     '[{"type":"ERROR","code":"example"}]', '{}', '', 'Warning: incomplete checks'):
            with self.subTest(text=text), self.assertRaises(ValueError):
                tools.plugin_check_results(text)

    def test_current_payload(self):
        tools.validate_payload(self.payload, '0.1.1')
        tools.compare(self.payload, self.baseline, subset=True)

    def test_valid_archive(self):
        tools.extract(self.archive(), self.root / 'stage', '0.1.1')
        tools.compare(self.payload, self.root / 'stage/joshternet')

    def test_directory_messaging_preserves_approved_code(self):
        original = tools.manifest(self.baseline)
        readme = self.payload / 'readme.txt'
        readme.write_text(readme.read_text().replace('== Description ==', '== Description ==\n\nEveryone participates equally.', 1))
        tools.approved_listing(self.baseline, self.payload, '0.1.1')
        updated = tools.manifest(self.baseline)
        self.assertNotEqual(original['readme.txt'], updated['readme.txt'])
        self.assertEqual({k: v for k, v in original.items() if k != 'readme.txt'},
                         {k: v for k, v in updated.items() if k != 'readme.txt'})
        tools.compare(self.baseline, self.payload)

    def test_directory_contributor_capitalization_preserves_identity(self):
        readme = self.payload / 'readme.txt'
        readme.write_text(readme.read_text().replace('Contributors: TheWoollyBully', 'Contributors: thewoollybully'))
        tools.approved_listing(self.baseline, self.payload, '0.1.1')
        tools.compare(self.baseline, self.payload)

    def test_directory_messaging_cannot_change_plugin_code(self):
        (self.payload / 'uninstall.php').write_text('changed runtime')
        with self.assertRaisesRegex(ValueError, 'plugin files differ'):
            tools.approved_listing(self.baseline, self.payload, '0.1.1')

    def test_directory_messaging_cannot_change_protected_readme_sections(self):
        readme = self.payload / 'readme.txt'
        original = readme.read_text()
        for before, after in (('Contributors:', 'Contributor:'),
                              ('Contributors: TheWoollyBully', 'Contributors: another-user'),
                              ('https://joshternet.org/privacy/', 'https://example.com/privacy/'),
                              ('1. Install and activate Joshternet.', '1. Other instructions.'),
                              ('== Changelog ==', '== Changelog ==\nDifferent changelog.')):
            readme.write_text(original.replace(before, after))
            with self.subTest(before=before), self.assertRaises(ValueError):
                tools.approved_listing(self.baseline, self.payload, '0.1.1')

    def test_directory_messaging_cannot_change_stable_version(self):
        readme = self.payload / 'readme.txt'
        readme.write_text(readme.read_text().replace('Stable tag: 0.1.1', 'Stable tag: 0.1.2'))
        with self.assertRaisesRegex(ValueError, 'Version mismatch'):
            tools.approved_listing(self.baseline, self.payload, '0.1.1')

    def test_invalid_versions(self):
        for value in ('v0.1.1', '0.1', '0.1.1/../x', '0.1.1;echo bad', '$(id)', '0.1.1-rc1', ''):
            with self.subTest(value=value), self.assertRaises(ValueError):
                tools.version(value)

    def test_version_mismatch(self):
        with self.assertRaisesRegex(ValueError, 'Version mismatch'):
            tools.validate_payload(self.payload, '0.1.2')

    def test_contamination_and_missing_files(self):
        for name in ('composer.json', '.wordpress-org/icon.png', 'includes/extra.php'):
            path = self.payload / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('unexpected')
            with self.assertRaises(ValueError):
                tools.validate_payload(self.payload, '0.1.1')
            path.unlink()
        (self.payload / 'LICENSE').unlink()
        with self.assertRaises(ValueError):
            tools.validate_payload(self.payload, '0.1.1')

    def test_unsafe_archives(self):
        for name in ('../escape', '/absolute', 'joshternet/../../escape', 'other/file',
                     'joshternet/vendor/file', 'joshternet\\escape', 'joshternet/unexpected/'):
            with self.subTest(name=name), self.assertRaises(ValueError):
                tools.extract(self.archive([(name, 'unsafe')]), self.root / 'stage', '0.1.1')
            self.assertFalse((self.root / 'stage').exists())

    def test_duplicate_and_symlink_archive(self):
        with self.assertRaises(ValueError):
            tools.extract(self.archive([('joshternet/readme.txt', 'duplicate')]), self.root / 'stage', '0.1.1')
        info = zipfile.ZipInfo('joshternet/link')
        info.create_system = 3
        info.external_attr = (stat.S_IFLNK | 0o777) << 16
        with self.assertRaises(ValueError):
            tools.extract(self.archive([(info, 'target')]), self.root / 'stage', '0.1.1')

    def test_approved_checksum_mismatch(self):
        with self.assertRaisesRegex(ValueError, 'checksum'):
            tools.extract(self.archive(), self.root / 'stage', '0.1.1', approved=True)

    def test_source_difference(self):
        (self.payload / 'uninstall.php').write_text('different')
        with self.assertRaises(ValueError):
            tools.compare(self.payload, self.baseline, subset=True)

    def test_directory_symlink(self):
        (self.payload / 'linked').symlink_to(ROOT / 'LICENSE')
        with self.assertRaises(ValueError):
            tools.manifest(self.payload)

    def test_screenshot_caption_mismatch(self):
        readme = self.payload / 'readme.txt'
        readme.write_text(readme.read_text().replace('2. Optional', '3. Optional'))
        with self.assertRaisesRegex(ValueError, 'Screenshot'):
            tools.validate_payload(self.payload, '0.1.1')

    @mock.patch.object(tools.subprocess, 'check_output', return_value='')
    @mock.patch.object(tools.subprocess, 'run')
    def test_absent_svn_tag(self, run, listing):
        tools.svn_preflight(self.payload, '0.1.1')
        run.assert_not_called()

    @mock.patch.object(tools.subprocess, 'check_output', return_value='0.1.1/\n')
    @mock.patch.object(tools.subprocess, 'run')
    @mock.patch.object(tools, 'compare')
    def test_identical_svn_tag(self, compare, run, listing):
        tools.svn_preflight(self.payload, '0.1.1')
        compare.assert_called_once()
        self.assertEqual(run.call_args.args[0][1], 'export')

    @mock.patch.object(tools.subprocess, 'check_output', return_value='0.1.1/\n')
    @mock.patch.object(tools.subprocess, 'run')
    @mock.patch.object(tools, 'compare', side_effect=ValueError('different'))
    def test_different_svn_tag(self, compare, run, listing):
        with self.assertRaises(ValueError):
            tools.svn_preflight(self.payload, '0.1.1')

    @mock.patch.object(tools.subprocess, 'check_output', return_value='0.1.2/\n')
    def test_unpublished_older_version(self, listing):
        with self.assertRaisesRegex(ValueError, 'newer'):
            tools.svn_preflight(self.payload, '0.1.1')

    @mock.patch.object(tools.subprocess, 'check_output', side_effect=subprocess.CalledProcessError(1, 'svn'))
    def test_svn_network_failure(self, listing):
        with self.assertRaises(subprocess.CalledProcessError):
            tools.svn_preflight(self.payload, '0.1.1')


if __name__ == '__main__':
    unittest.main()
