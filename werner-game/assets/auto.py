import sys,json,numpy as np
from PIL import Image,ImageDraw
from scipy import ndimage as nd
SP=sys.argv[1];name=sys.argv[2]
im=Image.open(f'{SP}/sheets/{name}.jpg').convert('RGB');A=np.array(im).astype(int)
light=(A[:,:,2]>225)&(A[:,:,0]>150)&(A[:,:,1]>195)
light=nd.binary_closing(light,iterations=2)
lab,n=nd.label(light)
boxes=[]
for i,sl in enumerate(nd.find_objects(lab)):
    h=sl[0].stop-sl[0].start;w=sl[1].stop-sl[1].start
    if h>150 and w>120: boxes.append((sl[1].start,sl[0].start,sl[1].stop,sl[0].stop))
boxes.sort(key=lambda b:(b[1]//150,b[0]))
print(name,boxes)
json.dump(boxes,open(f'{SP}/sheets/{name}.json','w'))
