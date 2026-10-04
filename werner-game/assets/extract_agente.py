from PIL import Image
import numpy as np, json, sys, base64, io
from collections import deque
SP=sys.argv[1]

src=Image.open('agente-spritesheet.jpg').convert('RGB')
A=np.array(src).astype(int)
panels={'stance':(14,69,347,473),'walk1':(413,196,625,473),'walk2':(633,196,837,473),'walk3':(846,196,1050,473),'victory':(1058,196,1251,473),
 'attack':(14,517,301,832),'hurt':(323,517,652,832),'hurt2':(674,517,936,832),'down':(958,517,1251,832)}
out={}
for name,(x0,y0,x1,y1) in panels.items():
    x0+=2;y0+=2;x1-=2;y1-=2
    P=A[y0:y1,x0:x1]
    h,w,_=P.shape
    bg=np.median(np.concatenate([P[0],P[-1],P[:,0],P[:,-1]]),axis=0)
    d=np.sqrt(((P-bg)**2).sum(2))
    trans=np.zeros((h,w),bool)
    q=deque()
    for x in range(w):
        for y in (0,h-1): q.append((y,x))
    for y in range(h):
        for x in (0,w-1): q.append((y,x))
    while q:
        y,x=q.popleft()
        if trans[y,x] or d[y,x]>48: continue
        trans[y,x]=True
        for dy,dx in((1,0),(-1,0),(0,1),(0,-1)):
            ny,nx=y+dy,x+dx
            if 0<=ny<h and 0<=nx<w and not trans[ny,nx]: q.append((ny,nx))
    # also kill isolated bg-like pixels (speed lines / holes between legs)
    trans|= d<22
    # halo cleanup
    for _ in range(2):
        nb=np.zeros_like(trans)
        nb[1:]|=trans[:-1];nb[:-1]|=trans[1:];nb[:,1:]|=trans[:,:-1];nb[:,:-1]|=trans[:,1:]
        trans|= nb & (d<85)
    # keep big components
    solid=~trans
    lab=np.zeros((h,w),int);cid=0;sizes={}
    for y in range(h):
        for x in range(w):
            if solid[y,x] and not lab[y,x]:
                cid+=1;st=[(y,x)];lab[y,x]=cid;n=0
                while st:
                    cy,cx=st.pop();n+=1
                    for dy,dx in((1,0),(-1,0),(0,1),(0,-1)):
                        ny,nx=cy+dy,cx+dx
                        if 0<=ny<h and 0<=nx<w and solid[ny,nx] and not lab[ny,nx]:
                            lab[ny,nx]=cid;st.append((ny,nx))
                sizes[cid]=n
    big=max(sizes.values())
    keep=np.isin(lab,[c for c,n in sizes.items() if n>big*0.02])
    alpha=(keep*255).astype(np.uint8)
    ys,xs=np.where(keep)
    by0,by1,bx0,bx1=ys.min(),ys.max(),xs.min(),xs.max()
    rgba=np.dstack([P.astype(np.uint8),alpha])[by0:by1+1,bx0:bx1+1]
    img=Image.fromarray(rgba,'RGBA')
    # anchor: head center x from top 7% rows
    hh=by1-by0+1
    top=keep[by0:by0+max(4,int(hh*.07)),bx0:bx1+1]
    ax=float(np.where(top)[1].mean())
    out[name]={'w':int(bx1-bx0+1),'h':int(hh),'ax':round(ax,1)}
    img.save(f'{SP}/ag/{name}.png')
for name,(x0,y0,x1,y1) in {'face':(877,84,970,178)}.items():
    src.crop((x0,y0,x1,y1)).save(f'{SP}/ag/{name}.png')
print(json.dumps(out))
json.dump(out,open(f'{SP}/ag/meta.json','w'))
