<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CodeLens AI — Code Explainer</title>

<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

<style>
:root {
  --bg: #080810; --surface: #0f0f1a; --surface2: #14141f;
  --border: #1e1e30; --border2: #2a2a40;
  --text: #e2e2f0; --muted: #6b6b90; --dim: #333350;
  --accent: #6c63ff; --accent2: #9d8fff;
  --green: #3dd68c; --red: #f87171; --yellow: #fbbf24;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: 'Syne', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

/* ── Header ── */
header {
  background: var(--surface); border-bottom: 1px solid var(--border);
  padding: 14px 24px; display: flex; align-items: center; gap: 12px;
  position: sticky; top: 0; z-index: 100;
}
.logo { width: 34px; height: 34px; background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: 9px; display: grid; place-items: center; font-size: 17px; box-shadow: 0 0 18px rgba(108,99,255,0.35); flex-shrink: 0; }
.logo-text h1 { font-size: 16px; font-weight: 800; }
.logo-text p { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.header-badge { margin-left: auto; background: rgba(61,214,140,0.1); color: var(--green); border: 1px solid rgba(61,214,140,0.3); border-radius: 20px; padding: 4px 12px; font-size: 11px; font-weight: 700; }

/* ── Layout ── */
#app { flex: 1; display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; height: calc(100vh - 65px); }
#left { border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
#right { display: flex; flex-direction: column; overflow: hidden; }

/* ── Left Panel ── */
#inputArea { background: var(--surface); padding: 18px 20px; border-bottom: 1px solid var(--border); }
.panel-label { font-size: 10px; font-weight: 700; letter-spacing: 2px; color: var(--accent2); margin-bottom: 12px; display: block; }

/* Language selector */
.lang-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.lang-btn { background: var(--surface2); color: var(--muted); border: 1px solid var(--border2); border-radius: 6px; padding: 5px 14px; font-size: 12px; font-weight: 700; cursor: pointer; font-family: 'Syne', sans-serif; transition: all 0.15s; }
.lang-btn.active { background: var(--accent); color: white; border-color: var(--accent); }

/* Editor with live highlight overlay */
.editor-wrap { position: relative; border: 1px solid var(--border2); border-radius: 10px; overflow: hidden; transition: border-color 0.2s; background: var(--bg); }
.editor-wrap.focused { border-color: var(--accent); }
#highlightMirror { position: absolute; top: 0; left: 0; right: 0; bottom: 0; padding: 14px; pointer-events: none; font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.75; overflow: hidden; z-index: 1; white-space: pre-wrap; word-break: break-word; }
#codeInput { position: relative; z-index: 2; width: 100%; height: 230px; background: transparent; border: none; padding: 14px; color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.75; resize: none; outline: none; caret-color: var(--accent2); }

/* Live legend */
#liveLegend { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; min-height: 4px; }
.legend-tag { border-radius: 20px; padding: 2px 10px; font-size: 11px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }

/* Submit row */
.submit-row { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
.snippet-count { font-size: 12px; color: var(--muted); }
#analyzeBtn { background: var(--accent); color: white; border: none; border-radius: 8px; padding: 10px 24px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: 'Syne', sans-serif; transition: all 0.2s; box-shadow: 0 0 18px rgba(108,99,255,0.3); }
#analyzeBtn:hover { background: var(--accent2); transform: translateY(-1px); }
#analyzeBtn:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

/* History list */
#historyList { flex: 1; overflow-y: auto; padding: 14px; background: var(--bg); }
.hist-label { font-size: 10px; color: var(--muted); font-weight: 700; letter-spacing: 2px; margin-bottom: 10px; font-family: 'JetBrains Mono', monospace; }
.hist-empty { color: var(--dim); font-size: 13px; text-align: center; margin-top: 30px; line-height: 2; }
.hist-item { padding: 10px 13px; cursor: pointer; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); margin-bottom: 7px; transition: border-color 0.15s; }
.hist-item:hover, .hist-item.active { border-color: var(--accent); }
.hist-top { display: flex; justify-content: space-between; margin-bottom: 4px; }
.hist-badge { background: var(--accent); color: white; border-radius: 6px; padding: 2px 8px; font-size: 11px; font-weight: 800; }
.hist-preview { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Right Panel Tabs ── */
.tabs { background: var(--surface); border-bottom: 1px solid var(--border); display: flex; padding: 0 16px; overflow-x: auto; }
.tab-btn { background: none; border: none; border-bottom: 2px solid transparent; padding: 11px 14px; font-size: 13px; font-weight: 600; color: var(--muted); cursor: pointer; font-family: 'Syne', sans-serif; transition: all 0.15s; white-space: nowrap; }
.tab-btn.active { color: var(--accent2); border-bottom-color: var(--accent2); }
.tab-btn:hover { color: var(--text); }
#resultArea { flex: 1; overflow-y: auto; padding: 18px; }
.tab-pane { display: none; }
.tab-pane.active { display: block; animation: fadeIn 0.25s ease; }

/* ── Cards ── */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-bottom: 13px; }
.card-label { font-size: 10px; color: var(--accent2); font-weight: 700; letter-spacing: 2px; display: block; margin-bottom: 10px; }
.explanation-text { font-size: 14px; line-height: 1.85; color: #c8c8e8; }

/* Snippet cards in "All" tab */
.snippet-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 13px; cursor: pointer; transition: border-color 0.15s; }
.snippet-card:hover { border-color: var(--accent); }
.snippet-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.snippet-num { background: var(--accent); color: white; border-radius: 8px; padding: 3px 10px; font-size: 12px; font-weight: 800; }
.code-preview { background: var(--bg); border-radius: 8px; padding: 9px 12px; margin-bottom: 10px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--muted); line-height: 1.6; max-height: 68px; overflow: hidden; position: relative; }
.code-fade { position: absolute; bottom: 0; left: 0; right: 0; height: 26px; background: linear-gradient(transparent, var(--bg)); }
.type-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); }

/* Highlighted code lines */
.code-line { display: flex; align-items: flex-start; border-left: 3px solid transparent; border-radius: 0 4px 4px 0; margin-bottom: 1px; padding: 1px 8px; }
.line-num { color: var(--dim); font-size: 11px; min-width: 28px; text-align: right; padding-right: 10px; padding-top: 3px; user-select: none; flex-shrink: 0; }
.line-badge { font-size: 9px; font-weight: 800; border-radius: 4px; padding: 2px 5px; margin-right: 8px; margin-top: 4px; white-space: nowrap; flex-shrink: 0; font-family: 'JetBrains Mono', monospace; }
.line-code { font-family: 'JetBrains Mono', monospace; font-size: 13px; line-height: 1.75; white-space: pre-wrap; word-break: break-word; }

/* AST panel */
.ast-node { background: var(--bg); border-radius: 6px; padding: 7px 12px; margin-bottom: 5px; border-left: 3px solid var(--accent); font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #c8c8e8; line-height: 1.6; }

/* Diff view */
.diff-grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid var(--border2); border-radius: 10px; overflow: hidden; font-family: 'JetBrains Mono', monospace; font-size: 12px; }
.diff-col { padding: 14px; }
.diff-col.original { background: rgba(248,113,113,0.05); border-right: 1px solid var(--border2); }
.diff-col.optimized { background: rgba(61,214,140,0.04); }
.diff-col-label { font-weight: 700; font-size: 10px; letter-spacing: 2px; margin-bottom: 8px; }
.diff-line { line-height: 1.7; white-space: pre-wrap; word-break: break-word; }
.opt-note { background: rgba(61,214,140,0.08); border: 1px solid rgba(61,214,140,0.25); border-radius: 10px; padding: 10px 15px; margin-bottom: 13px; font-size: 13px; color: var(--green); line-height: 1.7; }

/* Complexity */
.complexity-bar { padding: 11px 15px; background: var(--bg); border-radius: 8px; margin-bottom: 8px; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #c8c8e8; line-height: 1.7; }

/* Empty state */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 78%; text-align: center; gap: 10px; }
.empty-icon { font-size: 44px; }
.empty-title { font-weight: 700; font-size: 16px; color: var(--muted); }
.empty-sub { font-size: 13px; color: var(--dim); max-width: 260px; line-height: 1.8; }

/* Loading */
#loadingOverlay { display: none; position: fixed; inset: 0; background: rgba(8,8,16,0.75); z-index: 200; place-items: center; flex-direction: column; gap: 14px; }
#loadingOverlay.show { display: flex; }
.spinner-ring { width: 44px; height: 44px; border: 3px solid rgba(108,99,255,0.3); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
.loading-text { font-size: 14px; color: var(--accent2); font-weight: 600; }

@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: none; } }
</style>
</head>
<body>

<!-- Loading overlay -->
<div id="loadingOverlay">
  <div class="spinner-ring"></div>
  <div class="loading-text">⚡ Analyzing with AI + AST parser...</div>
</div>

<!-- Header -->
<header>
  <div class="logo">⚡</div>
  <div class="logo-text">
    <h1>CodeLens AI</h1>
    <p>powered by OpenAI · PHP backend · AST annotation</p>
  </div>
  <div class="header-badge">✅ AST-Enhanced</div>
</header>

<div id="app">

  <!-- ══ LEFT PANEL ══ -->
  <div id="left">
    <div id="inputArea">
      <span class="panel-label">📝 INPUT CODE</span>

      <div class="lang-row">
        <span style="font-size:12px;color:var(--muted);font-weight:700">LANGUAGE:</span>
        <button class="lang-btn active" data-lang="python">🐍 Python</button>
        <button class="lang-btn" data-lang="javascript">🟨 JavaScript</button>
        <!-- Hidden select kept for PHP form submission -->
        <select id="language" style="display:none">
          <option value="python">Python</option>
          <option value="javascript">JavaScript</option>
        </select>
      </div>

      <!-- Live highlighted textarea -->
      <div class="editor-wrap" id="editorWrap">
        <div id="highlightMirror"></div>
        <textarea id="codeInput" placeholder="Paste your Python or JavaScript code here...&#10;Lines highlight live as you type!&#10;&#10;Example: def fibonacci(n):..."></textarea>
      </div>

      <div id="liveLegend"></div>

      <div class="submit-row">
        <span class="snippet-count" id="snippetCount">0 snippets analyzed</span>
        <button id="analyzeBtn" onclick="analyze()">⚡ Analyze Code</button>
      </div>
    </div>

    <!-- History -->
    <div id="historyList">
      <div class="hist-label">SUBMITTED SNIPPETS</div>
      <div class="hist-empty" id="histEmpty">No snippets yet.<br>Paste code and click Analyze!</div>
    </div>
  </div>

  <!-- ══ RIGHT PANEL ══ -->
  <div id="right">
    <div class="tabs">
      <button class="tab-btn active" data-tab="all">
        📋 All Explanations
        <span id="allBadge" style="display:none;background:var(--accent);color:white;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px"></span>
      </button>
      <button class="tab-btn" data-tab="detail"     id="detailTab"     style="display:none">💡 Detail</button>
      <button class="tab-btn" data-tab="ast"        id="astTab"        style="display:none">🌳 AST Analysis</button>
      <button class="tab-btn" data-tab="diff"       id="diffTab"       style="display:none">🔀 Diff</button>
      <button class="tab-btn" data-tab="complexity" id="complexityTab" style="display:none">📊 Complexity</button>
    </div>

    <div id="resultArea">

      <!-- ALL tab -->
      <div class="tab-pane active" id="tab-all">
        <div class="empty-state" id="emptyState">
          <div class="empty-icon">⚡</div>
          <div class="empty-title">Submit your first snippet</div>
          <div class="empty-sub">Paste code on the left → click Analyze. All explanations appear here.</div>
        </div>
        <div id="allSnippets"></div>
      </div>

      <!-- DETAIL tab -->
      <div class="tab-pane" id="tab-detail"></div>

      <!-- AST tab -->
      <div class="tab-pane" id="tab-ast"></div>

      <!-- DIFF tab -->
      <div class="tab-pane" id="tab-diff"></div>

      <!-- COMPLEXITY tab -->
      <div class="tab-pane" id="tab-complexity"></div>

    </div>
  </div>
</div>

<script>
// ─────────────────────────────────────────
//  TYPE CONFIG — same as your JS highlight overlay
// ─────────────────────────────────────────
const TYPE_CONFIG = {
  function:     { color: "#a78bfa", bg: "rgba(167,139,250,0.13)", label: "fn" },
  class:        { color: "#60a5fa", bg: "rgba(96,165,250,0.13)",  label: "class" },
  loop:         { color: "#3dd68c", bg: "rgba(61,214,140,0.11)",  label: "loop" },
  conditional:  { color: "#fbbf24", bg: "rgba(251,191,36,0.11)",  label: "if" },
  import:       { color: "#f472b6", bg: "rgba(244,114,182,0.11)", label: "import" },
  error:        { color: "#fb923c", bg: "rgba(251,146,60,0.11)",  label: "try" },
  return:       { color: "#818cf8", bg: "rgba(129,140,248,0.11)", label: "return" },
  async:        { color: "#38bdf8", bg: "rgba(56,189,248,0.11)",  label: "async" },
  comprehension:{ color: "#22d3ee", bg: "rgba(34,211,238,0.11)",  label: "comp" },
  lambda:       { color: "#c084fc", bg: "rgba(192,132,252,0.11)", label: "λ" },
};

const AST_NODE_COLORS = {
  function_def:      "#a78bfa",
  class_def:         "#60a5fa",
  for_loop:          "#3dd68c",
  while_loop:        "#3dd68c",
  array_iteration:   "#34d399",
  conditional:       "#fbbf24",
  error_handling:    "#fb923c",
  import:            "#f472b6",
  return:            "#818cf8",
  lambda:            "#c084fc",
  list_comprehension:"#22d3ee",
  await_expression:  "#38bdf8",
  promise_chain:     "#818cf8",
};

// ─────────────────────────────────────────
//  STATE
// ─────────────────────────────────────────
let language = "python";
let history  = [];
let selectedId = null;

// ─────────────────────────────────────────
//  CLIENT-SIDE AST (for live highlighting)
// ─────────────────────────────────────────
function classifyLine(line, lang) {
  const t = line.trim();
  if (!t) return "normal";
  if (lang === "python") {
    if (/^(async\s+)?def\s+\w+/.test(t))           return "function";
    if (/^class\s+\w+/.test(t))                     return "class";
    if (/^for\s+\w+\s+in\s+|^while\s+/.test(t))    return "loop";
    if (/^if\s+|^elif\s+|^else:/.test(t))           return "conditional";
    if (/^(import\s+|from\s+\w+\s+import)/.test(t)) return "import";
    if (/^(try:|except|finally:)/.test(t))           return "error";
    if (/^return\s+/.test(t))                        return "return";
    if (/\[.+for.+in.+\]/.test(t))                  return "comprehension";
    if (/lambda\s+/.test(t))                         return "lambda";
  } else {
    if (/^(async\s+)?function\s+\w+|const\s+\w+\s*=\s*(async\s*)?\(/.test(t)) return "function";
    if (/^class\s+\w+/.test(t))                      return "class";
    if (/^for\s*\(|\.forEach\(|\.map\(|^while\s*\(/.test(t)) return "loop";
    if (/^if\s*\(|^else\s*\{|^else if/.test(t))      return "conditional";
    if (/^(import\s+|const .* = require)/.test(t))   return "import";
    if (/^(try\s*\{|catch\s*\(|finally\s*\{)/.test(t)) return "error";
    if (/^(return\s+|return;)/.test(t))               return "return";
    if (/async\s+|await\s+/.test(t))                  return "async";
  }
  return "normal";
}
function classifyLines(code, lang) { return code.split("\n").map(l => classifyLine(l, lang)); }

// ─────────────────────────────────────────
//  LIVE HIGHLIGHT OVERLAY
// ─────────────────────────────────────────
function updateHighlight() {
  const code  = $("#codeInput").val();
  const types = classifyLines(code, language);
  let html = "";
  code.split("\n").forEach((line, i) => {
    const cfg    = TYPE_CONFIG[types[i]];
    const bg     = cfg ? cfg.bg    : "transparent";
    const border = cfg ? cfg.color : "transparent";
    html += `<div style="background:${bg};border-left:3px solid ${border};border-radius:0 3px 3px 0;padding-left:4px;min-height:1.75em;line-height:1.75"><span style="color:transparent">${esc(line)||" "}</span></div>`;
  });
  $("#highlightMirror").html(html);
  const found = [...new Set(types.filter(t => t !== "normal"))];
  $("#liveLegend").html(found.map(t => {
    const c = TYPE_CONFIG[t];
    return `<span class="legend-tag" style="background:${c.bg};color:${c.color};border:1px solid ${c.color}44">${c.label}: ${t}</span>`;
  }).join(""));
}

// ─────────────────────────────────────────
//  MAIN ANALYZE — calls explain.php
// ─────────────────────────────────────────
async function analyze() {
  const code = $("#codeInput").val().trim();
  if (!code) { alert("Paste some code first!"); return; }

  $("#loadingOverlay").addClass("show");
  $("#analyzeBtn").prop("disabled", true);

  const formData = new FormData();
  formData.append("code", code);
  formData.append("language", language);

  try {
    const res  = await fetch("explain.php", { method: "POST", body: formData });
    const data = await res.json();

    if (!data || data.error) {
      let msg = "Unknown error.";
      if (typeof data?.error === "string")      msg = data.error;
      else if (typeof data?.error === "object") msg = data.error.message || JSON.stringify(data.error);
      showGlobalError(msg);
      return;
    }

    // Save to history — attach original code & line types
    const lineTypes = classifyLines(code, language);
    data.forEach(snippet => {
      const entry = {
        id:          Date.now() + snippet.snippet_number,
        index:       history.length + 1,
        code,
        language,
        lineTypes,
        snippet_number: snippet.snippet_number,
        explanation:    snippet.explanation    || "No explanation.",
        complexity:     snippet.complexity     || "N/A",
        optimized:      snippet.optimized      || code,
        notes:          snippet.notes          || "",
        ast_nodes:      snippet.ast_nodes_detected || 0,
        ast_depth:      snippet.ast_max_depth       || 0,
        recursive_fns:  snippet.recursive_fns       || [],
        timestamp:      new Date().toLocaleTimeString()
      };
      history.push(entry);
      addHistoryItem(entry);
    });

    renderAllSnippets();
    $("#snippetCount").text(`${history.length} snippet${history.length !== 1 ? "s" : ""} analyzed`);
    switchTab("all");
    $("#codeInput").val("");
    updateHighlight();

  } catch(e) {
    showGlobalError("Request failed: " + e.message);
  }

  $("#loadingOverlay").removeClass("show");
  $("#analyzeBtn").prop("disabled", false);
}

// ─────────────────────────────────────────
//  RENDER FUNCTIONS
// ─────────────────────────────────────────
function esc(s) {
  return String(s)
    .replace(/&/g,"&amp;").replace(/</g,"&lt;")
    .replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}

function renderTypeTag(type) {
  const c = TYPE_CONFIG[type]; if (!c) return "";
  return `<span style="background:${c.bg};color:${c.color};border:1px solid ${c.color}44;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;font-family:'JetBrains Mono',monospace">${c.label}: ${type}</span>`;
}

function renderHighlightedCode(code, lineTypes) {
  return code.split("\n").map((line, i) => {
    const type   = lineTypes[i] || "normal";
    const cfg    = TYPE_CONFIG[type];
    const bg     = cfg ? cfg.bg    : "transparent";
    const border = cfg ? cfg.color : "transparent";
    const badge  = cfg ? `<span class="line-badge" style="background:${cfg.color}22;color:${cfg.color}">${cfg.label}</span>` : `<span style="min-width:28px;display:inline-block"></span>`;
    const color  = cfg ? "#e2e2f0" : "#888899";
    return `<div class="code-line" style="background:${bg};border-left-color:${border}">
      <span class="line-num">${i+1}</span>${badge}
      <span class="line-code" style="color:${color}">${esc(line)||" "}</span>
    </div>`;
  }).join("");
}

function renderAllSnippets() {
  if (!history.length) { $("#emptyState").show(); $("#allSnippets").html(""); return; }
  $("#emptyState").hide();
  $("#allSnippets").html(history.map(h => {
    const foundTypes = [...new Set(h.lineTypes.filter(t => t !== "normal"))];
    return `<div class="snippet-card" data-id="${h.id}">
      <div class="snippet-header">
        <div style="display:flex;align-items:center;gap:8px">
          <span class="snippet-num">#${h.index}</span>
          <span style="font-size:11px;color:var(--accent2);font-weight:700">${h.language.toUpperCase()}</span>
          <span style="font-size:10px;color:var(--muted)">${h.timestamp}</span>
        </div>
        <span style="font-size:11px;color:var(--muted)">click for details →</span>
      </div>
      <div class="code-preview">
        <span style="font-family:'JetBrains Mono',monospace">${esc(h.code.split("\n").slice(0,4).join("\n"))}</span>
        <div class="code-fade"></div>
      </div>
      <span class="card-label">EXPLANATION</span>
      <p class="explanation-text">${esc(h.explanation)}</p>
      ${foundTypes.length ? `<div class="type-tags">${foundTypes.map(renderTypeTag).join(" ")}</div>` : ""}
    </div>`;
  }).join(""));
  $("#allBadge").text(history.length).show();
}

function renderDetail(h) {
  const foundTypes = [...new Set(h.lineTypes.filter(t => t !== "normal"))];
  $("#tab-detail").html(`
    <div class="card">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <span style="background:var(--accent);color:white;border-radius:8px;padding:3px 10px;font-size:12px;font-weight:800">#${h.index}</span>
        <span style="font-size:11px;color:var(--accent2);font-weight:700">${h.language.toUpperCase()}</span>
        <span style="font-size:11px;color:var(--muted)">${h.timestamp}</span>
      </div>
      <span class="card-label">PLAIN ENGLISH EXPLANATION</span>
      <p class="explanation-text">${esc(h.explanation)}</p>
    </div>
    <div class="card">
      <span class="card-label">HIGHLIGHTED CODE</span>
      ${renderHighlightedCode(h.code, h.lineTypes)}
    </div>
    ${foundTypes.length ? `
    <div class="card">
      <span class="card-label">DETECTED COMPONENTS</span>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px">${foundTypes.map(renderTypeTag).join(" ")}</div>
    </div>` : ""}`);
}

function renderAST(h) {
  // Parse the AST node info from backend response + client-side line types
  const lineTypes = h.lineTypes;
  const lines     = h.code.split("\n");

  // Group detected nodes
  const nodeGroups = {};
  lineTypes.forEach((type, i) => {
    if (type === "normal") return;
    if (!nodeGroups[type]) nodeGroups[type] = [];
    nodeGroups[type].push({ line: i + 1, code: lines[i].trim() });
  });

  let astHtml = `
    <div class="card">
      <span class="card-label">🌳 AST STRUCTURAL ANALYSIS — SNIPPET #${h.index}</span>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:14px">
        <div style="background:var(--bg);border-radius:8px;padding:12px;text-align:center;border:1px solid var(--border2)">
          <div style="font-size:22px;font-weight:800;color:var(--accent2)">${h.ast_nodes}</div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px">AST Nodes</div>
        </div>
        <div style="background:var(--bg);border-radius:8px;padding:12px;text-align:center;border:1px solid var(--border2)">
          <div style="font-size:22px;font-weight:800;color:var(--green)">${h.ast_depth}</div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px">Max Depth</div>
        </div>
        <div style="background:var(--bg);border-radius:8px;padding:12px;text-align:center;border:1px solid var(--border2)">
          <div style="font-size:22px;font-weight:800;color:var(--yellow)">${h.recursive_fns.length > 0 ? "Yes" : "No"}</div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px">Recursion</div>
        </div>
      </div>`;

  if (h.recursive_fns.length > 0) {
    astHtml += `<div style="background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.25);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:var(--yellow)">
      ⚠️ <strong>Recursive functions detected:</strong> ${esc(h.recursive_fns.join(", "))}
    </div>`;
  }

  // Render each node type group
  Object.entries(nodeGroups).forEach(([type, nodes]) => {
    const cfg   = TYPE_CONFIG[type] || { color: "#94a3b8", bg: "rgba(148,163,184,0.1)", label: type };
    astHtml += `
      <div style="margin-bottom:12px">
        <div style="font-size:10px;font-weight:700;letter-spacing:2px;color:${cfg.color};margin-bottom:6px">${type.toUpperCase().replace("_"," ")} (${nodes.length})</div>
        ${nodes.map(n => `
          <div class="ast-node" style="border-left-color:${cfg.color}">
            <span style="color:var(--muted);margin-right:8px;font-size:10px">line ${n.line}</span>
            <span style="color:${cfg.color}">${esc(n.code)}</span>
          </div>`).join("")}
      </div>`;
  });

  astHtml += `<div style="margin-top:12px;padding:10px 14px;background:rgba(108,99,255,0.08);border:1px solid rgba(108,99,255,0.2);border-radius:8px;font-size:12px;color:var(--muted);line-height:1.7">
    💡 <strong style="color:var(--accent2)">How this works:</strong> This AST (Abstract Syntax Tree) analysis was performed by a PHP regex-based structural parser in <code>explain.php</code> BEFORE sending to the AI. The detected structure was injected into the prompt to ground the model and reduce hallucinations.
  </div>`;

  astHtml += `</div>`;
  $("#tab-ast").html(astHtml);
}

function renderDiff(h) {
  const oLines = h.code.split("\n");
  const nLines = h.optimized.split("\n");
  const oHtml  = oLines.map((l,i) => `<div class="diff-line" style="color:${nLines[i]!==l?"var(--red)":"var(--muted)"}"><span style="opacity:.3;margin-right:8px">${i+1}</span>${esc(l)||" "}</div>`).join("");
  const nHtml  = nLines.map((l,i) => `<div class="diff-line" style="color:${oLines[i]!==l?"var(--green)":"var(--muted)"}"><span style="opacity:.3;margin-right:8px">${i+1}</span>${esc(l)||" "}</div>`).join("");
  $("#tab-diff").html(`
    ${h.notes ? `<div class="opt-note"><strong>🔧 Optimization Notes:</strong> ${esc(h.notes)}</div>` : ""}
    <div class="diff-grid">
      <div class="diff-col original"><div class="diff-col-label" style="color:var(--red)">ORIGINAL</div>${oHtml}</div>
      <div class="diff-col optimized"><div class="diff-col-label" style="color:var(--green)">OPTIMIZED</div>${nHtml}</div>
    </div>`);
}

function renderComplexity(h) {
  const parts = h.complexity.split(/\.\s+|Time|Space/).filter(Boolean);
  $("#tab-complexity").html(`
    <div class="card">
      <span class="card-label">SNIPPET #${h.index} — TIME & SPACE COMPLEXITY</span>
      <div class="complexity-bar" style="border-left:3px solid var(--accent)">${esc(h.complexity)}</div>
    </div>`);
}

function selectSnippet(id) {
  const h = history.find(x => x.id === id); if (!h) return;
  selectedId = id;
  renderDetail(h); renderAST(h); renderDiff(h); renderComplexity(h);
  $("#detailTab,#astTab,#diffTab,#complexityTab").show();
  switchTab("detail");
  $(".hist-item").removeClass("active");
  $(`.hist-item[data-id="${id}"]`).addClass("active");
}

function switchTab(name) {
  $(".tab-btn").removeClass("active"); $(`.tab-btn[data-tab="${name}"]`).addClass("active");
  $(".tab-pane").removeClass("active"); $(`#tab-${name}`).addClass("active");
}

function addHistoryItem(h) {
  $("#histEmpty").hide();
  $("#historyList").append(`
    <div class="hist-item" data-id="${h.id}">
      <div class="hist-top">
        <div style="display:flex;align-items:center;gap:7px">
          <span class="hist-badge">#${h.index}</span>
          <span style="font-size:11px;color:var(--accent2);font-weight:700">${h.language.toUpperCase()}</span>
        </div>
        <span style="font-size:10px;color:var(--muted)">${h.timestamp}</span>
      </div>
      <div class="hist-preview">${esc(h.code.slice(0,48))}…</div>
    </div>`);
}

function showGlobalError(msg) {
  $("#allSnippets").html(`<div class="card" style="border-color:var(--red)">
    <span class="card-label" style="color:var(--red)">ERROR</span>
    <p style="color:var(--red);font-size:13px;line-height:1.7">${esc(msg)}</p>
  </div>`);
  switchTab("all");
  $("#emptyState").hide();
}

// ─────────────────────────────────────────
//  EVENT BINDINGS
// ─────────────────────────────────────────
$(function() {

  // Language toggle
  $(".lang-btn").on("click", function() {
    language = $(this).data("lang");
    $(".lang-btn").removeClass("active"); $(this).addClass("active");
    $("#language").val(language);
    updateHighlight();
  });

  // Live highlight
  $("#codeInput").on("input", updateHighlight);
  $("#codeInput").on("scroll", () => { $("#highlightMirror").scrollTop($("#codeInput").scrollTop()); });
  $("#codeInput").on("focus", () => $("#editorWrap").addClass("focused"));
  $("#codeInput").on("blur",  () => $("#editorWrap").removeClass("focused"));

  // Ctrl+Enter shortcut
  $("#codeInput").on("keydown", function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === "Enter") analyze();
  });

  // Tabs
  $(document).on("click", ".tab-btn",      function() { switchTab($(this).data("tab")); });
  $(document).on("click", ".snippet-card", function() { selectSnippet(parseInt($(this).data("id"))); });
  $(document).on("click", ".hist-item",    function() { selectSnippet(parseInt($(this).data("id"))); });
});
</script>
</body>
</html>