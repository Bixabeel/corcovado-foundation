#!/usr/bin/env python3
"""
Compares the <main> DOM (and header/footer) of every original static page with the WordPress
page at the same URL. Prints differences per page. Used for the visual-validation report.

Usage: python3 tools/compare-dom.py ORIGINAL_BASE WORDPRESS_BASE [--chrome]
  e.g. python3 tools/compare-dom.py http://127.0.0.1:8801 http://localhost:8080
"""
import difflib
import os
import re
import sys
import urllib.request

from bs4 import BeautifulSoup, Comment, NavigableString, Tag

sys.path.insert(0, os.path.dirname(__file__))
import importlib.util
spec = importlib.util.spec_from_file_location('ex', os.path.join(os.path.dirname(__file__), 'extract-content.py'))
ex = importlib.util.module_from_spec(spec)
spec.loader.exec_module(ex)


def fetch(url):
    with urllib.request.urlopen(url) as r:
        return r.read().decode('utf-8')


WP_BASE = ''


def norm_url(v, page_file=None, wp=False):
    v = (v or '').strip()
    if wp:
        v = re.sub(r'^' + re.escape(WP_BASE), '', v)
        m = re.search(r'/wp-content/uploads/\d{4}/\d{2}/(.+)$', v)
        if m:
            name = re.sub(r'-(\d+x\d+)-\d+(?=\.)', r'-\1', m.group(1))
            return 'FILE:' + name.lower().replace(' ', '-').replace('--', '-')
        m = re.search(r'/wp-content/themes/[^/]+/assets/img/brand/(.+)$', v)
        if m:
            return 'FILE:' + m.group(1).lower()
        return v
    if v.startswith(('#', 'mailto:', 'tel:', 'http')) or v == '':
        return v
    try:
        conv = ex.convert_link(page_file, v)
    except SystemExit:
        return v
    if conv.startswith('media:'):
        name = os.path.basename(conv[6:]).lower().replace(' ', '-')
        name = name.replace('--', '-')
        return 'FILE:' + name
    return conv


def lines(el, page_file, wp, out, depth=0):
    for c in el.children:
        if isinstance(c, Comment):
            continue
        if isinstance(c, NavigableString):
            t = re.sub(r'\s+', ' ', str(c)).strip()
            if t:
                out.append('  ' * depth + '"' + t + '"')
            continue
        if not isinstance(c, Tag) or c.name in ('script', 'style'):
            continue
        attrs = []
        for k in sorted(c.attrs):
            v = c.attrs[k]
            if isinstance(v, list):
                v = ' '.join(v)
            if k in ('href', 'src'):
                v = norm_url(v, page_file, wp)
                if v.startswith('FILE:'):
                    v = re.sub(r'\.(webp|png|jpe?g)$', '.IMG', v)
            if k == 'onerror':
                v = 'onerror'
            if k == 'loading' and c.name == 'img':
                continue
            attrs.append('%s=%s' % (k, v))
        out.append('  ' * depth + '<' + c.name + (' ' + ' '.join(attrs) if attrs else '') + '>')
        if c.name != 'svg':
            lines(c, page_file, wp, out, depth + 1)


def page_lines(html, sel, page_file, wp):
    s = BeautifulSoup(html, 'html.parser')
    el = s.select_one(sel)
    out = []
    if el:
        lines(el, page_file, wp, out)
    return out


def main():
    global WP_BASE
    obase, wbase = sys.argv[1].rstrip('/'), sys.argv[2].rstrip('/')
    WP_BASE = wbase
    chrome = '--chrome' in sys.argv
    total = 0
    for lang, f, slug, _ in ex.PAGES:
        if f.endswith('404.html'):
            wp_path = '/es/no-existe-' if lang == 'es' else '/does-not-exist-'
        else:
            wp_path = ex.page_path_for(f)
        try:
            ohtml = fetch(obase + '/' + f)
            req = urllib.request.Request(wbase + wp_path)
            try:
                whtml = fetch(wbase + wp_path)
            except urllib.error.HTTPError as e:
                whtml = e.read().decode('utf-8')
        except Exception as e:  # noqa
            print('!!', f, e)
            continue
        sels = ['main'] + (['header.site-header', 'footer.site-footer'] if chrome else [])
        for sel in sels:
            a = page_lines(ohtml, sel, f, False)
            b = page_lines(whtml, sel, f, True)
            diff = [d for d in difflib.unified_diff(a, b, lineterm='', n=1) if not d.startswith(('---', '+++'))]
            if diff:
                n = len([d for d in diff if d[:1] in '+-'])
                total += n
                print('=' * 8, f, '→', wp_path, sel, '(%d lines differ)' % n)
                print('\n'.join(diff[:400]))
            else:
                print('OK', f, sel)
    print('TOTAL differing lines:', total)


if __name__ == '__main__':
    main()
