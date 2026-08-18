<?php

namespace Modules\Pos\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Pos\Jobs\GenerateDesignImageJob;

class DesignAiChatApiController extends Controller
{
    private const BASE_SYSTEM_PROMPT = <<<'PROMPT'
You are an expert AI design assistant inside Zeebroo Design Studio (Fabric.js canvas).
A canvas dimension header is prepended — use those exact pixel values for ALL coordinates and sizes.

CRITICAL RULES — VIOLATION BREAKS THE APP:
1. Your ENTIRE response must be a single raw JSON object. Start with { and end with }.
2. NO markdown. NO ```json fences. NO prose before or after the JSON. NO explanations outside JSON.
3. Put your reply text INSIDE the JSON: {"reply":"your message here","commands":[...]}
4. Do NOT wrap the JSON in code blocks. The very first character of your response must be {.

━━━ AVAILABLE COMMANDS ━━━

{"type":"set_background","fill":"#eef0f5"}

{"type":"add_rect","fill":"#0f1f4a","left":0,"top":0,"width":794,"height":160,"rx":0,"opacity":1}
  optional → "stroke":"#c9a843","strokeWidth":2,"shadow":{"color":"rgba(0,0,0,0.28)","blur":20,"offsetX":0,"offsetY":8}

{"type":"add_circle","fill":"#c9a843","left":24,"top":24,"radius":55,"opacity":1}
  optional → "stroke","strokeWidth","shadow"

{"type":"add_triangle","fill":"rgba(201,168,67,0.18)","left":640,"top":0,"width":154,"height":160,"opacity":0.18}

{"type":"add_line","fill":"#c9a843","left":0,"top":160,"width":794,"height":5}

{"type":"add_text","text":"YOUR COMPANY","fontSize":26,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Montserrat","left":148,"top":32,"fontStyle":"normal"}
  fontFamily options → Montserrat, Raleway, Inter, Lato, Playfair Display, Oswald

{"type":"set_shadow","color":"rgba(0,0,0,0.22)","blur":14,"offsetX":0,"offsetY":5}
{"type":"set_fill","fill":"#ef4444"}
{"type":"set_text_props","fontSize":24,"fontWeight":"bold","fill":"#1e293b","fontFamily":"Inter"}
{"type":"bring_front"} {"type":"send_back"} {"type":"delete_selected"} {"type":"zoom_fit"}

{"type":"transform_text","scope":"all","transform":"title_case"}
  Bulk-renames every text layer on the canvas. Use whenever the user asks to change
  capitalisation, rename products/labels, or reformat all text at once.
  scope     → "all" (every text object) | "selected" (active object only)
  transform → "capitalize"    first letter of entire string uppercase   "hello world" → "Hello world"
            | "title_case"   first letter of EACH word uppercase        "hello world" → "Hello World"
            | "upper_first_2" first TWO characters uppercase            "hello world" → "HEllo world"
            | "uppercase"    entire string uppercase
            | "lowercase"    entire string lowercase
            | "sentence_case" first letter up, rest lowercase
  Trigger phrases: "first letter capital", "capitalize all", "title case", "rename products",
                   "first 2 letters capital", "make uppercase", "all caps", "lowercase everything"

━━━ GEOMETRY NOTES ━━━
- add_circle: "left" and "top" are the LEFT and TOP edges of the bounding box (not the centre).
  Circle centred at (cx,cy) with radius R → left = cx-R, top = cy-R
- add_triangle: vertices = (left, top+height), (left+width/2, top), (left+width, top+height)
- For transparent watermark effects use rgba() in fill, e.g. "fill":"rgba(15,31,74,0.05)"
- opacity field applies the final rendered opacity (0.0–1.0) to any shape
- add_rect and add_circle support optional "scaleX" and "scaleY":
    Wide full-page band:   width:220, scaleX:4.23 → rendered width 930px (covers 794px canvas)
    Tall header:           height:140, scaleY:1.24 → rendered height 174px
    Elliptical arc circle: radius:80, scaleX:3.63, scaleY:1.96 → 581×314px ellipse (for decorative off-canvas arcs)
    Small logo circle:     radius:80, scaleX:0.86, scaleY:0.86 → 138×138px circle
  Use negative left/top values to place shapes partially off-canvas (deliberate bleed).

━━━ COLOUR PALETTES ━━━
Navy+Gold:       #0f1f4a · #1a2b5e · #c9a843 · #eef0f5 · #374151 · #6b7280
Corporate Blue:  #1e3a5f · #2563eb · #eff6ff · #64748b
Emerald Pro:     #064e3b · #059669 · #ecfdf5 · #374151
Burgundy:        #4c0519 · #be123c · #fff1f2 · #374151
Charcoal Minimal:#111827 · #374151 · #f9fafb · #9ca3af
Deep Purple:     #1e1b4b · #7c3aed · #f5f3ff · #4b5563
Warm Earth:      #7c2d12 · #ea580c · #fff7ed · #374151

━━━ DESIGN PRINCIPLES ━━━
Build in layers (bottom to top):
  1. Background fill
  2. Large decorative circles/triangles (watermarks, rgba fill, opacity 0.04–0.15)
  3. Structural blocks (header rect, footer rect) with shadow
  4. Accent lines and rails (thin rects/lines)
  5. Logo elements (circle + letter)
  6. All text content
  7. Fine detail accents last

Typography scale:
  Display   → 42–72px Montserrat bold (hero headlines)
  Heading   → 22–28px Montserrat/Raleway bold
  Subhead   → 13–16px Raleway/Inter
  Body      → 11–13px Inter/Lato
  Caption   → 9–10px Inter

NEVER output W, H, round(), or any symbolic expression — always integer pixel values.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ADVANCED DESIGN RECIPES  (emit ALL commands; scale to actual canvas)
For left/width: multiply recipe value by (canvas_width/794)
For top/height/radius: multiply recipe value by (canvas_height/1123)
Round all results to nearest integer.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

══ PROFESSIONAL LETTERHEAD  (794×1123) ══
Triggers: letterhead, company letterhead, create letterhead, letter template

⚠ RANDOMIZE: Every letterhead request MUST use a different style. Never repeat the same color or arc position twice in a row. Pick a style the user has not seen yet.

FIXED STRUCTURE (same for every style — never change these):
  Background:   set_background #eef0f5
  Header rect:  left:-68, top:-59, width:220, height:140, scaleX:4.23, scaleY:1.24
  Logo circle:  fill:#d6d6d6, left:27, top:10, radius:80, scaleX:0.86, scaleY:0.86
  LOGO text:    fontSize:28, fill:#111827, fontFamily:Inter, left:57, top:65
  Company name: fontSize:36, fontWeight:bold, fill:#ffffff, fontFamily:Inter, left:370, top:15
  Slogan:       fontSize:18, fill:#ffffff, fontFamily:Inter, left:577, top:59
  Divider:      left:-29, top:170, width:220, height:140, scaleX:3.97, scaleY:0.27
  Address:      fontSize:12, fontFamily:Inter, left:16, top:183
  Phone:        fontSize:12, fontFamily:Inter, left:268, top:184
  Website:      fontSize:12, fontFamily:Inter, left:612, top:183

STYLE CATALOGUE — pick ONE per request, vary every time:

[1] CORPORATE BLUE · left arc
  header:#166ec0  arc:fill:#083377,left:-275,top:-119,r:80,sX:3.63,sY:1.96  divider:#062993  contacts:white

[2] WARM GOLD · left arc · light divider
  header:#c08216  arc:fill:#3b82f6,left:-275,top:-119,r:80,sX:3.63,sY:1.96  divider:#cfcfcf  contacts:#111827

[3] CORPORATE BLUE · right arc
  header:#166ec0  arc:fill:#083377,left:297,top:-121,r:80,sX:3.63,sY:1.71  divider:#062993  contacts:white

[4] CORPORATE BLUE · dual circles (add 2 circles BEFORE header in commands)
  circle1:fill:#e4e2e2,left:-295,top:-83,r:80,sX:3.16,sY:3.16
  circle2:fill:#d6d4f2,left:-309,top:-35,r:80,sX:2.96,sY:2.96
  header:#166ec0  divider:#062993  contacts:white
  (no separate arc circle — the two circles ARE the decoration)

[5] NAVY + GOLD ARC · left arc
  header:#0f1f4a  arc:fill:#c9a843,left:-275,top:-119,r:80,sX:3.63,sY:1.96  divider:#1a2b5e  contacts:white

[6] EMERALD GREEN · left arc
  header:#064e3b  arc:fill:#022c22,left:-275,top:-119,r:80,sX:3.63,sY:1.96  divider:#065f46  contacts:white

[7] DEEP PURPLE · left arc
  header:#3730a3  arc:fill:#1e1b4b,left:-275,top:-119,r:80,sX:3.63,sY:1.96  divider:#312e81  contacts:white

[8] BURGUNDY RED · left arc
  header:#9b1c1c  arc:fill:#450a0a,left:-275,top:-119,r:80,sX:3.63,sY:1.96  divider:#7f1d1d  contacts:white

COMMAND ORDER:
  1.set_background  2.header rect  3.circle(s) for style[4] OR nothing  4.arc circle (skip for style[4])  5.logo circle  6.LOGO text  7.company name  8.slogan  9.divider  10.address  11.phone  12.website

EXAMPLE — style [5] Navy+Gold fully expanded:
{"type":"set_background","fill":"#eef0f5"},
{"type":"add_rect","fill":"#0f1f4a","left":-68,"top":-59,"width":220,"height":140,"scaleX":4.23,"scaleY":1.24},
{"type":"add_circle","fill":"#c9a843","left":-275,"top":-119,"radius":80,"scaleX":3.63,"scaleY":1.96},
{"type":"add_circle","fill":"#d6d6d6","left":27,"top":10,"radius":80,"scaleX":0.86,"scaleY":0.86},
{"type":"add_text","text":"LOGO","fontSize":28,"fontWeight":"normal","fill":"#111827","fontFamily":"Inter","left":57,"top":65},
{"type":"add_text","text":"Your Company Name","fontSize":36,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Inter","left":370,"top":15},
{"type":"add_text","text":"Your Business Slogan","fontSize":18,"fontWeight":"normal","fill":"#ffffff","fontFamily":"Inter","left":577,"top":59},
{"type":"add_rect","fill":"#1a2b5e","left":-29,"top":170,"width":220,"height":140,"scaleX":3.97,"scaleY":0.27},
{"type":"add_text","text":"[No.128, Place your address, Road, City]","fontSize":12,"fontWeight":"normal","fill":"#ffffff","fontFamily":"Inter","left":16,"top":183},
{"type":"add_text","text":"011 - XXX XXXX | 011 - XXX XXXX","fontSize":12,"fontWeight":"normal","fill":"#ffffff","fontFamily":"Inter","left":268,"top":184},
{"type":"add_text","text":"www.yourwebsite.com","fontSize":12,"fontWeight":"normal","fill":"#ffffff","fontFamily":"Inter","left":612,"top":183}

══ BUSINESS CARD  (20 commands, 794×1123) ══
Triggers: business card, visiting card, name card, contact card

{"type":"set_background","fill":"#0f1f4a"},
{"type":"add_circle","fill":"rgba(201,168,67,0.07)","left":360,"top":-200,"radius":460},
{"type":"add_circle","fill":"rgba(201,168,67,0.05)","left":520,"top":300,"radius":300},
{"type":"add_rect","fill":"#c9a843","left":0,"top":0,"width":7,"height":1123},
{"type":"add_rect","fill":"#c9a843","left":0,"top":1106,"width":794,"height":17},
{"type":"add_rect","fill":"rgba(255,255,255,0.03)","left":7,"top":0,"width":787,"height":580},
{"type":"add_text","text":"YOUR COMPANY","fontSize":12,"fill":"#c9a843","fontFamily":"Raleway","left":46,"top":268},
{"type":"add_text","text":"JOHN DOE","fontSize":54,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Montserrat","left":42,"top":300},
{"type":"add_text","text":"Chief Executive Officer","fontSize":17,"fill":"#c9a843","fontFamily":"Raleway","left":46,"top":388},
{"type":"add_rect","fill":"#c9a843","left":44,"top":428,"width":56,"height":4},
{"type":"add_text","text":"+1 234 567 890","fontSize":15,"fill":"#e2e8f0","fontFamily":"Inter","left":44,"top":456},
{"type":"add_text","text":"john.doe@yourcompany.com","fontSize":15,"fill":"#e2e8f0","fontFamily":"Inter","left":44,"top":490},
{"type":"add_text","text":"www.yourcompany.com","fontSize":15,"fill":"#e2e8f0","fontFamily":"Inter","left":44,"top":524},
{"type":"add_text","text":"123 Business Ave, Suite 100, City, State 12345","fontSize":12,"fill":"#9ca3af","fontFamily":"Inter","left":44,"top":562},
{"type":"add_triangle","fill":"rgba(201,168,67,0.10)","left":596,"top":740,"width":198,"height":383},
{"type":"add_circle","fill":"rgba(201,168,67,0.14)","left":560,"top":458,"radius":138},
{"type":"add_circle","fill":"#c9a843","left":600,"top":498,"radius":98},
{"type":"add_text","text":"Z","fontSize":76,"fontWeight":"bold","fill":"rgba(15,31,74,0.85)","fontFamily":"Montserrat","left":631,"top":530},
{"type":"add_line","fill":"rgba(255,255,255,0.08)","left":44,"top":620,"width":706,"height":1},
{"type":"add_text","text":"YOUR COMPANY · BUILDING TOMORROW'S SUCCESS","fontSize":9,"fill":"rgba(201,168,67,0.45)","fontFamily":"Raleway","left":44,"top":636}

══ INVOICE TEMPLATE  (24 commands, 794×1123) ══
Triggers: invoice, create invoice, billing template, payment form

{"type":"set_background","fill":"#f9fafb"},
{"type":"add_rect","fill":"#0f1f4a","left":0,"top":0,"width":794,"height":8},
{"type":"add_rect","fill":"#ffffff","left":0,"top":8,"width":794,"height":200,"shadow":{"color":"rgba(0,0,0,0.08)","blur":14,"offsetX":0,"offsetY":4}},
{"type":"add_circle","fill":"#c9a843","left":24,"top":26,"radius":48},
{"type":"add_text","text":"Z","fontSize":36,"fontWeight":"bold","fill":"#0f1f4a","fontFamily":"Montserrat","left":48,"top":35},
{"type":"add_text","text":"YOUR COMPANY","fontSize":22,"fontWeight":"bold","fill":"#0f1f4a","fontFamily":"Montserrat","left":132,"top":38},
{"type":"add_text","text":"www.yourcompany.com  ·  info@yourcompany.com","fontSize":10,"fill":"#6b7280","fontFamily":"Inter","left":134,"top":72},
{"type":"add_text","text":"INVOICE","fontSize":46,"fontWeight":"bold","fill":"#0f1f4a","fontFamily":"Montserrat","left":532,"top":24},
{"type":"add_rect","fill":"#c9a843","left":532,"top":84,"width":224,"height":4},
{"type":"add_text","text":"Invoice No: INV-0001","fontSize":11,"fill":"#374151","fontFamily":"Inter","left":532,"top":100},
{"type":"add_text","text":"Issue Date: DD / MM / YYYY","fontSize":11,"fill":"#374151","fontFamily":"Inter","left":532,"top":120},
{"type":"add_text","text":"Due Date:","fontSize":11,"fontWeight":"bold","fill":"#be123c","fontFamily":"Inter","left":532,"top":140},
{"type":"add_rect","fill":"#f1f5f9","left":0,"top":208,"width":794,"height":52},
{"type":"add_text","text":"BILL TO","fontSize":10,"fontWeight":"bold","fill":"#6b7280","fontFamily":"Inter","left":28,"top":222},
{"type":"add_text","text":"[Client Name]","fontSize":15,"fontWeight":"bold","fill":"#0f1f4a","fontFamily":"Montserrat","left":28,"top":240},
{"type":"add_rect","fill":"#0f1f4a","left":0,"top":282,"width":794,"height":40},
{"type":"add_text","text":"Description","fontSize":12,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Inter","left":20,"top":296},
{"type":"add_text","text":"Qty","fontSize":12,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Inter","left":494,"top":296},
{"type":"add_text","text":"Rate","fontSize":12,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Inter","left":572,"top":296},
{"type":"add_text","text":"Amount","fontSize":12,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Inter","left":680,"top":296},
{"type":"add_line","fill":"#e5e7eb","left":0,"top":362,"width":794,"height":1},
{"type":"add_line","fill":"#e5e7eb","left":0,"top":404,"width":794,"height":1},
{"type":"add_rect","fill":"#0f1f4a","left":464,"top":982,"width":330,"height":90},
{"type":"add_text","text":"TOTAL DUE","fontSize":13,"fontWeight":"bold","fill":"#c9a843","fontFamily":"Montserrat","left":480,"top":996},
{"type":"add_text","text":"$ 0,000.00","fontSize":30,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Montserrat","left":480,"top":1028}

══ EVENT POSTER  (22 commands, 794×1123) ══
Triggers: poster, event poster, flyer, announcement, concert poster, event flyer

{"type":"set_background","fill":"#0c0a1e"},
{"type":"add_circle","fill":"rgba(124,58,237,0.22)","left":-120,"top":100,"radius":380},
{"type":"add_circle","fill":"rgba(236,72,153,0.16)","left":540,"top":560,"radius":320},
{"type":"add_circle","fill":"rgba(201,168,67,0.09)","left":280,"top":440,"radius":220},
{"type":"add_rect","fill":"rgba(255,255,255,0.02)","left":0,"top":0,"width":794,"height":1123},
{"type":"add_text","text":"YOUR COMPANY PRESENTS","fontSize":11,"fill":"rgba(201,168,67,0.75)","fontFamily":"Raleway","left":270,"top":128},
{"type":"add_text","text":"SATURDAY · 15 AUGUST 2026","fontSize":14,"fill":"#c9a843","fontFamily":"Montserrat","left":212,"top":152},
{"type":"add_line","fill":"rgba(201,168,67,0.50)","left":160,"top":182,"width":474,"height":2},
{"type":"add_text","text":"GRAND","fontSize":86,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Montserrat","left":72,"top":204},
{"type":"add_text","text":"GALA","fontSize":112,"fontWeight":"bold","fill":"#c9a843","fontFamily":"Montserrat","left":124,"top":298},
{"type":"add_text","text":"EVENING","fontSize":54,"fontWeight":"bold","fill":"rgba(255,255,255,0.88)","fontFamily":"Montserrat","left":142,"top":424},
{"type":"add_line","fill":"rgba(201,168,67,0.35)","left":72,"top":506,"width":650,"height":1},
{"type":"add_text","text":"AN EXCLUSIVE EVENING OF FINE DINING, LIVE MUSIC & NETWORKING","fontSize":11,"fill":"rgba(255,255,255,0.65)","fontFamily":"Raleway","left":56,"top":524},
{"type":"add_rect","fill":"#c9a843","left":196,"top":590,"width":402,"height":58,"rx":4},
{"type":"add_text","text":"RESERVE YOUR SEAT","fontSize":19,"fontWeight":"bold","fill":"#0c0a1e","fontFamily":"Montserrat","left":236,"top":609},
{"type":"add_text","text":"The Grand Ballroom · City Convention Centre","fontSize":13,"fill":"rgba(255,255,255,0.62)","fontFamily":"Inter","left":140,"top":682},
{"type":"add_text","text":"Dress Code: Black Tie   ·   Doors Open 7:00 PM","fontSize":12,"fill":"#c9a843","fontFamily":"Raleway","left":192,"top":708},
{"type":"add_line","fill":"rgba(255,255,255,0.10)","left":60,"top":754,"width":674,"height":1},
{"type":"add_text","text":"TICKETS & ENQUIRIES","fontSize":10,"fontWeight":"bold","fill":"rgba(201,168,67,0.80)","fontFamily":"Inter","left":306,"top":774},
{"type":"add_text","text":"info@yourcompany.com   ·   +1 234 567 890","fontSize":12,"fill":"rgba(255,255,255,0.55)","fontFamily":"Inter","left":196,"top":796},
{"type":"add_triangle","fill":"rgba(124,58,237,0.12)","left":0,"top":860,"width":200,"height":263},
{"type":"add_triangle","fill":"rgba(236,72,153,0.10)","left":620,"top":900,"width":174,"height":223}

══ COMPANY PROFILE COVER  (22 commands, 794×1123) ══
Triggers: company profile, profile cover, report cover, brochure cover, annual report

{"type":"set_background","fill":"#0f1f4a"},
{"type":"add_rect","fill":"#162d6e","left":0,"top":562,"width":794,"height":561},
{"type":"add_circle","fill":"rgba(201,168,67,0.06)","left":-140,"top":720,"radius":440},
{"type":"add_circle","fill":"rgba(201,168,67,0.04)","left":610,"top":220,"radius":360},
{"type":"add_rect","fill":"#c9a843","left":0,"top":0,"width":7,"height":562},
{"type":"add_rect","fill":"#c9a843","left":0,"top":558,"width":794,"height":7},
{"type":"add_triangle","fill":"rgba(201,168,67,0.10)","left":558,"top":640,"width":236,"height":483},
{"type":"add_text","text":"YOUR COMPANY","fontSize":13,"fill":"#c9a843","fontFamily":"Raleway","left":32,"top":46},
{"type":"add_text","text":"EST. 2010","fontSize":10,"fill":"rgba(255,255,255,0.35)","fontFamily":"Inter","left":32,"top":72},
{"type":"add_circle","fill":"rgba(201,168,67,0.12)","left":268,"top":118,"radius":190},
{"type":"add_circle","fill":"#c9a843","left":308,"top":158,"radius":150},
{"type":"add_text","text":"Z","fontSize":118,"fontWeight":"bold","fill":"#0f1f4a","fontFamily":"Montserrat","left":341,"top":192},
{"type":"add_text","text":"COMPANY","fontSize":44,"fontWeight":"bold","fill":"#ffffff","fontFamily":"Montserrat","left":82,"top":614},
{"type":"add_text","text":"PROFILE","fontSize":76,"fontWeight":"bold","fill":"#c9a843","fontFamily":"Montserrat","left":50,"top":668},
{"type":"add_text","text":"2026","fontSize":18,"fill":"rgba(255,255,255,0.38)","fontFamily":"Montserrat","left":54,"top":760},
{"type":"add_line","fill":"rgba(201,168,67,0.55)","left":54,"top":798,"width":330,"height":2},
{"type":"add_text","text":"Excellence  ·  Innovation  ·  Growth","fontSize":14,"fill":"rgba(255,255,255,0.58)","fontFamily":"Raleway","left":54,"top":820},
{"type":"add_text","text":"Building Tomorrow's Success Today","fontSize":13,"fill":"rgba(255,255,255,0.42)","fontFamily":"Inter","left":54,"top":850},
{"type":"add_line","fill":"rgba(255,255,255,0.08)","left":0,"top":1042,"width":794,"height":1},
{"type":"add_text","text":"www.yourcompany.com","fontSize":11,"fill":"rgba(201,168,67,0.65)","fontFamily":"Inter","left":290,"top":1060},
{"type":"add_text","text":"CONFIDENTIAL","fontSize":10,"fill":"rgba(255,255,255,0.18)","fontFamily":"Inter","left":328,"top":1086},
{"type":"add_circle","fill":"rgba(201,168,67,0.06)","left":524,"top":-80,"radius":260}

━━━ FOR SIMPLE / SHORT REQUESTS ━━━
Use 1–5 commands. Pick a palette that matches the mood. Reply text: 1–2 friendly sentences.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IMAGE-BACKED DESIGNS  (real photos, product images, scenic backgrounds)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

When the request contains any of these:
  • Real-world locations / scenes → beach, ocean, mountain, city, forest, sunset, sky, restaurant interior, office, market, park, night scene
  • Product / object photos      → drink, food, coffee, product, shoe, bag, phone, car, furniture, merchandise, bottle
  • Explicit photo request        → "show image of", "add photo of", "with a picture of", "real photo"

…include an "image_prompts" array in your JSON response AND keep ALL shape/text commands in "commands".

image_prompts item schema:
{
  "id":         "bg" | "obj1" | "obj2" …  (unique per image)
  "prompt":     English phrase for Gemini image generation (craft carefully)
  "role":       "background" | "object"
  "aspect":     "landscape" | "portrait" | "square"
  // For "object" role ONLY:
  "left_pct":   0.0–1.0   ← fraction of canvas width for left edge
  "top_pct":    0.0–1.0   ← fraction of canvas height for top edge
  "height_pct": 0.0–1.0   ← image height as fraction of canvas height
}

CRITICAL RULES:
1. "commands" → shapes and text ONLY — never add any image commands there
2. Background image fills the whole canvas; it is placed behind everything
3. Position text on the OPPOSITE side from the object image (object right → text left)
4. Text fills must contrast with the image (white + shadow on photos; #1e293b on light objects)
5. For OBJECT images: always end the prompt with ", isolated object, solid bright lime-green background (#00FF00), no shadows on the background, clean studio lighting, product photography"
   The client automatically removes the green background — do NOT write "transparent background" or "PNG format".
6. ALWAYS append to background prompts: ", photorealistic, no text overlays, vibrant, professional marketing photo"
7. Object sizing: height_pct controls the image height; the image width is proportional.
   Keep height_pct ≤ 0.75 and ensure left_pct + estimated_width_pct ≤ 0.98 (stay within canvas).
   For a square object image at height_pct=0.65: estimated_width_pct ≈ 0.65*(canvas_height/canvas_width).
   When in doubt, use height_pct=0.60 and left_pct=0.52 for right-side objects.
8. Choose "aspect" for background to match canvas shape:
   • canvas_width > canvas_height × 1.2  → "landscape"
   • canvas_height > canvas_width × 1.2  → "portrait"
   • Otherwise                            → "square"
9. Text coordinates are in canvas pixels (canvas_width × canvas_height space).
   Keep all text left + estimated_text_width < canvas_width − 40.

EXAMPLE — "beach background, cool drink, MARINO 20% OFF This weekend" on a 1080×1080 canvas:
{
  "reply": "Generating your beach promo — I'll fetch the images now!",
  "commands": [
    {"type":"add_text","text":"MARINO","fontSize":90,"fontWeight":"900","fill":"#FFFFFF","fontFamily":"Montserrat","left":50,"top":200,"shadow":{"color":"rgba(0,0,0,0.55)","blur":22,"offsetX":0,"offsetY":5}},
    {"type":"add_text","text":"20% OFF","fontSize":60,"fontWeight":"800","fill":"#FF9900","fontFamily":"Montserrat","left":50,"top":318,"shadow":{"color":"rgba(0,0,0,0.45)","blur":18,"offsetX":0,"offsetY":4}},
    {"type":"add_text","text":"This Weekend","fontSize":32,"fontWeight":"400","fill":"#FFFFFF","fontFamily":"Inter","left":50,"top":405,"shadow":{"color":"rgba(0,0,0,0.35)","blur":12,"offsetX":0,"offsetY":3}}
  ],
  "image_prompts": [
    {
      "id": "bg",
      "prompt": "tropical beach with turquoise waves and golden sand, sunny day, aerial view, photorealistic, no text overlays, vibrant, professional marketing photo",
      "role": "background",
      "aspect": "square"
    },
    {
      "id": "obj1",
      "prompt": "cold refreshing cocktail drink with ice and lime garnish, isolated object, solid bright lime-green background (#00FF00), no shadows on the background, clean studio lighting, product photography",
      "role": "object",
      "left_pct": 0.52,
      "top_pct": 0.08,
      "height_pct": 0.60
    }
  ]
}
PROMPT;

    private const MODELS = [
        'gemini-2.5-flash',
        'gemini-2.5-pro',
        'gemini-2.5-flash-lite-preview-06-17',
    ];

    /** Models that support native image generation via responseModalities */
    private const IMAGE_MODELS = [
        'gemini-2.0-flash-preview-image-generation',
        'gemini-2.0-flash-exp',
    ];

    // 8 colour palettes: header · arc · watermark-light-1 · watermark-light-2 · divider · contact-text
    private const COLOR_PALETTES = [
        'blue'   => ['header' => '#166ec0', 'arc' => '#083377', 'l1' => '#c3d9f5', 'l2' => '#d4e4f7', 'divider' => '#062993', 'contact' => '#ffffff'],
        'gold'   => ['header' => '#c08216', 'arc' => '#3b82f6', 'l1' => '#fde68a', 'l2' => '#fef3c7', 'divider' => '#cfcfcf', 'contact' => '#111827'],
        'green'  => ['header' => '#064e3b', 'arc' => '#022c22', 'l1' => '#bbf7d0', 'l2' => '#d1fae5', 'divider' => '#065f46', 'contact' => '#ffffff'],
        'navy'   => ['header' => '#0f1f4a', 'arc' => '#c9a843', 'l1' => '#dbeafe', 'l2' => '#eff6ff', 'divider' => '#1a2b5e', 'contact' => '#ffffff'],
        'purple' => ['header' => '#3730a3', 'arc' => '#1e1b4b', 'l1' => '#ddd6fe', 'l2' => '#ede9fe', 'divider' => '#312e81', 'contact' => '#ffffff'],
        'red'    => ['header' => '#9b1c1c', 'arc' => '#450a0a', 'l1' => '#fecaca', 'l2' => '#fee2e2', 'divider' => '#7f1d1d', 'contact' => '#ffffff'],
        'teal'   => ['header' => '#0f766e', 'arc' => '#063a36', 'l1' => '#ccfbf1', 'l2' => '#f0fdfa', 'divider' => '#0d9488', 'contact' => '#ffffff'],
        'amber'  => ['header' => '#b45309', 'arc' => '#78350f', 'l1' => '#fde68a', 'l2' => '#fef3c7', 'divider' => '#92400e', 'contact' => '#ffffff'],
    ];

    // Known fill colours in the sample JSONs mapped to their semantic role
    private const FILL_ROLES = [
        '#166ec0' => 'header',
        '#c08216' => 'header',
        '#083377' => 'arc',
        '#3b82f6' => 'arc',
        '#e4e2e2' => 'l1',
        '#d6d4f2' => 'l2',
        '#062993' => 'divider',
        '#cfcfcf' => 'divider',
    ];

    /**
     * Replace literal control characters inside JSON string values so json_decode doesn't choke.
     * Gemini sometimes outputs real newlines/tabs inside string fields instead of \n/\t escapes.
     */
    private function sanitizeJsonStrings(string $text): string
    {
        $result   = '';
        $inString = false;
        $escape   = false;
        $len      = strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];

            if ($escape) {
                $result .= $c;
                $escape  = false;
                continue;
            }

            if ($c === '\\' && $inString) {
                $result .= $c;
                $escape  = true;
                continue;
            }

            if ($c === '"') {
                $result    .= $c;
                $inString   = !$inString;
                continue;
            }

            if ($inString) {
                if ($c === "\n") { $result .= '\\n'; continue; }
                if ($c === "\r") { $result .= '\\r'; continue; }
                if ($c === "\t") { $result .= '\\t'; continue; }
                if (ord($c) < 0x20) { continue; } // drop other control chars
            }

            $result .= $c;
        }

        return $result;
    }

    // ── Facebook post: sample layout + prompt-aware text via Gemini ──────────

    private function loadRandomFbPostData(): ?array
    {
        $dir   = public_path('model_data/fb_post');
        $files = glob($dir . '/fbpost*.json');
        if (empty($files)) return null;

        shuffle($files);
        foreach ($files as $file) {
            $raw  = @file_get_contents($file);
            $data = $raw ? json_decode($raw, true) : null;
            if ($data && isset($data['canvas']['objects'])) {
                return $data;
            }
        }
        return null;
    }

    private function scaleFbPostObjects(array $data, int $targetW = 1080): array
    {
        $sourceW = max(1, (float)($data['canvas']['width'] ?? 667));
        $s       = $targetW / $sourceW;

        foreach ($data['canvas']['objects'] as &$obj) {
            $obj['left']   = round(($obj['left']   ?? 0) * $s, 2);
            $obj['top']    = round(($obj['top']    ?? 0) * $s, 2);
            $obj['scaleX'] = round(($obj['scaleX'] ?? 1) * $s, 2);
            $obj['scaleY'] = round(($obj['scaleY'] ?? 1) * $s, 2);
        }
        unset($obj);
        return $data;
    }

    private function buildFbPostPrompt(array $data, string $userMessage): string
    {
        $bg = $data['canvas']['background'] ?? '#f0f4ff';
        if (is_array($bg) && isset($bg['colorStops'][0]['color'])) {
            $bgColor = $bg['colorStops'][0]['color'];
        } else {
            $bgColor = is_string($bg) ? $bg : '#f0f4ff';
        }

        $shapeLines = [];
        $textSlots  = [];

        foreach ($data['canvas']['objects'] as $obj) {
            $type = $obj['type']   ?? '';
            $sx   = (float)($obj['scaleX'] ?? 1);
            $sy   = (float)($obj['scaleY'] ?? 1);
            $left = (float)($obj['left']   ?? 0);
            $top  = (float)($obj['top']    ?? 0);
            $op   = (float)($obj['opacity'] ?? 1);
            $fill = $obj['fill']   ?? '#cccccc';

            if ($type === 'rect') {
                $cmd = [
                    'type' => 'add_rect', 'left' => $left, 'top' => $top,
                    'width' => (float)($obj['width'] ?? 100), 'height' => (float)($obj['height'] ?? 100),
                    'fill' => $fill, 'scaleX' => $sx, 'scaleY' => $sy,
                    'rx' => (float)($obj['rx'] ?? 0), 'opacity' => $op,
                ];
                $shapeLines[] = json_encode($cmd);

            } elseif ($type === 'circle') {
                $cmd = [
                    'type' => 'add_circle', 'left' => $left, 'top' => $top,
                    'radius' => (float)($obj['radius'] ?? 80),
                    'fill' => $fill, 'scaleX' => $sx, 'scaleY' => $sy, 'opacity' => $op,
                ];
                $shapeLines[] = json_encode($cmd);

            } elseif ($type === 'polygon') {
                // Approximate as a circle for draw-command compatibility
                $cmd = [
                    'type' => 'add_circle', 'left' => $left, 'top' => $top,
                    'radius' => 76, 'fill' => $fill, 'scaleX' => $sx, 'scaleY' => $sy, 'opacity' => $op,
                ];
                $shapeLines[] = json_encode($cmd);

            } elseif ($type === 'image') {
                // Convert to a styled placeholder rect
                $w = round(($obj['width'] ?? 300) * $sx);
                $h = round(($obj['height'] ?? 225) * $sy);
                $sw = (float)($obj['strokeWidth'] ?? 3);
                $cmd = [
                    'type' => 'add_rect', 'left' => $left, 'top' => $top,
                    'width' => $w, 'height' => $h, 'fill' => '#cccccc',
                    'stroke' => '#ffffff', 'strokeWidth' => $sw, 'rx' => 4, 'opacity' => 0.7,
                ];
                $shapeLines[] = json_encode($cmd);

            } elseif (in_array($type, ['i-text', 'text', 'textbox'], true)) {
                $fs = max(8, (int)round(($obj['fontSize'] ?? 16) * $sx));
                $textSlots[] = [
                    'left'        => $left,
                    'top'         => $top,
                    'fontSize'    => $fs,
                    'fontWeight'  => $obj['fontWeight']  ?? 'normal',
                    'fontFamily'  => $obj['fontFamily']  ?? 'Inter',
                    'fill'        => $fill,
                    'opacity'     => $op,
                    'placeholder' => $obj['text'] ?? '',
                ];
            }
        }

        $fixedBlock = implode("\n", $shapeLines);

        $slotBlock = '';
        foreach ($textSlots as $i => $t) {
            if ($t['opacity'] < 0.4) {
                $role = 'watermark — 2-3 capital letters abbreviating the topic, e.g. "SALE" or "ADS"';
            } elseif ($t['fontSize'] >= 60) {
                $role = 'primary headline — bold, punchy, 2-5 words matching the request';
            } elseif ($t['fontSize'] >= 36) {
                $role = 'subheadline — offer or benefit in 4-8 words';
            } else {
                $role = 'body text — 1-3 short lines of supporting detail';
            }
            $opAttr = $t['opacity'] < 1 ? ', "opacity":' . $t['opacity'] : '';
            $slotBlock .= 'SLOT ' . ($i + 1) . " — role: {$role}\n";
            $slotBlock .= '{"type":"add_text","left":' . $t['left'] . ',"top":' . $t['top']
                        . ',"fontSize":' . $t['fontSize'] . ',"fontWeight":"' . $t['fontWeight']
                        . '","fontFamily":"' . $t['fontFamily'] . '","fill":"' . $t['fill'] . '"'
                        . $opAttr . ',"text":"[WRITE THIS]"}' . "\n\n";
        }

        return <<<PROMPT
You are a Facebook post designer. Canvas: 1080×1080 px.

Your ENTIRE response must be a single raw JSON object — no markdown, no fences, no prose outside the JSON:
{"reply":"one friendly sentence","commands":[...]}

════ STEP 1 — Copy these shape commands into commands[] VERBATIM (do not change any value): ════
{"type":"set_background","fill":"{$bgColor}"}
{$fixedBlock}

════ STEP 2 — For each text slot below, append an add_text command with the same left/top/fontSize/fontWeight/fontFamily/fill, but write TEXT that matches the user's request: ════

{$slotBlock}
User's request: {$userMessage}

Output only the JSON object. First character must be {.
PROMPT;
    }

    // ── Letterhead: random layout × random colour palette ────────────────────

    private function applyPaletteToFill(string $fill, float $objTop, string $objType, array $palette): string
    {
        $fl = strtolower(trim($fill));

        // Replace known structural colours with the palette equivalent
        if (isset(self::FILL_ROLES[$fl])) {
            return $palette[self::FILL_ROLES[$fl]] ?? $fill;
        }

        // Text inside the contact-bar area (below header, y > 110): apply contact text colour
        if (in_array($objType, ['i-text', 'text', 'textbox'], true) && $objTop > 110) {
            if (in_array($fl, ['#ffffff', '#111827'], true)) {
                return $palette['contact'];
            }
        }

        return $fill;
    }

    private function fabricObjectToCommand(array $obj, array $palette): ?array
    {
        $type   = $obj['type']   ?? '';
        $scaleX = (float) ($obj['scaleX'] ?? 1);
        $scaleY = (float) ($obj['scaleY'] ?? 1);
        $top    = (float) ($obj['top']    ?? 0);
        $fill   = $this->applyPaletteToFill($obj['fill'] ?? '', $top, $type, $palette);

        if ($type === 'rect') {
            return [
                'type'    => 'add_rect',
                'left'    => (float) ($obj['left']   ?? 0),
                'top'     => $top,
                'width'   => (float) ($obj['width']  ?? 200),
                'height'  => (float) ($obj['height'] ?? 100),
                'fill'    => $fill,
                'scaleX'  => $scaleX,
                'scaleY'  => $scaleY,
                'rx'      => (float) ($obj['rx']      ?? 0),
                'opacity' => (float) ($obj['opacity'] ?? 1),
            ];
        }

        if ($type === 'circle') {
            return [
                'type'    => 'add_circle',
                'left'    => (float) ($obj['left']   ?? 0),
                'top'     => $top,
                'radius'  => (float) ($obj['radius'] ?? 60),
                'fill'    => $fill,
                'scaleX'  => $scaleX,
                'scaleY'  => $scaleY,
                'opacity' => (float) ($obj['opacity'] ?? 1),
            ];
        }

        if (in_array($type, ['i-text', 'text', 'textbox'], true)) {
            $fontSize          = (float) ($obj['fontSize'] ?? 16);
            $effectiveFontSize = max(8, (int) round($fontSize * $scaleX));

            return [
                'type'       => 'add_text',
                'text'       => $obj['text']       ?? 'Text',
                'left'       => (float) ($obj['left'] ?? 0),
                'top'        => $top,
                'fontSize'   => $effectiveFontSize,
                'fontWeight' => $obj['fontWeight'] ?? 'normal',
                'fontStyle'  => $obj['fontStyle']  ?? 'normal',
                'fill'       => $fill,
                'fontFamily' => $obj['fontFamily'] ?? 'Inter',
            ];
        }

        return null;
    }

    private function createLetterheadFromSample(): ?JsonResponse
    {
        $dir   = public_path('model_data/letterheads');
        $files = glob($dir . '/letterhead*.json');
        if (empty($files)) {
            return null;
        }

        // Random structural layout
        shuffle($files);
        $file = $files[0];

        $raw  = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : null;
        if (!$data || !isset($data['canvas']['objects'])) {
            return null;
        }

        // Random colour palette — independent of layout choice → 4 × 8 = 32 combinations
        $paletteNames = array_keys(self::COLOR_PALETTES);
        shuffle($paletteNames);
        $paletteName = $paletteNames[0];
        $palette     = self::COLOR_PALETTES[$paletteName];

        $commands   = [];
        $commands[] = ['type' => 'set_background', 'fill' => '#eef0f5'];

        foreach ($data['canvas']['objects'] as $obj) {
            $cmd = $this->fabricObjectToCommand($obj, $palette);
            if ($cmd !== null) {
                $commands[] = $cmd;
            }
        }

        $layouts = [
            'letterhead1' => 'left arc',
            'letterhead2' => 'left arc',
            'letterhead3' => 'right arc',
            'letterhead4' => 'dual circle',
        ];
        $key    = pathinfo($file, PATHINFO_FILENAME);
        $layout = $layouts[$key] ?? 'classic';
        $label  = ucfirst($paletteName) . ' · ' . $layout;

        return response()->json([
            'reply'    => "Here's a {$label} letterhead! Click any element to edit the text, colors, or layout.",
            'commands' => $commands,
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function chat(Request $request): JsonResponse
    {
        $message = trim($request->input('message', ''));
        if (empty($message) || mb_strlen($message) > 800) {
            return response()->json(['reply' => 'Please enter a message.', 'commands' => []], 200);
        }

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json([
                'reply'    => 'AI assistant is not configured. Please add a Gemini API key.',
                'commands' => [],
            ], 200);
        }

        // Letterhead requests: bypass Gemini and animate a randomly chosen sample
        if (preg_match('/\b(letterhead|letter\s*head)\b/i', $message)) {
            $result = $this->createLetterheadFromSample();
            if ($result !== null) {
                return $result;
            }
        }

        $cw = (int) ($request->input('canvas_width',  794) ?: 794);
        $ch = (int) ($request->input('canvas_height', 1123) ?: 1123);

        $canvasHeader = "CANVAS: {$cw}px wide × {$ch}px tall. Use these exact values — do not output symbolic expressions.\n\n";
        $systemPrompt = $canvasHeader . self::BASE_SYSTEM_PROMPT;

        // Facebook post: use a random sample layout with Gemini writing prompt-relevant text
        if (preg_match('/\b(fb\s*post|facebook\s*post|social\s*(media\s*)?post|ig\s*post|instagram\s*post)\b/i', $message)) {
            $fbData = $this->loadRandomFbPostData();
            if ($fbData !== null) {
                $fbData      = $this->scaleFbPostObjects($fbData, 1080);
                $systemPrompt = $this->buildFbPostPrompt($fbData, $message);
            }
        }

        $envModel = config('services.gemini.model');
        $models   = $envModel
            ? array_unique(array_merge([$envModel], self::MODELS))
            : self::MODELS;

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'          => [['role' => 'user', 'parts' => [['text' => $request->input('message')]]]],
            'generationConfig'  => ['temperature' => 0.65],
        ];

        try {
            foreach ($models as $model) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $response = Http::timeout(45)->post($url, $payload);

                // Retry once after a short pause on rate-limit
                if ($response->status() === 429) {
                    sleep(2);
                    $response = Http::timeout(45)->post($url, $payload);
                }

                if (!$response->successful()) {
                    \Log::warning('Gemini AI: non-2xx response', [
                        'model'  => $model,
                        'status' => $response->status(),
                        'body'   => substr($response->body(), 0, 300),
                    ]);
                    continue; // try next fallback model
                }

                $raw = $response->json('candidates.0.content.parts.0.text');
                if (!$raw) {
                    \Log::warning('Gemini AI: empty text in response', ['model' => $model]);
                    continue;
                }

                // Strategy 1: extract JSON from inside a code fence (works even with prose before it)
                $jsonStr = null;
                if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $raw, $m)) {
                    $jsonStr = $m[1];
                }

                // Strategy 2: strip fences anchored at start/end (simple case)
                if ($jsonStr === null) {
                    $jsonStr = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
                    $jsonStr = ltrim($jsonStr, "\xEF\xBB\xBF");
                }

                // Fix literal control characters inside JSON string values
                $jsonStr = $this->sanitizeJsonStrings($jsonStr);

                $parsed = json_decode($jsonStr, true);

                // Strategy 3: find the first { and parse from there (handles leading prose)
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $start = strpos($jsonStr, '{');
                    if ($start !== false) {
                        $parsed = json_decode(substr($jsonStr, $start), true);
                    }
                }

                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['reply'])) {
                    $safeCommands = [];
                    foreach (($parsed['commands'] ?? []) as $cmd) {
                        if (!isset($cmd['type']) || !is_string($cmd['type'])) continue;
                        foreach (['left','top','width','height','radius','fontSize'] as $f) {
                            if (isset($cmd[$f]) && !is_numeric($cmd[$f])) continue 2;
                        }
                        $safeCommands[] = $cmd;
                    }

                    $result = [
                        'reply'    => trim($parsed['reply']),
                        'commands' => $safeCommands,
                    ];

                    // Dispatch a background job for each image prompt; return job IDs for client polling
                    if (!empty($parsed['image_prompts']) && is_array($parsed['image_prompts'])) {
                        $imageJobs = [];

                        foreach ($parsed['image_prompts'] as $ip) {
                            if (!isset($ip['id'], $ip['prompt'], $ip['role'])
                                || !is_string($ip['prompt'])
                                || !in_array($ip['role'], ['background', 'object'], true)) {
                                continue;
                            }

                            $jobId = (string) Str::uuid();

                            // Seed the cache entry so the poller sees 'pending' immediately
                            Cache::put(
                                GenerateDesignImageJob::cacheKey($jobId),
                                ['status' => 'pending', 'path' => null, 'error' => null],
                                now()->addHours(2),
                            );

                            GenerateDesignImageJob::dispatch($jobId, $ip['prompt'], $ip['aspect'] ?? 'square', $apiKey);

                            $imageJobs[] = array_filter([
                                'job_id'     => $jobId,
                                'role'       => $ip['role'],
                                // Object images are generated with a lime-green background;
                                // chroma_key:true tells the Electron client to strip it.
                                // Use ?: null so background jobs omit this key after array_filter.
                                'chroma_key' => ($ip['role'] === 'object') ?: null,
                                'left_pct'   => $ip['left_pct']   ?? null,
                                'top_pct'    => $ip['top_pct']    ?? null,
                                'height_pct' => $ip['height_pct'] ?? null,
                            ], fn ($v) => $v !== null);
                        }

                        if (!empty($imageJobs)) {
                            $result['image_jobs'] = array_values($imageJobs);
                        }
                    }

                    return response()->json($result);
                }

                \Log::warning('Gemini AI: JSON parse failed', [
                    'model'      => $model,
                    'json_error' => json_last_error_msg(),
                    'raw_prefix' => substr($jsonStr, 0, 600),
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Gemini AI: exception', ['message' => $e->getMessage()]);
        }

        return response()->json([
            'reply'    => "I'm having trouble reaching the AI right now. Please try again.",
            'commands' => [],
        ], 200);
    }

    // ── Voice endpoint ────────────────────────────────────────────────────────

    /**
     * Design Studio Voice Worker endpoint.
     *
     * POST /pos-api/design-studio/voice
     *
     * Accepts a base64-encoded audio blob (WebM/Opus from MediaRecorder).
     * Sends the audio to Gemini multimodal which transcribes the voice AND
     * generates a design-command reply in one round-trip.
     *
     * Returns:
     *   { transcript, lang, reply, commands, image_jobs? }
     */
    public function voice(Request $request): JsonResponse
    {
        $request->validate([
            'audio'         => 'required|string|max:6000000',
            'mime_type'     => 'nullable|string|max:100',
            'canvas_width'  => 'nullable|integer|min:1|max:8000',
            'canvas_height' => 'nullable|integer|min:1|max:8000',
        ]);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json(['transcript' => null, 'reply' => null, 'commands' => []], 503);
        }

        $rawMime    = $request->input('mime_type', 'audio/webm');
        $geminiMime = trim(explode(';', $rawMime)[0]);
        $models     = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'];

        /* ── Phase 1: Transcribe audio (tiny prompt, multimodal) ─────────────
           Only task: listen to the audio and return { transcript, lang }.
           Keeping the prompt minimal avoids the model getting distracted by
           design-command rules and produces much more reliable transcription.
        ──────────────────────────────────────────────────────────────────── */
        $transcribePrompt =
            "You are a transcription service. Listen to the attached audio carefully.\n"
            . "Detect the spoken language and transcribe it exactly as heard.\n"
            . "Return ONLY valid JSON — no markdown, no code fences, no extra text:\n"
            . '{"transcript":"exact words spoken in original language","lang":"BCP-47 code e.g. en-US or si-LK or ta-LK"}';

        $transcribePayload = [
            'systemInstruction' => ['parts' => [['text' => $transcribePrompt]]],
            'contents'          => [[
                'role'  => 'user',
                'parts' => [[
                    'inline_data' => [
                        'mime_type' => $geminiMime,
                        'data'      => $request->input('audio'),
                    ],
                ]],
            ]],
            'generationConfig'  => ['maxOutputTokens' => 300, 'temperature' => 0.1],
        ];

        $transcript = null;
        $lang       = 'en-US';

        foreach ($models as $model) {
            try {
                $response = Http::timeout(30)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                    $transcribePayload
                );
            } catch (\Exception $e) { continue; }

            if ($response->status() === 429) continue;
            if (!$response->successful())    continue;   // try next model, not break

            $raw = $response->json('candidates.0.content.parts.0.text');
            if (!$raw) continue;

            $jsonStr = $raw;
            if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/s', $raw, $m)) {
                $jsonStr = $m[1];
            } else {
                $jsonStr = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($raw));
                $jsonStr = ltrim($jsonStr, "\xEF\xBB\xBF");
            }

            $parsed = json_decode($jsonStr, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $start = strpos($jsonStr, '{');
                if ($start !== false) $parsed = json_decode(substr($jsonStr, $start), true);
            }

            if (json_last_error() === JSON_ERROR_NONE && !empty($parsed['transcript'])) {
                $transcript = trim($parsed['transcript']);
                $lang       = $parsed['lang'] ?? 'en-US';
                break;
            }
        }

        // Nothing transcribed — probably silence or very short clip
        if (!$transcript) {
            return response()->json(['transcript' => null, 'reply' => null, 'commands' => []], 200);
        }

        /* ── Phase 2: Run transcript through the existing chat logic ─────────
           Build a synthetic Request so we reuse chat() without duplication.
           This guarantees voice and text produce identical output formats.
        ──────────────────────────────────────────────────────────────────── */
        $syntheticRequest = new \Illuminate\Http\Request();
        $syntheticRequest->merge([
            'message'       => $transcript,
            'canvas_width'  => $request->input('canvas_width',  794),
            'canvas_height' => $request->input('canvas_height', 1123),
        ]);

        /** @var \Illuminate\Http\JsonResponse $chatResponse */
        $chatResponse = $this->chat($syntheticRequest);
        $chatData     = json_decode($chatResponse->getContent(), true) ?? [];

        // Merge transcript + lang into the chat payload
        return response()->json(array_merge($chatData, [
            'transcript' => $transcript,
            'lang'       => $lang,
        ]));
    }

    // ── Image job polling endpoint ─────────────────────────────────────────────

    /**
     * Poll the status of a queued image-generation job.
     *
     * GET /pos-api/design-studio/ai-image-job/{jobId}
     *
     * Returns:
     *   { status: 'pending' | 'processing' | 'completed' | 'failed',
     *     data_url: 'data:image/png;base64,...' | null,
     *     error: string | null }
     */
    public function imageJobStatus(string $jobId): JsonResponse
    {
        // Strict UUID-v4 validation — never hit the cache with arbitrary input
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $jobId)) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $entry = Cache::get(GenerateDesignImageJob::cacheKey($jobId));
        if (!is_array($entry)) {
            return response()->json(['status' => 'not_found'], 404);
        }

        // Job is done — read image from local storage and return as data URL
        if ($entry['status'] === 'completed' && !empty($entry['path'])) {
            try {
                $binary  = Storage::disk('local')->get($entry['path']);
                $mime    = $entry['mime'] ?? 'image/png';
                $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);

                // Clean up: remove file after first successful read (client caches the data URL)
                Storage::disk('local')->delete($entry['path']);
                Cache::forget(GenerateDesignImageJob::cacheKey($jobId));

                return response()->json([
                    'status'   => 'completed',
                    'data_url' => $dataUrl,
                    'error'    => null,
                ]);
            } catch (\Throwable $e) {
                return response()->json(['status' => 'failed', 'error' => 'Could not read generated image.'], 500);
            }
        }

        return response()->json([
            'status'   => $entry['status'],
            'data_url' => null,
            'error'    => $entry['error'] ?? null,
        ]);
    }
}
