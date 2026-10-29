@extends('admin.layout')
@section('title', 'Settings')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Settings</h1>
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
@if ($errors->any())<p class="mb-3 rounded bg-red-50 p-2 text-sm text-red-800" role="alert">{{ $errors->first() }}</p>@endif
@php($locked = ! auth()->user()->can('settings.edit'))
<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf @method('PUT')
    @foreach ($sections as $section => $fields)
        <fieldset class="rounded border bg-white p-4 dark:bg-slate-800" @disabled($locked)>
            <legend class="px-1 font-semibold capitalize">{{ $section }}</legend>
            @foreach ($fields as $key => [, $type])
                <div class="mb-3">
                    @if ($type === 'bool')
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $values[$key]))> {{ $key }}</label>
                    @else
                        <label class="mb-1 block text-sm font-medium" for="{{ $key }}">{{ $key }}</label>
                        @if ($type === 'text')
                            <textarea class="w-full rounded border px-2 py-1" id="{{ $key }}" name="{{ $key }}" rows="2">{{ old($key, $values[$key]) }}</textarea>
                        @elseif (str_starts_with($type, 'enum:'))
                            <select class="rounded border px-2 py-1" id="{{ $key }}" name="{{ $key }}">
                                @foreach (explode(',', substr($type, 5)) as $opt)<option value="{{ $opt }}" @selected(old($key, $values[$key]) === $opt)>{{ $opt }}</option>@endforeach
                            </select>
                        @elseif ($type === 'secret')
                            <input class="w-full rounded border px-2 py-1" id="{{ $key }}" name="{{ $key }}" type="password" autocomplete="off" placeholder="{{ $values[$key] !== '' ? '•••••••• (stored, leave blank to keep)' : 'Not set' }}">
                            @if ($values[$key] !== '')<label class="mt-1 flex items-center gap-2 text-xs"><input type="checkbox" name="{{ $key }}_clear" value="1"> Remove stored key</label>@endif
                        @else
                            <input class="w-full rounded border px-2 py-1" id="{{ $key }}" name="{{ $key }}" type="{{ $type === 'int' ? 'number' : ($type === 'color' ? 'color' : 'text') }}" value="{{ old($key, $values[$key]) }}">
                        @endif
                    @endif
                </div>
            @endforeach
        </fieldset>
    @endforeach
    @unless ($locked)<button class="rounded bg-slate-900 px-4 py-2 font-semibold text-white" type="submit">Save settings</button>@endunless
</form>
@endsection
