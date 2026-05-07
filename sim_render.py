#!/usr/bin/env python3
"""TSPL stream → PNG render (pure Python, no PIL).
Usage: sim_render.py <input.bin> <output_basename_without_ext>
Produces: <basename>.png (rendered label preview)
          <basename>.txt (TSPL header summary + diagnostic info)
Renders only BITMAP commands. TEXT cmds are listed in .txt with their position.
Polarity: TSPL bit=1 → white, bit=0 → black ink.
"""
import sys, os, re, struct, zlib

LABEL_W_DOTS = 400  # 50 mm × 8 dots/mm
LABEL_H_DOTS = 240  # 30 mm × 8 dots/mm

def write_png(path, w, h, pixels):
    def chunk(typ, data):
        crc = zlib.crc32(typ + data) & 0xFFFFFFFF
        return struct.pack('>I', len(data)) + typ + data + struct.pack('>I', crc)
    sig = b'\x89PNG\r\n\x1a\n'
    ihdr = struct.pack('>IIBBBBB', w, h, 8, 0, 0, 0, 0)
    raw = bytearray()
    for y in range(h):
        raw.append(0)
        raw.extend(pixels[y*w:(y+1)*w])
    idat = zlib.compress(bytes(raw), 9)
    with open(path,'wb') as f:
        f.write(sig)
        f.write(chunk(b'IHDR', ihdr))
        f.write(chunk(b'IDAT', idat))
        f.write(chunk(b'IEND', b''))

def render_canvas(label_w, label_h):
    return bytearray([0xFF] * (label_w * label_h))  # white canvas

def blit_bitmap(canvas, label_w, x, y, w_bytes, h, raster):
    """TSPL bit=1 white, bit=0 black."""
    for row in range(h):
        for bx in range(w_bytes):
            b_idx = row * w_bytes + bx
            if b_idx >= len(raster): return
            byte = raster[b_idx]
            for bit in range(8):
                pix_x = x + bx * 8 + (7 - bit)
                pix_y = y + row
                if 0 <= pix_x < label_w and 0 <= pix_y < LABEL_H_DOTS:
                    if not ((byte >> bit) & 1):  # bit 0 = black
                        canvas[pix_y * label_w + pix_x] = 0

def main():
    inp, out_base = sys.argv[1], sys.argv[2]
    with open(inp, 'rb') as f:
        data = f.read()

    summary = []
    summary.append(f"Input: {inp}  ({len(data)} bytes)")
    summary.append("")

    # Detect canvas size from SIZE cmd, e.g. "SIZE 50.0 mm,30.0 mm" or "SIZE 50 mm,30 mm"
    label_w = LABEL_W_DOTS
    label_h = LABEL_H_DOTS
    sm = re.search(rb'SIZE\s+([\d.]+)\s*mm\s*,\s*([\d.]+)\s*mm', data)
    if sm:
        try:
            wmm = float(sm.group(1)); hmm = float(sm.group(2))
            label_w = int(round(wmm * 8))
            label_h = int(round(hmm * 8))
            summary.append(f"SIZE {wmm} mm × {hmm} mm = {label_w} × {label_h} dots")
        except Exception:
            pass

    canvas = bytearray([0xFF] * (label_w * label_h))

    # Header summary — extract ASCII commands up to first BITMAP
    header_end = data.find(b'BITMAP')
    if header_end < 0: header_end = min(500, len(data))
    header_block = data[:header_end].decode('latin-1', errors='replace')
    summary.append("=== HEADER (pre-BITMAP) ===")
    summary.append(header_block)
    summary.append("")

    # All BITMAP commands. Render TWO canvases:
    #   canvas      = raw-bytes interpretation (what printer renders if NO UTF-8 decoding)
    #   canvas_utf8 = UTF-8-decoded interpretation (what printer renders if it decodes raster)
    # Outputs as <basename>.png (raw) and <basename>_utf8.png (decoded) when applicable.
    canvas_utf8 = bytearray([0xFF] * (label_w * label_h))
    has_utf8_render = False
    bm_count = 0
    for m in re.finditer(rb'BITMAP\s+(\d+),(\d+),(\d+),(\d+),(\d+),', data):
        bm_count += 1
        x = int(m.group(1)); y = int(m.group(2))
        w_bytes = int(m.group(3)); h = int(m.group(4)); mode = int(m.group(5))
        # Slice up to W*H bytes for raw mode
        raster_raw = data[m.end():m.end() + w_bytes * h]
        # For UTF-8 mode, slice the FULL blob until \nPRINT (not just W*H)
        next_pr = data.find(b'\nPRINT', m.end())
        next_bm = data.find(b'BITMAP', m.end())
        blob_end = min(x for x in [next_pr, next_bm, len(data)] if x > 0)
        full_blob = data[m.end():blob_end]
        actual = len(raster_raw)
        summary.append(f"BITMAP #{bm_count}: x={x} y={y} w={w_bytes}bytes ({w_bytes*8}px) h={h} mode={mode}")
        summary.append(f"  expected raster: {w_bytes*h} bytes; raw slice: {actual}; full blob: {len(full_blob)}")
        if actual > 0:
            high = sum(1 for b in raster_raw if b >= 0x80)
            zero = sum(1 for b in raster_raw if b == 0x00)
            ones = sum(1 for b in raster_raw if b == 0xFF)
            summary.append(f"  raw byte stats: 0xFF={ones}  0x00={zero}  high(0x80+)={high}  total={actual}")
            blit_bitmap(canvas, label_w, x, y, w_bytes, h, raster_raw)

        # UTF-8 decode interpretation
        if len(full_blob) > w_bytes * h:
            try:
                decoded = full_blob.decode('utf-8')
                if all(ord(c) < 256 for c in decoded):
                    decoded_bytes = decoded.encode('latin-1')
                    summary.append(f"  ✓ UTF-8 decoded: {len(decoded_bytes)} bytes (target {w_bytes*h}) — printer may render this")
                    blit_bitmap(canvas_utf8, label_w, x, y, w_bytes, h, decoded_bytes[:w_bytes*h])
                    has_utf8_render = True
                else:
                    summary.append(f"  · UTF-8 decoded but has codepoints > 0xFF — invalid for raster")
            except Exception as e:
                summary.append(f"  · UTF-8 decode failed: {e}")

    summary.append("")
    summary.append(f"Total BITMAP commands rendered: {bm_count}")

    # All TEXT commands — note positions but can't render glyphs without PIL
    text_count = 0
    for m in re.finditer(rb'TEXT\s+(\d+),(\d+),"([^"]*)",(\d+),(\d+),(\d+),"([^"]*)"', data):
        text_count += 1
        x, y = int(m.group(1)), int(m.group(2))
        font, rot, xmul, ymul = m.group(3).decode('latin-1'), int(m.group(4)), int(m.group(5)), int(m.group(6))
        text = m.group(7).decode('latin-1', errors='replace')
        summary.append(f"TEXT #{text_count} @({x},{y}) font={font} rot={rot} {xmul}×{ymul}: {text!r}")
    if text_count:
        summary.append(f"  (TEXT not rendered — no PIL; positions noted)")

    # PRINT detection
    pm = re.search(rb'PRINT\s+(\d+)(?:,(\d+))?', data)
    if pm:
        summary.append(f"PRINT cmd: copies={pm.group(1)} {('subset='+pm.group(2).decode()) if pm.group(2) else ''}")
    else:
        summary.append("PRINT cmd: NOT FOUND (printer would not execute)")

    # Trailing-byte check
    if pm:
        tail = data[pm.end():pm.end()+20]
        if tail.startswith(b'\r\n') or tail.startswith(b'\n'):
            summary.append(f"  trailing terminator: OK (after PRINT: {tail[:8]!r})")
        else:
            summary.append(f"  ⚠ trailing terminator: MISSING/SHORT (after PRINT: {tail[:8]!r})")

    write_png(out_base + '.png', label_w, label_h, bytes(canvas))
    if has_utf8_render:
        write_png(out_base + '_utf8.png', label_w, label_h, bytes(canvas_utf8))
        summary.append("")
        summary.append("Rendered both: <name>.png (raw) and <name>_utf8.png (UTF-8 decoded)")
    with open(out_base + '.txt', 'w', encoding='utf-8') as f:
        f.write('\n'.join(summary))

    print(out_base + '.png')

if __name__ == '__main__':
    main()
