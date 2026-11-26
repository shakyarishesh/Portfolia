@extends('admin.layout')
@section('title', 'New portfolio')
@section('crumbs')<a href="{{ route('admin.portfolios.index') }}">Portfolios</a>@endsection
@section('content')
<form method="post" action="{{ route('admin.portfolios.store') }}" class="stack">
  @csrf
  <div class="card">
    <h2>Details</h2>
    @include('admin.portfolios._fields')
  </div>
  <div class="card">
    <h2>Portfolio type</h2>
    @include('admin.portfolios._type-fields')
  </div>
  <fieldset class="card">
    <legend><h2>Template</h2></legend>
    <p class="small muted">You can change it later; switching templates never changes the content. It must be one recommended for the type (see Portfolio types).</p>
    <div class="template-grid">
      @foreach ($templates as $key => $meta)
        <label class="template-card selectable">
          <input type="radio" name="template" value="{{ $key }}" @checked(old('template', $portfolio->template) === $key) required>
          @include('admin.portfolios._template-card')
        </label>
      @endforeach
    </div>
  </fieldset>
  <div class="form-actions">
    <button class="btn primary" type="submit" data-loading="Creating…">Create portfolio</button>
    <a class="btn" href="{{ route('admin.portfolios.index') }}">Cancel</a>
  </div>
</form>
@endsection
