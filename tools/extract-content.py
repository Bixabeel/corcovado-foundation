#!/usr/bin/env python3
"""
Corcovado Foundation — content extractor (static HTML → WordPress import data).

Reads the original static site (the repository root) and writes
wp-content/plugins/corcovado-foundation-core/migration/data/content.json, which the
"Corcovado Foundation → Import / Migration" screen imports into WordPress.

Nothing is invented: every text, link, image and attribute comes from the HTML/JS files.
The few deliberate corrections are listed in FIXES and in docs/VISUAL-DIFFERENCES.md.

Requirements: Python 3.9+, beautifulsoup4, Node.js (to read team-data*.js safely).
Usage:  python3 tools/extract-content.py [--check]
"""

import copy
import json
import os
import re
import subprocess
import sys
from datetime import datetime

from bs4 import BeautifulSoup, Comment, NavigableString, Tag

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
OUT = os.path.join(ROOT, 'wp-content/plugins/corcovado-foundation-core/migration/data/content.json')

# Pages of the static site: (language, file, WordPress slug, nav title).
PAGES = [
    ('en', 'index.html', '', 'Home'),
    ('en', 'about-us.html', 'about-us', 'About Us'),
    ('en', 'allies.html', 'allies', 'Allies'),
    ('en', 'contact-us.html', 'contact-us', 'Contact Us'),
    ('en', 'donate-now.html', 'donate-now', 'Donate Now'),
    ('en', 'ecosystem-restoration.html', 'ecosystem-restoration', 'Ecosystem Restoration'),
    ('en', 'environmental-education.html', 'environmental-education', 'Environmental Education'),
    ('en', 'events-calendar.html', 'events-calendar', 'Events Calendar'),
    ('en', 'national-park-support.html', 'national-park-support', 'National Park Support'),
    ('en', 'news.html', 'news', 'News'),
    ('en', 'our-impact.html', 'our-impact', 'Our Impact'),
    ('en', 'programs.html', 'programs', 'Our Programs'),
    ('en', 'sea-turtles.html', 'sea-turtles', 'Sea Turtles'),
    ('en', 'sponsors.html', 'sponsors', 'Sponsors'),
    ('en', 'virtual-library.html', 'virtual-library', 'Virtual Library'),
    ('en', 'volunteering.html', 'volunteering', 'Volunteering'),
    ('en', '404.html', 'page-not-found', 'Page not found'),
    ('es', 'es/index.html', 'es', 'Inicio'),
    ('es', 'es/about-us.html', 'about-us', 'Nosotros'),
    ('es', 'es/allies.html', 'allies', 'Aliados'),
    ('es', 'es/contact-us.html', 'contact-us', 'Contáctanos'),
    ('es', 'es/donate-now.html', 'donate-now', 'Donar Ahora'),
    ('es', 'es/ecosystem-restoration.html', 'ecosystem-restoration', 'Restauración de Ecosistemas'),
    ('es', 'es/environmental-education.html', 'environmental-education', 'Educación Ambiental'),
    ('es', 'es/events-calendar.html', 'events-calendar', 'Calendario de Eventos'),
    ('es', 'es/national-park-support.html', 'national-park-support', 'Apoyo al Parque Nacional'),
    ('es', 'es/news.html', 'news', 'Noticias'),
    ('es', 'es/our-impact.html', 'our-impact', 'Nuestro Impacto'),
    ('es', 'es/programs.html', 'programs', 'Nuestros Programas'),
    ('es', 'es/sea-turtles.html', 'sea-turtles', 'Tortugas Marinas'),
    ('es', 'es/sponsors.html', 'sponsors', 'Patrocinadores'),
    ('es', 'es/biblioteca-virtual.html', 'biblioteca-virtual', 'Biblioteca Virtual'),
    ('es', 'es/volunteering.html', 'volunteering', 'Voluntariado'),
    ('es', 'es/404.html', 'pagina-no-encontrada', 'Página no encontrada'),
]

# EN ↔ ES page pairs (by EN file → ES file).
PAGE_PAIRS = {
    'index.html': 'es/index.html', 'virtual-library.html': 'es/biblioteca-virtual.html', '404.html': 'es/404.html',
}

# EN library file ↔ ES library file (same document in the other language).
LIBRARY_PAIRS = {
    'annual-report-2025.pdf': 'reporte-anual-2025.pdf',
}

SPONSOR_KEYS = ['founding', 'supporters', 'gold', 'silver', 'bronze']

# Deliberate corrections of real defects (documented in docs/VISUAL-DIFFERENCES.md).
FIXES = []

INLINE_OK = {'strong': set(), 'b': set(), 'em': set(), 'i': set(), 'br': set(),
             'span': {'class'}, 'a': {'href', 'target', 'rel', 'style'}}
TEXT_ATTRS = {'alt': 'alt', 'placeholder': 'placeholder', 'aria-label': 'aria', 'title': 'tooltip',
              'data-prefix': 'prefix', 'data-suffix': 'suffix', 'data-copy-label': 'button',
              'data-copied-label': 'button_after'}
SKIP_TEXT_TAGS = {'script', 'style', 'svg', 'noscript'}


def read(path):
    with open(os.path.join(ROOT, path), encoding='utf-8-sig') as fh:
        return fh.read()


def soup(path):
    return BeautifulSoup(read(path), 'html.parser')


def classes(tag):
    return tag.get('class') or []


def clean_text(s):
    return re.sub(r'\s+', ' ', s).strip()


# ---------------------------------------------------------------------------
# Links and media
# ---------------------------------------------------------------------------

MEDIA = {}  # key -> {key, file, alt}


def media_key(page_file, src):
    """Repository-relative path of a local file referenced from page_file."""
    base = os.path.dirname(page_file)
    path = os.path.normpath(os.path.join(base, src)).replace('\\', '/')
    return path


def add_media(key, alt=''):
    if not os.path.exists(os.path.join(ROOT, key)):
        raise SystemExit('Missing referenced file: ' + key)
    m = MEDIA.setdefault(key, {'key': key, 'alt': ''})
    if alt and not m['alt'] and key.startswith('assets/img/'):
        m['alt'] = alt
    return key


def page_path_for(target_file):
    """Static file (repo-relative) → WordPress path ("/about-us", "/es/", "/")."""
    for lang, f, slug, _ in PAGES:
        if f == target_file:
            if f == 'index.html':
                return '/'
            if f == 'es/index.html':
                return '/es/'
            return '/' + ('es/' if lang == 'es' else '') + slug
    return None


def convert_link(page_file, href):
    href = (href or '').strip()
    if href == '' or href.startswith(('#', 'mailto:', 'tel:', 'http://', 'https://', '//')):
        return href
    path, _, anchor = href.partition('#')
    target = media_key(page_file, path)
    wp = page_path_for(target)
    if wp is None and target == 'events-calendar.htm':
        wp = '/events-calendar'
    if wp is not None:
        return wp + ('#' + anchor if anchor else '')
    if os.path.exists(os.path.join(ROOT, target)):
        add_media(target)
        return 'media:' + target
    raise SystemExit('Unresolved link %s in %s' % (href, page_file))


# ---------------------------------------------------------------------------
# Templating: replace content with slots
# ---------------------------------------------------------------------------

def text_label(tag):
    """Label key for a text slot from its element."""
    name = tag.name
    cls = ' '.join(classes(tag))
    if name == 'h1':
        return 'title_main'
    if name in ('h2',):
        return 'title'
    if name in ('h3', 'h4', 'h5', 'h6'):
        return 'subtitle'
    if name == 'a' or name == 'button':
        return 'button' if ('btn' in cls or name == 'button') else 'link_text'
    if name == 'li':
        return 'list_item'
    if name == 'label':
        return 'field_label'
    if name == 'option':
        return 'option'
    if name == 'strong':
        return 'highlight'
    if name == 'span':
        if 'pill' in cls or 'tag' in cls or 'eyebrow' in cls or 'label' in cls:
            return 'label'
        return 'small_text'
    return 'text'


def is_inline_rich(tag):
    """True if tag's content mixes text with simple inline markup only (→ one 'html' slot)."""
    has_tag = False
    has_text = False
    for d in tag.descendants:
        if isinstance(d, Comment):
            return False
        if isinstance(d, Tag):
            if d.name not in INLINE_OK:
                return False
            if any(a not in INLINE_OK[d.name] for a in d.attrs):
                return False
            has_tag = True
        elif isinstance(d, NavigableString) and d.strip():
            has_text = True
    return has_tag and has_text and tag.name in ('p', 'h1', 'h2', 'h3', 'h4', 'li', 'span', 'div', 'strong', 'label', 'small')


class Templater:
    def __init__(self, page_file, lang):
        self.page_file = page_file
        self.lang = lang

    def template(self, el, slots, dyn_hook):
        """Mutates a *copy* of el in place, appending slots; returns template HTML."""
        self._walk(el, slots, dyn_hook)
        return str(el)

    def _new(self, slots, kind, label, value, **extra):
        slot = {'k': kind, 'l': label, 'v': value}
        slot.update(extra)
        slots.append(slot)
        return len(slots) - 1

    def _attrs(self, tag, slots):
        for attr in list(tag.attrs.keys()):
            val = tag.attrs[attr]
            if isinstance(val, list):
                continue
            if attr == 'href' and tag.name == 'a':
                link = convert_link(self.page_file, val)
                i = self._new(slots, 'url', 'link', link)
                tag.attrs[attr] = '{{a:%d}}' % i
            elif attr == 'src' and tag.name == 'img':
                if val == '':
                    continue
                key = add_media(media_key(self.page_file, val), tag.get('alt', ''))
                i = self._new(slots, 'img', 'image', key)
                tag.attrs[attr] = '{{a:%d}}' % i
            elif attr == 'data-count':
                i = self._new(slots, 'num', 'number', val)
                tag.attrs[attr] = '{{a:%d}}' % i
            elif attr in TEXT_ATTRS:
                if attr in ('aria-label',) and tag.name in ('nav',):
                    continue
                i = self._new(slots, 'text', TEXT_ATTRS[attr], val)
                tag.attrs[attr] = '{{a:%d}}' % i

    def _walk(self, tag, slots, dyn_hook):
        if dyn_hook(tag, slots, self):
            return
        if tag.name in SKIP_TEXT_TAGS:
            return
        self._attrs(tag, slots)
        if tag.has_attr('data-count'):
            return  # "0" placeholder text animated by main.js
        if tag.name == 'option':
            text = clean_text(tag.get_text())
            i = self._new(slots, 'text', 'option', text)
            if tag.get('value') is not None and clean_text(tag['value']) == text:
                tag['value'] = '{{a:%d}}' % i
            tag.clear()
            tag.append(NavigableString('{{%d}}' % i))
            return
        if is_inline_rich(tag):
            inner = tag.decode_contents()
            lead = re.match(r'^\s*', inner).group(0)
            trail = re.search(r'\s*$', inner).group(0)
            body = inner.strip()
            # Links inside rich text: convert their hrefs too.
            frag = BeautifulSoup(body, 'html.parser')
            for a in frag.find_all('a'):
                if a.get('href'):
                    a['href'] = convert_link(self.page_file, a['href'])
            i = self._new(slots, 'html', text_label(tag), str(frag))
            tag.clear()
            tag.append(NavigableString(lead + '{{%d}}' % i + trail))
            return
        list_items = self._list_children(tag)
        for child in list(tag.children):
            if isinstance(child, Comment):
                continue
            if child.parent is None:
                continue  # consumed by a dynamic region
            if isinstance(child, Tag):
                if list_items and child in list_items:
                    continue
                self._walk(child, slots, dyn_hook)
            elif isinstance(child, NavigableString) and child.strip():
                raw = str(child)
                lead = re.match(r'^\s*', raw).group(0)
                trail = re.search(r'\s*$', raw).group(0)
                i = self._new(slots, 'text', text_label(tag), clean_text(raw))
                child.replace_with(NavigableString(lead + '{{%d}}' % i + trail))
        if list_items:
            self._make_list(tag, list_items, slots, dyn_hook)

    def _list_children(self, tag):
        """Repeatable children: ≥2 element children with the same tag and base class."""
        if tag.name in ('form', 'nav', 'select') or tag.find_parent('form') is not None and tag.name != 'ul':
            return None
        kids = [c for c in tag.children if isinstance(c, Tag)]
        if len(kids) < 2:
            return None
        def base(c):
            return (c.name, tuple(x for x in classes(c) if not x.startswith('reveal')))
        b0 = base(kids[0])
        if any(base(k) != b0 for k in kids):
            return None
        if b0[0] not in ('article', 'div', 'li', 'a', 'span', 'details'):
            return None
        if b0[0] == 'div' and not b0[1]:
            return None
        if tag.name in ('p', 'h1', 'h2', 'h3', 'label'):
            return None
        return kids

    def _make_list(self, tag, kids, slots, dyn_hook):
        items = []
        children = list(tag.children)
        for k in kids:
            # Whitespace following the item belongs to it (keeps inline-block gaps).
            idx = children.index(k)
            trail = ''
            if idx + 1 < len(children) and isinstance(children[idx + 1], NavigableString) and not isinstance(children[idx + 1], Comment) and not children[idx + 1].strip():
                trail = str(children[idx + 1])
                children[idx + 1].extract()
            item_slots = []
            kc = copy.copy(k)
            self._walk(kc, item_slots, dyn_hook)
            items.append({'tpl': str(kc) + trail, 'slots': item_slots})
        label = 'items'
        i = self._new(slots, 'list', label, None, items=items)
        del slots[i]['v']
        first = kids[0]
        first.replace_with(NavigableString('{{%d}}' % i))
        for k in kids[1:]:
            k.extract()


# ---------------------------------------------------------------------------
# Dynamic regions
# ---------------------------------------------------------------------------

HOME_NEWS_TITLES = {}
NEWS, EVENTS, RESOURCES, TEAM_GROUPS, TEAM, RES_CATS, PARTNERS = [], [], [], [], [], [], []
PARTNER_BY_IMG = {}


def partner_key_for(page_file, a):
    img = a.find('img')
    key = media_key(page_file, img['src'])
    if key not in PARTNER_BY_IMG:
        add_media(key, img.get('alt', ''))
        p = {'key': 'partner:' + os.path.splitext(os.path.basename(key))[0].strip().lower().replace(' ', '-'),
             'title': img.get('alt', ''), 'image': key, 'order': len(PARTNERS),
             'fields': {'url': a.get('href', ''), 'logo_alt': img.get('alt', '')}}
        PARTNERS.append(p)
        PARTNER_BY_IMG[key] = p
    return PARTNER_BY_IMG[key]['key']


def make_dyn_hook(page_file, lang, team_data):
    news_ctx = {'n': 0}

    def hook(tag, slots, tpl):
        cls = classes(tag)
        # News cards.
        if 'story-grid' in cls:
            cards = tag.find_all('article', recursive=False)
            heading = cards[0].find(re.compile('^h[1-6]$')).name
            delays = any(c for c in cards if any(x.startswith('reveal-delay') for x in classes(c)))
            link = cards[0].find('a', class_='card-link')
            if page_file.endswith('news.html'):
                for n, c in enumerate(cards):
                    NEWS.append({
                        'key': 'news:%s:%d' % (lang, n + 1), 'lang': lang, 'order': n,
                        'title': clean_text(c.find(heading).get_text()),
                        'excerpt': clean_text(c.find('p').get_text()),
                        'translation': 'news:en:%d' % (n + 1) if lang == 'es' else None,
                    })
            if page_file.endswith('index.html'):
                HOME_NEWS_TITLES[lang] = [(clean_text(c.find(heading).get_text()), clean_text(c.find('p').get_text())) for c in cards]
            i = tpl._new(slots, 'dyn', 'news', None, type='news',
                         o={'heading': heading, 'delays': delays, 'short_titles': page_file.endswith('index.html'),
                            'count': 0 if page_file.endswith('news.html') else len(cards)},
                         f={'link_label': {'k': 'text', 'l': 'button', 'v': clean_text(link.get_text())}})
            del slots[i]['v']
            _replace_children(tag, i)
            return True
        # Events.
        if 'vol-grid' in cls and page_file.endswith('events-calendar.html'):
            cards = tag.find_all('div', recursive=False)
            for n, c in enumerate(cards):
                pill = clean_text(c.find('span', class_='vol-pill').get_text())
                a = c.find('a', class_='card-link')
                start, end = parse_event_dates(pill)
                EVENTS.append({
                    'key': 'event:%s:%d' % (lang, n + 1), 'lang': lang, 'order': n,
                    'title': clean_text(c.find('h2').get_text()),
                    'fields': {'date_label': pill, 'start_date': start, 'end_date': end,
                               'description': clean_text(c.find('p').get_text()),
                               'link_label': clean_text(a.get_text()), 'link_url': convert_link(page_file, a['href'])},
                    'translation': 'event:en:%d' % (n + 1) if lang == 'es' else None,
                })
            i = tpl._new(slots, 'dyn', 'events', None, type='events', o={'heading': 'h2'}, f={})
            del slots[i]['v']
            _replace_children(tag, i)
            return True
        # Library resources.
        if 'program-grid' in cls and tag.find('article', class_='resource-card', recursive=False):
            cards = tag.find_all('article', recursive=False)
            featured = 1 if (tag.find_parent('section', id='resources') or tag.find_parent('section', id='recursos')) else 0
            first = cards[0]
            dl = first.find('a', class_='card-link')
            date_txt = clean_text(first.find('span', class_='resource-date').get_text())
            prefix = date_txt.split(':')[0] + ':'
            for n, c in enumerate(cards):
                img = c.find('img')
                a = c.find('a', class_='card-link')
                file_key = add_media(media_key(page_file, a['href']))
                card_date = clean_text(c.find('span', class_='resource-date').get_text())
                card_date = clean_text(card_date.split(':', 1)[1]) if ':' in card_date else card_date
                cat = clean_text(c.find('span', class_='resource-category').get_text())
                if not any(x['lang'] == lang and x['name'] == cat for x in RES_CATS):
                    RES_CATS.append({'key': 'rescat:%s:%d' % (lang, len([x for x in RES_CATS if x['lang'] == lang]) + 1), 'lang': lang, 'name': cat,
                                     'order': len([x for x in RES_CATS if x['lang'] == lang])})
                cat_key = next(x['key'] for x in RES_CATS if x['lang'] == lang and x['name'] == cat)
                RESOURCES.append({
                    'key': 'resource:%s:%s' % (lang, os.path.basename(file_key)), 'lang': lang,
                    'order': len([r for r in RESOURCES if r['lang'] == lang]),
                    'title': clean_text(c.find('h3').get_text()),
                    'image': add_media(media_key(page_file, img['src']), img.get('alt', '')),
                    'category': cat_key,
                    'fields': {'description': clean_text(c.find('p').get_text()),
                               'file': 'media:' + file_key,
                               'file_info': clean_text(c.find('span', class_='resource-file-info').get_text()),
                               'date_label': card_date,
                               'pub_date': parse_month_year(card_date),
                               'featured': featured, 'thumb_alt': img.get('alt', '')},
                })
            i = tpl._new(slots, 'dyn', 'resources', None, type='resources', o={'featured': featured},
                         f={'download_label': {'k': 'text', 'l': 'button', 'v': clean_text(dl.get_text())},
                            'published_label': {'k': 'text', 'l': 'label', 'v': prefix}})
            del slots[i]['v']
            _replace_children(tag, i)
            return True
        # Partner logos (marquee slides are a run of a.logo-slide inside .logo-marquee).
        if 'logo-marquee' in cls or 'logo-grid' in cls:
            item_cls = 'logo-slide' if 'logo-marquee' in cls else 'logo-grid-item'
            anchors = tag.find_all('a', class_=item_cls, recursive=False)
            keys = [partner_key_for(page_file, a) for a in anchors]
            uniq = list(dict.fromkeys(keys))
            repeat = len(keys) // len(uniq) if uniq and len(keys) % len(uniq) == 0 else 1
            opts = {'style': 'marquee' if item_cls == 'logo-slide' else 'grid', 'repeat': repeat, 'keys': uniq}
            # Other children of the marquee (the "View all partners" link) stay in the template.
            for child in list(tag.children):
                if isinstance(child, Tag) and child not in anchors:
                    tpl._walk(child, slots, hook)
            i = tpl._new(slots, 'dyn', 'partners', None, type='partners', o=opts,
                         f={'all': {'k': 'bool', 'l': 'partners_all', 'v': ''}})
            del slots[i]['v']
            first = True
            for a in anchors:
                if first:
                    a.replace_with(NavigableString('{{%d}}' % i))
                    first = False
                else:
                    a.extract()
            return True
        # Sponsor category members.
        if tag.name == 'ul' and 'sponsor-category__members' in cls:
            n = news_ctx.setdefault('sponsor', 0)
            news_ctx['sponsor'] = n + 1
            pending = tag.find('li', class_='sponsor-category__pending')
            i_aria = tpl._new(slots, 'text', 'aria', tag.get('aria-label', ''))
            tag['aria-label'] = '{{a:%d}}' % i_aria
            i = tpl._new(slots, 'dyn', 'sponsor_members', None, type='sponsor_members', o={'key': SPONSOR_KEYS[n]},
                         f={'pending': {'k': 'text', 'l': 'pending', 'v': clean_text(pending.get_text()) if pending else ''}})
            del slots[i]['v']
            _replace_children(tag, i)
            return True
        # Team.
        if 'team-category' in cls:
            parent = tag.parent
            cats = parent.find_all('div', class_='team-category', recursive=False)
            if tag is not cats[0]:
                return True
            for gn, cat in enumerate(cats):
                gkey = 'teamgroup:%s:%d' % (lang, gn + 1)
                TEAM_GROUPS.append({'key': gkey, 'lang': lang, 'order': gn,
                                    'name': clean_text(cat.find('h3').get_text())})
                for mn, card in enumerate(cat.select('article.team-card')):
                    mid = card['data-member-id']
                    img = card.find('img')
                    name_el = card.find('h4')
                    note = name_el.find('span')
                    note_txt = clean_text(note.get_text()) if note else ''
                    if note:
                        note.extract()
                    data = team_data.get(mid, {})
                    TEAM.append({
                        'key': 'team:%s:%s' % (lang, mid), 'lang': lang, 'slug': mid, 'group': gkey,
                        'order': mn, 'title': clean_text(name_el.get_text()),
                        'image': add_media(media_key(page_file, img['src']), img.get('alt', '')),
                        'fields': {
                            'role': clean_text(card.find(class_='team-card__role').get_text()),
                            'name_note': note_txt,
                            'summary': clean_text(card.find(class_='team-card__summary').get_text()),
                            'bio': data.get('bio', '').strip(),
                            'modal_name': data.get('name', '') if data.get('name', '') != clean_text(name_el.get_text()) else '',
                            'modal_role': data.get('role', '') if data.get('role', '') != clean_text(card.find(class_='team-card__role').get_text()) else '',
                            'photo_alt': img.get('alt', '') if img.get('alt', '') != clean_text(name_el.get_text()) else '',
                            'photo_fallback': 1 if img.get('onerror') else '',
                        },
                        'translation': 'team:en:%s' % mid if lang == 'es' else None,
                    })
                    img_js = re.sub(r'^(\.\./)+|^/', '', data.get('image', ''))
                    if img_js and img_js != TEAM[-1]['image']:
                        TEAM[-1]['modal_image'] = add_media(img_js)
                    if gn == 0 and mn == 0:
                        first_title = TEAM[-1]['title']
                        cta = clean_text(card.find(class_='team-card__cta').get_text())
                        aria = card['aria-label'].replace(first_title, '%s')
            i = tpl._new(slots, 'dyn', 'team', None, type='team', o={},
                         f={'cta': {'k': 'text', 'l': 'button', 'v': cta},
                            'aria': {'k': 'text', 'l': 'team_aria', 'v': aria}})
            del slots[i]['v']
            # Replace the run of categories (and comments between them) with the token.
            nodes = list(parent.children)
            start = nodes.index(cats[0])
            end = nodes.index(cats[-1])
            for node in nodes[start + 1:end + 1]:
                node.extract()
            cats[0].replace_with(NavigableString('{{%d}}' % i))
            return True
        return False

    return hook


def _replace_children(tag, i):
    inner_lead = ''
    kids = list(tag.children)
    if kids and isinstance(kids[0], NavigableString) and not kids[0].strip():
        inner_lead = str(kids[0])
    tag.clear()
    tag.append(NavigableString(inner_lead + '{{%d}}' % i + '\n'))


MONTHS = {m: i + 1 for i, m in enumerate(['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august',
                                          'september', 'october', 'november', 'december'])}
MONTHS.update({m: i + 1 for i, m in enumerate(['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
                                               'septiembre', 'octubre', 'noviembre', 'diciembre'])})
SHORT = {'jan': 1, 'feb': 2, 'mar': 3, 'apr': 4, 'may': 5, 'jun': 6, 'jul': 7, 'aug': 8, 'sep': 9, 'oct': 10, 'nov': 11,
         'dec': 12, 'ene': 1, 'abr': 4, 'ago': 8, 'dic': 12}


def month_num(word):
    w = word.lower().strip('.')
    return MONTHS.get(w) or SHORT.get(w[:3])


def parse_month_year(txt):
    m = re.match(r'([A-Za-zé]+)\s+(\d{4})', txt)
    if m and month_num(m.group(1)):
        return '%s-%02d-01' % (m.group(2), month_num(m.group(1)))
    return ''


def parse_event_dates(txt):
    """'July 1 - Dec 15, 2026' → ('2026-07-01', '2026-12-15'); 'Year-round' → ('', '')."""
    t = txt.replace('–', '-')
    years = re.findall(r'\d{4}', t)
    if not years:
        return '', ''
    year = years[-1]
    dates = []
    for part in t.split(' - ') if ' - ' in t else [t]:
        m = re.search(r'([A-Za-zé]+)\.?\s+(\d{1,2})', part) or re.search(r'(\d{1,2})\s+(?:de\s+)?([A-Za-zé]+)', part)
        if not m:
            continue
        a, b = m.group(1), m.group(2)
        if a.isdigit():
            a, b = b, a
        mn = month_num(a)
        if mn:
            dates.append('%s-%02d-%02d' % (year, mn, int(b)))
    if not dates:
        return '', ''
    return dates[0], (dates[1] if len(dates) > 1 else '')


# ---------------------------------------------------------------------------
# Pages
# ---------------------------------------------------------------------------

def team_members(lang):
    js = 'assets/js/team-data.js' if lang == 'en' else 'assets/js/team-data-es.js'
    code = read(js)
    node = ("const vm=require('vm');const fs=require('fs');const c=fs.readFileSync(process.argv[1],'utf8');"
            "const ctx={};vm.createContext(ctx);vm.runInContext(c+';this.__T=TEAM_MEMBERS;',ctx);"
            "process.stdout.write(JSON.stringify(ctx.__T));")
    out = subprocess.check_output(['node', '-e', node, os.path.join(ROOT, js)])
    return json.loads(out)


def section_label(el):
    h = el.find(re.compile('^h[1-3]$'))
    if h:
        return clean_text(h.get_text())[:80]
    if el.find('nav', class_='breadcrumb'):
        return 'Breadcrumb'
    return ' '.join(classes(el)) or el.name


def meta(s, name=None, prop=None):
    el = s.find('meta', attrs={'name': name}) if name else s.find('meta', attrs={'property': prop})
    return el['content'] if el and el.has_attr('content') else ''


def logo_setting(src, default):
    base = os.path.basename(src)
    return {'logo.png': 'default', 'logo-border.webp': 'default', 'logo1.png': 'home',
            'logo-funcorco.svg': 'svg', 'logo_en.png': 'en'}.get(base, default)


def apply_fixes(page_file, main):
    """Corrections of real defects (documented)."""
    if page_file in ('volunteering.html', 'es/volunteering.html'):
        a = main.find('a', href='#volunteer-form')
        if a:
            a['href'] = '#formulario-voluntario'
            FIXES.append(page_file + ': hero button pointed to #volunteer-form (no such id) → #formulario-voluntario')
    if page_file == 'es/donate-now.html':
        for a in main.find_all('a', href='../index.html'):
            a['href'] = 'index.html'
            FIXES.append(page_file + ': "Volver al inicio" pointed to the English home → Spanish home')
    if page_file == 'es/index.html':
        mq = main.find('div', class_='logo-marquee')
        nested = mq.find('section', recursive=False) if mq else None
        if nested:
            nested.extract()
            main.append(nested)
            FIXES.append(page_file + ': final call-to-action was nested inside the logo marquee (unclosed tag) and was '
                         'never visible → rendered as its own section after the logos')


def extract_page(lang, page_file, slug, title, team_data):
    s = soup(page_file)
    main = s.find('main')
    apply_fixes(page_file, main)
    hook = make_dyn_hook(page_file, lang, team_data)
    sections = []
    for el in [c for c in main.children if isinstance(c, Tag)]:
        label = section_label(el)
        el2 = copy.copy(el)
        slots = []
        tpl = Templater(page_file, lang).template(el2, slots, hook)
        sections.append({'label': label, 'tpl': tpl, 'slots': slots})
    header = s.find('header', class_='site-header')
    footer = s.find('footer', class_='site-footer')
    brand = header.find('a', class_='brand').find('img')
    flogo = footer.find('div', class_='footer-brand').find('img')
    doc_title = clean_text(s.title.get_text())
    desc = meta(s, name='description')
    if not page_file.endswith('404.html'):
        assert meta(s, prop='og:title') == doc_title, page_file
        assert meta(s, prop='og:description') == desc, page_file
    og_img = meta(s, prop='og:image').split('/assets/', 1)[-1]
    og_key = add_media('assets/' + og_img) if og_img else ''
    key = 'page:%s:%s' % (lang, slug or 'home')
    page = {
        'key': key, 'lang': lang, 'file': page_file, 'slug': slug, 'title': title,
        'parent': 'page:es:es' if lang == 'es' and page_file != 'es/index.html' else None,
        'front': page_file == 'index.html', 'es_root': page_file == 'es/index.html',
        'is_404': page_file.endswith('404.html'),
        'seo_title': doc_title, 'seo_description': desc, 'og_image': og_key,
        'layout': {'v': 2, 'settings': {
            'header_logo': logo_setting(brand['src'], 'default'),
            'footer_logo': logo_setting(flogo['src'], 'default'),
            'footer_style': 'icons' if footer.select('.footer-social svg') else 'standard',
            'fonts': bool(s.find('link', href=re.compile('fonts.googleapis.com/css'))),
        }, 'sections': sections},
    }
    return page


def nav_and_footer(lang):
    """Menus and footer texts from the majority header/footer variant (news.html)."""
    f = 'news.html' if lang == 'en' else 'es/news.html'
    s = soup(f)
    header = s.find('header', class_='site-header')
    footer = s.find('footer', class_='site-footer')
    nav = [{'label': clean_text(a.get_text()), 'url': convert_link(f, a['href'])}
           for a in header.select('nav.site-nav > a.nav-link')]
    donate = header.find('a', class_='donate-btn')
    cols = []
    for col in footer.select('.footer-grid > div')[1:]:
        cols.append({'title': clean_text(col.find('h3').get_text()),
                     'links': [{'label': clean_text(a.get_text()), 'url': convert_link(f, a['href'])}
                               for a in col.find_all('a', class_='footer-link')]})
    social = {a['aria-label']: a['href'] for a in footer.select('.footer-social a')}
    social_labels = [a['aria-label'] for a in footer.select('.footer-social a')]
    bottom = footer.find(class_='footer-bottom')
    copy_el = bottom.find(['p', 'span'], recursive=False)
    credit = bottom.find('a', class_='footer-credit')
    top = bottom.find('a', class_='footer-top-link')
    return {
        'nav': nav,
        'mods': {
            'donate_label': clean_text(donate.get_text()), 'donate_url': convert_link(f, donate['href']),
            'brand_alt': header.find('a', class_='brand').find('img')['alt'],
            'menu_open_label': header.find('button', class_='nav-toggle')['aria-label'],
            'nav_label': header.find('nav')['aria-label'],
            'lang_label': header.find('div', class_='lang-switch')['aria-label'],
            'skip_label': clean_text(s.find('a', class_='skip-link').get_text()),
            'footer_alt': footer.find('div', class_='footer-brand').find('img')['alt'],
            'footer_text': clean_text(footer.find('p', class_='footer-text').get_text()),
            'copyright': clean_text(copy_el.get_text()),
            'credit_label': clean_text(credit.get_text()), 'credit_url': credit['href'],
            'top_label': clean_text(top.get_text()),
            'social_labels': social_labels,
        },
        'social': social,
        'footer_columns': cols,
    }


def main():
    check = '--check' in sys.argv
    team = {'en': team_members('en'), 'es': team_members('es')}
    pages = [extract_page(lang, f, slug, title, team[lang]) for lang, f, slug, title in PAGES]

    # Home page cards use shorter titles than the News page: keep them as "short title".
    for n in NEWS:
        titles = HOME_NEWS_TITLES.get(n['lang'], [])
        short, text = titles[n['order']] if n['order'] < len(titles) else ('', '')
        n['fields'] = {'short_title': short if short and short != n['title'] else '',
                       'short_excerpt': text if text and text != n['excerpt'] else ''}

    # Translations of pages.
    by_file = {p['file']: p for p in pages}
    for p in pages:
        if p['lang'] == 'en':
            es_file = PAGE_PAIRS.get(p['file'], 'es/' + p['file'])
            if es_file in by_file:
                p['translation'] = by_file[es_file]['key']

    # Resource translations (same file name, or a known EN↔ES pair).
    for r in RESOURCES:
        if r['lang'] != 'es':
            continue
        fname = os.path.basename(r['fields']['file'])
        en_name = {v: k for k, v in LIBRARY_PAIRS.items()}.get(fname, fname)
        for e in RESOURCES:
            if e['lang'] == 'en' and os.path.basename(e['fields']['file']) == en_name:
                r['translation'] = e['key']
        if 'translation' not in r:
            # Fall back to the position in the library (both libraries list the same documents).
            same = [e for e in RESOURCES if e['lang'] == 'en' and e['order'] == r['order']]
            r['translation'] = same[0]['key'] if same else None
    for e in EVENTS:
        if e['lang'] == 'es' and not any(x['key'] == e['translation'] for x in EVENTS):
            e['translation'] = None

    # Partners shown everywhere in the same order: keep "keys" only where the selection differs.
    all_keys = [p['key'] for p in PARTNERS]

    def strip_keys(slots):
        for sl in slots:
            if sl.get('k') == 'dyn' and sl.get('type') == 'partners':
                if sl['o']['keys'] == all_keys:
                    del sl['o']['keys']
            if sl.get('k') == 'list':
                for it in sl['items']:
                    strip_keys(it['slots'])
    for p in pages:
        for sec in p['layout']['sections']:
            strip_keys(sec['slots'])

    chrome = {'en': nav_and_footer('en'), 'es': nav_and_footer('es')}

    # Theme logos are part of the theme; everything else referenced goes to the Media Library.
    data = {
        'version': 1,
        'generated': datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ'),
        'source_commit': subprocess.run(['git', 'rev-parse', '--short', 'HEAD'], cwd=ROOT, capture_output=True, text=True).stdout.strip(),
        'media': sorted(MEDIA.values(), key=lambda m: m['key']),
        'pages': pages,
        'news': NEWS,
        'events': EVENTS,
        'team_groups': TEAM_GROUPS,
        'team': TEAM,
        'resource_categories': RES_CATS,
        'resources': RESOURCES,
        'partners': PARTNERS,
        'chrome': chrome,
        'fixes': sorted(set(FIXES)),
    }
    text = json.dumps(data, ensure_ascii=False, indent=1)
    if check:
        cur = open(OUT, encoding='utf-8').read() if os.path.exists(OUT) else ''
        a = json.loads(cur) if cur else {}
        a.pop('generated', None)
        b = json.loads(text)
        b.pop('generated', None)
        print('up to date' if a == b else 'OUTDATED')
        sys.exit(0 if a == b else 1)
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, 'w', encoding='utf-8') as fh:
        fh.write(text + '\n')
    print('pages %d, media %d, news %d, events %d, team %d (%d groups), resources %d, partners %d' % (
        len(pages), len(MEDIA), len(NEWS), len(EVENTS), len(TEAM), len(TEAM_GROUPS), len(RESOURCES), len(PARTNERS)))
    for f in sorted(set(FIXES)):
        print('FIX:', f)


if __name__ == '__main__':
    main()
