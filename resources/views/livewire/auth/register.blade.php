@php $en = app()->getLocale() === 'en'; @endphp
<div>
    <h1>{{ __('Create an account') }}</h1>
    <p class="s-96b813 mut">{{ $en ? 'Free plan · '.(\App\Billing\V2\Flag::on() ? '1 000' : '100').' successful requests a month' : 'Free გეგმა · '.(\App\Billing\V2\Flag::on() ? '1 000' : '100').' წარმატებული მოთხოვნა თვეში' }}</p>
    <form wire:submit="register" class="s-f6ce4d col">
        <div class="fld">
            <span class="s-c4f362">{{ __('Account type') }}</span>
            <div class="seg-r" role="radiogroup">
                <label><input type="radio" wire:model.live="buyer_kind" value="individual"> {{ __('Individual') }}</label>
                <label><input type="radio" wire:model.live="buyer_kind" value="legal"> {{ __('Legal entity') }}</label>
            </div>
        </div>
        <div class="fld">
            <label for="reg-name">{{ __('Name and surname') }}</label>
            <input id="reg-name" class="inp @error('name') err @enderror" wire:model="name" type="text" autocomplete="name" required>
            @error('name')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
        </div>
        @if ($buyer_kind === 'legal')
            <div class="s-939272 col">
                <div class="s-02a2d3 xs mut">{{ $en ? 'Company details · for invoices' : 'კომპანიის მონაცემები · ინვოისისთვის' }}</div>
                <div class="fld">
                    <label for="reg-company">{{ __('Company name') }}</label>
                    <input id="reg-company" class="inp @error('company_name') err @enderror" wire:model="company_name" type="text" required>
                    @error('company_name')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
                </div>
                <div class="fld">
                    <label for="reg-code">{{ __('Identification code') }}</label>
                    <input id="reg-code" class="inp mono @error('company_code') err @enderror" wire:model="company_code" type="text" inputmode="numeric" required>
                    @error('company_code')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $en ? 'The code must be 9 or 11 digits' : 'კოდი უნდა იყოს 9 ან 11 ციფრი' }}</span>@else<span class="hint">{{ $en ? '9 or 11 digits' : '9 ან 11 ციფრი' }}</span>@enderror
                </div>
                <div class="fld">
                    <label for="reg-address">{{ __('Legal address') }}</label>
                    <input id="reg-address" class="inp @error('company_address') err @enderror" wire:model="company_address" type="text" placeholder="{{ $en ? 'City, street' : 'ქალაქი, ქუჩა' }}" required>
                    @error('company_address')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
                </div>
                <label class="chk-l"><input type="checkbox" wire:model="vat_payer" value="1"> {{ __('VAT payer') }}</label>
            </div>
        @endif
        <div class="fld">
            <label for="reg-email">{{ __('Email') }}</label>
            <input id="reg-email" class="inp @error('email') err @enderror" wire:model="email" type="email" autocomplete="username" required @if ($inviteLockedEmail) readonly @endif>
            @error('email')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
        </div>
        <div class="fld">
            <label for="reg-password">{{ __('Password') }}</label>
            <input id="reg-password" class="inp @error('password') err @enderror" wire:model="password" type="password" autocomplete="new-password" minlength="8" required>
            @error('password')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@else<span class="hint">{{ \Carapi\Platform\Passwords::rule(app()->getLocale()) }}</span>@enderror
        </div>
        <div class="fld">
            <label for="reg-password2">{{ __('Repeat the password') }}</label>
            <input id="reg-password2" class="inp" wire:model="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <label class="chk-l"><input type="checkbox" wire:model="accept_terms" value="1"><span>{{ __('I agree to the') }} <a href="{{ url('/terms') }}" class="lnk">{{ __('terms of use') }}</a> · <a href="{{ url('/privacy') }}" class="lnk">{{ $en ? 'privacy policy' : 'კონფიდენციალურობის პოლიტიკა' }}</a>.</span></label>
        @error('accept_terms')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ __('You must accept the terms.') }}</span>@enderror
        <button type="submit" class="s-e6a3b7 btn b-pri b-lg">{{ __('Create an account') }}</button>
    </form>
    <p class="s-c7b101 sm mut">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="lnk">{{ __('Sign in') }}</a></p>
</div>
