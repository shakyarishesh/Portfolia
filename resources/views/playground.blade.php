@php
  $endpoints = [
    ['id' => 'list', 'method' => 'GET', 'path' => '/api/demo/employees?department=engineering', 'label' => 'List employees (filtered)',
     'note' => 'Query-string filters validated server-side. Try department=marketing to see a 422.'],
    ['id' => 'show', 'method' => 'GET', 'path' => '/api/demo/employees/3', 'label' => 'Get one employee', 'note' => 'Try an id that does not exist to see a 404.'],
    ['id' => 'payroll', 'method' => 'POST', 'path' => '/api/demo/payroll/calculate', 'label' => 'Calculate a payslip',
     'note' => 'Basic + allowances + overtime − unpaid leave − provident fund − progressive tax (illustrative slabs). Remove working_days to see validation errors.',
     'body' => ['basic_salary' => 85000, 'allowances' => 12000, 'working_days' => 26, 'unpaid_leave_days' => 1, 'overtime_hours' => 6, 'overtime_multiplier' => 1.5, 'provident_fund_percent' => 10]],
    ['id' => 'portfolio', 'method' => 'GET', 'path' => '/api/portfolio', 'label' => 'This portfolio as JSON', 'note' => 'The real, public read-only API that powers the terminal.'],
  ];
@endphp
<!doctype html>
<html lang="en" class="doc">
<head>
  @include('portfolio.partials.head', [
      'title' => "API playground — {$profile->full_name}",
      'description' => 'Try a small sandboxed REST API: validation, status codes and a payroll calculation, with sample data only.',
      'ogType' => 'website',
  ])
</head>
<body>
  <div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><div class="grid"></div></div>
  <main class="page">
    <div class="wrap">
      <a class="back" href="{{ url('/') }}">← {{ $profile->full_name }}</a>
      <p class="eyebrow">API playground</p>
      <h1 class="title big">Send a request</h1>
      <p class="lead">A small sandboxed API built the way I build production endpoints: validated input, consistent JSON and correct status codes. Everything here is invented sample data. Requests are rate-limited to 30 per minute.</p>

      <div class="playground" data-endpoints='@json($endpoints)'>
        <div class="pg-request card">
          <label for="pg-endpoint">Endpoint</label>
          <select id="pg-endpoint">@foreach ($endpoints as $e)<option value="{{ $e['id'] }}">{{ $e['method'] }} · {{ $e['label'] }}</option>@endforeach</select>
          <div class="pg-url"><span class="pg-method"></span><input id="pg-path" aria-label="Request path" spellcheck="false"></div>
          <p class="pg-note small"></p>
          <label for="pg-body" class="pg-body-label">JSON body</label>
          <textarea id="pg-body" rows="11" spellcheck="false"></textarea>
          <p class="pg-error" role="alert" hidden></p>
          <button class="btn primary" type="button" id="pg-send">Send request</button>
        </div>
        <div class="pg-response card" aria-live="polite">
          <div class="pg-meta"><span>Response</span><span class="pg-status"></span><span class="pg-time"></span></div>
          <pre class="pg-output"><code>Pick an endpoint and press “Send request”.</code></pre>
        </div>
      </div>
    </div>
  </main>
  <script>
  (() => {
    const root = document.querySelector('.playground');
    const endpoints = JSON.parse(root.dataset.endpoints);
    const $ = s => root.querySelector(s);
    const sel = $('#pg-endpoint'), path = $('#pg-path'), body = $('#pg-body'), out = $('.pg-output code'), status = $('.pg-status'), time = $('.pg-time'), err = $('.pg-error'), send = $('#pg-send');
    let current;
    function pick() {
      current = endpoints.find(e => e.id === sel.value);
      $('.pg-method').textContent = current.method;
      path.value = current.path;
      $('.pg-note').textContent = current.note;
      const hasBody = current.method !== 'GET';
      body.hidden = $('.pg-body-label').hidden = !hasBody;
      body.value = hasBody ? JSON.stringify(current.body, null, 2) : '';
      err.hidden = true;
    }
    sel.addEventListener('change', pick);
    pick();
    send.addEventListener('click', async () => {
      err.hidden = true;
      if (!path.value.startsWith('/api/')) { err.textContent = 'Only paths under /api/ can be called from here.'; err.hidden = false; return; }
      let payload;
      if (current.method !== 'GET') {
        try { payload = JSON.stringify(JSON.parse(body.value || '{}')); }
        catch (e) { err.textContent = 'The body is not valid JSON: ' + e.message; err.hidden = false; return; }
      }
      send.disabled = true; send.textContent = 'Sending…';
      status.textContent = ''; time.textContent = ''; out.textContent = 'Waiting for response…';
      const t0 = performance.now();
      try {
        const res = await fetch(path.value, { method: current.method, headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: payload });
        const ms = Math.round(performance.now() - t0);
        const text = await res.text();
        let shown = text;
        try { shown = JSON.stringify(JSON.parse(text), null, 2); } catch {}
        status.textContent = `${res.status} ${res.statusText}`;
        status.className = 'pg-status ' + (res.ok ? 'ok' : 'bad');
        time.textContent = `${ms} ms`;
        out.textContent = shown;
      } catch (e) {
        status.textContent = 'Network error'; status.className = 'pg-status bad';
        out.textContent = 'The request could not be completed. Check your connection and try again.';
      } finally {
        send.disabled = false; send.textContent = 'Send request';
      }
    });
  })();
  </script>
</body>
</html>
