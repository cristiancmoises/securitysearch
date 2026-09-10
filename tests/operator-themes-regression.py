#!/usr/bin/env python3
"""Offline asset provenance, conversion, publication exclusion and runtime gating.
Generated color-frame fixtures are not the historical Lain/SecOps artwork.
"""
import hashlib, importlib.util, io, json, os
from pathlib import Path
import subprocess, tempfile, unittest
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
def module(name):
    spec=importlib.util.spec_from_file_location(name,ROOT/'scripts'/f'{name}.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
m=module('operator_themes');policy=module('release_media_policy')
class Assets(unittest.TestCase):
    def setUp(self):self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.root=Path(self.tmp.name)
    def pack(self):
        directory=self.root/'pack';directory.mkdir(exist_ok=True)
        data=b'RIFF\x08\x00\x00\x00WEBPfixture' # Validation-only bytes; conversion test uses genuine decoded WebP.
        for name in m.NAMES:(directory/name).write_bytes(data)
        manifest={'schema':1,'deployment_only':True,'source_commit':m.COMMIT,'source_blobs':{k:m.SOURCES[k][1] for k in ('Lain','SecOps')},'assets':{n:{'size':len(data),'sha256':hashlib.sha256(data).hexdigest()} for n in m.NAMES}}
        (directory/'manifest.json').write_text(json.dumps(manifest));return directory
    def test_pack_hashes_and_extra_files(self):
        d=self.pack();m.validate(d);(d/'secret.txt').write_text('fixture')
        with self.assertRaises(RuntimeError):m.validate(d)
    def test_tampering_is_not_ignored(self):
        d=self.pack();(d/'lain.webp').write_bytes(b'RIFFinvalidWEBP')
        with self.assertRaises(RuntimeError):m.validate(d)
    def test_symlink_not_followed(self):
        d=self.pack();(d/'lain.webp').unlink();(d/'lain.webp').symlink_to(d/'secops.webp')
        with self.assertRaises(RuntimeError):m.validate(d)
    def test_pinned_originals_not_substituted(self):
        for name in ('Lain','SecOps','Tron'):
            with self.assertRaises(RuntimeError):m.verify_original(name,b'arbitrary bytes')
        m.verify_original('Tron',(ROOT/'static/misc/tron.gif').read_bytes())
    def test_runtime_styles_only_for_valid_installed_pack(self):
        d=self.pack();site=self.root/'site';(site/'lib').mkdir(parents=True);(site/'static').mkdir()
        (site/'lib/operator_themes.php').write_bytes((ROOT/'lib/operator_themes.php').read_bytes())
        script="require "+repr(str(site/'lib/operator_themes.php'))+";echo json_encode(operator_themes::catalog());"
        def read():return subprocess.check_output(['php','-r',script],text=True)
        self.assertEqual(json.loads(read()),[])
        d.rename(site/'static/operator-themes');self.assertEqual(set(json.loads(read())),{'Lain','SecOps'})
        (site/'static/operator-themes/lain.webp').write_bytes(b'broken')
        self.assertEqual(set(json.loads(read())),{'SecOps'})
    def test_animation_conversion_with_fixture(self):
        try:from PIL import Image
        except ImportError:self.skipTest('Pillow is an optional operator-side preparation dependency, not required in the Docker application/audit.')
        a=Image.new('RGB',(64,48),(0,0,0));b=Image.new('RGB',(64,48),(0,100,100));buf=io.BytesIO();a.save(buf,'GIF',save_all=True,append_images=[b],duration=[80,120],loop=0)
        m.convert(buf.getvalue(),'Lain',self.root)
        with Image.open(self.root/'lain.webp') as result:self.assertEqual(result.n_frames,2)
        with Image.open(self.root/'Lain-preview.webp') as preview:self.assertEqual(preview.size,(240,135))
    def test_prepare_refuses_repository_destination(self):
        # A deployment/source archive intentionally has no .git metadata. Test the
        # destination policy against our own real, disposable checkout, not ROOT.
        repo = self.root / 'checkout'
        repo.mkdir()
        env = {key: value for key, value in os.environ.items() if not key.startswith('GIT_')}
        env.update(GIT_CONFIG_NOSYSTEM='1', GIT_CONFIG_GLOBAL=os.devnull,
                   GIT_TERMINAL_PROMPT='0')
        with patch.dict(os.environ, env, clear=True):
            subprocess.run(['git', 'init', '--quiet', '--template=', str(repo)],
                           check=True, capture_output=True, text=True, timeout=10)
            # Pin the intended RuntimeError message: an unrelated failure must not
            # count as a pass. Neither downloads nor conversion may be reached.
            with patch.object(m, 'load_original', side_effect=AssertionError('Unexpected asset read/download')) as load, \
                 patch.object(m, 'convert', side_effect=AssertionError('Unexpected conversion')) as convert:
                for output in (repo, repo / 'static/operator-themes'):
                    with self.subTest(output=output.name):
                        with self.assertRaisesRegex(RuntimeError, 'Keep operator assets OUTSIDE the Git checkout'):
                            m.prepare(repo, output)
                load.assert_not_called()
                convert.assert_not_called()
            self.assertFalse((repo / 'static').exists())
class History(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.root=Path(self.tmp.name)
        self.git('init','-q','-b','main');self.git('config','user.name','Fixture');self.git('config','user.email','fixture@example.invalid')
        (self.root/'index.php').write_text('<?php');self.commit();self.base=self.git('rev-parse','HEAD').strip()
    def git(self,*args):return subprocess.check_output(['git','-C',str(self.root),*args],text=True,stderr=subprocess.DEVNULL)
    def commit(self):self.git('add','-A');self.git('commit','-qm','fixture')
    def test_clean_tree(self):policy.check_tree(self.root)
    def test_added_then_deleted_image_in_outgoing_history_rejected(self):
        (self.root/'lain.gifv').write_bytes(b'fixture');self.commit();(self.root/'lain.gifv').unlink();self.commit();policy.check_tree(self.root)
        with self.assertRaises(RuntimeError):policy.check_history(self.root,'HEAD',self.base)
    def test_derived_image_names_rejected(self):
        for filename in ['secops.webp','Lain-preview.png','static/operator-themes/anything.jpg']:
            path=self.root/filename;path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(b'fixture');self.commit()
            with self.assertRaises(RuntimeError):policy.check_tree(self.root)
            path.unlink();self.commit()
    def test_original_blob_renamed_is_rejected(self):
        self.assertTrue(policy.forbidden('ordinary-file.bin',next(iter(policy.FORBIDDEN_BLOBS))))
    def test_safe_palette_and_matrix_allowlist_exact(self):
        for path,oid in policy.SAFE_PUBLIC.items():
            self.assertFalse(policy.forbidden(path,oid));self.assertTrue(policy.forbidden(path,'0'*40))
    def test_existing_history_is_not_rewritten(self):
        (self.root/'secops.gif').write_bytes(b'fixture');self.commit();old=self.git('rev-parse','HEAD').strip()
        (self.root/'secops.gif').unlink();self.commit();baseline=self.git('rev-parse','HEAD').strip()
        (self.root/'new.txt').write_text('fixture');self.commit();policy.check_history(self.root,'HEAD',baseline)
        self.assertEqual(self.git('cat-file','-t',old).strip(),'commit')
if __name__=='__main__':unittest.main(verbosity=2)
