/* Actual input-value normalization, never CSS-only. GPL-3.0-or-later. */
(() => {
 'use strict';
 if (window.MAXInput) return;
 const configNode = document.getElementById('max-input-config');
 if (!configNode) return;
 const config = JSON.parse(configNode.textContent);
 if (config.importWizard) {
  const notice = document.querySelector('[data-max-input-import="rejected"]');
  const result = document.querySelector('#id-right .info');
  if (notice && result) result.before(notice);
 }
 // Native step-5 links commit an import. Submit their opaque parameters by POST
 // with the current native token, preserving the native wizard and role checks.
 if (config.importWizard) document.addEventListener('click', event => {
  const link = event.target.closest && event.target.closest('a[href]');
  if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  const url = new URL(link.href, location.href);
  if (url.origin !== location.origin || url.pathname !== location.pathname || url.searchParams.get('step') !== '5') return;
  event.preventDefault();
  const token = document.querySelector('meta[name="anti-csrf-newtoken"]');
  if (!token || !token.content) return; // Server also fails closed without a token.
  const form = document.createElement('form');form.method = 'POST';form.action = url.pathname;
  for (const [name,value] of url.searchParams) {
   if (name === 'token') continue;
   const field = document.createElement('input');field.type = 'hidden';field.name = name;field.value = value;form.append(field);
  }
  const field = document.createElement('input');field.type = 'hidden';field.name = 'token';field.value = token.content;form.append(field);
  document.body.append(form);HTMLFormElement.prototype.submit.call(form);
 });
 // Reflow the two native card forms verified by installed acceptance. This does
 // not change field values, table data, focus order or native submission names.
 if (config.formLayout) {
  document.body.classList.add('max-input-layout');
  for (const node of document.querySelectorAll('input[name],textarea[name]')) {
   if (config.fields[node.name] && node.form) node.form.classList.add('max-input-form');
  }
 }
 const composing = new WeakSet();
 const protectedSpan = /((?:(?<![a-z0-9+.-])(?:[a-z][a-z0-9+.-]{0,31}:\/\/|mailto:|www\.)[^\s<>"']+)|(?:(?<![\p{L}\p{N}.!#$%&*+/=?^_`{|}~-])[\p{L}\p{N}.!#$%&*+/=?^_`{|}~-]+@[\p{L}\p{N}.-]+\.[\p{L}]{2,})|(?:[a-z]:[\\/][^\s<>"']+)|(?:(?<!\S)\/[^\s<>"']+))/giu;
 function text(value) {
  return value.split(protectedSpan).map((part, index) => index % 2 ? part : part.normalize('NFC').toLocaleUpperCase('tr-TR').normalize('NFC')).join('');
 }
 function html(value) {
  if (!value.includes('<')) return text(value);
  if (/<!DOCTYPE|<!ENTITY/i.test(value)) throw new Error('MAXInputInvalid');
  const template = document.createElement('template');
  template.innerHTML = value; // Inert fragment, never inserted into the live document.
  const iterator = document.createTreeWalker(template.content, NodeFilter.SHOW_TEXT);
  for (let node = iterator.nextNode(); node; node = iterator.nextNode()) {
   if (node.parentElement && node.parentElement.closest('script,style,code,pre,textarea')) continue;
   node.nodeValue = text(node.nodeValue);
  }
  return template.innerHTML;
 }
 function facts(value) {
  let data;
  try { data = JSON.parse(value); } catch (_) { return value; }
  if (!data || typeof data !== 'object' || Array.isArray(data)) return value;
  for (const name of ['variant','packaging']) if (typeof data[name] === 'string') data[name] = text(data[name]);
  if (typeof data.currency === 'string') data.currency = data.currency.replace(/[a-z]/g, c => c.toUpperCase());
  return JSON.stringify(data);
 }
 function rule(node) {
  if (!(node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement)) return null;
  if (node.disabled || node.readOnly || node.type === 'password' || node.type === 'hidden' || node.type === 'file') return null;
  return config.fields[node.name] || (config.facts && node.name === 'facts' ? ['facts',4096] : null);
 }
 function apply(node) {
  const selected = rule(node);
  if (!selected || composing.has(node)) return;
  const old = node.value;
  const transform = selected[0] === 'html' ? html : selected[0] === 'facts' ? facts : text;
  let next;
  try { next = transform(old); } catch (_) { node.setCustomValidity('Metin girdisini kontrol edin / Check text input'); return; }
  if ([...next].length > selected[1]) { node.setCustomValidity('Büyük harfe dönüşüm sonrası metin çok uzun / Text is too long after uppercase conversion'); return; }
  node.setCustomValidity('');
  if (next === old) return;
  const start = node.selectionStart, end = node.selectionEnd, direction = node.selectionDirection;
  node.value = next;
  if (start !== null && end !== null && selected[0] === 'text') {
   try { node.setSelectionRange(text(old.slice(0,start)).length, text(old.slice(0,end)).length, direction || 'none'); } catch (_) { /* Native non-text widgets manage their own caret. */ }
  }
 }
 document.addEventListener('compositionstart', event => composing.add(event.target), true);
 document.addEventListener('compositionend', event => { composing.delete(event.target); apply(event.target); }, true);
 for (const name of ['input','change','blur']) document.addEventListener(name, event => {
  if (!event.isComposing && !composing.has(event.target)) apply(event.target);
 }, true);
 document.addEventListener('submit', event => {
  for (const node of event.target.elements || []) apply(node);
  if (!event.target.checkValidity()) { event.preventDefault(); event.target.reportValidity(); }
 }, true);
 // CKEditor maintains a separate DOM and syncs its textarea. Normalize through its
 // public API before native serialization; server enforcement remains authoritative.
 function editor(instance) {
  const source = instance.element && instance.element.$;
  const selected = source && config.fields[source.name];
  if (!selected || selected[0] !== 'html' || instance._maxInput) return;
  instance._maxInput = true;
  instance.on('blur', () => { instance.setData(html(instance.getData())); });
  instance.on('getData', event => { if (typeof event.data.dataValue === 'string') event.data.dataValue = html(event.data.dataValue); });
 }
 function editors() {
  if (!window.CKEDITOR || window.CKEDITOR._maxInput) return;
  window.CKEDITOR._maxInput = true;
  Object.values(window.CKEDITOR.instances || {}).forEach(editor);
  window.CKEDITOR.on('instanceReady', event => editor(event.editor));
 }
 editors(); window.addEventListener('load', editors, { once:true });
 window.MAXInput = Object.freeze({text,html,apply});
})();
