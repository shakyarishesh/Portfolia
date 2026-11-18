@extends('site.layout')
@section('title', 'Application received · '.config('portfolio.platform_name'))
@section('content')
<div class="wrap apply">
  <h1>Thanks — your portfolio is on its way</h1>
  <p class="lead">An admin will review what you sent. Once it is approved, your portfolio will be live at
    <b>{{ session('applied_url', 'your chosen address') }}</b>.</p>
  <p class="muted">If anything needs changing we will contact you at the email address you gave.</p>
  <p>You can keep editing it — add details, change the template, upload a new photo — in your own dashboard. Sign in any time at
    <a href="{{ route('login') }}">{{ route('login') }}</a> with your email, username or phone and your password.</p>
  <p class="cta-row" style="display:flex;gap:.75rem;flex-wrap:wrap">
    @auth<a class="btn btn-primary" href="{{ route('admin.dashboard') }}">Open my dashboard</a>@endauth
    <a class="btn" href="{{ route('root') }}">Back to the home page</a>
  </p>
</div>
@endsection
