"""Extract published posts/pages/menus from the WordPress dump into JSON."""
import re, json, sys
SQL = r'C:\Projects1\pressafrica\exxhrgce_wp623.sql'
OUT = r'C:\Projects1\pressafrica\prototype_html\data'

def parse_values(s, i):
    """Parse a sequence of (..),(..) tuples starting at s[i]; yield rows."""
    n = len(s)
    while i < n:
        while i < n and s[i] in ' \n\r\t,': i += 1
        if i >= n or s[i] == ';': return
        if s[i] != '(': i += 1; continue
        i += 1; row = []
        while True:
            while s[i] in ' \t': i += 1
            if s[i] == "'":
                i += 1; buf = []
                while True:
                    c = s[i]
                    if c == chr(92):
                        nx = s[i+1]
                        buf.append({'n':chr(10),'r':chr(13),'t':chr(9),'0':chr(0)}.get(nx, nx)); i += 2
                    elif c == "'":
                        if s[i+1] == "'": buf.append("'"); i += 2
                        else: i += 1; break
                    else: buf.append(c); i += 1
                row.append(''.join(buf))
            else:
                j = i
                while s[j] not in ',)': j += 1
                v = s[i:j].strip(); i = j
                row.append(None if v == 'NULL' else v)
            if s[i] == ',': i += 1; continue
            if s[i] == ')': i += 1; break
        yield row

def table_rows(text, table):
    out = []
    for mm in re.finditer(r'INSERT INTO `%s` \(([^)]*)\)\s*VALUES\s*' % table, text):
        cols = re.findall(r'`(\w+)`', mm.group(1))
        out += [dict(zip(cols, r)) for r in parse_values(text, mm.end())]
    return out

text = open(SQL, encoding='utf-8', errors='replace').read()
posts = table_rows(text, 'wpha_posts')
meta = table_rows(text, 'wpha_postmeta')
terms = table_rows(text, 'wpha_terms')
tax = table_rows(text, 'wpha_term_taxonomy')
rel = table_rows(text, 'wpha_term_relationships')
users = table_rows(text, 'wpha_users')
print('posts', len(posts), 'meta', len(meta), 'terms', len(terms), 'rel', len(rel))

pm = {}
for m in meta: pm.setdefault(m['post_id'], {})[m['meta_key']] = m['meta_value']
tname = {t['term_id']: t for t in terms}
tt = {x['term_taxonomy_id']: x for x in tax}
cats = {}
for r in rel:
    x = tt.get(r['term_taxonomy_id'])
    if x and x['taxonomy'] in ('category', 'post_tag'):
        cats.setdefault(r['object_id'], []).append({'tax': x['taxonomy'], 'slug': tname[x['term_id']]['slug'], 'name': tname[x['term_id']]['name']})
att = {p['ID']: p for p in posts if p['post_type'] == 'attachment'}
authors = {u['ID']: u['display_name'] for u in users}

def thumb(pid):
    t = pm.get(pid, {}).get('_thumbnail_id')
    if t and t in att:
        g = att[t]['guid']
        return {'url': g, 'path': re.sub(r'^.*/wp-content/uploads/', '', g), 'alt': pm.get(t, {}).get('_wp_attachment_image_alt', '')}
    return None

arts = []
for p in posts:
    if p['post_type'] in ('post', 'page') and p['post_status'] == 'publish':
        d = pm.get(p['ID'], {})
        arts.append({
            'id': p['ID'], 'type': p['post_type'], 'slug': p['post_name'], 'title': p['post_title'],
            'date': p['post_date'], 'modified': p['post_modified'], 'author': authors.get(p['post_author'], ''),
            'excerpt': p['post_excerpt'], 'content': p['post_content'], 'image': thumb(p['ID']),
            'categories': [c for c in cats.get(p['ID'], []) if c['tax'] == 'category'],
            'tags': [c for c in cats.get(p['ID'], []) if c['tax'] == 'post_tag'],
            'seo_title': d.get('_seopress_titles_title', ''), 'seo_desc': d.get('_seopress_titles_desc', '')})
arts.sort(key=lambda a: a['date'], reverse=True)
json.dump(arts, open(OUT + r'\articles.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
catlist = [{'id': t['term_id'], 'slug': tname[t['term_id']]['slug'], 'name': tname[t['term_id']]['name'], 'parent': t['parent'], 'count': t['count']} for t in tax.__iter__() if t['taxonomy'] == 'category']
json.dump(catlist, open(OUT + r'\categories.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print('articles', sum(a['type']=='post' for a in arts), 'pages', sum(a['type']=='page' for a in arts), 'cats', len(catlist))
