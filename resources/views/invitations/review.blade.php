@extends('invitations.layout')
@section('content')
<h1>Review your invitation</h1><p>You are accepting access to <strong>{{ $subject->church->name }}</strong>. Account establishment alone has not granted Church authority.</p>
@if($type === 'staff')<div class="roles">Responsibilities: {{ $subject->roles->map(fn($role) => $role->role->label())->join(', ') }}</div>@endif
@if($errors->any())<p class="error">This invitation cannot be accepted.</p>@endif
<form method="post" action="{{ route('invitations.accept', ['continuation' => $continuation]) }}">@csrf<div class="field"><label><input style="width:auto;min-height:auto" type="checkbox" name="legal_acceptance" value="1" required> I accept the applicable Terms and Privacy Policy.</label></div><button type="submit">Accept invitation</button></form>
@endsection
