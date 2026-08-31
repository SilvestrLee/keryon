@extends('invitations.layout')
@section('content')
<h1>{{ $type === 'activation' ? 'Activate '.$subject->church->name : 'Join '.$subject->church->name }}</h1><p>This secure invitation is bound to <strong>{{ $email }}</strong>.</p>
@if($type === 'staff')<div class="roles">Responsibilities: {{ $subject->roles->map(fn($role) => $role->role->label())->join(', ') }}@if($subject->roles->contains(fn($role) => $role->role->value === 'care'))<p><strong>Care</strong> includes access to private prayer requests and Care Center records.</p>@endif</div>@endif
@if($errors->any())<p class="error">Unable to continue. Check the information and try again.</p>@endif
<form method="post" action="{{ route('invitations.establish', ['continuation' => $continuation]) }}">@csrf
@if(!$existing)<div class="field"><label for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required></div>@endif
<div class="field"><label for="password">{{ $existing ? 'Password' : 'Create a password' }}</label><input id="password" name="password" type="password" minlength="12" autocomplete="{{ $existing ? 'current-password' : 'new-password' }}" required></div>
@if(!$existing)<div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>@endif
<button type="submit">Continue securely</button></form>
@endsection
