#!/usr/bin/env python3
"""Offline: compares provided server ZIP with hashes before GRE patch. No secrets printed."""
import sys, json, hashlib, zipfile
from pathlib import Path
if len(sys.argv) != 3:
    print('Uso: python comparar_con_respaldo.py MANIFIESTO_SHA256.json facturacion_actual.zip'); sys.exit(2)
manifest=json.loads(Path(sys.argv[1]).read_text(encoding='utf8'))
expected=manifest['hashes_originales_para_verificar_antes_de_reemplazar']
with zipfile.ZipFile(sys.argv[2]) as z:
    files=set(z.namelist())
    errors=0
    for name, sha in expected.items():
        if name not in files:
            print('FALTA:',name);errors+=1;continue
        actual=hashlib.sha256(z.read(name)).hexdigest()
        if actual == sha: print('OK:',name)
        else: print('DISTINTO (NO REEMPLAZAR AÚN):',name);errors+=1
sys.exit(1 if errors else 0)
