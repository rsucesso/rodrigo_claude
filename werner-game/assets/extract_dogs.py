import sys,json,numpy as np
from PIL import Image,ImageDraw
from scipy import ndimage as nd
SP=sys.argv[1]
REG={'tied':(18,282,548,500),'walkA':(950,278,1260,388),'walkB':(950,392,1260,502),
 'm0':(954,612,1100,718),'m1':(1110,612,1256,718),'m2':(954,728,1100,834),'m3':(1110,728,1256,834),'thank':(18,600,656,834)}
allm={}
for di in range(4):
    A=np.array(Image.open(f'{SP}/sheets/dog{di}.jpg').convert('RGB')).astype(int)
    out=[]
    for rn,(x0,y0,x1,y1) in REG.items():
        P=A[y0:y1,x0:x1];h,w,_=P.shape
        bg=np.median(np.concatenate([P[-1],P[:,0],P[:,-1],P[0]]),axis=0)
        d=np.sqrt(((P-bg)**2).sum(2))
        lab,n=nd.label(d<40)
        border=set(np.unique(np.concatenate([lab[0],lab[-1],lab[:,0],lab[:,-1]])))-{0}
        trans=np.isin(lab,list(border))|(d<18)
        for _ in range(2): trans|=nd.binary_dilation(trans)&(d<70)
        solid=nd.binary_opening(~trans,iterations=1)|(~trans&~nd.binary_erosion(~trans))
        solid=~trans
        lab2,n2=nd.label(solid);sizes=nd.sum(solid,lab2,range(1,n2+1));big=sizes.max()
        objs=nd.find_objects(lab2)
        comps=[i+1 for i,s in enumerate(sizes) if s>big*0.25]
        comps.sort(key=lambda c:objs[c-1][1].start)
        for ci,c in enumerate(comps):
            sl=objs[c-1];sub=(lab2==c)[sl]
            rgba=np.dstack([P[sl].astype(np.uint8),(sub*255).astype(np.uint8)])
            k=f'{rn}{ci}';im=Image.fromarray(rgba,'RGBA');im.save(f'{SP}/sheets/dog{di}_{k}.png')
            allm[f'dog{di}_{k}']={'w':im.width,'h':im.height};out.append((k,im))
    W=sum(i.width for _,i in out)+10*len(out);H=max(i.height for _,i in out)+14
    mo=Image.new('RGBA',(W,H),(40,90,50,255));dr=ImageDraw.Draw(mo);x=0
    for k,im in out: mo.alpha_composite(im,(x,H-im.height));dr.text((x+1,1),k,fill=(255,255,0,255));x+=im.width+10
    mo.save(f'{SP}/sheets/dog{di}_montage.png')
json.dump(allm,open(f'{SP}/sheets/dogs_meta.json','w'))
print(json.dumps(allm))
