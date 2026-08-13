// SPDX-FileCopyrightText: 2010-2026 the Friendica project
//
// SPDX-License-Identifier: AGPL-3.0-or-later

// @license magnet:?xt=urn:btih:0b31508aeb0634b347b8270c7bee4d411b5d4109&dn=agpl-3.0.txt AGPLv3-or-later

import * as acorn from '/view/asset/acorn/dist/acorn.mjs';

/**
 * Check AST for DOM-ready patterns (jQuery ready, DOMContentLoaded, load events)
 * @param {Object} ast - The parsed AST
 * @returns {boolean} - True if DOM-ready handler detected
 */
function checkForDOMReadyPatterns(ast) {
  function walk(node) {
    if (!node || typeof node !== 'object') return false;
    if (node.type === 'CallExpression' && node.callee.type === 'Identifier') {
      if (['$', 'jQuery'].includes(node.callee.name) && node.arguments.length > 0) {
        if (node.arguments[0].type === 'FunctionExpression') return true;
      }
    }
    if (node.type === 'CallExpression' && node.callee.type === 'MemberExpression') {
      if (node.callee.property.name === 'ready') return true;
      if (node.callee.property.name === 'addEventListener' && node.callee.object.type === 'Identifier' && ['document', 'window'].includes(node.callee.object.name)) {
        const eventArg = node.arguments[0];
        if (eventArg?.type === 'Literal' && ['DOMContentLoaded', 'load'].includes(eventArg.value)) return true;
      }
    }
    for (const key in node) {
      if (key === 'parent' || key === 'start' || key === 'end' || key === 'loc' || key === 'range') continue;
      const child = node[key];
      if (Array.isArray(child)) {
        for (const c of child) if (walk(c)) return true;
      } else if (walk(child)) return true;
    }
    return false;
  }
  return walk(ast);
}

/**
 * Classify script content using AST analysis
 * @param {string} content - The script content
 * @param {string} source - Source context ('head', 'container', etc.)
 * @returns {'global'|'body'|null}
 */
function classifyScriptContent(content, source) {
  const ast = acorn.parse(content, { ecmaVersion: 2020, sourceType: 'script' });
  if (checkForDOMReadyPatterns(ast)) return 'body';
  if (ast.body.length > 0) {
    const firstNode = ast.body[0];
    if (firstNode.type === 'VariableDeclaration') return 'global';
    if (firstNode.type === 'FunctionDeclaration') return 'global';
    if (firstNode.type === 'ClassDeclaration') return 'global';
    if (content.trim().startsWith('aStr')) return 'global';
  }
  return null;
}

/**
 * Transform code to promote top-level declarations to window scope
 * @param {string} code - The JavaScript code to transform
 * @returns {string} - Transformed code
 */
function promoteToGlobal(code) {
  const ast = acorn.parse(code, { ecmaVersion: 2020, sourceType: 'script', locations: true, ranges: true });
  let newCode = '';
  let lastEnd = 0;
  for (const node of ast.body) {
    if (node.start > lastEnd) newCode += code.slice(lastEnd, node.start);
    if (node.type === 'VariableDeclaration') {
      for (const declaration of node.declarations) {
        if (declaration.id.type === 'Identifier') {
          newCode += `window.${declaration.id.name} = ${declaration.init ? code.slice(declaration.init.start, declaration.init.end) : 'undefined'};`;
        } else {
          newCode += code.slice(node.start, node.end);
        }
      }
    }
    else if (node.type === 'FunctionDeclaration') {
      newCode += `window.${node.id.name} = ${code.slice(node.start, node.end)};`;
    }
    else if (node.type === 'ClassDeclaration') {
      newCode += `window.${node.id.name} = ${code.slice(node.start, node.end)};`;
    }
    else {
      newCode += code.slice(node.start, node.end);
    }
    lastEnd = node.end;
  }
  if (lastEnd < code.length) newCode += code.slice(lastEnd);
  return newCode;
}

/**
 * Classify a script as either global (definitions) or body (execution)
 * @param {string} content
 * @param {Array} globalScripts
 * @param {Array} bodyScripts
 * @param {HTMLScriptElement|null} scriptEl
 * @param {string} source
 */
function classifyScript(content, globalScripts, bodyScripts, scriptEl = null, source = '') {
  if (!content) return;
  if (scriptEl) {
    const attr = (scriptEl.getAttribute('data-spa-scope') || scriptEl.getAttribute('data-spa-script') || '').toLowerCase();
    if (attr === 'global' || attr === 'head') { globalScripts.push(content); return; }
    if (attr === 'body' || attr === 'fragment') { bodyScripts.push(content); return; }
  }
  const classification = classifyScriptContent(content, source);
  if (classification === 'body') { bodyScripts.push(content); return; }
  if (classification === 'global') { globalScripts.push(content); return; }
  if (source === 'head') { globalScripts.push(content); return; }
  bodyScripts.push(content);
}

/**
 * Execute a list of script contents
 * @param {Array} scripts
 * @param {string} context
 */
function executeScripts(scripts, context) {
  if (!scripts || scripts.length === 0) return;

  try {
    // We combine all scripts of the same category (global or body) into a single
    // execution block. This ensures they share a lexical scope, allowing one
    // script to use 'const' or 'let' variables defined in another script
    // from the same page load.
    //
    // 1. Lexical Scoping:
    // We wrap the combined scripts in an anonymous block { ... } to prevent
    // "redeclaration" errors (TypeError: redeclaration of const) when
    // navigating between pages in SPA mode. This block creates a new
    // lexical scope for each page load.
    //
    // 2. Variable & Function promotion to global scope:
    // Use AST parser for robust transformation
    //
    // 3. Runtime Safety:
    // We wrap the whole block in a try-catch. This is important because
    // assignments to global variables that were previously defined as 'const'
    // during the initial page load will throw a TypeError. We want to catch
    // these gracefully so other scripts can continue.
    const scriptEl = document.createElement('script');
    scriptEl.textContent = 'try {\n{\n' + promoteToGlobal(scripts.join('\n\n/* --- Next Script --- */\n\n')) + '\n}\n} catch (e) { }';
    document.head.appendChild(scriptEl);
    document.head.removeChild(scriptEl);
  } catch (e) {
    // Error executing scripts - silently ignored for production
  }
}

export {
  checkForDOMReadyPatterns,
  classifyScriptContent,
  promoteToGlobal,
  classifyScript,
  executeScripts
};