(function (global) {
  function countOf(value) {
    return typeof value === "function" ? value() : value;
  }

  function contrastColor(hex) {
    const clean = String(hex || "").replace("#", "");
    if (clean.length !== 6) {
      return "#111111";
    }
    const red = parseInt(clean.slice(0, 2), 16);
    const green = parseInt(clean.slice(2, 4), 16);
    const blue = parseInt(clean.slice(4, 6), 16);
    const luminance = (red * 299 + green * 587 + blue * 114) / 1000;
    return luminance > 150 ? "#111111" : "#ffffff";
  }

  function roundRect(ctx, x, y, width, height, radius) {
    const r = Math.max(0, Math.min(radius, width / 2, height / 2));
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
  }

  function traceShape(ctx, x, y, size, shape, radius) {
    ctx.beginPath();
    if (shape === "round") {
      ctx.arc(x + size / 2, y + size / 2, size / 2, 0, Math.PI * 2);
      return;
    }
    roundRect(ctx, x, y, size, size, radius);
  }

  /*
   * Placed diamonds: a 3D stone (drop shadow, light/dark shading, inner facet,
   * specular glint). Rendered once per colour/shape/pixel size into a sprite and
   * then only blitted, so large boards stay cheap to redraw.
   */
  // Light comes from the top left (canvas y points down).
  const LIGHT_ANGLE = Math.atan2(-1, -1);

  function facetShade(light) {
    return light >= 0
      ? `rgba(255, 255, 255, ${(0.42 * light).toFixed(3)})`
      : `rgba(0, 0, 0, ${(-0.26 * light).toFixed(3)})`;
  }

  function fillFacet(g, points, light, edge) {
    g.beginPath();
    g.moveTo(points[0][0], points[0][1]);
    for (let i = 1; i < points.length; i += 1) {
      g.lineTo(points[i][0], points[i][1]);
    }
    g.closePath();
    g.fillStyle = facetShade(light);
    g.fill();
    g.lineWidth = edge;
    g.strokeStyle = "rgba(255, 255, 255, 0.16)";
    g.stroke();
  }

  /* Round drill seen from above: octagonal table ringed by star and girdle facets. */
  function cutRoundFacets(g, cx, cy, radius) {
    const sides = 8;
    const table = radius * 0.56;
    const outer = radius * 1.09; // just past the rim, clipped by the circle
    const edge = Math.max(0.5, radius * 0.035);
    const at = (r, step) => {
      const angle = (step / sides) * Math.PI * 2 - Math.PI / 2;
      return [cx + Math.cos(angle) * r, cy + Math.sin(angle) * r];
    };
    const lightAt = (step) => Math.cos((step / sides) * Math.PI * 2 - Math.PI / 2 - LIGHT_ANGLE);

    for (let k = 0; k < sides; k += 1) {
      // Star facet on the table edge, then the girdle facet next to it.
      fillFacet(g, [at(table, k), at(table, k + 1), at(outer, k + 0.5)], lightAt(k + 0.5), edge);
      fillFacet(g, [at(table, k + 1), at(outer, k + 0.5), at(outer, k + 1.5)], lightAt(k + 1) * 0.8 - 0.08, edge);
    }

    const points = [];
    for (let k = 0; k < sides; k += 1) {
      points.push(at(table, k));
    }
    const top = g.createLinearGradient(cx - table, cy - table, cx + table, cy + table);
    top.addColorStop(0, "rgba(255, 255, 255, 0.34)");
    top.addColorStop(1, "rgba(255, 255, 255, 0.04)");
    g.beginPath();
    g.moveTo(points[0][0], points[0][1]);
    points.slice(1).forEach((point) => g.lineTo(point[0], point[1]));
    g.closePath();
    g.fillStyle = top;
    g.fill();
    g.lineWidth = edge;
    g.strokeStyle = "rgba(255, 255, 255, 0.28)";
    g.stroke();

    g.beginPath();
    g.ellipse(cx - table * 0.35, cy - table * 0.42, table * 0.34, table * 0.17, -0.6, 0, Math.PI * 2);
    g.fillStyle = "rgba(255, 255, 255, 0.6)";
    g.fill();
  }

  /* Square drill seen from above: flat table with four bevelled edges. */
  function cutSquareFacets(g, x, y, size) {
    const bevel = size * 0.2;
    const edge = Math.max(0.5, size * 0.03);
    const x2 = x + size;
    const y2 = y + size;

    fillFacet(g, [[x, y], [x2, y], [x2 - bevel, y + bevel], [x + bevel, y + bevel]], 0.85, edge);
    fillFacet(g, [[x, y], [x + bevel, y + bevel], [x + bevel, y2 - bevel], [x, y2]], 0.45, edge);
    fillFacet(g, [[x2, y], [x2, y2], [x2 - bevel, y2 - bevel], [x2 - bevel, y + bevel]], -0.5, edge);
    fillFacet(g, [[x, y2], [x + bevel, y2 - bevel], [x2 - bevel, y2 - bevel], [x2, y2]], -0.85, edge);

    const top = g.createLinearGradient(x + bevel, y + bevel, x2 - bevel, y2 - bevel);
    top.addColorStop(0, "rgba(255, 255, 255, 0.3)");
    top.addColorStop(0.55, "rgba(255, 255, 255, 0.06)");
    top.addColorStop(1, "rgba(0, 0, 0, 0.05)");
    g.fillStyle = top;
    g.fillRect(x + bevel, y + bevel, size - bevel * 2, size - bevel * 2);

    g.beginPath();
    g.ellipse(x + size * 0.38, y + size * 0.36, size * 0.13, size * 0.07, -0.6, 0, Math.PI * 2);
    g.fillStyle = "rgba(255, 255, 255, 0.55)";
    g.fill();
  }

  const stoneCache = new Map();

  function stoneSprite(hex, shape, px, radiusPx) {
    const key = `${hex}|${shape}|${px}|${radiusPx}`;
    const cached = stoneCache.get(key);
    if (cached) {
      return cached;
    }
    if (stoneCache.size > 400) {
      stoneCache.clear();
    }

    const pad = Math.ceil(px * 0.14);
    const sprite = document.createElement("canvas");
    sprite.width = px + pad * 2;
    sprite.height = px + pad * 2;
    const g = sprite.getContext("2d");

    // Body with a soft drop shadow underneath.
    g.save();
    g.shadowColor = "rgba(20, 16, 40, 0.3)";
    g.shadowBlur = px * 0.12;
    g.shadowOffsetY = px * 0.06;
    traceShape(g, pad, pad, px, shape, radiusPx);
    g.fillStyle = hex;
    g.fill();
    g.restore();

    g.save();
    traceShape(g, pad, pad, px, shape, radiusPx);
    g.clip();

    if (px >= 16) {
      if (shape === "round") {
        cutRoundFacets(g, pad + px / 2, pad + px / 2, px / 2);
      } else {
        cutSquareFacets(g, pad, pad, px);
      }
    } else {
      // Too small for visible facets: simple dome shading.
      const body = g.createLinearGradient(pad, pad, pad + px, pad + px);
      body.addColorStop(0, "rgba(255, 255, 255, 0.45)");
      body.addColorStop(0.45, "rgba(255, 255, 255, 0)");
      body.addColorStop(1, "rgba(0, 0, 0, 0.24)");
      g.fillStyle = body;
      g.fillRect(pad, pad, px, px);
    }
    g.restore();

    // Thin rim for definition against light colours.
    traceShape(g, pad + 0.5, pad + 0.5, px - 1, shape, radiusPx);
    g.lineWidth = 1;
    g.strokeStyle = "rgba(0, 0, 0, 0.14)";
    g.stroke();

    const entry = { canvas: sprite, pad };
    stoneCache.set(key, entry);
    return entry;
  }

  function drawStone(ctx, dx, dy, drawn, shape, radius, hex) {
    // Match the sprite to the device pixels this context paints at (dpr, magnifier zoom).
    const ratio = ctx.getTransform().a || 1;
    const px = Math.max(4, Math.round(drawn * ratio));
    const stone = stoneSprite(hex, shape, px, Math.round(radius * ratio));
    const unit = drawn / px;
    ctx.drawImage(
      stone.canvas,
      dx - stone.pad * unit,
      dy - stone.pad * unit,
      stone.canvas.width * unit,
      stone.canvas.height * unit,
    );
  }

  function drawCell(ctx, x, y, size, variant, spec) {
    const scale = spec.scale || 1;
    const drawn = size * scale;
    const dx = x + (size - drawn) / 2;
    const dy = y + (size - drawn) / 2;
    const shape = spec.placed || (variant === "admin" && !spec.empty) ? spec.shape : "square";
    // Placed square drills are cut almost sharp; empty cells keep a soft corner.
    const radius = variant === "admin" ? 4 : spec.placed ? Math.min(3, drawn * 0.1) : 2;

    if (variant === "admin" && spec.empty) {
      roundRect(ctx, dx, dy, drawn, drawn, radius);
      ctx.fillStyle = "#ffffff";
      ctx.fill();
      ctx.setLineDash([3, 2]);
      ctx.strokeStyle = "#c3c4c7";
      ctx.lineWidth = 1;
      ctx.stroke();
      ctx.setLineDash([]);
      ctx.beginPath();
      ctx.arc(dx + drawn / 2, dy + drawn / 2, Math.min(3, drawn * 0.12), 0, Math.PI * 2);
      ctx.fillStyle = "#dcdcde";
      ctx.fill();
    } else if (spec.placed) {
      drawStone(ctx, dx, dy, drawn, shape, radius, spec.hex || "#eeeeee");
    } else {
      traceShape(ctx, dx, dy, drawn, shape, radius);
      ctx.fillStyle = variant === "admin" ? spec.hex || "#eeeeee" : "#ffffff";
      ctx.fill();

      if (spec.symbol && drawn >= 14) {
        ctx.fillStyle = variant === "admin" ? contrastColor(spec.hex) : spec.wrong ? "#c43737" : "#1c2144";
        ctx.font = `800 ${Math.max(8, drawn * (variant === "admin" ? 0.42 : 0.5))}px Nunito, sans-serif`;
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(spec.symbol, dx + drawn / 2, dy + drawn / 2 + 0.5);
      }
    }

    if (spec.wrong && !spec.placed) {
      ctx.strokeStyle = "#e35b5b";
      ctx.lineWidth = 2;
      traceShape(ctx, dx + 1, dy + 1, Math.max(0, drawn - 2), "square", radius);
      ctx.stroke();
    }

    if (variant === "admin" && spec.hover) {
      ctx.strokeStyle = "#2271b1";
      ctx.lineWidth = 2;
      traceShape(ctx, dx + 1, dy + 1, Math.max(0, drawn - 2), shape, radius);
      ctx.stroke();
    }
  }

  function createViewport(host, options) {
    const canvas = document.createElement("canvas");
    canvas.className = "diamondistry-board-canvas";
    host.replaceChildren(canvas);
    host.classList.add("is-canvas");

    let cellSize = options.cellSize;
    const gap = options.gap;
    const padding = options.padding;
    const scrollParent = options.scrollParent || host.parentElement;
    const variant = options.variant || "play";
    let frame = 0;

    function columns() {
      return countOf(options.columns);
    }

    function rows() {
      return countOf(options.rows);
    }

    function metrics() {
      const stride = cellSize + gap;
      const cols = columns();
      const rowCount = rows();
      return {
        stride,
        cols,
        rowCount,
        width: padding * 2 + cols * cellSize + Math.max(0, cols - 1) * gap,
        height: padding * 2 + rowCount * cellSize + Math.max(0, rowCount - 1) * gap,
      };
    }

    function layout() {
      const box = metrics();
      host.style.width = `${box.width}px`;
      host.style.height = `${box.height}px`;
      const hostRect = host.getBoundingClientRect();
      const parentRect = scrollParent.getBoundingClientRect();
      const originX = Math.max(0, Math.min(box.width, parentRect.left - hostRect.left));
      const originY = Math.max(0, Math.min(box.height, parentRect.top - hostRect.top));
      const viewW = Math.max(
        1,
        Math.min(box.width - originX, parentRect.right - hostRect.left - originX),
      );
      const viewH = Math.max(
        1,
        Math.min(box.height - originY, parentRect.bottom - hostRect.top - originY),
      );
      const dpr = Math.min(global.devicePixelRatio || 1, 2);
      canvas.style.left = `${originX}px`;
      canvas.style.top = `${originY}px`;
      canvas.style.width = `${viewW}px`;
      canvas.style.height = `${viewH}px`;
      const bitmapW = Math.max(1, Math.round(viewW * dpr));
      const bitmapH = Math.max(1, Math.round(viewH * dpr));
      if (canvas.width !== bitmapW || canvas.height !== bitmapH) {
        canvas.width = bitmapW;
        canvas.height = bitmapH;
      }
      return { ...box, viewW, viewH, dpr, originX, originY };
    }

    function drawRegion(ctx, originX, originY, viewW, viewH) {
      const box = metrics();
      const startCol = Math.max(0, Math.floor((originX - padding) / box.stride) - 1);
      const endCol = Math.min(box.cols - 1, Math.ceil((originX + viewW - padding) / box.stride) + 1);
      const startRow = Math.max(0, Math.floor((originY - padding) / box.stride) - 1);
      const endRow = Math.min(box.rowCount - 1, Math.ceil((originY + viewH - padding) / box.stride) + 1);
      let hoverCell = null;

      if (variant === "play") {
        ctx.fillStyle = "#dedede";
        ctx.fillRect(originX, originY, viewW, viewH);
      } else {
        ctx.clearRect(originX, originY, viewW, viewH);
      }

      for (let row = startRow; row <= endRow; row += 1) {
        for (let col = startCol; col <= endCol; col += 1) {
          const index = row * box.cols + col;
          const spec = options.styleAt(index) || { empty: true };
          const x = padding + col * box.stride;
          const y = padding + row * box.stride;
          if (spec.hover) {
            hoverCell = { x, y, spec };
            continue;
          }
          drawCell(ctx, x, y, cellSize, variant, spec);
        }
      }

      if (hoverCell) {
        drawCell(ctx, hoverCell.x, hoverCell.y, cellSize, variant, hoverCell.spec);
      }
    }

    function redraw() {
      const box = layout();
      const ctx = canvas.getContext("2d");
      ctx.setTransform(box.dpr, 0, 0, box.dpr, -box.originX * box.dpr, -box.originY * box.dpr);
      drawRegion(ctx, box.originX, box.originY, box.viewW, box.viewH);
    }

    function cellFromEvent(event) {
      const rect = host.getBoundingClientRect();
      const x = event.clientX - rect.left - padding;
      const y = event.clientY - rect.top - padding;
      const box = metrics();
      if (x < 0 || y < 0) {
        return -1;
      }
      const col = Math.floor(x / box.stride);
      const row = Math.floor(y / box.stride);
      if (col < 0 || row < 0 || col >= box.cols || row >= box.rowCount) {
        return -1;
      }
      if (x - col * box.stride > cellSize || y - row * box.stride > cellSize) {
        return -1;
      }
      return row * box.cols + col;
    }

    function localPoint(event) {
      const rect = host.getBoundingClientRect();
      return {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
      };
    }

    function paintMagnifier(target, focusX, focusY, lens, scale) {
      const dpr = Math.min(global.devicePixelRatio || 1, 2);
      const ctx = target.getContext("2d");
      const source = lens / scale;
      const originX = focusX - source / 2;
      const originY = focusY - source / 2;
      const bitmap = Math.max(1, Math.round(lens * dpr));
      if (target.width !== bitmap || target.height !== bitmap) {
        target.width = bitmap;
        target.height = bitmap;
      }
      ctx.setTransform(
        dpr * scale,
        0,
        0,
        dpr * scale,
        -originX * dpr * scale,
        -originY * dpr * scale,
      );
      ctx.fillStyle = "#dedede";
      ctx.fillRect(originX, originY, source, source);
      drawRegion(ctx, originX, originY, source, source);
    }

    function onScroll() {
      if (frame) {
        return;
      }
      frame = global.requestAnimationFrame(() => {
        frame = 0;
        redraw();
      });
    }

    scrollParent.addEventListener("scroll", onScroll, { passive: true });
    global.addEventListener("resize", onScroll);
    redraw();

    return {
      setCellSize(size) {
        cellSize = size;
        redraw();
      },
      redraw,
      cellFromEvent,
      localPoint,
      paintMagnifier,
    };
  }

  function previewData(canvas) {
    if (!canvas._diamondistryPreview) {
      canvas._diamondistryPreview = JSON.parse(canvas.getAttribute("data-preview") || "{}");
    }
    return canvas._diamondistryPreview;
  }

  function cellSpan(index, count, size) {
    const start = Math.floor((index * size) / count);
    const end = index === count - 1 ? size : Math.floor(((index + 1) * size) / count);
    return [start, Math.max(start + 1, end)];
  }

  function paintPreview(canvas, data) {
    const columns = Number(data.width) || 1;
    const rows = Number(data.height) || 1;
    const pattern = data.pattern || [];
    const palette = data.palette || {};
    const status = data.status || "new";
    const filled = new Set((data.filled || []).map((index) => Number(index)));
    const round = data.shape === "round";
    const ghost = data.ghostUnfilled === true || Boolean(canvas.closest && canvas.closest(".diamondistry-reference"));
    const parent = canvas.parentElement;
    const cssW = parent ? parent.clientWidth : 0;
    const cssH = parent ? parent.clientHeight : 0;
    const fitted = cssW >= 2 && cssH >= 2;
    const dpr = Math.min(global.devicePixelRatio || 1, 2);
    let bitmapW;
    let bitmapH;

    if (fitted) {
      const maxW = Math.max(columns, Math.round(cssW * dpr));
      const maxH = Math.max(rows, Math.round(cssH * dpr));
      const cell = Math.floor(Math.min(maxW / columns, maxH / rows));
      if (ghost && cell >= 2) {
        bitmapW = cell * columns;
        bitmapH = cell * rows;
        canvas.style.width = `${bitmapW / dpr}px`;
        canvas.style.height = `${bitmapH / dpr}px`;
      } else {
        bitmapW = maxW;
        bitmapH = maxH;
        canvas.style.width = "100%";
        canvas.style.height = "100%";
      }
      canvas.style.imageRendering = "auto";
    } else {
      const longest = Math.max(columns, rows);
      const scale = round ? Math.min(6, Math.max(2, Math.floor(360 / longest))) : 1;
      bitmapW = columns * scale;
      bitmapH = rows * scale;
    }

    canvas._diamondistryPreview = data;
    canvas.width = bitmapW;
    canvas.height = bitmapH;
    const ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, bitmapW, bitmapH);
    ctx.imageSmoothingEnabled = round;

    for (let index = 0; index < columns * rows; index += 1) {
      const colorId = pattern[index];
      const hex = palette[colorId] || palette[String(colorId)] || "";
      const isFilled = filled.has(index);
      const showColor = ghost || status !== "progress" || isFilled;
      const col = index % columns;
      const row = Math.floor(index / columns);
      const [x0, x1] = cellSpan(col, columns, bitmapW);
      const [y0, y1] = cellSpan(row, rows, bitmapH);
      ctx.globalAlpha = ghost && !isFilled ? 0.4 : 1;
      ctx.fillStyle = showColor && hex ? hex : data.empty || "#dedede";
      if (round && showColor && hex) {
        const width = x1 - x0;
        const height = y1 - y0;
        const inset = Math.min(width, height) * 0.12;
        ctx.beginPath();
        ctx.arc(x0 + width / 2, y0 + height / 2, Math.max(0.5, (Math.min(width, height) - inset * 2) / 2), 0, Math.PI * 2);
        ctx.fill();
      } else {
        ctx.fillRect(x0, y0, x1 - x0, y1 - y0);
      }
    }

    ctx.globalAlpha = 1;
    watchPreview(canvas);
  }

  function watchPreview(canvas) {
    if (canvas._previewResize || typeof ResizeObserver === "undefined" || !canvas.parentElement) {
      return;
    }
    const parent = canvas.parentElement;
    canvas._previewResize = true;
    let width = parent.clientWidth;
    let height = parent.clientHeight;
    const observer = new ResizeObserver(() => {
      const nextWidth = parent.clientWidth;
      const nextHeight = parent.clientHeight;
      if (nextWidth === width && nextHeight === height) {
        return;
      }
      width = nextWidth;
      height = nextHeight;
      if (canvas._diamondistryPreview && nextWidth >= 2 && nextHeight >= 2) {
        paintPreview(canvas, canvas._diamondistryPreview);
      }
    });
    observer.observe(parent);
  }

  function bootPreviews(root) {
    (root || document).querySelectorAll(".diamondistry-preview-canvas").forEach((canvas) => {
      if (!canvas.getAttribute("data-preview")) {
        return;
      }
      paintPreview(canvas, previewData(canvas));
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => bootPreviews(document));
  } else {
    bootPreviews(document);
  }

  /*
   * Finished painting as a branded poster for download: the board in faceted
   * drills, with logo, title and date underneath. Returns a <canvas>.
   */
  function renderPoster(data, options) {
    const opts = options || {};
    const cols = Number(data.width) || 1;
    const rows = Number(data.height) || 1;
    const shape = data.shape === "round" ? "round" : "square";
    const palette = Array.isArray(data.palette)
      ? Object.fromEntries(data.palette.map((color) => [Number(color.id), color.hex]))
      : data.palette || {};

    const cell = Math.max(12, Math.min(48, Math.floor(1600 / Math.max(cols, rows))));
    const boardPad = Math.round(cell * 0.6);
    const boardW = cols * cell + boardPad * 2;
    const boardH = rows * cell + boardPad * 2;
    const margin = 96;
    const footer = 150;
    const width = Math.max(boardW + margin * 2, 900);
    const height = boardH + margin * 2 + footer;

    // Rendered at 2x so even small paintings print sharp; drawStone follows the transform.
    const scale = 2;
    const canvas = document.createElement("canvas");
    canvas.width = width * scale;
    canvas.height = height * scale;
    const ctx = canvas.getContext("2d");
    ctx.setTransform(scale, 0, 0, scale, 0, 0);

    const bg = ctx.createLinearGradient(0, 0, width, height);
    bg.addColorStop(0, "#ffffff");
    bg.addColorStop(1, "#f3effd");
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, width, height);

    // Board tray with a soft shadow.
    const boardX = Math.round((width - boardW) / 2);
    const boardY = margin;
    ctx.save();
    ctx.shadowColor = "rgba(40, 30, 80, 0.16)";
    ctx.shadowBlur = 40;
    ctx.shadowOffsetY = 16;
    roundRect(ctx, boardX, boardY, boardW, boardH, 28);
    ctx.fillStyle = "#e4e2ec";
    ctx.fill();
    ctx.restore();

    const drill = cell * 0.9;
    const inset = (cell - drill) / 2;
    const radius = Math.min(3, drill * 0.1);
    for (let row = 0; row < rows; row += 1) {
      for (let col = 0; col < cols; col += 1) {
        const hex = palette[Number(data.pattern[row * cols + col])] || "#eeeeee";
        drawStone(ctx, boardX + boardPad + col * cell + inset, boardY + boardPad + row * cell + inset, drill, shape, radius, hex);
      }
    }

    // Footer: logo left, small purple eyebrow + title right; logo and title share one baseline.
    const footerMid = boardY + boardH + margin * 0.6 + 36;
    const baseline = footerMid + 30;
    const left = Math.max(margin, boardX);
    const right = width - left;
    if (opts.logo && opts.logo.naturalWidth) {
      const logoH = 64;
      const logoW = (opts.logo.naturalWidth / opts.logo.naturalHeight) * logoH;
      // +20: the logo file has padding under its wordmark; this puts the wordmark on the title's baseline.
      ctx.drawImage(opts.logo, left, baseline - logoH + 20, logoW, logoH);
    } else {
      ctx.fillStyle = "#1c2144";
      ctx.font = "800 40px Nunito, Arial, sans-serif";
      ctx.textBaseline = "alphabetic";
      ctx.fillText(opts.siteName || "Diamondistry", left, baseline);
    }

    ctx.textAlign = "right";
    ctx.textBaseline = "alphabetic";
    ctx.fillStyle = "#6e4df6";
    ctx.font = "800 15px Nunito, Arial, sans-serif";
    if ("letterSpacing" in ctx) {
      ctx.letterSpacing = "2px";
    }
    ctx.fillText(String(opts.subtitle || "").toUpperCase(), right, footerMid - 12);
    if ("letterSpacing" in ctx) {
      ctx.letterSpacing = "0px";
    }
    ctx.fillStyle = "#1c2144";
    ctx.font = "600 36px Fraunces, Georgia, serif";
    ctx.fillText(opts.title || "", right, baseline);

    return canvas;
  }

  global.DiamondistryGrid = {
    createViewport,
    paintPreview,
    previewData,
    bootPreviews,
    renderPoster,
  };
})(window);
