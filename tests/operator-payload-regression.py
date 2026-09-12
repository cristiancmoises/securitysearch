#!/usr/bin/env python3
"""Schema-2 integrity, expansion bounds, deduplication and optimized-Python tests."""
import base64
import copy
import gzip
import hashlib
import json
import os
from pathlib import Path
import random
import subprocess
import sys
import unittest
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'))
import operator_payload as m

class Payload(unittest.TestCase):
 def setUp(self):
  self.data={'helper.py':b'not executed\n','update-a.patch':b'diff --git a/a b/a\ncommon\ndiff --git a/b b/b\nother\n','update-b.patch':b'diff --git a/a b/a\ncommon\n'}
  self.names=sorted(self.data);self.packed=m.encode_resources(self.data)
  self.value=json.loads(gzip.decompress(base64.b64decode(self.packed)))
 def pack(self,v):return base64.b64encode(gzip.compress(json.dumps(v,separators=(',',':')).encode(),mtime=0)).decode()
 def reject(self,v):self.assertRaises(m.PayloadError,m.decode_resources,self.pack(v),self.names)
 def test_exact_roundtrip_and_determinism(self):
  self.assertEqual(m.decode_resources(self.packed,self.names),self.data)
  self.assertEqual(m.encode_resources(dict(reversed(list(self.data.items())))),self.packed)
 def test_shared_file_diff_chunk_stored_once(self):
  a=self.value['files']['update-a.patch']['parts'];b=self.value['files']['update-b.patch']['parts']
  self.assertEqual(a[0],b[0]);self.assertEqual(len(self.value['chunks']),3)
 def test_empty_file_roundtrip(self):
  p=m.encode_resources({'empty':b''});self.assertEqual(m.decode_resources(p,['empty']),{'empty':b''})
 def test_arbitrary_binary_and_long_sections(self):
  rng=random.Random(37);raw=bytes(rng.randrange(256) for _ in range(100))
  data={'bytes':bytes(range(256))*600,'large.patch':b'diff --git a/a b/a\n'+raw*3000,'empty':b''}
  encoded=m.encode_resources(data);self.assertEqual(m.decode_resources(encoded,sorted(data)),data)
 def test_chunk_corruption(self):
  k=next(iter(self.value['chunks']));self.value['chunks'][k]=base64.b64encode(b'wrong').decode();self.reject(self.value)
 def test_chunk_identity_cannot_be_relabelled(self):
  # Even if all file hashes and sizes are recomputed to accept substituted bytes,
  # a chunk key must still identify its own bytes rather than become an alias.
  key=next(iter(self.value['chunks']));self.value['chunks'][key]=base64.b64encode(b'relabelled').decode()
  for row in self.value['files'].values():
   raw=b''.join(base64.b64decode(self.value['chunks'][k]) for k in row['parts'])
   row['size']=len(raw);row['sha256']=hashlib.sha256(raw).hexdigest()
  with self.assertRaisesRegex(m.PayloadError,'chunk integrity'):
   m.decode_resources(self.pack(self.value),self.names)
 def test_file_corruption(self):
  self.value['files']['helper.py']['sha256']='0'*64;self.reject(self.value)
 def test_missing_chunk(self):
  del self.value['chunks'][next(iter(self.value['chunks']))];self.reject(self.value)
 def test_extra_unused_chunk(self):
  raw=b'unused';self.value['chunks'][hashlib.sha256(raw).hexdigest()]=base64.b64encode(raw).decode();self.reject(self.value)
 def test_wrong_length(self):
  for size in (0,999):
   v=copy.deepcopy(self.value);v['files']['helper.py']['size']=size;self.reject(v)
 def test_bool_float_negative_sizes_refused(self):
  for size in (True,False,1.0,-1,'12',None):
   v=copy.deepcopy(self.value);v['files']['helper.py']['size']=size;self.reject(v)
 def test_wrong_schema_or_fields(self):
  for key,value in [('schema',True),('schema',1),('schema',3),('files',[]),('chunks',[])]:
   v=copy.deepcopy(self.value);v[key]=value;self.reject(v)
  self.value['extra']=1;self.reject(self.value)
 def test_missing_extra_resource(self):
  v=copy.deepcopy(self.value);del v['files']['helper.py'];self.reject(v)
  v=copy.deepcopy(self.value);v['files']['new.py']=v['files']['helper.py'];self.reject(v)
 def test_path_like_or_duplicate_expected_names(self):
  for names in [['../x'],['/x'],['a/b'],['a\\b'],['..'],['x','x'],['b','a'],[],['\n']]:
   with self.subTest(names=names):self.assertRaises(m.PayloadError,m.decode_resources,self.packed,names)
 def test_names_cannot_exceed_count_limit(self):
  with patch.object(m,'MAX_RESOURCES',2):self.assertRaises(m.PayloadError,m.decode_resources,self.packed,self.names)
 def test_chunk_table_count_bound(self):
  with patch.object(m,'MAX_CHUNKS',2):self.assertRaises(m.PayloadError,m.decode_resources,self.packed,self.names)
 def test_chunk_size_bound(self):
  with patch.object(m,'CHUNK_SIZE',2):self.assertRaises(m.PayloadError,m.decode_resources,self.packed,self.names)
 def test_file_size_bound(self):
  with patch.object(m,'MAX_RESOURCE',2):self.assertRaises(m.PayloadError,m.decode_resources,self.packed,self.names)
 def test_total_reconstructed_bytes_bound(self):
  # Dedup must not allow repeated references to bypass the expanded-byte cap.
  data={'a':b'x'*20,'b':b'x'*20};encoded=m.encode_resources(data)
  with patch.object(m,'MAX_TOTAL',39):self.assertRaises(m.PayloadError,m.decode_resources,encoded,['a','b'])
 def test_parts_total_bound(self):
  with patch.object(m,'MAX_PARTS',2):self.assertRaises(m.PayloadError,m.decode_resources,self.packed,self.names)
 def test_declared_small_file_repetition_bomb(self):
  row=self.value['files']['helper.py'];row['parts']*=100;self.reject(self.value)
 def test_compressed_and_base64_input_bounds(self):
  with patch.object(m,'MAX_PACKED',10):self.assertRaises(m.PayloadError,m.decode_resources,self.packed,self.names)
 def test_inflated_json_limit(self):
  encoded=base64.b64encode(gzip.compress(b' '*(m.MAX_JSON+1),mtime=0)).decode()
  self.assertRaises(m.PayloadError,m.decode_resources,encoded,self.names)
 def test_truncated_gzip_all_tail_lengths(self):
  raw=base64.b64decode(self.packed)
  for count in range(1,10):
   with self.subTest(count=count):self.assertRaises(m.PayloadError,m.decode_resources,base64.b64encode(raw[:-count]).decode(),self.names)
 def test_trailing_or_multiple_gzip_members(self):
  raw=base64.b64decode(self.packed)
  for tail in [b'\x00',b'garbage',gzip.compress(b'{}',mtime=0)]:
   self.assertRaises(m.PayloadError,m.decode_resources,base64.b64encode(raw+tail).decode(),self.names)
 def test_bad_crc(self):
  raw=bytearray(base64.b64decode(self.packed));raw[-8]^=1
  self.assertRaises(m.PayloadError,m.decode_resources,base64.b64encode(raw).decode(),self.names)
 def test_invalid_base64_and_nonstring(self):
  for bad in ['',self.packed+'\n','!!!!',42,b'abc',None]:
   self.assertRaises(m.PayloadError,m.decode_resources,bad,self.names)
 def test_bad_chunk_encoding_and_key(self):
  k=next(iter(self.value['chunks']))
  for bad in ('@@@',42,[],None):
   v=copy.deepcopy(self.value);v['chunks'][k]=bad;self.reject(v)
  v=copy.deepcopy(self.value);v['chunks']['BAD']=v['chunks'].pop(k);self.reject(v)
 def test_bad_resource_row_shapes(self):
  for row in ([],{},dict(self.value['files']['helper.py'],extra=1)):
   v=copy.deepcopy(self.value);v['files']['helper.py']=row;self.reject(v)
  for parts in ('text',None,[[]],[1]):
   v=copy.deepcopy(self.value);v['files']['helper.py']['parts']=parts;self.reject(v)
 def test_duplicate_json_keys_including_nested(self):
  for raw in (b'{"schema":2,"schema":2}',b'{"files":{"a":{},"a":{}}}'):
   encoded=base64.b64encode(gzip.compress(raw,mtime=0)).decode();self.assertRaises(m.PayloadError,m.decode_resources,encoded,self.names)
 def test_nan_deep_or_invalid_utf8_json(self):
  for raw in (b'{"schema":NaN}',b'['*1500+b']'*1500,b'\xff',b'{}garbage'):
   encoded=base64.b64encode(gzip.compress(raw,mtime=0)).decode();self.assertRaises(m.PayloadError,m.decode_resources,encoded,self.names)
 def test_encode_rejects_invalid_input(self):
  for data in ({'a':'text'},{'../a':b'hi'},{}):self.assertRaises(m.PayloadError,m.encode_resources,data)
 def test_python_optimization_cannot_disable_integrity(self):
  self.value['files']['helper.py']['sha256']='0'*64
  code='import operator_payload as m,sys\ntry:m.decode_resources(sys.argv[1],'+repr(self.names)+')\nexcept m.PayloadError:sys.exit(0)\nsys.exit(1)'
  env=dict(os.environ,PYTHONPATH=str(Path(m.__file__).parent))
  p=subprocess.run([sys.executable,'-B','-O','-c',code,self.pack(self.value)],env=env,capture_output=True,timeout=15)
  self.assertEqual(p.returncode,0,p.stderr)
if __name__=='__main__':unittest.main(verbosity=2)
