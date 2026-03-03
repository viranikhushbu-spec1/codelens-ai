# ⚡ CodeLens AI — AI-Powered Code Explainer

> A web-based tool built in **Core PHP** that takes Python or JavaScript code as input, runs a structural AST parser on the backend, and uses **OpenAI GPT-4o-mini** to generate plain-English explanations, complexity analysis, optimized diffs, and detected code component highlights.

---

## 🚀 How to Run Locally

### Requirements
- PHP 7.4+ (with `curl` extension enabled)
- A local server: **XAMPP**, **WAMP**, **MAMP**, or PHP built-in server

### Steps

**Option 1 — XAMPP / WAMP / MAMP**
```
1. Copy the project folder to:
   - XAMPP → htdocs/codelens/
   - WAMP  → www/codelens/
   - MAMP  → htdocs/codelens/

2. Open config.php and add your OpenAI API key:
   define("OPENAI_API_KEY", "sk-proj-...");

3. Start Apache from XAMPP/WAMP/MAMP control panel

4. Open browser → http://localhost/codelens/
```

**Option 2 — PHP Built-in Server**
```bash
cd codelens/
php -S localhost:8000
# Open: http://localhost:8000
```

---

## 🔑 Getting Your OpenAI API Key

1. Go to 👉 [platform.openai.com/api-keys](https://platform.openai.com/api-keys)
2. Sign up / Log in
3. Click **Create new secret key**
4. Copy the key (starts with `sk-proj-...`)
5. Paste it in `config.php`:
```php
define("OPENAI_API_KEY", "sk-proj-your-key-here");
```
---

## 📁 Project Structure

```
codelens/
├── index.php       ← Frontend UI (HTML + CSS + jQuery)
├── explain.php     ← Backend: AST parser + OpenAI API call
├── config.php      ← API key configuration
└── README.md
```

---

## ✅ Features

| Requirement | Implementation |
|---|---|
| ✅ Accept Python & JavaScript input | Language toggle buttons + textarea |
| ✅ AI plain-English explanation (2–4 sentences) | OpenAI GPT-4o-mini generates per snippet |
| ✅ Live highlighting in textarea | Color overlay renders line-by-line as you type |
| ✅ Multiple snippets with individual explanations | "All Explanations" tab — all snippets numbered |
| ✅ Diff view: original vs optimized | Side-by-side color diff tab |
| ✅ Time & Space complexity | Dedicated Complexity tab |
| ✅ **AST annotation before LLM** | PHP regex parser runs first, injects structure into prompt |

---

---

## 🏗️ System Architecture & Technical Decisions

### Architecture Overview

```
Browser (index.php)
    ↓  User types code → jQuery live highlight overlay fires
    ↓  User clicks "Analyze" → jQuery sends POST to explain.php

explain.php (PHP Backend)
    ↓
    ├── Step 1: buildAST($code, $language)
    │     → Line-by-line regex parser
    │     → Extracts: functions, classes, loops, conditionals,
    │       imports, try/catch, return, async/await,
    │       lambdas, list comprehensions, recursion detection
    │     → Calculates max nesting depth
    │
    ├── Step 2: buildASTSummary($ast)
    │     → Converts AST nodes into a human-readable
    │       structured text block
    │
    ├── Step 3: Build Prompt
    │     → AST summary injected at the TOP of the prompt
    │       BEFORE the code — grounding the model in real structure
    │
    ├── Step 4: OpenAI API Call (gpt-4o-mini)
    │     → System message: "Use AST annotations to give grounded explanations"
    │     → Response format: json_object (guaranteed valid JSON)
    │     → Returns: explanation + complexity + optimized + notes
    │
    └── Step 5: Response
          → Attaches ast_nodes_detected, ast_max_depth, recursive_fns
          → Returns JSON array of snippets to frontend

Browser (index.php)
    ↓  jQuery receives JSON
    ↓  Renders: All Explanations / Detail / AST / Diff / Complexity tabs
```

---

### Key Technical Decisions

#### 1. PHP Backend + jQuery Frontend (No Framework)
The entire backend is written in **Core PHP** with no frameworks (no Laravel, no Symfony). This was a deliberate decision to keep the project:
- Simple to run — just drop in a server folder, no `composer install`
- Easy to understand — no abstraction layers
- Fast to set up — ideal for a take-home project timeframe

jQuery was chosen on the frontend for the same reason — lightweight, no build step, works directly in the browser.

#### 2. Two-Layer AST Architecture
The project uses **two separate AST parsers** that work together:

| Layer | Where | Purpose |
|---|---|---|
| **Client-side AST** | `index.php` (JavaScript) | Live textarea highlighting as user types — instant visual feedback |
| **Server-side AST** | `explain.php` (PHP) | Deep structural analysis before the AI call — injected into the prompt |

This separation means highlighting is instant (no server round-trip) while the LLM prompt gets the deep structural context.

#### 3. AST Annotation Before LLM
The PHP `buildAST()` function parses the code **before** sending it to OpenAI. The AST summary is injected at the **top** of the prompt so the model sees the structure first:

```
=== AST STRUCTURAL ANALYSIS (pre-parsed) ===
FUNCTIONS (1):
  • async function `fetchUserData(userId)` at line 2 (depth 0)
ERROR HANDLING (2):
  • `try` at line 3
  • `catch` at line 7
ASYNC: Asynchronous patterns detected
RECURSION: Possible recursive call in: fibonacci
METADATA: 12 total lines, max nesting depth 2

=== CODE TO ANALYZE ===
...actual code...
```

**Why this matters:** The LLM cannot misidentify a function name, miss a loop, or hallucinate a structure that doesn't exist — the parser has already told it exactly what's there. This reduces hallucinations and improves accuracy significantly.

#### 4. `response_format: json_object` (OpenAI Feature)
Instead of parsing free-form text, the OpenAI call uses:
```php
"response_format" => ["type" => "json_object"]
```
This forces OpenAI to always return valid JSON, eliminating the need for regex cleanup and making the backend deterministic.

#### 5. Multiple Snippets in One Request
The prompt instructs the AI to detect if multiple independent snippets exist in a single input, split them, and return an array. This allows users to paste multiple functions at once and get individual explanations for each — without needing multiple API calls.

#### 6. Error Propagation
API errors (quota exceeded, invalid key, rate limits) are forwarded directly from OpenAI's response to the frontend as-is, rather than being wrapped in generic messages. This makes debugging faster during development and testing.

---

## 🤖 AI Tool Selection: OpenAI GPT-4o-mini

### Why GPT-4o-mini?

| Criteria | Decision |
|---|---|
| **Code reasoning** | GPT-4o-mini performs excellently on code understanding and explanation tasks |
| **JSON compliance** | With `response_format: json_object`, it returns perfectly structured JSON every time — no parsing failures |
| **Cost** | GPT-4o-mini is ~20x cheaper than GPT-4o while delivering near-identical quality for code explanation tasks |
| **Speed** | Faster response times than larger models — important for a real-time web tool |
| **Instruction following** | Reliably respects the strict output schema (snippet_number, explanation, complexity, optimized, notes) |

### Why not GPT-4o?
GPT-4o would offer marginally better explanations but at 20x the cost. For a code explainer that outputs 2–4 sentences, GPT-4o-mini is more than sufficient and a better production decision.

### Why not Claude or Gemini?
- **Claude** requires a separate SDK and has CORS restrictions for direct browser calls
- **Gemini** free tier has regional quota limitations (0 RPM in some regions including India)
- **OpenAI** has the most mature PHP/cURL integration and the `response_format` JSON mode is well-documented and reliable

---

## 🛡️ Handling Hallucinations & Code Accuracy

1. **AST pre-annotation** — The PHP parser detects real code structures before the AI sees the code. The AI cannot hallucinate a function that doesn't exist because the parser already told it exactly which functions are present

2. **Temperature: 0.1** — Very low temperature keeps responses factual and consistent, avoiding creative/speculative explanations

3. **System prompt grounding** — The system message explicitly says: *"Use the AST annotations provided to give accurate, grounded explanations"*

4. **JSON response format** — Structured output prevents the model from adding unreliable narrative that falls outside the schema

5. **Diff view transparency** — Users can visually compare original vs optimized code to verify the AI's suggestions

6. **No code execution** — The tool only explains and suggests; it never runs user code

---

## 🧪 Test Snippets

### Python — Fibonacci
```python
def fibonacci(n):
    if n <= 1:
        return n
    return fibonacci(n - 1) + fibonacci(n - 2)
```

### Python — Class + Error Handling
```python
class BankAccount:
    def __init__(self, balance=0):
        self.balance = balance

    def deposit(self, amount):
        if amount <= 0:
            raise ValueError("Deposit must be positive")
        self.balance += amount

    def withdraw(self, amount):
        try:
            if amount > self.balance:
                raise Exception("Insufficient funds")
            self.balance -= amount
        except Exception as e:
            print(f"Error: {e}")
```

### JavaScript — Async/Await
```javascript
async function fetchUserData(userId) {
    try {
        const response = await fetch(`https://api.example.com/users/${userId}`);
        if (!response.ok) throw new Error("User not found");
        const data = await response.json();
        return data;
    } catch (error) {
        console.error("Failed to fetch:", error);
        return null;
    }
}
```

---

## ⌨️ Keyboard Shortcut

| Shortcut | Action |
|---|---|
| `Ctrl + Enter` (Windows/Linux) | Analyze code |
| `Cmd + Enter` (Mac) | Analyze code |

---
