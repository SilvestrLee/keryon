<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceType;
use App\Workspace\WorkspaceRegistry;
use Illuminate\Contracts\View\View;

class WorkspaceSelectionController
{
    public function __invoke(WorkspaceRegistry $workspaces): View
    {
        return view('workspace-selection', [
            'workspaces' => $workspaces->for(auth()->user(), WorkspaceType::Church)->groupBy(fn ($option) => $option->type->value),
        ]);
    }
}
