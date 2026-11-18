@extends('site.layout')
@section('title', 'Application received · '.config('app.name'))
@section('content')
<div class="wrap apply">
  <h1>Thanks — your portfolio is on its way</h1>
  <p class="lead">An admin will review what you sent. Once it is approved, your portfolio will be live at
    <b>{{ session('applied_url', 'your chosen address') }}</b>.</p>
  <p class="muted">If anything needs changing we will contact you at the email address you gave.</p>
  <p><a class="btn" href="{{ route('root') }}">Back to the home page</a></p>
</div>
@endsection
