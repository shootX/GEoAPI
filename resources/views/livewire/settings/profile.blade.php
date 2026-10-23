@php
    $en = app()->getLocale() === 'en';
    $me = auth()->user();
    $verified = $me->email_verified_at !== null;
@endphp
<div>
<div class="app">
    <x-ga.panel-side active="settings" />
    <div class="main">
        <x-ga.settings-top :title="$en ? 'Profile' : 'პროფილი'" active="profile" />
        <div class="content">
            <h1 class="mh">{{ $en ? 'Profile' : 'პროფილი' }}</h1>
            @if (session('status'))
                <div class="al al-ok" role="status"><x-ga.icon name="ok" :size="18" /><div>{{ session('status') }}</div></div>
            @endif

            <div class="set-row">
                <div><h2>{{ $en ? 'Personal details' : 'პირადი მონაცემები' }}</h2><p class="xs mut">{{ $en ? 'Your name and the email you sign in with.' : 'თქვენი სახელი და ელფოსტა, რომლითაც შედიხართ.' }}</p></div>
                <form class="panel form-n" wire:submit="save">
                    <label>{{ $en ? 'Name' : 'სახელი' }}
                        <input wire:model="name" type="text" autocomplete="name" maxlength="255" required>
                    </label>
                    @error('name')<p class="hint err">{{ $message }}</p>@enderror
                    <label><span class="lrow"><span>{{ $en ? 'Email' : 'ელფოსტა' }} @if ($verified)<span class="bd bd-ok">{{ $en ? 'verified' : 'დადასტურებული' }}</span>@else<span class="bd bd-wa">{{ $en ? 'not verified' : 'დაუდასტურებელი' }}</span>@endif</span></span>
                        <input wire:model="email" type="email" autocomplete="email" maxlength="255" required>
                    </label>
                    @error('email')<p class="hint err">{{ $message }}</p>@else<p class="hint">{{ $en ? 'If you change it, we send a confirmation link to the new address.' : 'თუ შეცვლით, ახალ მისამართზე დადასტურების ბმულს გამოგიგზავნით.' }}</p>@enderror
                    <div class="row">
                        <button class="btn pine" type="submit" wire:loading.attr="disabled" wire:target="save">{{ $en ? 'Save' : 'შენახვა' }}</button>
                    </div>
                </form>
            </div>

            <div class="set-row">
                <div><h2>{{ $en ? 'Password and security' : 'პაროლი და უსაფრთხოება' }}</h2><p class="xs mut">{{ $en ? 'Password, two-factor authentication, passkeys.' : 'პაროლი, ორსაფეხურიანი ავთენტიფიკაცია, passkey.' }}</p></div>
                <div class="s-a562f4 panel row"><div class="row"><span class="ic"><x-ga.icon name="lock" :size="18" /></span><div><b>{{ $en ? 'Change the password' : 'პაროლის შეცვლა' }}</b><p class="xs mut">{{ \Carapi\Platform\Passwords::rule(app()->getLocale()) }}</p></div></div>
                    <a class="edit" href="{{ route('settings.security') }}">{{ $en ? 'Open' : 'გახსნა' }}</a></div>
            </div>

            <div class="set-row">
                <div><h2>{{ $en ? 'Your data' : 'თქვენი მონაცემები' }}</h2><p class="xs mut">{{ $en ? 'Export a copy or delete the account.' : 'ასლის ჩამოტვირთვა ან ანგარიშის წაშლა.' }}</p></div>
                <div class="panel form-n">
                    @if ($personalDataExportEnabled)
                        <div class="row set-line">
                            <p class="muted">{{ $en ? 'We build the archive in the background and email you a download link.' : 'არქივს ფონურად მოვამზადებთ და ჩამოტვირთვის ბმულს ელფოსტით გამოგიგზავნით.' }}</p>
                            <button class="btn" type="button" wire:click="requestDataExport" wire:loading.attr="disabled" wire:target="requestDataExport">{{ $en ? 'Email me my data' : 'მონაცემების გამოგზავნა' }}</button>
                        </div>
                    @endif
                    <form class="form-n" wire:submit="deleteAccount" wire:confirm="{{ $en ? 'Delete your account permanently? This cannot be undone.' : 'წავშალოთ ანგარიში სამუდამოდ? ამის გაუქმება შეუძლებელია.' }}">
                        <p class="muted">{{ $en ? 'Deleting the account is permanent. It is not possible while you own a team with other members.' : 'ანგარიშის წაშლა საბოლოოა. ეს შეუძლებელია, სანამ ფლობთ გუნდს, სადაც სხვა წევრებიც არიან.' }}</p>
                        <label>{{ $en ? 'Password' : 'პაროლი' }}
                            <input wire:model="delete_password" type="password" autocomplete="current-password" required>
                        </label>
                        @error('delete_password')<p class="hint err">{{ $message }}</p>@enderror
                        <div class="row"><button class="btn danger" type="submit">{{ $en ? 'Delete account' : 'ანგარიშის წაშლა' }}</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
