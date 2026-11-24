@extends('layouts.app')
@section('content')
<main class="grid min-h-screen lg:grid-cols-2">
    <section class="hidden flex-col justify-between bg-[var(--color-primary)] p-12 text-white lg:flex">
        <span class="text-xl font-bold tracking-widest">EduGest</span>
        <div><div class="mb-6 h-1 w-16 bg-[var(--color-secondary)]"></div><h1 class="max-w-lg text-4xl font-semibold leading-tight">O conhecimento começa com uma boa gestão.</h1><p class="mt-6 max-w-md text-lg text-white/80">Um espaço para ligar a instituição, os docentes e os estudantes.</p></div>
        <p class="text-sm text-white/70">EduGest — Sistema de Gestão Académica</p>
    </section>
    <section class="flex items-center justify-center px-5 py-12 sm:px-12">
        <div class="w-full max-w-md">
            @if(data_get($currentTenant?->settings, 'logo_path'))
                <img src="{{ route('tenant.logo') }}" alt="Logótipo institucional" class="mb-6 h-20 max-w-full object-contain">
            @else
                <div aria-hidden="true" class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-[var(--color-primary)] text-2xl font-bold text-white">{{ mb_substr($currentTenant?->name ?? 'EduGest', 0, 1) }}</div>
            @endif
            <p class="mb-2 text-sm font-semibold text-slate-600">{{ $currentTenant?->name ?? 'EduGest · Administração central' }}</p>
            <h2 class="text-3xl font-bold">Bem-vindo de volta</h2><p class="mt-3 text-slate-600">Introduza os seus dados para aceder ao portal.</p>
            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf
                <div><label for="identity" class="mb-2 block text-sm font-medium">E-mail, código de utilizador ou telemóvel</label>
                <input id="identity" name="identity" type="text" value="{{ old('identity') }}" required autofocus autocomplete="username" maxlength="255" aria-describedby="identity-error" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 focus:outline-2 focus:outline-[var(--color-primary)]">
                @error('identity')<p id="identity-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="password" class="mb-2 block text-sm font-medium">Palavra-passe</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 focus:outline-2 focus:outline-[var(--color-primary)]">
                @error('password')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <button class="w-full rounded-xl bg-[var(--color-primary)] px-5 py-3 font-semibold text-white focus:outline-2 focus:outline-offset-4 focus:outline-[var(--color-primary)]">Entrar no portal</button>
            </form>
            <p class="mt-8 text-center text-xs text-slate-500">Acesso reservado aos utilizadores desta instituição.</p>
        </div>
    </section>
</main>
@endsection
