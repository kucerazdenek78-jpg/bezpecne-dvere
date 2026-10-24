import numpy as np, os, sys
from PIL import Image
from skimage import color, morphology
from scipy import ndimage as ndi
SRC='/home/claude/konfigurator/konfigurator-erkado/img/typy'
OUT='/home/claude/konfigurator/konfigurator-erkado/img/maska'
os.makedirs(OUT,exist_ok=True)
def process(name, dbg=None):
    im=np.asarray(Image.open(f'{SRC}/{name}.jpg').convert('RGB')).astype(float)/255
    lab=color.rgb2lab(im); H,W=lab.shape[:2]
    # vzorek povrchu: boční pásy křídla (sklo a madlo bývají uprostřed / u zámku)
    ys=slice(int(H*.1),int(H*.9))
    reg=np.concatenate([lab[ys,int(W*.02):int(W*.08)].reshape(-1,3),lab[ys,int(W*.92):int(W*.98)].reshape(-1,3),
                        lab[int(H*.02):int(H*.06),int(W*.1):int(W*.9)].reshape(-1,3),lab[int(H*.94):int(H*.98),int(W*.1):int(W*.9)].reshape(-1,3)])
    q=np.round(reg/6).astype(int)
    keys,cnt=np.unique(q,axis=0,return_counts=True)
    order=np.argsort(-cnt); pick=order[0]
    if keys[pick][0]*6<15:
        for o in order:
            if keys[o][0]*6>=15 and cnt[o]>=0.12*cnt[order[0]]: pick=o; break
    dom=keys[pick]*6.0
    d=np.linalg.norm(reg-dom,axis=1); near=reg[d<14]; dom=np.median(near,axis=0)
    dc_s=np.sqrt(((near[:,1:]-dom[1:])**2).sum(-1)); 
    thrL=max(7,2.6*np.std(near[:,0])+3); thrC=max(7,2.2*np.percentile(dc_s,90))
    dC=np.sqrt(((lab[...,1:]-dom[1:])**2).sum(-1)); dL=np.abs(lab[...,0]-dom[0])
    m=(dC<thrC)&(dL<thrL)
    m=ndi.binary_opening(m,structure=np.ones((2,2)))
    # díry (drážky, kresba dřeva) uvnitř povrchu uzavřít, ale velké tmavé prvky (sklo, madlo) nechat
    lbl,n=ndi.label(~m); sizes=ndi.sum(np.ones_like(lbl),lbl,range(1,n+1))
    small=np.isin(lbl,np.where(sizes<120)[0]+1); m=m|small
    # odstraň malé ostrůvky povrchu
    lbl,n=ndi.label(m); sizes=ndi.sum(np.ones_like(lbl),lbl,range(1,n+1))
    m=np.isin(lbl,np.where(sizes>=150)[0]+1)
    # okraj: přibrat 2 px přechodu, pokud barevně patří spíš k povrchu (odstraní lem původního dekoru)
    for _ in range(2):
        ring=ndi.binary_dilation(m)&~m
        m=m|(ring&(dC<thrC*2.2)&(dL<thrL*2.2))
    L=lab[...,0]
    low=ndi.gaussian_filter(np.where(m,L,dom[0]),12)
    det=L-ndi.median_filter(L,7)
    det=np.where(np.abs(det)>5,det,0)  # jen výrazné drážky / lisování
    shade=(low+det)/max(dom[0],8)
    shade=np.clip(shade,0.45,1.35)
    alpha=ndi.gaussian_filter(m.astype(float),0.7)
    g=np.clip(shade/1.5*255,0,255).astype(np.uint8)
    a=np.clip(alpha*255,0,255).astype(np.uint8)
    Image.fromarray(np.dstack([g,a]),'LA').save(f'{OUT}/{name}.png',optimize=True)
    return m.mean(), dom
if __name__=='__main__':
    names=sys.argv[1:] or [f[:-4] for f in sorted(os.listdir(SRC))]
    for n in names:
        cov,dom=process(n); print(n, round(cov,2), np.round(dom,0))
