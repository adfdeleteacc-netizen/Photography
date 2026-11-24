<?php

namespace App\Services\Tax;

use App\Models\{Booking, GstReport, KycDocument, TaxInvoice};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * India compliance:
 *  - GST invoice from the platform to the creator for the commission (18%: CGST+SGST intra-state, IGST inter-state)
 *  - TCS (s.52 CGST) withheld on net supplies; reported in GSTR-8
 *  - Credit notes on refunds
 */
class TaxService
{
    public function issueInvoiceForBooking(Booking $b): TaxInvoice
    {
        return DB::transaction(function () use ($b) {
            if ($existing = TaxInvoice::where('booking_id', $b->id)->where('is_credit_note', false)->first()) {
                return $existing;
            }
            return $this->create($b, $b->commission_paise, $b->gst_on_commission_paise, $b->tcs_paise, false);
        });
    }

    public function issueCreditNote(Booking $b, int $refundPaise): TaxInvoice
    {
        $r = fn (int $part) => intdiv($part * $refundPaise, max($b->total_amount_paise, 1));
        return $this->create($b, $r($b->commission_paise), $r($b->gst_on_commission_paise), $r($b->tcs_paise), true);
    }

    private function create(Booking $b, int $taxable, int $gst, int $tcs, bool $credit): TaxInvoice
    {
        $b->loadMissing('photographer.user');
        $creator = $b->photographer->user;
        $gstin = KycDocument::where('user_id', $creator->id)->where('type', 'gstin')->where('status', 'verified')->value('number');
        $pos = $gstin ? substr($gstin, 0, 2) : null;
        $intra = $pos && $pos === config('marketplace.platform_state_code');

        $inv = TaxInvoice::create([
            'number' => 'TMP', 'booking_id' => $b->id, 'creator_user_id' => $creator->id,
            'creator_gstin' => $gstin, 'place_of_supply' => $pos, 'taxable_value_paise' => $taxable,
            'cgst_paise' => $intra ? intdiv($gst, 2) : 0,
            'sgst_paise' => $intra ? $gst - intdiv($gst, 2) : 0,
            'igst_paise' => $intra ? 0 : $gst,
            'tcs_paise' => $tcs, 'is_credit_note' => $credit,
        ]);
        $inv->update(['number' => sprintf('%s/%s/%06d', $credit ? 'CN' : 'TI', self::financialYear(), $inv->id)]);
        return $inv;
    }

    public static function financialYear(?\Carbon\Carbon $d = null): string
    {
        $d ??= now();
        $y = $d->month >= 4 ? $d->year : $d->year - 1;
        return $y . '-' . substr((string) ($y + 1), 2);
    }

    /**
     * GSTR-8 style supplier-wise statement for a month (YYYY-MM): gross supplies, returns, net, TCS.
     * Suppliers without GSTIN are grouped as "UNREGISTERED". Review against the current GSTR-8 offline-tool
     * template with your CA before filing — field layouts change.
     */
    public function gstr8Rows(string $period): array
    {
        [$y, $m] = explode('-', $period);

        return DB::table('bookings as b')
            ->join('photographer_profiles as pp', 'pp.id', '=', 'b.photographer_profile_id')
            ->leftJoin('kyc_documents as k', fn ($j) => $j->on('k.user_id', '=', 'pp.user_id')->where('k.type', 'gstin')->where('k.status', 'verified'))
            ->leftJoin('payments as p', 'p.booking_id', '=', 'b.id')
            ->whereYear('b.confirmed_at', $y)->whereMonth('b.confirmed_at', $m)
            ->whereIn('b.status', [Booking::CONFIRMED, Booking::COMPLETED, Booking::REFUNDED, Booking::CANCELLED])
            ->groupBy('pp.user_id', 'k.number')
            ->selectRaw("COALESCE(k.number, 'UNREGISTERED') as supplier_gstin, pp.user_id as supplier_user_id,
                         SUM(b.base_amount_paise) as gross_paise,
                         SUM(COALESCE(p.refunded_paise,0)) as returns_paise,
                         SUM(b.tcs_paise) as tcs_paise")
            ->get()->map(fn ($r) => [
                'supplier_gstin' => $r->supplier_gstin,
                'gross_supplies' => $r->gross_paise / 100,
                'returns' => $r->returns_paise / 100,
                'net_supplies' => ($r->gross_paise - $r->returns_paise) / 100,
                'tcs_amount' => $r->tcs_paise / 100,
            ])->all();
    }

    public function exportGstr8Csv(string $period, ?int $adminId = null): GstReport
    {
        $rows = $this->gstr8Rows($period);
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['Supplier GSTIN', 'Gross Supplies (INR)', 'Returns (INR)', 'Net Supplies (INR)', 'TCS Amount (INR)']);
        foreach ($rows as $r) { fputcsv($csv, array_values($r)); }
        rewind($csv);

        $path = "gst-reports/gstr8-{$period}.csv";
        Storage::disk('local')->put($path, stream_get_contents($csv));

        return GstReport::updateOrCreate(['type' => 'gstr8', 'period' => $period], [
            'file_path' => $path, 'generated_by' => $adminId,
            'summary' => ['suppliers' => count($rows), 'tcs_total' => collect($rows)->sum('tcs_amount')],
        ]);
    }

    /** Platform-level tax summary used by the admin dashboard / reports. */
    public function summary(string $period): array
    {
        [$y, $m] = explode('-', $period);
        $q = TaxInvoice::whereYear('created_at', $y)->whereMonth('created_at', $m);
        $sign = fn ($col) => (clone $q)->selectRaw("COALESCE(SUM(CASE WHEN is_credit_note=1 THEN -{$col} ELSE {$col} END),0) v")->value('v') / 100;

        return [
            'period' => $period,
            'commission_taxable' => $sign('taxable_value_paise'),
            'cgst' => $sign('cgst_paise'), 'sgst' => $sign('sgst_paise'), 'igst' => $sign('igst_paise'),
            'tcs' => $sign('tcs_paise'),
        ];
    }
}
