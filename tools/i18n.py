"""Translation files for HelloAdmin Guard without WP-CLI.

  python3 tools/i18n.py pot          -> languages/helloadmin-guard.pot from the PHP sources
  python3 tools/i18n.py mo           -> languages/*.mo from languages/*.po

The standard way, when WP-CLI is around: wp i18n make-pot . languages/helloadmin-guard.pot && wp i18n make-mo languages
"""
import os, re, sys, struct, datetime

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG = os.path.join(ROOT, 'languages')
DOMAIN = 'helloadmin-guard'
CALL = re.compile(r"(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'" + DOMAIN + r"'\s*\)")


def header_field(name):
    m = re.search(r'^\s*\*\s*' + re.escape(name) + r':\s*(.+)$', open(os.path.join(ROOT, DOMAIN + '.php'), encoding='utf-8').read(), re.M)
    return m.group(1).strip() if m else ''


def strings():
    found = {}
    for key in ('Plugin Name', 'Description'):
        found.setdefault(header_field(key), {'refs': [DOMAIN + '.php'], 'comment': key + ' of the plugin'})
    for dp, dn, fn in os.walk(ROOT):
        dn[:] = [d for d in dn if d not in ('vendor', '.git', 'tests', 'tools', 'node_modules', 'languages')]
        for f in sorted(fn):
            if not f.endswith('.php'):
                continue
            path = os.path.join(dp, f)
            lines = open(path, encoding='utf-8').read().split('\n')
            for i, line in enumerate(lines):
                for m in CALL.finditer(line):
                    s = m.group(1).replace("\\'", "'")
                    comment = ''
                    for back in (1, 2):
                        if i - back >= 0 and 'translators:' in lines[i - back]:
                            comment = re.sub(r'^\s*/\*\s*|\s*\*/\s*$', '', lines[i - back]).strip()
                    e = found.setdefault(s, {'refs': [], 'comment': ''})
                    e['refs'].append(os.path.relpath(path, ROOT) + ':' + str(i + 1))
                    e['comment'] = e['comment'] or comment
    return found


def q(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'


def write_pot():
    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M+0000')
    out = ['# Copyright (C) HelloAdmin', '# This file is distributed under the GPL-2.0-or-later.', 'msgid ""', 'msgstr ""',
           q('Project-Id-Version: HelloAdmin Guard\n'), q('Report-Msgid-Bugs-To: https://github.com/HelloAdminCom/helloadmin-guard/issues\n'),
           q('POT-Creation-Date: ' + now + '\n'), q('MIME-Version: 1.0\n'), q('Content-Type: text/plain; charset=UTF-8\n'),
           q('Content-Transfer-Encoding: 8bit\n'), q('X-Domain: ' + DOMAIN + '\n'), '']
    for s, e in strings().items():
        if e['comment']:
            out.append('#. ' + e['comment'])
        out.append('#: ' + ' '.join(e['refs']))
        if '%s' in s:
            out.append('#, php-format')
        out += ['msgid ' + q(s), 'msgstr ""', '']
    open(os.path.join(LANG, DOMAIN + '.pot'), 'w', encoding='utf-8').write('\n'.join(out))


def unq(s):
    return s[1:-1].replace('\\n', '\n').replace('\\"', '"').replace('\\\\', '\\')


def read_po(path):
    entries, cur, field = [], {'msgid': '', 'msgstr': ''}, None
    for line in open(path, encoding='utf-8').read().split('\n') + ['']:
        if line.startswith('msgid '):
            if field == 'msgstr':
                entries.append(cur)
                cur = {'msgid': '', 'msgstr': ''}
            field, cur['msgid'] = 'msgid', unq(line[6:])
        elif line.startswith('msgstr '):
            field, cur['msgstr'] = 'msgstr', unq(line[7:])
        elif line.startswith('"') and field:
            cur[field] += unq(line)
        elif not line.strip() and field == 'msgstr':
            entries.append(cur)
            cur, field = {'msgid': '', 'msgstr': ''}, None
    return {e['msgid']: e['msgstr'] for e in entries if e['msgstr']}


def write_mo(po_path):
    cat = read_po(po_path)
    keys = sorted(cat)
    ids = b''.join(k.encode('utf-8') + b'\0' for k in keys)
    strs = b''.join(cat[k].encode('utf-8') + b'\0' for k in keys)
    n = len(keys)
    o_ids, o_strs, pos = [], [], 0
    for k in keys:
        b = k.encode('utf-8'); o_ids.append((len(b), pos)); pos += len(b) + 1
    pos = 0
    for k in keys:
        b = cat[k].encode('utf-8'); o_strs.append((len(b), pos)); pos += len(b) + 1
    start_ids = 28 + 16 * n
    start_strs = start_ids + len(ids)
    data = struct.pack('<7I', 0x950412de, 0, n, 28, 28 + 8 * n, 0, 0)
    data += b''.join(struct.pack('<2I', l, start_ids + p) for l, p in o_ids)
    data += b''.join(struct.pack('<2I', l, start_strs + p) for l, p in o_strs)
    data += ids + strs
    open(po_path[:-3] + '.mo', 'wb').write(data)
    return n


if __name__ == '__main__':
    os.makedirs(LANG, exist_ok=True)
    if sys.argv[1:] == ['pot']:
        write_pot(); print('pot:', len(strings()), 'strings')
    elif sys.argv[1:] == ['mo']:
        for f in sorted(os.listdir(LANG)):
            if f.endswith('.po'):
                print(f, write_mo(os.path.join(LANG, f)), 'entries')
