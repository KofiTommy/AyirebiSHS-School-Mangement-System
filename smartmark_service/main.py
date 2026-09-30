"""Private OMR service for SmartMark's standard answer sheet."""
import os
import json
import cv2
import numpy as np
from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile

app = FastAPI(title="SmartMark Scanner", version="0.1.0")
TOKEN = os.getenv("SMARTMARK_API_TOKEN", "")

def _order(points):
    points = points.reshape(4, 2).astype("float32")
    sums, diffs = points.sum(axis=1), np.diff(points, axis=1).reshape(-1)
    return np.array([points[np.argmin(sums)], points[np.argmin(diffs)], points[np.argmax(sums)], points[np.argmax(diffs)]], dtype="float32")

def _straighten(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    edges = cv2.Canny(cv2.GaussianBlur(gray, (5, 5), 0), 60, 180)
    contours, _ = cv2.findContours(edges, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    h, w = gray.shape
    for contour in sorted(contours, key=cv2.contourArea, reverse=True)[:12]:
        if cv2.contourArea(contour) < h * w * .18: continue
        quad = cv2.approxPolyDP(contour, .02 * cv2.arcLength(contour, True), True)
        if len(quad) != 4: continue
        p = _order(quad); width = int(max(np.linalg.norm(p[1]-p[0]), np.linalg.norm(p[2]-p[3]))); height = int(max(np.linalg.norm(p[3]-p[0]), np.linalg.norm(p[2]-p[1])))
        if width >= 200 and height >= 200:
            target = np.array([[0,0],[width-1,0],[width-1,height-1],[0,height-1]], dtype="float32")
            return cv2.warpPerspective(image, cv2.getPerspectiveTransform(p, target), (width, height))
    return image

def detect(image, questions, options, template=None):
    image = _straighten(image); h, w = image.shape[:2]
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY); binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
    template = template if isinstance(template, dict) else {}
    top, bottom = int(h*float(template.get('top_ratio', .18))), int(h*float(template.get('bottom_ratio', .93)))
    left, right = int(w*float(template.get('left_ratio', .10))), int(w*float(template.get('right_ratio', .90))); letters = "ABCDE"; answers = {}
    for number in range(questions):
        y0, y1 = top+(bottom-top)*number//questions, top+(bottom-top)*(number+1)//questions; values=[]
        for option in range(options):
            x0, x1 = left+(right-left)*option//options, left+(right-left)*(option+1)//options; px, py = max(2,int((x1-x0)*.28)), max(1,int((y1-y0)*.24)); cell = binary[y0+py:y1-py,x0+px:x1-px]
            values.append(float(np.mean(cell)/255) if cell.size else 0)
        ranked = sorted(range(options), key=lambda i: values[i], reverse=True); first, second = ranked[0], ranked[1]; selected=[]
        if values[first] >= .13:
            selected.append(letters[first])
            if values[second] >= .13 and values[second] >= values[first]*.65: selected.append(letters[second])
        gap=max(0,values[first]-values[second]); confidence=int(max(0,min(100,(gap*450)+(values[first]*90)))) if selected else int(max(0,min(100,100-values[first]*300)))
        answers[str(number+1)] = {'selected':selected,'confidence':confidence,'review':len(selected)!=1 or confidence<80,'fill_scores':[round(v,3) for v in values]}
    return {'answers':answers,'image':{'width':w,'height':h},'template':{'top_ratio':.18,'bottom_ratio':.93,'left_ratio':.10,'right_ratio':.90,'question_count':questions,'option_count':options}}

@app.get('/health')
def health(): return {'status':'ok'}

@app.post('/detect')
async def scan(image: UploadFile = File(...), question_count: int = Form(...), option_count: int = Form(...), template: str = Form(""), x_smartmark_token: str | None = Header(default=None)):
    if TOKEN and x_smartmark_token != TOKEN: raise HTTPException(401,'Invalid scanner token')
    if not 1 <= question_count <= 100 or not 2 <= option_count <= 5: raise HTTPException(422,'Invalid assessment dimensions')
    content=await image.read()
    if len(content)>8*1024*1024: raise HTTPException(413,'Image exceeds 8 MB')
    decoded=cv2.imdecode(np.frombuffer(content,dtype=np.uint8),cv2.IMREAD_COLOR)
    if decoded is None: raise HTTPException(422,'Unreadable image')
    try: learned_template = json.loads(template) if template else None
    except json.JSONDecodeError: learned_template = None
    return detect(decoded,question_count,option_count,learned_template)

@app.post('/learn-template')
async def learn_template(image: UploadFile = File(...), question_count: int = Form(...), option_count: int = Form(...), x_smartmark_token: str | None = Header(default=None)):
    """Read a teacher's shaded master sheet and return a reusable, confirmed-later template."""
    return await scan(image, question_count, option_count, "", x_smartmark_token)
