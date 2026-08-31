<?php

namespace App\Http\Controllers;

use App\Localization\UserLocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SelectLocaleController
{
    public function __invoke(Request $request, UserLocaleResolver $locales): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys($locales->supported()))],
        ]);
        $locales->select($request->user(), $data['locale']);

        return back();
    }
}
