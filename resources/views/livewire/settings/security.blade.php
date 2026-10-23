@php
    $en = app()->getLocale() === 'en';
    $rule = \Geoapi\Platform\Passwords::rule(app()->getLocale());
@endphp
<div>
<div class="app">
    <x-ga.panel-side active="settings" />
    <div class="main">
        <x-ga.settings-top :title="$en ? 'Security' : 'უსაფრთხოება'" active="security" />
        <div class="content">
            <h1 class="mh">{{ $en ? 'Security' : 'უსაფრთხოება' }}</h1>
            @if (session('password_notice'))
                <div class="al al-wa" role="alert"><x-ga.icon name="warn" :size="18" /><div>{{ session('password_notice') }}</div></div>
            @endif
            @if (session('status'))
                <div class="al al-ok" role="status"><x-ga.icon name="ok" :size="18" /><div>{{ session('status') }}</div></div>
            @endif

            <div class="set-row">
                <div><h2>{{ $en ? 'Password' : 'პაროლი' }}</h2><p class="xs mut">{{ $rule }}</p></div>
                <form class="panel form-n" wire:submit="updatePassword">
                    <label>{{ $en ? 'Current password' : 'მიმდინარე პაროლი' }}
                        <input wire:model="current_password" type="password" autocomplete="current-password" required>
                    </label>
                    @error('current_password')<p class="hint err">{{ $message }}</p>@enderror
                    <label>{{ $en ? 'New password' : 'ახალი პაროლი' }}
                        <input wire:model="password" type="password" autocomplete="new-password" minlength="{{ \Geoapi\Platform\Passwords::MIN }}" maxlength="{{ \Geoapi\Platform\Passwords::MAX }}" required>
                    </label>
                    @error('password')<p class="hint err">{{ $message }}</p>@else<p class="hint">{{ $rule }}</p>@enderror
                    <label>{{ $en ? 'Repeat the new password' : 'გაიმეორეთ ახალი პაროლი' }}
                        <input wire:model="password_confirmation" type="password" autocomplete="new-password" required>
                    </label>
                    <div class="row">
                        <button class="btn pine" type="submit" wire:loading.attr="disabled" wire:target="updatePassword">{{ $en ? 'Change the password' : 'პაროლის შეცვლა' }}</button>
                    </div>
                </form>
            </div>

            @if ($twoFactorAvailable)
                <div class="set-row">
                    <div><h2>{{ $en ? 'Two-factor authentication' : 'ორსაფეხურიანი ავთენტიფიკაცია' }}</h2><p class="xs mut">{{ $en ? 'A code from an authenticator app at every sign-in.' : 'ყოველ შესვლაზე კოდი ავთენტიფიკატორის აპიდან.' }}</p></div>
                    <div class="panel form-n">
                        @if ($twoFactorEnabled)
                            <div class="row"><span class="bd bd-ok">{{ $en ? 'On' : 'ჩართულია' }}</span></div>
                            @if ($recoveryCodes !== [])
                                <div class="reveal">
                                    <b>{{ $en ? 'Recovery codes' : 'აღდგენის კოდები' }}</b>
                                    <p class="xs">{{ $en ? 'Keep them somewhere safe. Each code works once.' : 'შეინახეთ უსაფრთხო ადგილას. თითო კოდი ერთხელ მუშაობს.' }}</p>
                                    <ul class="codes mono">@foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul>
                                </div>
                            @endif
                            <form class="form-n" wire:submit="disableTwoFactor">
                                <label>{{ $en ? 'Current password' : 'მიმდინარე პაროლი' }}
                                    <input wire:model="current_password" type="password" autocomplete="current-password" required>
                                </label>
                                <div class="row"><button class="btn danger" type="submit">{{ $en ? 'Turn off' : 'გამორთვა' }}</button></div>
                            </form>
                        @elseif ($showSetup && $setupSecret)
                            <p class="muted">{{ $en ? 'Scan the code with an authenticator app, then enter the 6-digit code it shows.' : 'დაასკანერეთ კოდი ავთენტიფიკატორის აპით და შეიყვანეთ მის მიერ ნაჩვენები 6-ნიშნა კოდი.' }}</p>
                            @if ($qrInline)
                                <div class="qr" aria-hidden="true">{!! $qrInline !!}</div>
                            @endif
                            <p class="xs mut">{{ $en ? 'Manual key:' : 'ხელით შესაყვანი გასაღები:' }} <code class="mono">{{ $setupSecret }}</code></p>
                            <form class="form-n" wire:submit="confirmTwoFactorSetup">
                                <label>{{ $en ? 'Code' : 'კოდი' }}
                                    <input wire:model="confirmationCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
                                </label>
                                @error('confirmationCode')<p class="hint err">{{ $message }}</p>@enderror
                                <div class="row"><button class="btn pine" type="submit">{{ $en ? 'Confirm and turn on' : 'დადასტურება და ჩართვა' }}</button></div>
                            </form>
                        @else
                            <div class="row"><span class="bd bd-out">{{ $en ? 'Off' : 'გამორთულია' }}</span></div>
                            <div class="row"><button class="btn" type="button" wire:click="beginTwoFactorSetup">{{ $en ? 'Turn on' : 'ჩართვა' }}</button></div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($passkeysEnabled)
                <div class="set-row">
                    <div><h2>{{ $en ? 'Passkeys' : 'Passkey' }}</h2><p class="xs mut">{{ $en ? 'Sign in with Face ID, Touch ID or a security key.' : 'შესვლა Face ID-ით, Touch ID-ით ან უსაფრთხოების გასაღებით.' }}</p></div>
                    <div class="panel form-n" x-data="{
                        busy: false,
                        error: null,
                        async register() {
                            if (this.busy) return;
                            this.error = null;
                            this.busy = true;
                            try {
                                if (! window.ElectrikPasskeys) {
                                    throw new Error('Passkey script failed to load. Refresh and try again.');
                                }
                                await window.ElectrikPasskeys.register({
                                    name: ($wire.passkeyName || '').trim() || 'Passkey',
                                });
                                await $wire.passkeyRegistered();
                            } catch (e) {
                                this.error = window.ElectrikPasskeys?.friendlyError?.(e, 'Unable to register passkey.')
                                    || 'Unable to register passkey.';
                            } finally {
                                this.busy = false;
                            }
                        }
                    }">
                        @forelse ($passkeys as $passkey)
                            <div class="row set-line" wire:key="passkey-{{ $passkey->getKey() }}">
                                <div><b>{{ $passkey->name }}</b><p class="xs mut">{{ $en ? 'Added' : 'დამატებულია' }} {{ optional($passkey->created_at)->format('Y-m-d') }}@if ($passkey->last_used_at) · {{ $en ? 'last used' : 'ბოლოს' }} {{ $passkey->last_used_at->format('Y-m-d') }}@endif</p></div>
                                <button class="lnk-er" type="button" wire:click="deletePasskey('{{ $passkey->getKey() }}')" wire:confirm="{{ $en ? 'Remove this passkey?' : 'წავშალოთ ეს passkey?' }}">{{ $en ? 'Remove' : 'წაშლა' }}</button>
                            </div>
                        @empty
                            <p class="muted">{{ $en ? 'No passkeys yet.' : 'Passkey ჯერ არ გაქვთ.' }}</p>
                        @endforelse
                        <label>{{ $en ? 'Device name' : 'მოწყობილობის სახელი' }}
                            <input wire:model="passkeyName" placeholder="MacBook">
                        </label>
                        <p class="hint err" x-show="error" x-cloak x-text="error"></p>
                        <div class="row"><button class="btn" type="button" x-on:click="register()" x-bind:disabled="busy">{{ $en ? 'Add a passkey' : 'Passkey-ის დამატება' }}</button></div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@if ($passkeysEnabled)
    @include('electrik::partials.passkeys-script')
@endif
</div>
