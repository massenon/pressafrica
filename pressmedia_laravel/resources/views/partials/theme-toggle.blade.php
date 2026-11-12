@php($dark = request()->cookie('theme') === 'dark')
<button type="button" data-theme-toggle aria-pressed="{{ $dark ? 'true' : 'false' }}" aria-label="{{ __('public.theme_dark') }}" class="rounded border px-2 py-1 text-sm">◐</button>
