import sys,json,numpy as np
from PIL import Image,ImageDraw
from scipy import ndimage as nd
SP=sys.argv[1]
def extract(A,box,top_half=False):
    x0,y0,x1,y1=box;x0+=3;y0+=3;x1-=3;y1-=3
    if top_half: y1=y0+int((y1-y0)*.5)
    P=A[y0:y1,x0:x1];h,w,_=P.shape
    edge=np.concatenate([P[0],P[-1],P[:,0],P[:,-1]]);bg=np.median(edge,axis=0)
    d=np.sqrt(((P-bg)**2).sum(2))
    near=d<48
    lab,n=nd.label(near)
    border=set(np.unique(np.concatenate([lab[0],lab[-1],lab[:,0],lab[:,-1]])))-{0}
    trans=np.isin(lab,list(border))|(d<22)
    for _ in range(2):
        nb=nd.binary_dilation(trans)&(d<85);trans|=nb
    solid=~trans
    lab2,n2=nd.label(solid)
    sizes=nd.sum(solid,lab2,range(1,n2+1))
    big=sizes.max()
    keep=np.isin(lab2,[i+1 for i,s in enumerate(sizes) if s>big*0.03])
    ys,xs=np.where(keep)
    by0,by1,bx0,bx1=ys.min(),ys.max(),xs.min(),xs.max()
    rgba=np.dstack([P.astype(np.uint8),(keep*255).astype(np.uint8)])[by0:by1+1,bx0:bx1+1]
    hh=by1-by0+1
    top=keep[by0:by0+max(4,int(hh*.07)),bx0:bx1+1]
    ax=float(np.where(top)[1].mean())
    return Image.fromarray(rgba,'RGBA'),{'w':int(bx1-bx0+1),'h':int(hh),'ax':round(ax,1)}
RUG=[(14,70,351,478),(417,198,631,478),(640,198,846,478),(854,198,1061,478),(1070,198,1262,478),(15,523,304,839),(326,523,659,839),(681,523,946,839),(968,523,1262,839)]
for name in ['agbald','agblue','gym','rugby','punk','repgray','repblue']:
    A=np.array(Image.open(f'{SP}/sheets/{name}.jpg').convert('RGB')).astype(int)
    boxes=json.load(open(f'{SP}/sheets/{name}.json'))
    if name=='punk': boxes=RUG
    meta={};ims=[]
    for i,b in enumerate(boxes):
        th=(i==8 and name in('gym','rugby','punk'))
        im,m=extract(A,b,th);meta[f'f{i}']=m;im.save(f'{SP}/sheets/{name}_f{i}.png');ims.append(im)
    json.dump(meta,open(f'{SP}/sheets/{name}_meta.json','w'))
    W=sum(i.width for i in ims)+12*len(ims);H=max(i.height for i in ims)+16
    mo=Image.new('RGBA',(W,H),(40,90,50,255));dr=ImageDraw.Draw(mo);x=0
    for i,im in enumerate(ims):
        mo.alpha_composite(im,(x,H-im.height));dr.text((x+2,2),f'f{i}',fill=(255,255,0,255));x+=im.width+12
    mo.save(f'{SP}/sheets/{name}_montage.png');print(name,json.dumps(meta))
