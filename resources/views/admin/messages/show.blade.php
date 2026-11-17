@extends('admin.layout')
@section('title', $message->subject ?: 'Message from '.$message->name)
@section('crumbs')<a href="{{ route('admin.messages.index') }}">Messages</a>@endsection
@php
  $toggle = fn (string $field, string $on, string $off) => ['field' => $field, 'on' => $message->{$field} ? 0 : 1, 'label' => $message->{$field} ? $off : $on];
@endphp
@section('actions')
  <a class="btn primary" href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?: 'your message')) }}">Reply by email</a>
  @foreach ([$toggle('replied_at', 'Mark replied', 'Mark not replied'), $toggle('read_at', 'Mark read', 'Mark unread'), $toggle('archived_at', 'Archive', 'Unarchive')] as $t)
    <form class="inline" method="post" action="{{ route('admin.messages.state', $message) }}">@csrf<input type="hidden" name="field" value="{{ $t['field'] }}"><input type="hidden" name="on" value="{{ $t['on'] }}"><button class="btn">{{ $t['label'] }}</button></form>
  @endforeach
  <form class="inline" method="post" action="{{ route('admin.messages.destroy', $message) }}" data-confirm="Delete this message?">@csrf @method('DELETE')<button class="btn danger">Delete</button></form>
@endsection
@section('content')
<div class="card stack">
  <p><b>{{ $message->name }}</b> &lt;{{ $message->email }}&gt; <span class="muted small">· {{ $message->created_at->format('j M Y, H:i') }}
    @if ($message->replied_at) · replied {{ $message->replied_at->diffForHumans() }}@endif
    @if ($message->archived_at) · archived @endif</span></p>
  <div class="excerpt" style="max-height:none">{{ $message->message }}</div>
</div>
@endsection
