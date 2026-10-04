import sys,json,numpy as np
from PIL import Image,ImageDraw
from scipy import ndimage as nd
SP=sys.argv[1]
A=np.array(Image.open(f'{SP}/sheets/women.jpg').convert('RGB')).astype(int)
panels=[(15,70,352,478),(417,198,745,478),(946,198,1262,478),(15,523,305,839),(326,523,659,839)]
out=[];meta={}
for pi,(x0,y0,x1,y1) in enumerate(panels):
    x0+=4;y0+=4;x1-=4;y1-=4
    if pi==0: y0=170  # skip title text
    if pi==4: y0=600
    P=A[y0:y1,x0:x1];h,w,_=P.shape
    bg=np.median(np.concatenate([P[-1],P[:,0],P[:,-1]]),axis=0)
    d=np.sqrt(((P-bg)**2).sum(2))
    lab,n=nd.label(d<48)
    border=set(np.unique(np.concatenate([lab[0],lab[-1],lab[:,0],lab[:,-1]])))-{0}
    trans=np.isin(lab,list(border))|(d<22)
    for _ in range(2): trans|=nd.binary_dilation(trans)&(d<85)
    solid=~trans
    lab2,n2=nd.label(solid);sizes=nd.sum(solid,lab2,range(1,n2+1));big=sizes.max()
    comps=[i+1 for i,s in enumerate(sizes) if s>big*0.3]
    objs=nd.find_objects(lab2)
    comps.sort(key=lambda c:objs[c-1][1].start)
    for c in comps:
        sl=objs[c-1];keep=(lab2==c)
        # include small nearby pieces (e.g. separated feet) inside bbox
        sub=keep[sl];rgba=np.dstack([P[sl].astype(np.uint8),(sub*255).astype(np.uint8)])
        im=Image.fromarray(rgba,'RGBA');k=f'w{len(out)}'
        hh=sub.shape[0];top=sub[:max(4,int(hh*.08))];ax=float(np.where(top)[1].mean())
        meta[k]={'w':im.width,'h':im.height,'ax':round(ax,1),'panel':pi};im.save(f'{SP}/sheets/women_{k}.png');out.append(im)
json.dump(meta,open(f'{SP}/sheets/women_meta.json','w'))
W=sum(i.width for i in out)+10*len(out);H=max(i.height for i in out)+14
mo=Image.new('RGBA',(W,H),(40,90,50,255));dr=ImageDraw.Draw(mo);x=0
for i,im in enumerate(out): mo.alpha_composite(im,(x,H-im.height));dr.text((x+2,1),f'w{i}',fill=(255,255,0,255));x+=im.width+10
mo.save(f'{SP}/sheets/women_montage.png');print(json.dumps(meta))
