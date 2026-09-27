/*!
 * خُلاصة — سكربت تضمين فهرس الجهة.
 * المواصفة §9 · القرار الأوّل في §1: «سكربت تضمين <script> يعرض الفهرس —
 * لا يحتاج عملاً تقنياً من الجهة».
 *
 *   <div id="khulasah"></div>
 *   <script src="https://cdn.khulasat.io/embed.js" data-tenant="tenant-a"></script>
 *
 * ثلاثة قيود بُني عليها:
 *   ١. بلا مصادقة وبلا بيانات خاصّة — الفهرس عامٌّ كالصفحات نفسها.
 *   ٢. بلا اعتمادية خارجية، وبلا إطار. الحجم تحت 10KB.
 *   ٣. **لا يُحقن HTML من الشبكة.** كلّ نصٍّ يمرّ بـ textContent، فوسمٌ
 *      خبيث في عنوان ملخّص لا يصير عنصراً في صفحة الجهة.
 */
(function () {
  'use strict';

  var script = document.currentScript;
  if (!script) return;

  var tenant = script.getAttribute('data-tenant');
  if (!tenant) return;

  var mount = document.getElementById(script.getAttribute('data-target') || 'khulasah');
  if (!mount) return;

  var base = script.getAttribute('data-base') || script.src.replace(/\/embed\.js.*$/, '');
  var limit = parseInt(script.getAttribute('data-limit') || '10', 10);

  var css =
    '.khs{font-family:inherit;line-height:1.9;direction:rtl;text-align:right}' +
    '.khs-list{list-style:none;margin:0;padding:0}' +
    '.khs-item{padding:14px 0;border-bottom:1px solid rgba(0,0,0,.09)}' +
    '.khs-item:last-child{border-bottom:0}' +
    '.khs-title{display:block;font-weight:600;color:inherit;text-decoration:none}' +
    '.khs-title:hover{text-decoration:underline;text-underline-offset:4px}' +
    '.khs-meta{display:block;margin-top:4px;font-size:.875em;opacity:.7}' +
    '.khs-msg{padding:14px 0;opacity:.7}';

  function styles() {
    if (document.getElementById('khs-style')) return;
    var el = document.createElement('style');
    el.id = 'khs-style';
    el.textContent = css;
    document.head.appendChild(el);
  }

  /** كل نصّ يُوضع بـ textContent — لا innerHTML مع بياناتٍ من الشبكة. */
  function node(tag, className, text) {
    var el = document.createElement(tag);
    if (className) el.className = className;
    if (text != null) el.textContent = String(text);
    return el;
  }

  function message(text) {
    mount.textContent = '';
    mount.appendChild(node('p', 'khs-msg', text));
  }

  function render(items) {
    mount.textContent = '';
    mount.className = (mount.className ? mount.className + ' ' : '') + 'khs';

    if (!items.length) {
      message('لا ملخّصات منشورة بعد.');
      return;
    }

    var list = node('ul', 'khs-list');

    items.slice(0, limit).forEach(function (item) {
      var li = node('li', 'khs-item');

      var link = node('a', 'khs-title', item.title);
      // الرابط يُبنى عندنا من slug، ولا يُؤخذ href من الاستجابة كما هو.
      link.href = base + '/' + encodeURIComponent(item.slug) + '/';
      li.appendChild(link);

      var meta = [item.speaker, item.date].filter(Boolean).join(' · ');
      if (meta) li.appendChild(node('span', 'khs-meta', meta));

      list.appendChild(li);
    });

    mount.appendChild(list);
  }

  styles();
  message('جارٍ التحميل…');

  fetch(base + '/index.json', { credentials: 'omit' })
    .then(function (response) {
      if (!response.ok) throw new Error('http ' + response.status);
      return response.json();
    })
    .then(function (data) {
      render(Array.isArray(data.summaries) ? data.summaries : []);
    })
    .catch(function () {
      // العطل لا يُفسد صفحة الجهة، ولا يُظهر لزائرها رسالةً تقنية.
      message('تعذّر تحميل الملخّصات الآن.');
    });
})();
