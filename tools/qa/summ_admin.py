import json,sys
r=json.load(open('docs/qa/data/admin.json',encoding='utf8'))
for n,v in r['pages'].items():
    for vp,d in v.items():
        flag=[]
        if d['sw']>d['vw']+1: flag.append(f"OVERFLOW sw={d['sw']} {d['off'][:2]}")
        if d.get('console'): flag.append(str(d['console'][:2]))
        if d['h1']!=1: flag.append(f"h1={d['h1']}")
        if d['noname']: flag.append('noname '+str(d['noname'][:2]))
        if d['small'] and vp in ('1440','390'): flag.append(f"small({len(d['small'])}) "+str(d['small'][:3]))
        if flag: print(n,vp,' | '.join(flag)[:420])
