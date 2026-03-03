<?php
require "config.php";
header("Content-Type: application/json");

$code     = $_POST['code']     ?? '';
$language = $_POST['language'] ?? 'python';

if (!$code) {
    echo json_encode(["error" => "Code is empty"]);
    exit;
}

/* ============================================================
   AST-STYLE STRUCTURAL PARSER
   Parses the code line-by-line and extracts:
   - function/method definitions
   - class definitions
   - loops (for / while)
   - conditionals (if / elif / else)
   - imports / requires
   - error handling (try / catch / except)
   - recursion detection
   - async / await usage
   - lambda / arrow functions
   - list comprehensions (Python)
   - return statements
   - nesting depth (max indent level)
   This annotation is injected into the LLM prompt to
   ground the model in the actual code structure and
   reduce hallucinations.
============================================================ */
function buildAST($code, $language) {
    $lines     = explode("\n", $code);
    $ast       = [];
    $maxDepth  = 0;

    foreach ($lines as $lineNum => $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        // Measure nesting depth by leading spaces / tabs
        preg_match('/^(\s*)/', $line, $indentMatch);
        $indent = strlen(str_replace("\t", "    ", $indentMatch[1]));
        $depth  = (int)($indent / 4);
        if ($depth > $maxDepth) $maxDepth = $depth;

        $node = [
            'line'  => $lineNum + 1,
            'depth' => $depth,
            'raw'   => $trimmed,
        ];

        if ($language === 'python') {

            // Function / method definition
            if (preg_match('/^(async\s+)?def\s+(\w+)\s*\(([^)]*)\)/', $trimmed, $m)) {
                $node['type']   = 'function_def';
                $node['name']   = $m[2];
                $node['params'] = array_filter(array_map('trim', explode(',', $m[3])));
                $node['async']  = !empty($m[1]);
                $ast[]          = $node;
                continue;
            }
            // Class definition
            if (preg_match('/^class\s+(\w+)(\(([^)]*)\))?/', $trimmed, $m)) {
                $node['type']    = 'class_def';
                $node['name']    = $m[1];
                $node['extends'] = isset($m[3]) ? $m[3] : null;
                $ast[]           = $node;
                continue;
            }
            // For loop
            if (preg_match('/^for\s+(\w+)\s+in\s+(.+):/', $trimmed, $m)) {
                $node['type']     = 'for_loop';
                $node['variable'] = $m[1];
                $node['iterable'] = $m[2];
                $ast[]            = $node;
                continue;
            }
            // While loop
            if (preg_match('/^while\s+(.+):/', $trimmed, $m)) {
                $node['type']      = 'while_loop';
                $node['condition'] = $m[1];
                $ast[]             = $node;
                continue;
            }
            // If / elif / else
            if (preg_match('/^(if|elif)\s+(.+):/', $trimmed, $m)) {
                $node['type']      = 'conditional';
                $node['keyword']   = $m[1];
                $node['condition'] = $m[2];
                $ast[]             = $node;
                continue;
            }
            if (preg_match('/^else\s*:/', $trimmed)) {
                $node['type']    = 'conditional';
                $node['keyword'] = 'else';
                $ast[]           = $node;
                continue;
            }
            // Try / except / finally
            if (preg_match('/^(try|except|finally)\s*[:(]/', $trimmed, $m)) {
                $node['type']    = 'error_handling';
                $node['keyword'] = $m[1];
                $ast[]           = $node;
                continue;
            }
            // Import
            if (preg_match('/^(import|from)\s+(\S+)/', $trimmed, $m)) {
                $node['type']   = 'import';
                $node['module'] = $m[2];
                $ast[]          = $node;
                continue;
            }
            // Return
            if (preg_match('/^return\s+(.+)/', $trimmed, $m)) {
                $node['type']  = 'return';
                $node['value'] = $m[1];
                $ast[]         = $node;
                continue;
            }
            // Lambda
            if (strpos($trimmed, 'lambda ') !== false) {
                $node['type'] = 'lambda';
                $ast[]        = $node;
                continue;
            }
            // List comprehension
            if (preg_match('/\[.+\s+for\s+\w+\s+in\s+.+\]/', $trimmed)) {
                $node['type'] = 'list_comprehension';
                $ast[]        = $node;
                continue;
            }

        } else { // JavaScript

            // Async function declaration
            if (preg_match('/^async\s+function\s+(\w+)\s*\(([^)]*)\)/', $trimmed, $m)) {
                $node['type']   = 'function_def';
                $node['name']   = $m[1];
                $node['params'] = array_filter(array_map('trim', explode(',', $m[2])));
                $node['async']  = true;
                $ast[]          = $node;
                continue;
            }
            // Regular function declaration
            if (preg_match('/^function\s+(\w+)\s*\(([^)]*)\)/', $trimmed, $m)) {
                $node['type']   = 'function_def';
                $node['name']   = $m[1];
                $node['params'] = array_filter(array_map('trim', explode(',', $m[2])));
                $node['async']  = false;
                $ast[]          = $node;
                continue;
            }
            // Arrow function / const fn
            if (preg_match('/^(const|let|var)\s+(\w+)\s*=\s*(async\s*)?\(([^)]*)\)\s*=>/', $trimmed, $m)) {
                $node['type']   = 'function_def';
                $node['name']   = $m[2];
                $node['params'] = array_filter(array_map('trim', explode(',', $m[4])));
                $node['async']  = !empty($m[3]);
                $node['style']  = 'arrow';
                $ast[]          = $node;
                continue;
            }
            // Class definition
            if (preg_match('/^class\s+(\w+)(\s+extends\s+(\w+))?/', $trimmed, $m)) {
                $node['type']    = 'class_def';
                $node['name']    = $m[1];
                $node['extends'] = isset($m[3]) ? $m[3] : null;
                $ast[]           = $node;
                continue;
            }
            // For loop (classic + for...of + for...in)
            if (preg_match('/^for\s*\(/', $trimmed) || preg_match('/^for\s+\w+\s+of\s+/', $trimmed)) {
                $node['type'] = 'for_loop';
                $ast[]        = $node;
                continue;
            }
            // While loop
            if (preg_match('/^while\s*\(/', $trimmed)) {
                $node['type'] = 'while_loop';
                $ast[]        = $node;
                continue;
            }
            // Array iteration methods
            if (preg_match('/\.(forEach|map|filter|reduce|find|some|every)\s*\(/', $trimmed, $m)) {
                $node['type']   = 'array_iteration';
                $node['method'] = $m[1];
                $ast[]          = $node;
                continue;
            }
            // If / else
            if (preg_match('/^(if|else if)\s*\(/', $trimmed, $m)) {
                $node['type']    = 'conditional';
                $node['keyword'] = $m[1];
                $ast[]           = $node;
                continue;
            }
            if (preg_match('/^else\s*\{/', $trimmed)) {
                $node['type']    = 'conditional';
                $node['keyword'] = 'else';
                $ast[]           = $node;
                continue;
            }
            // Try / catch / finally
            if (preg_match('/^(try|catch|finally)\s*[({]/', $trimmed, $m)) {
                $node['type']    = 'error_handling';
                $node['keyword'] = $m[1];
                $ast[]           = $node;
                continue;
            }
            // Import / require
            if (preg_match('/^import\s+/', $trimmed) || preg_match('/require\s*\(/', $trimmed)) {
                $node['type'] = 'import';
                $ast[]        = $node;
                continue;
            }
            // Return
            if (preg_match('/^return\s+(.+)/', $trimmed, $m)) {
                $node['type']  = 'return';
                $node['value'] = $m[1];
                $ast[]         = $node;
                continue;
            }
            // Await
            if (strpos($trimmed, 'await ') !== false) {
                $node['type'] = 'await_expression';
                $ast[]        = $node;
                continue;
            }
            // Promise chain
            if (preg_match('/\.(then|catch)\s*\(/', $trimmed, $m)) {
                $node['type']   = 'promise_chain';
                $node['method'] = $m[1];
                $ast[]          = $node;
                continue;
            }
        }
    }

    // ── Detect recursion ──
    $functionNames = array_column(
        array_filter($ast, fn($n) => $n['type'] === 'function_def'),
        'name'
    );
    $recursiveFns = [];
    foreach ($functionNames as $fnName) {
        // Count how many times the function name appears (definition + calls)
        $occurrences = preg_match_all('/\b' . preg_quote($fnName, '/') . '\b/', $code);
        if ($occurrences > 1) {
            $recursiveFns[] = $fnName;
        }
    }

    return [
        'nodes'          => $ast,
        'max_depth'      => $maxDepth,
        'recursive_fns'  => $recursiveFns,
        'total_lines'    => count(explode("\n", $code)),
    ];
}

/* ============================================================
   BUILD A HUMAN-READABLE AST SUMMARY
   This is what gets injected into the LLM prompt
============================================================ */
function buildASTSummary($ast, $language) {
    $nodes   = $ast['nodes'];
    $summary = [];

    // Functions
    $fns = array_filter($nodes, fn($n) => $n['type'] === 'function_def');
    if ($fns) {
        foreach ($fns as $fn) {
            $async  = !empty($fn['async']) ? 'async ' : '';
            $params = implode(', ', $fn['params'] ?? []);
            $style  = isset($fn['style']) ? " [{$fn['style']}]" : '';
            $summary[] = "  • {$async}function `{$fn['name']}({$params})`{$style} at line {$fn['line']} (depth {$fn['depth']})";
        }
        $summary = array_merge(["FUNCTIONS (" . count($fns) . "):"], $summary);
    }

    // Classes
    $classes = array_filter($nodes, fn($n) => $n['type'] === 'class_def');
    if ($classes) {
        $classLines = ["CLASSES (" . count($classes) . "):"];
        foreach ($classes as $cls) {
            $ext = $cls['extends'] ? " extends {$cls['extends']}" : '';
            $classLines[] = "  • class `{$cls['name']}{$ext}` at line {$cls['line']}";
        }
        $summary = array_merge($summary, $classLines);
    }

    // Loops
    $loops = array_filter($nodes, fn($n) => in_array($n['type'], ['for_loop', 'while_loop', 'array_iteration']));
    if ($loops) {
        $loopLines = ["LOOPS / ITERATIONS (" . count($loops) . "):"];
        foreach ($loops as $loop) {
            if ($loop['type'] === 'for_loop') {
                $iter = isset($loop['variable']) ? " (iterates `{$loop['variable']}` over `{$loop['iterable']}`)" : '';
                $loopLines[] = "  • for-loop at line {$loop['line']}{$iter}";
            } elseif ($loop['type'] === 'while_loop') {
                $cond = isset($loop['condition']) ? " (condition: {$loop['condition']})" : '';
                $loopLines[] = "  • while-loop at line {$loop['line']}{$cond}";
            } elseif ($loop['type'] === 'array_iteration') {
                $loopLines[] = "  • .{$loop['method']}() iteration at line {$loop['line']}";
            }
        }
        $summary = array_merge($summary, $loopLines);
    }

    // Conditionals
    $conds = array_filter($nodes, fn($n) => $n['type'] === 'conditional');
    if ($conds) {
        $condLines = ["CONDITIONALS (" . count($conds) . "):"];
        foreach ($conds as $c) {
            $kw   = $c['keyword'];
            $cond = isset($c['condition']) ? ": {$c['condition']}" : '';
            $condLines[] = "  • `{$kw}`{$cond} at line {$c['line']}";
        }
        $summary = array_merge($summary, $condLines);
    }

    // Error handling
    $errors = array_filter($nodes, fn($n) => $n['type'] === 'error_handling');
    if ($errors) {
        $errLines = ["ERROR HANDLING (" . count($errors) . "):"];
        foreach ($errors as $e) {
            $errLines[] = "  • `{$e['keyword']}` at line {$e['line']}";
        }
        $summary = array_merge($summary, $errLines);
    }

    // Imports
    $imports = array_filter($nodes, fn($n) => $n['type'] === 'import');
    if ($imports) {
        $summary[] = "IMPORTS: " . count($imports) . " import(s) detected";
    }

    // Async / await
    $asyncNodes = array_filter($nodes, fn($n) => $n['type'] === 'await_expression' || (!empty($n['async'])));
    if ($asyncNodes) {
        $summary[] = "ASYNC: Asynchronous patterns detected (async/await)";
    }

    // Promises
    $promises = array_filter($nodes, fn($n) => $n['type'] === 'promise_chain');
    if ($promises) {
        $summary[] = "PROMISES: Promise chain (.then/.catch) detected";
    }

    // Lambda / list comprehension
    $lambdas = array_filter($nodes, fn($n) => $n['type'] === 'lambda');
    if ($lambdas) $summary[] = "LAMBDAS: " . count($lambdas) . " lambda expression(s) detected";

    $comps = array_filter($nodes, fn($n) => $n['type'] === 'list_comprehension');
    if ($comps) $summary[] = "LIST COMPREHENSIONS: " . count($comps) . " detected";

    // Recursion
    if (!empty($ast['recursive_fns'])) {
        $fns = implode(', ', $ast['recursive_fns']);
        $summary[] = "RECURSION: Possible recursive call(s) in: {$fns}";
    }

    // Metadata
    $summary[] = "METADATA: {$ast['total_lines']} total lines, max nesting depth {$ast['max_depth']}";

    return implode("\n", $summary);
}

/* ============================================================
   RUN AST PARSER
============================================================ */
$ast        = buildAST($code, $language);
$astSummary = buildASTSummary($ast, $language);

/* ============================================================
   BUILD PROMPT WITH AST ANNOTATION INJECTED
   The AST summary is passed to the LLM BEFORE the code,
   so the model is grounded in the actual structure.
   This reduces hallucinations and improves accuracy.
============================================================ */
$prompt = "
You are a senior software engineer analyzing $language code.

=== AST STRUCTURAL ANALYSIS (pre-parsed) ===
The following was extracted by a structural parser BEFORE sending to you.
Use this to ground your explanation in the actual code structure:

$astSummary

=== CODE TO ANALYZE ===
The user may provide ONE or MULTIPLE independent $language snippets.
Your job:
1. Detect if there are multiple independent snippets.
2. Split them logically.
3. Analyze each snippet separately using the AST context above.

Return ONLY valid JSON in this exact format:
{
  \"snippets\": [
    {
      \"snippet_number\": 1,
      \"explanation\": \"2-4 sentence plain English explanation grounded in the detected structure\",
      \"complexity\": \"Time complexity: O(...) — reason. Space complexity: O(...) — reason.\",
      \"optimized\": \"Improved version of the code as a plain string\",
      \"notes\": \"What was improved and why\"
    }
  ]
}

CODE:
$code
";

/* ============================================================
   OPENAI API CALL
============================================================ */
$requestBody = [
    "model"           => "gpt-4o-mini",
    "temperature"     => 0.1,
    "response_format" => ["type" => "json_object"],
    "messages"        => [
        [
            "role"    => "system",
            "content" => "You are a senior software engineer. Always return valid JSON only. Use the AST annotations provided to give accurate, grounded explanations."
        ],
        [
            "role"    => "user",
            "content" => $prompt
        ]
    ]
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "Authorization: Bearer " . OPENAI_API_KEY
    ],
    CURLOPT_POSTFIELDS     => json_encode($requestBody),
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if (!$response) {
    echo json_encode(["error" => "API request failed: " . $curlError]);
    exit;
}

/* ============================================================
   PARSE RESPONSE
============================================================ */
$result = json_decode($response, true);

// Forward API-level errors directly (quota, auth, etc.)
if (isset($result['error'])) {
    echo json_encode($result);
    exit;
}

$content = $result['choices'][0]['message']['content'] ?? '';

// Strip any accidental markdown fences
$content = preg_replace('/```json|```/', '', $content);
$content = trim($content);

$decoded = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "error" => "AI returned invalid JSON",
        "raw"   => $content
    ]);
    exit;
}

if (!isset($decoded['snippets']) || !is_array($decoded['snippets'])) {
    echo json_encode([
        "error" => "AI did not return expected format",
        "raw"   => $content
    ]);
    exit;
}

// Attach AST debug info to each snippet (optional, useful for debugging)
foreach ($decoded['snippets'] as &$snippet) {
    $snippet['ast_nodes_detected'] = count($ast['nodes']);
    $snippet['ast_max_depth']      = $ast['max_depth'];
    $snippet['recursive_fns']      = $ast['recursive_fns'];
}

echo json_encode($decoded['snippets']);