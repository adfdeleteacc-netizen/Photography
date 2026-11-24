<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div>
            <x-input-label for="name" value="Full name" />
            <x-text-input id="name" class="block mt-1 w-full" name="name" :value="old('name')" required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="phone" value="Mobile (optional)" />
            <x-text-input id="phone" class="block mt-1 w-full" name="phone" :value="old('phone')" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="role" value="I want to…" />
            <select id="role" name="role" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                <option value="customer" @selected(old('role') === 'customer')>Book photographers</option>
                <option value="photographer" @selected(old('role') === 'photographer')>Offer my services (photographer)</option>
                <option value="studio" @selected(old('role') === 'studio')>List my studio</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="referral_code" value="Referral code (optional)" />
            <x-text-input id="referral_code" class="block mt-1 w-full" name="referral_code" :value="old('referral_code', request('ref'))" />
            <x-input-error :messages="$errors->get('referral_code')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required />
        </div>
        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600" href="{{ route('login') }}">Already registered?</a>
            <x-primary-button class="ms-4">Register</x-primary-button>
        </div>
    </form>
</x-guest-layout>
