#!/usr/bin/env python3
"""Checks every URL of the migrated site against a running WordPress install and prints the
URL map and the redirect map as Markdown tables (used for docs/URL-MAP.md and docs/REDIRECT-MAP.md).
Usage: python3 tools/url-report.py http://localhost:8080"""
import json, os, sys, urllib.request, urllib.error

BASE = sys.argv[1].rstrip('/')
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
data = json.load(open(os.path.join(ROOT, 'wp-content/plugins/corcovado-foundation-core/migration/data/content.json')))

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None
opener = urllib.request.build_opener(NoRedirect)

def head(path):
    try:
        r = opener.open(urllib.request.Request(BASE + path, method='GET'))
        return r.status, ''
    except urllib.error.HTTPError as e:
        return e.code, (e.headers.get('Location') or '').replace(BASE, '')

def wp_path(p):
    if p['es_root']:
        return '/es/'
    if p['lang'] == 'es':
        return '/es/' + p['slug']
    return '/' + p['slug'] if p['slug'] else '/'

pages = [p for p in data['pages'] if not p['is_404']]
print('## URL map\n')
print('| Static file | WordPress URL | Language | Status |')
print('|---|---|---|---|')
for p in pages:
    path = wp_path(p)
    st, _ = head(path)
    print(f"| `{p['file']}` | `{path}` | {p['lang'].upper()} | {st} |")

print('\n## Redirect map\n')
print('| Old URL | Redirects to | Status |')
print('|---|---|---|')
legacy = []
for p in pages:
    legacy.append(('/' + p['file'], wp_path(p)))
legacy += [('/events-calendar.htm', '/events-calendar'), ('/es/virtual-library', '/es/biblioteca-virtual'),
           ('/es', '/es/'), ('/sitemap.xml', '/wp-sitemap.xml')]
pdfs = sorted({m['key'] for m in data['media'] if m['key'].startswith('library/')})
legacy += [('/' + k, '(file in the Media Library)') for k in pdfs[:3]]
for old, expected in legacy:
    st, loc = head(old)
    print(f"| `{old}` | `{loc or '—'}` | {st} |")
