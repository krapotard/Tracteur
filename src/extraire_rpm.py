# SPDX-License-Identifier: AGPL-3.0-or-later
# Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
# Extrait les bibliotheques (.so) d'un ou plusieurs paquets RPM, sans installation.
# Usage : python3 extraire_rpm.py DOSSIER_DESTINATION fichier.rpm [...]
import sys, lzma, gzip, struct, os

def extraire(rpm, dest):
    d = open(rpm, 'rb').read()
    def fin_entete(p):
        assert d[p:p+3] == b'\x8e\xad\xe8', 'entete rpm'
        n, hs = struct.unpack('>II', d[p+8:p+16])
        return p + 16 + n*16 + hs
    p = fin_entete(96)
    p = (p + 7)//8*8
    p = fin_entete(p)
    pl = d[p:]
    if pl[:6] == b'\xfd7zXZ\x00': raw = lzma.decompress(pl)
    elif pl[:2] == b'\x1f\x8b': raw = gzip.decompress(pl)
    else: raise Exception('compression inconnue ' + pl[:4].hex())
    i = 0; n = 0
    while raw[i:i+6] == b'070701':
        f = lambda k: int(raw[i+6+8*k:i+14+8*k], 16)
        mode, taille, nom_t = f(1), f(6), f(11)
        nom = raw[i+110:i+110+nom_t-1].decode()
        i = (i + 110 + nom_t + 3)//4*4
        data = raw[i:i+taille]; i = (i + taille + 3)//4*4
        if nom == 'TRAILER!!!': break
        if 'lib64/' in nom and '.so' in nom:
            cible = os.path.join(dest, os.path.basename(nom))
            t = mode & 0o170000
            if os.path.lexists(cible): os.remove(cible)
            if t == 0o120000: os.symlink(data.decode(), cible); n += 1
            elif t == 0o100000:
                open(cible, 'wb').write(data); os.chmod(cible, 0o755); n += 1
    return n

for rpm in sys.argv[2:]:
    print(os.path.basename(rpm), extraire(rpm, sys.argv[1]), 'fichiers')
