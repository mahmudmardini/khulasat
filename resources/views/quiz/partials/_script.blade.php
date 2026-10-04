{{--
  سكربتُ الأسئلة — T-195. **تحسينٌ فوق نموذجٍ يعمل بدونه**: سؤالٌ في الشاشة،
  وكلُّ جوابٍ يُحفظ لحظةَ اختياره، والتركيزُ ينتقل إلى السؤال أو إلى الحكم.

  وإن أخفق الحفظ بقي الخيارُ مؤشَّراً في النموذج، فيصل مع «أنهِ الاختبار».
--}}
<script>
(function () {
  var form = document.getElementById('kq-quiz');
  if (!form || !window.fetch) return;

  document.documentElement.classList.add('js');

  var S = JSON.parse(document.getElementById('kq-strings').textContent);
  var token = document.querySelector('meta[name="csrf-token"]').content;
  var immediate = form.dataset.immediate === '1';
  var questions = Array.prototype.slice.call(form.querySelectorAll('.kq-q'));
  var prev = form.querySelector('[data-prev]');
  var next = form.querySelector('[data-next]');
  var finish = form.querySelector('[data-finish]');
  var status = document.getElementById('kq-status');
  var current = 0;
  var confirmed = false;

  function digits(n) {
    return String(n).replace(/\d/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'[d]; });
  }

  function say(text, isError) {
    status.textContent = text || '';
    status.classList.toggle('is-error', !!isError);
  }

  function segment(q) {
    return form.querySelector('[data-seg="' + q.dataset.qid + '"]');
  }

  function show(index, focus) {
    current = Math.max(0, Math.min(questions.length - 1, index));

    questions.forEach(function (q, i) {
      q.classList.toggle('is-current', i === current);
      segment(q).classList.toggle('is-current', i === current);
    });

    prev.hidden = current === 0;
    next.hidden = current === questions.length - 1;
    finish.hidden = current !== questions.length - 1;
    confirmed = false;
    say('');

    if (focus) questions[current].querySelector('.kq-q-legend').focus();
  }

  function unanswered() {
    return questions.filter(function (q) { return q.dataset.answered !== '1'; }).length;
  }

  function reveal(q, data) {
    var options = q.querySelectorAll('.kq-opt');
    var chosen = q.querySelector('input:checked');

    options.forEach(function (label) {
      var k = Number(label.dataset.option);
      var tag = label.querySelector('[data-tag]');
      label.querySelector('input').disabled = true;

      if (k === data.correct_index) {
        label.classList.add('is-correct');
        tag.textContent = S.tag_correct;
      } else if (chosen && k === Number(chosen.value)) {
        label.classList.add('is-wrong');
        tag.textContent = S.tag_yours;
      }
    });

    var box = q.querySelector('[data-verdict]');
    box.classList.add(data.correct ? 'is-correct' : 'is-wrong');
    box.querySelector('.kq-verdict-head').textContent = data.correct ? S.verdict_correct : S.verdict_wrong;
    box.querySelector('.kq-verdict-body').textContent = data.explanation;
    box.hidden = false;
    box.focus();
  }

  function save(q, input) {
    var body = new FormData();
    body.append('question', q.dataset.qid);
    body.append('option', input.value);

    return fetch(form.dataset.answerUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: body,
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().then(function (data) { return { ok: response.ok || response.status === 409, data: data }; });
    });
  }

  form.addEventListener('change', function (event) {
    var input = event.target;
    if (input.type !== 'radio') return;

    var q = input.closest('.kq-q');
    q.dataset.answered = '1';
    segment(q).classList.add('is-done');

    save(q, input).then(function (result) {
      if (!result.ok) throw new Error('save');
      if (immediate && result.data.explanation !== undefined) {
        reveal(q, result.data);
      } else {
        say(S.saved);
      }
    }).catch(function () {
      say(S.save_failed, true);
    });
  });

  prev.addEventListener('click', function () { show(current - 1, true); });
  next.addEventListener('click', function () { show(current + 1, true); });

  form.addEventListener('submit', function (event) {
    var left = unanswered();

    if (left > 0 && !confirmed) {
      event.preventDefault();
      confirmed = true;
      say(S.unanswered.replace(':count', digits(left)), true);
    }
  });

  // ابدأ عند أوّل سؤالٍ بلا جواب — فمن حدّث الصفحة يعود حيث كان.
  var first = questions.findIndex(function (q) { return q.dataset.answered !== '1'; });
  show(first === -1 ? questions.length - 1 : first, false);
})();
</script>
