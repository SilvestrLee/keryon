<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shell.switch_workspace') }} - Keryon</title>
    @vite(['resources/css/app.css'])
</head>
<body class="ks-selection-page">
    <main class="ks-selection">
        <header>
            <x-keryon-logo />
            <p>{{ __('shell.workspace') }}</p>
            <h1>{{ __('shell.switch_workspace') }}</h1>
            <span>{{ __('shell.choose_workspace_help') }}</span>
        </header>
        <div class="ks-selection__groups">
            @foreach ([\App\Enums\WorkspaceType::Church, \App\Enums\WorkspaceType::Organization] as $type)
                @if (($workspaces[$type->value] ?? collect())->isNotEmpty())
                    <section>
                        <h2>{{ $type->label() }}</h2>
                        @foreach ($workspaces[$type->value] as $option)
                            <form method="POST" action="{{ route('workspace.switch', ['type' => $option->type->value, 'workspace' => $option->id]) }}">
                                @csrf
                                <button type="submit">
                                    <span><strong>{{ $option->name }}</strong><small>{{ $option->type->label() }}</small></span>
                                    <span aria-hidden="true">→</span>
                                </button>
                            </form>
                        @endforeach
                    </section>
                @endif
            @endforeach
        </div>
    </main>
</body>
</html>
