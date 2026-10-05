#!/usr/bin/env python3
"""Lists translatable strings of a text domain (used to build the Spanish translations)."""
import os, re, sys, json
root, domain = sys.argv[1], sys.argv[2]
pat = re.compile(r"(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'" + re.escape(domain) + "'")
out = []
for dp, dn, fn in os.walk(root):
    for f in fn:
        if f.endswith('.php'):
            for m in pat.finditer(open(os.path.join(dp, f), encoding='utf-8').read()):
                s = m.group(1).replace("\\'", "'")
                if s not in out:
                    out.append(s)
print(json.dumps(out, ensure_ascii=False, indent=0))
