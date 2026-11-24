import random
from decimal import Decimal, ROUND_HALF_UP

def php_round(x):  # PHP round() = half away from zero
    return int(Decimal(str(x)).quantize(Decimal('1'), rounding=ROUND_HALF_UP))

def breakdown(base, coupon=0, loyalty=0, pct=20.0, gst_pct=18, tcs_pct=0.5):
    discount = min(base, coupon + loyalty)
    commission = php_round(base * pct / 100)
    gst = php_round(commission * gst_pct / 100)
    tcs = php_round(base * tcs_pct / 100)
    return dict(base=base, total=base - discount, commission=commission, gst=gst, tcs=tcs,
                creator_share=base - commission, creator_net=base - commission - gst - tcs,
                discount=coupon + loyalty)

def capture_rows(b):
    return [('gateway_receivable','debit',b['total']), ('promo_expense','debit',b['discount']),
            ('creator','credit',b['creator_net']), ('platform_revenue','credit',b['commission']),
            ('gst_payable','credit',b['gst']), ('tcs_payable','credit',b['tcs'])]

def refund_rows(b, R):
    ratio = lambda part: (part * R) // max(b['total'], 1)
    baseR, comR, gstR, tcsR = ratio(b['base']), ratio(b['commission']), ratio(b['gst']), ratio(b['tcs'])
    creatorR = baseR - comR - gstR - tcsR
    discountR = baseR - R
    return [('creator','debit',creatorR), ('platform_revenue','debit',comR), ('gst_payable','debit',gstR),
            ('tcs_payable','debit',tcsR), ('promo_expense','credit',max(discountR,0)), ('gateway_receivable','credit',R)]

# 1. Spec example
b = breakdown(1_000_000)
print("Spec example (₹10,000):", {k: v/100 for k,v in b.items()})
assert b['commission']==200000 and b['gst']==36000 and b['tcs']==5000 and b['creator_share']==800000

# 2. Fuzz: capture + partial refunds always balance, never negative
random.seed(7); bad=0; neg=0
for _ in range(200000):
    base = random.randint(100, 20_000_000)
    coupon = random.choice([0, random.randint(0, base//2)])
    loyalty = random.choice([0, random.randint(0, max(base//5,0))])
    b = breakdown(base, coupon, loyalty, pct=random.choice([5,10,12.5,15,20,25]))
    rows = capture_rows(b)
    d = sum(r[2] for r in rows if r[1]=='debit'); c = sum(r[2] for r in rows if r[1]=='credit')
    if d != c: bad += 1
    R = random.randint(1, b['total'])
    rr = refund_rows(b, R)
    d = sum(r[2] for r in rr if r[1]=='debit'); c = sum(r[2] for r in rr if r[1]=='credit')
    if d != c: bad += 1
    if any(r[2] < 0 for r in rr): neg += 1
print("unbalanced entries:", bad, "| negative refund legs:", neg)
