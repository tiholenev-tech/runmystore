#!/usr/bin/env python3
# S73.A — Neon Glass + Matrix CSS (no visual change yet)
# Adds new classes before </style> on line ~1415
# Does NOT touch existing .preset-chip / .preset-ov / .preset-cat / .preset-box

import sys

PATH = '/var/www/runmystore/products.php'

S73A_CSS = """
/* ════════════════════════════════════════════════════════════
   S73.A — Neon Glass + Matrix CSS
   (used by wizard variants step and fullscreen matrix overlay)
   ════════════════════════════════════════════════════════════ */

/* Variants card (glass wrapper) */
.v4-var-card{padding:0;margin-bottom:12px;overflow:hidden;border-radius:18px;
    border:1px solid rgba(99,102,241,.18);
    background:linear-gradient(235deg,hsl(255 50% 10% / .55),hsl(255 50% 10% / 0) 33%),
        linear-gradient(45deg,hsl(222 50% 10% / .55),hsl(222 50% 10% / 0) 33%),
        rgba(8,9,13,.78);
    box-shadow:hsl(222 50% 2%) 0 10px 16px -8px, hsl(222 50% 4%) 0 20px 36px -14px;
    backdrop-filter:blur(12px)}

/* Axis tabs (Размер / Цвят) */
.v4-axis-tabs{display:flex;border-bottom:1px solid hsl(222 15% 18% / .8);padding:0 14px;gap:4px}
.v4-axis-tab{position:relative;padding:14px 18px 12px;font-size:13px;font-weight:700;
    color:rgba(255,255,255,.4);cursor:pointer;border:none;background:transparent;
    border-bottom:2px solid transparent;transition:all .2s;
    display:flex;align-items:center;gap:7px;margin-bottom:-1px;font-family:inherit}
.v4-axis-tab:hover{color:rgba(255,255,255,.6)}
.v4-axis-tab.active{color:hsl(255 60% 85%);border-bottom-color:hsl(255 70% 55%);
    text-shadow:0 0 12px hsl(255 60% 50% / .4)}
.v4-axis-tab svg{width:14px;height:14px;stroke:currentColor;stroke-width:2;fill:none;
    stroke-linecap:round;stroke-linejoin:round}
.v4-axis-count{display:inline-flex;align-items:center;justify-content:center;
    min-width:20px;height:20px;padding:0 6px;border-radius:100px;
    background:hsl(255 40% 25% / .7);color:hsl(255 60% 88%);
    font-size:10px;font-weight:800;border:1px solid hsl(255 50% 40% / .5)}
.v4-axis-tab.active .v4-axis-count{background:hsl(255 60% 45%);color:#fff;
    box-shadow:0 0 10px hsl(255 60% 50% / .5)}

/* Selected bar */
.v4-selected-bar{display:flex;flex-wrap:wrap;gap:6px;padding:12px 14px 10px;
    align-items:center;border-bottom:1px solid hsl(222 15% 18% / .5);min-height:56px}
.v4-sel-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 10px 5px 11px;
    border-radius:100px;
    background:linear-gradient(135deg,hsl(255 50% 28%),hsl(255 60% 22%));
    border:1px solid hsl(255 60% 50%);color:#fff;font-size:12px;font-weight:700;
    box-shadow:0 0 10px hsl(255 60% 45% / .3),inset 0 1px 0 hsl(255 60% 60% / .3);
    cursor:pointer;transition:all .15s}
.v4-sel-chip:hover{background:linear-gradient(135deg,hsl(255 55% 32%),hsl(255 65% 26%));
    transform:translateY(-1px)}
.v4-sel-chip-x{opacity:.65;font-size:10px;font-weight:400;padding-left:2px}
.v4-sel-chip .v4-dot{width:10px;height:10px;border-radius:50%;
    border:1px solid rgba(255,255,255,.3);flex-shrink:0}
.v4-sel-empty{font-size:11px;color:rgba(255,255,255,.4);font-style:italic;line-height:1.4}
.v4-clear-btn{margin-left:auto;padding:4px 10px;border-radius:100px;
    background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
    color:#fca5a5;font-size:10px;font-weight:700;cursor:pointer;
    letter-spacing:.02em;text-transform:uppercase;font-family:inherit}
.v4-clear-btn:hover{background:rgba(239,68,68,.15)}

/* Picker body + search */
.v4-picker-body{padding:12px 14px 14px;max-height:520px;overflow-y:auto;-webkit-overflow-scrolling:touch}
.v4-picker-search{position:relative;margin-bottom:12px}
.v4-picker-search input{width:100%;padding:10px 14px 10px 38px;border-radius:12px;
    border:1px solid hsl(222 15% 20% / .6);
    background:linear-gradient(to bottom,hsl(255 20% 15% / .2),hsl(255 30% 10% / .4));
    color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.v4-picker-search input::placeholder{color:rgba(255,255,255,.4)}
.v4-picker-search input:focus{border-color:hsl(255 50% 55% / .7);
    box-shadow:0 0 0 3px hsl(255 60% 50% / .15)}
.v4-picker-search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);
    color:rgba(255,255,255,.4);pointer-events:none}
.v4-picker-search-icon svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}

/* Preset groups (collapsible) */
.v4-preset-group{border:1px solid hsl(255 20% 18% / .4);border-radius:14px;
    margin-bottom:10px;overflow:hidden;background:rgba(0,0,0,.15)}
.v4-preset-group-header{display:flex;align-items:center;gap:8px;padding:10px 12px;
    cursor:pointer;transition:background .15s;
    background:linear-gradient(90deg,hsl(255 25% 15% / .5),hsl(255 15% 10% / .2) 80%,transparent)}
.v4-preset-group-header:hover{background:linear-gradient(90deg,hsl(255 35% 20% / .6),hsl(255 20% 12% / .3) 80%,transparent)}
.v4-preset-group-title{flex:1;font-size:11px;font-weight:800;color:hsl(255 50% 80%);
    letter-spacing:.05em;text-transform:uppercase}
.v4-preset-group-title.starred::before{content:'\\2605  ';color:hsl(45 90% 65%);
    font-size:10px;text-shadow:0 0 8px hsl(45 90% 50% / .5)}
.v4-preset-group-count{font-size:9px;font-weight:700;color:rgba(255,255,255,.4);
    padding:2px 7px;border-radius:100px;background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.06)}
.v4-preset-group-count.has-selected{color:hsl(255 60% 85%);background:hsl(255 40% 25% / .6);
    border-color:hsl(255 50% 40% / .6)}
.v4-preset-group-arrow{color:hsl(255 40% 55%);font-size:10px;transition:transform .25s}
.v4-preset-group.open .v4-preset-group-arrow{transform:rotate(90deg)}
.v4-preset-group-body{display:none;padding:8px 10px 12px;flex-wrap:wrap;gap:6px;
    border-top:1px solid hsl(255 20% 18% / .3);background:rgba(0,0,0,.2)}
.v4-preset-group.open .v4-preset-group-body{display:flex}

/* Preset chip inside groups — NOTE: uses .v4-p-chip (NOT .preset-chip to avoid conflict with existing CSS) */
.v4-p-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;
    border-radius:100px;font-size:12px;font-weight:600;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.6);cursor:pointer;transition:all .15s;
    user-select:none;-webkit-user-select:none}
.v4-p-chip:hover{background:hsl(255 30% 20% / .4);border-color:hsl(255 40% 40% / .5);
    color:hsl(255 60% 85%)}
.v4-p-chip.sel{background:linear-gradient(135deg,hsl(255 60% 32%),hsl(255 70% 26%));
    border-color:hsl(255 60% 55%);color:#fff;
    box-shadow:0 0 10px hsl(255 60% 45% / .4),inset 0 1px 0 hsl(255 60% 65% / .3);
    font-weight:700}
.v4-p-chip .v4-dot{width:10px;height:10px;border-radius:50%;
    border:1px solid rgba(255,255,255,.2);flex-shrink:0}

/* Custom add row (27/32 etc) */
.v4-add-row{display:flex;gap:8px;margin-top:10px;align-items:center}
.v4-add-input{flex:1;padding:10px 14px;border-radius:12px;
    border:1px dashed hsl(255 30% 40% / .5);background:rgba(255,255,255,.02);
    color:var(--text-primary);font-size:13px;outline:none;font-family:inherit}
.v4-add-input:focus{border-style:solid;border-color:hsl(255 50% 55% / .7);
    box-shadow:0 0 0 3px hsl(255 60% 50% / .15)}
.v4-add-btn{padding:10px 14px;border-radius:12px;
    background:linear-gradient(135deg,hsl(255 60% 35%),hsl(255 70% 28%));
    border:1px solid hsl(255 60% 50%);color:#fff;font-size:12px;font-weight:700;
    cursor:pointer;display:flex;align-items:center;gap:5px;
    box-shadow:0 0 12px hsl(255 60% 45% / .3),inset 0 1px 0 rgba(255,255,255,.2);
    font-family:inherit}
.v4-add-btn svg{width:14px;height:14px;stroke:#fff;stroke-width:2.5;fill:none;stroke-linecap:round}

/* Matrix CTA button */
.v4-matrix-cta{display:flex;align-items:center;justify-content:space-between;gap:12px;
    padding:14px 16px;border-radius:14px;
    background:linear-gradient(135deg,hsl(255 55% 30% / .7),hsl(222 55% 26% / .7));
    border:1px solid hsl(255 60% 55% / .6);color:#fff;font-size:13px;font-weight:700;
    cursor:pointer;transition:all .25s;
    box-shadow:0 8px 24px hsl(255 70% 30% / .35),0 0 20px hsl(255 60% 45% / .2),
        inset 0 1px 0 rgba(255,255,255,.2);
    text-shadow:0 0 12px rgba(255,255,255,.2);width:100%;font-family:inherit;letter-spacing:.01em;
    margin-top:12px}
.v4-matrix-cta:hover{transform:translateY(-1px)}
.v4-matrix-cta:active{transform:translateY(0) scale(.98)}
.v4-matrix-cta-info{display:flex;align-items:center;gap:10px;flex:1;min-width:0;text-align:left}
.v4-matrix-cta-pill{padding:3px 8px;border-radius:8px;background:rgba(0,0,0,.3);
    font-size:10px;font-weight:800;border:1px solid rgba(255,255,255,.15)}
.v4-matrix-cta-arrow{font-size:18px;flex-shrink:0}

/* Matrix overlay (fullscreen) */
.v4-matrix-ov{position:fixed;inset:0;z-index:9999;
    background:radial-gradient(ellipse 800px 500px at 20% 10%,hsl(255 60% 35% / .25) 0%,transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%,hsl(222 60% 35% / .25) 0%,transparent 60%),
        linear-gradient(180deg,#0a0b14 0%,#050609 100%);
    display:none;flex-direction:column;opacity:0;transition:opacity .25s}
.v4-matrix-ov.open{display:flex;opacity:1}

.v4-mx-header{flex-shrink:0;display:flex;align-items:center;gap:10px;
    padding:14px 16px 12px;background:rgba(3,7,18,.9);backdrop-filter:blur(12px);
    border-bottom:1px solid hsl(222 15% 18% / .6)}
.v4-mx-close{width:36px;height:36px;border-radius:11px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.6);cursor:pointer;display:flex;
    align-items:center;justify-content:center}
.v4-mx-close:hover{color:hsl(255 60% 85%)}
.v4-mx-close svg{width:16px;height:16px;stroke-width:2;fill:none;stroke:currentColor}
.v4-mx-title-wrap{flex:1;min-width:0}
.v4-mx-title{font-size:15px;font-weight:800;
    background:linear-gradient(135deg,#f1f5f9 30%,hsl(255 60% 80%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1.1}
.v4-mx-sub{font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;
    letter-spacing:.08em;font-weight:600;margin-top:2px}
.v4-mx-sub b{color:hsl(255 60% 85%);font-weight:700}

/* Matrix quick chips */
.v4-mx-quick{flex-shrink:0;display:flex;gap:6px;overflow-x:auto;
    padding:10px 16px;scrollbar-width:none;
    border-bottom:1px solid hsl(222 15% 18% / .5);background:rgba(3,7,18,.6)}
.v4-mx-quick::-webkit-scrollbar{display:none}
.v4-qchip{flex-shrink:0;padding:7px 13px;border-radius:100px;
    background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.6);font-size:11px;font-weight:700;cursor:pointer;
    transition:all .2s;white-space:nowrap;font-family:inherit}
.v4-qchip:hover{background:hsl(255 40% 22% / .4);border-color:hsl(255 50% 45% / .5);
    color:hsl(255 60% 88%)}
.v4-qchip.danger{border-color:rgba(239,68,68,.25);color:#fca5a5}
.v4-qchip.danger:hover{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.45)}

/* Matrix grid */
.v4-mx-body{flex:1;overflow:auto;-webkit-overflow-scrolling:touch;padding:6px 0;position:relative}
.v4-mx-table{border-collapse:separate;border-spacing:0;width:max-content;min-width:100%}
.v4-mx-head,.v4-mx-rowh,.v4-mx-corner{position:sticky;background:rgba(8,9,13,.98);
    backdrop-filter:blur(8px);z-index:2}
.v4-mx-corner{left:0;top:0;z-index:4;
    border-right:1px solid hsl(222 15% 18% / .8);
    border-bottom:1px solid hsl(222 15% 18% / .8)}
.v4-mx-head{top:0;z-index:3;padding:10px 8px;text-align:center;
    font-size:11px;font-weight:700;color:hsl(255 60% 85%);min-width:110px;
    border-bottom:1px solid hsl(222 15% 18% / .8);
    border-left:1px solid hsl(222 10% 14% / .3)}
.v4-mx-head .v4-dot{width:12px;height:12px;border-radius:50%;
    border:1px solid rgba(255,255,255,.25);display:inline-block;
    margin-right:6px;vertical-align:middle;box-shadow:0 0 8px rgba(255,255,255,.1)}
.v4-mx-rowh{left:0;z-index:2;padding:8px 14px;text-align:left;font-size:13px;
    font-weight:800;color:hsl(255 60% 88%);min-width:80px;
    border-right:1px solid hsl(222 15% 18% / .8);
    border-bottom:1px solid hsl(222 10% 14% / .5);
    background:linear-gradient(to right,hsl(255 30% 16% / .7),hsl(255 20% 12% / .6))}
.v4-mx-cell{padding:6px 4px;min-width:110px;
    border-bottom:1px solid hsl(222 10% 14% / .5);
    border-left:1px solid hsl(222 10% 14% / .3);
    background:rgba(0,0,0,.15);vertical-align:middle}
.v4-mx-cell.has-value{background:hsl(255 30% 14% / .35)}

.v4-cell-inputs{display:flex;flex-direction:column;gap:4px;align-items:stretch}
.v4-cell-qty-row{display:flex;align-items:center;gap:4px;justify-content:center}
.v4-cell-input{width:54px;height:34px;padding:4px 2px;border-radius:8px;
    border:1px solid hsl(222 15% 22% / .7);background:rgba(8,9,13,.5);
    color:var(--text-primary);font-size:14px;font-weight:800;font-family:inherit;
    text-align:center;outline:none;transition:all .15s;-moz-appearance:textfield}
.v4-cell-input::-webkit-outer-spin-button,.v4-cell-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.v4-cell-input:focus{border-color:hsl(255 60% 55%);background:hsl(255 40% 15% / .6);
    box-shadow:0 0 0 2px hsl(255 60% 50% / .2),0 0 12px hsl(255 60% 45% / .3)}
.v4-cell-input.qty{color:hsl(255 60% 90%)}
.v4-cell-input.min-input{width:36px;height:26px;font-size:11px;font-weight:700;
    color:hsl(45 80% 70%);border-color:hsl(45 40% 22% / .7)}
.v4-cell-input.min-input:focus{border-color:hsl(45 70% 55%);
    box-shadow:0 0 0 2px hsl(45 70% 50% / .2)}
.v4-cell-label{font-size:8px;font-weight:700;color:rgba(255,255,255,.4);
    letter-spacing:.08em;text-transform:uppercase;text-align:center;
    width:54px;line-height:1;margin:0 auto}
.v4-cell-min-row{display:flex;align-items:center;gap:2px;justify-content:center}
.v4-min-step{width:18px;height:26px;border-radius:6px;
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
    color:hsl(45 80% 70%);cursor:pointer;display:flex;align-items:center;
    justify-content:center;font-size:10px;font-weight:700;font-family:inherit;padding:0}
.v4-min-step:hover{background:hsl(45 30% 20% / .5);border-color:hsl(45 40% 40% / .5)}

/* Matrix bottom stats */
.v4-mx-bottom{flex-shrink:0;padding:14px 16px 20px;background:rgba(3,7,18,.95);
    backdrop-filter:blur(12px);border-top:1px solid hsl(222 15% 18% / .8)}
.v4-mx-stats{display:flex;justify-content:space-around;gap:8px;margin-bottom:12px;
    padding:10px 14px;border-radius:14px;background:rgba(0,0,0,.3);
    border:1px solid rgba(255,255,255,.05)}
.v4-mx-stat{text-align:center;flex:1}
.v4-mx-stat-value{font-size:20px;font-weight:800;letter-spacing:-.02em;
    background:linear-gradient(135deg,#fff 0%,hsl(255 60% 85%) 100%);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
    line-height:1}
.v4-mx-stat-label{font-size:9px;color:rgba(255,255,255,.4);text-transform:uppercase;
    letter-spacing:.1em;font-weight:700;margin-top:4px}

/* Matrix "попълнено" summary (green) */
.v4-mx-summary{display:flex;align-items:center;gap:10px;padding:12px 14px;
    border-radius:14px;background:rgba(34,197,94,.06);
    border:1px solid rgba(34,197,94,.25);margin-top:12px}
.v4-mx-summary-check{width:28px;height:28px;border-radius:50%;
    background:linear-gradient(135deg,#22c55e,#16a34a);
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 12px rgba(34,197,94,.5);flex-shrink:0;color:#fff;
    font-size:14px;font-weight:800}
.v4-mx-summary-text{flex:1}
.v4-mx-summary-title{font-size:12px;font-weight:700;color:#86efac;margin-bottom:1px}
.v4-mx-summary-sub{font-size:10px;color:rgba(134,239,172,.7)}
.v4-mx-summary-edit{padding:5px 12px;border-radius:100px;
    background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);
    color:#86efac;font-size:10px;font-weight:700;cursor:pointer;
    text-transform:uppercase;letter-spacing:.03em;font-family:inherit}

@media (max-width:380px){
    .v4-mx-head,.v4-mx-cell{min-width:96px}
    .v4-cell-input{width:48px}
}

/* END S73.A */
"""

# Read
with open(PATH, 'r', encoding='utf-8') as f:
    content = f.read()

# Safety check: abort if already applied
if 'S73.A — Neon Glass + Matrix CSS' in content:
    print('ERROR: S73.A CSS already applied. Aborting to avoid duplication.')
    sys.exit(1)

# Find </style> at ~line 1415 (the main frontend style block, NOT the print label style at ~line 2206)
# Anchor: the rc-dd-danger rule just before it
ANCHOR = '.rc-dd-danger{color:var(--danger)}\n</style>'
REPLACEMENT = '.rc-dd-danger{color:var(--danger)}\n' + S73A_CSS + '\n</style>'

if ANCHOR not in content:
    print('ERROR: anchor not found. File structure may have changed.')
    sys.exit(1)

if content.count(ANCHOR) > 1:
    print(f'ERROR: anchor matches {content.count(ANCHOR)} times. Expected exactly 1.')
    sys.exit(1)

new_content = content.replace(ANCHOR, REPLACEMENT)

# Write
with open(PATH, 'w', encoding='utf-8') as f:
    f.write(new_content)

added_lines = S73A_CSS.count('\n')
print(f'OK: S73.A CSS added ({added_lines} lines).')
