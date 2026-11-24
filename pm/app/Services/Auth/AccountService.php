<?php

namespace App\Services\Auth;

use App\Models\{BankAccount, KycDocument, Referral, User};
use App\Services\Coupon\LoyaltyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountService
{
    public function __construct(private LoyaltyService $loyalty) {}

    public function register(array $d): User
    {
        return DB::transaction(function () use ($d) {
            $referrer = ! empty($d['referral_code']) ? User::where('referral_code', $d['referral_code'])->first() : null;

            $user = User::create([
                'name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'] ?? null,
                'password' => $d['password'], 'referral_code' => $this->uniqueReferralCode(),
                'referred_by' => $referrer?->id,
            ]);
            $user->assignRole($d['role']);
            $this->loyalty->wallet($user->id);

            if ($referrer) {
                Referral::create(['referrer_id' => $referrer->id, 'referred_id' => $user->id]);
            }
            $user->sendEmailVerificationNotification();
            return $user;
        });
    }

    /** Reward the referrer the first time the referred user completes a booking (or subscribes, for creators). */
    public function rewardReferralIfFirst(User $referred): void
    {
        DB::transaction(function () use ($referred) {
            $r = Referral::where('referred_id', $referred->id)->where('status', 'pending')->lockForUpdate()->first();
            if (! $r) { return; }
            $pts = config('marketplace.loyalty.referral_reward_points');
            $this->loyalty->earn($r->referrer_id, $pts, 'referral', null, "Referral: {$referred->name}");
            $r->update(['status' => 'rewarded', 'reward_points' => $pts, 'rewarded_at' => now()]);
        });
    }

    public function submitKyc(User $user, array $d, UploadedFile $panFile, UploadedFile $idFile): void
    {
        DB::transaction(function () use ($user, $d, $panFile, $idFile) {
            // KYC files are private; random names, never the client filename.
            $store = fn (UploadedFile $f) => $f->storeAs("kyc/{$user->id}", Str::uuid() . '.' . $f->extension(), 'local');

            $user->kycDocuments()->whereIn('type', ['pan', 'identity', 'gstin'])->where('status', '!=', 'verified')->delete();
            KycDocument::create(['user_id' => $user->id, 'type' => 'pan', 'number' => strtoupper($d['pan']), 'file_path' => $store($panFile)]);
            KycDocument::create(['user_id' => $user->id, 'type' => 'identity', 'file_path' => $store($idFile)]);
            if (! empty($d['gstin'])) {
                KycDocument::create(['user_id' => $user->id, 'type' => 'gstin', 'number' => strtoupper($d['gstin'])]);
            }
            $user->bankAccounts()->where('status', '!=', 'verified')->delete();
            BankAccount::create([
                'user_id' => $user->id, 'account_holder' => $d['bank']['account_holder'],
                'account_number' => $d['bank']['account_number'], 'ifsc' => strtoupper($d['bank']['ifsc']),
            ]);
        });
    }

    public function reviewKyc(KycDocument $doc, User $admin, bool $approve, ?string $reason = null): KycDocument
    {
        $doc->update([
            'status' => $approve ? 'verified' : 'rejected', 'rejection_reason' => $approve ? null : $reason,
            'reviewed_by' => $admin->id, 'reviewed_at' => now(),
        ]);
        // Bank accounts are verified together with the PAN (replace with penny-drop verification in production).
        if ($doc->type === 'pan') {
            $doc->user->bankAccounts()->where('status', 'pending')->update(['status' => $approve ? 'verified' : 'rejected']);
            $doc->user->photographerProfile?->update(['is_verified' => $approve]);
            $doc->user->studio?->update(['is_verified' => $approve]);
        }
        return $doc;
    }

    private function uniqueReferralCode(): string
    {
        do { $c = strtoupper(Str::random(8)); } while (User::where('referral_code', $c)->exists());
        return $c;
    }
}
