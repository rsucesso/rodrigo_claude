import json,base64,io,sys
from PIL import Image
SP=sys.argv[1]
def M(path): return json.load(open(path))
wm=M(f'{SP}/spr/meta.json'); am=M(f'{SP}/ag/meta.json')
E=[]  # key, png, h_orig, scale, ax(orig px) or 'c'
for k,s in dict(stance=.356,walk1=.46,walk2=.46,walk3=.46,walk4=.46,stance2=.395,punch=.395,kick=.395,victory=.395).items():
    E.append((k,f'{SP}/spr/{k}.png',s,wm[k]['ax']))
for k,s in dict(stance=.275,walk1=.355,walk2=.355,walk3=.355,victory=.355,attack=.33,hurt=.33,hurt2=.33,down=.33).items():
    ax=am[k]['ax'];
    if k=='attack': ax=140
    if k=='down': ax='c'
    E.append(('ag_'+k,f'{SP}/ag/{k}.png',s,ax))
def sheet(pre,name,mapping,target,refs,axo={}):
    meta=M(f'{SP}/sheets/{name}_meta.json')
    for key,(fi,row) in mapping.items():
        m=meta[f'f{fi}'];ref=refs[row];s=target/ref
        ax=axo.get(key,m['ax'])
        E.append((pre+key,f'{SP}/sheets/{name}_f{fi}.png',s,ax))
AGMAP=dict(stance=(0,0),walk1=(1,1),walk2=(2,1),walk3=(3,1),victory=(4,1),attack=(5,2),hurt=(6,2),hurt2=(7,2),down=(8,2))
for pre,name in (('agb_','agblue'),('agz_','agbald')):
    meta=M(f'{SP}/sheets/{name}_meta.json')
    refs=[meta['f0']['h'],meta['f1']['h'],meta['f1']['h']*1.076]
    sheet(pre,name,AGMAP,94,refs,{'attack':meta['f5']['w']*.62,'down':'c'})
gm=M(f'{SP}/sheets/gym_meta.json')
sheet('gym_','gym',dict(stance=(0,0),walk1=(1,1),walk2=(2,1),attack=(3,1),charge=(4,1),stance2=(5,2),block=(6,2),taunt=(7,2),down=(8,2)),126,[gm['f0']['h'],gm['f1']['h']*1.06,gm['f5']['h']],{'attack':143,'charge':138,'down':'c'})
rm=M(f'{SP}/sheets/rugby_meta.json')
sheet('rug_','rugby',dict(stance=(0,0),walk1=(1,1),walk2=(2,1),attack=(3,1),victory=(4,1),stance2=(5,2),block=(6,2),taunt=(7,2),down=(8,2)),126,[rm['f0']['h'],rm['f4']['h'],rm['f5']['h']],{'attack':140,'down':'c'})
pm=M(f'{SP}/sheets/punk_meta.json')
sheet('punk_','punk',dict(stance=(0,0),attack=(1,1),walk1=(2,1),walk2=(3,1),victory=(4,1),stance2=(5,2),block=(6,2),taunt=(7,2),down=(8,2)),126,[pm['f0']['h'],pm['f4']['h']-18,pm['f5']['h']-14],{'attack':109,'walk1':147,'walk2':160,'victory':56,'stance2':126,'taunt':102,'down':'c'})
MAXH=240
js=['const SPRITE_DATA={'];META={}
def enc(im,colors=80):
    q=im.quantize(colors=colors,method=Image.Quantize.FASTOCTREE);b=io.BytesIO();q.save(b,'PNG',optimize=True);return base64.b64encode(b.getvalue()).decode()
tot=0
for key,path,s,ax in E:
    im=Image.open(path).convert('RGBA');w,h=im.size
    r=min(1,MAXH/h)
    if r<1: im=im.resize((max(1,round(w*r)),max(1,round(h*r))),Image.LANCZOS)
    axv=w/2 if ax=='c' else ax
    META[key]={'w':im.width,'h':im.height,'ax':round(axv*r,1),'s':round(s/r,4)}
    d=enc(im);tot+=len(d);js.append(f"  {key}:'data:image/png;base64,{d}',")
for key,path in (('face',f'{SP}/spr/face.png'),('faceAngry',f'{SP}/spr/faceAngry.png')):
    d=enc(Image.open(path).convert('RGBA'),96);tot+=len(d);js.append(f"  {key}:'data:image/png;base64,{d}',")
js.append('};');js.append('const SPRITE_META='+json.dumps(META)+';')
open(f'{SP}/sprites.js','w').write('\n'.join(js));print('bytes',tot,len(E))
