# AAC PMPro Member Import Instructions

This document explains how to import AAC members from an external system into WordPress/Paid Memberships Pro while preserving existing Stripe subscriptions, mapping Stripe products to PMPro membership levels, handling family plans, and separating membership, discount, and donation values for reporting or Salesforce sync.

## Core Rule

PMPro does not automatically convert a Stripe Product or Stripe Price into a PMPro Membership Level during import.

You must map each Stripe product or price to the correct PMPro membership level before import.

Stripe remains the billing system. PMPro remains the membership/access system.

## PMPro And Stripe Responsibilities

Stripe handles:

- Customer record
- Payment method
- Subscription
- Renewal schedule
- Payment attempts
- Charges, invoices, failed payments, and cancellations

PMPro handles:

- WordPress user
- Membership level
- Membership status
- Member access
- Group/family relationship
- PMPro order history
- Member-facing account/profile data

For imported members, the goal is to attach PMPro to the existing Stripe subscription, not create a new Stripe subscription.

## Required Import Fields For Existing Stripe Subscriptions

For each paying member account, include:

```csv
user_email
user_login
first_name
last_name
membership_id
membership_status
membership_gateway
membership_subscription_transaction_id
membership_payment_transaction_id
pmpro_stripe_customerid
membership_billing_amount
membership_cycle_number
membership_cycle_period
```

Recommended values:

```csv
membership_status = active
membership_gateway = stripe
membership_subscription_transaction_id = sub_...
membership_payment_transaction_id = pi_... or ch_...
pmpro_stripe_customerid = cus_...
membership_cycle_number = 1
membership_cycle_period = Year
```

`membership_subscription_transaction_id` should be the existing Stripe subscription ID, usually beginning with `sub_`.

`pmpro_stripe_customerid` should be the existing Stripe customer ID, usually beginning with `cus_`.

Do not create a new Stripe subscription in PMPro for these imported members.

## Membership Level Mapping

Before import, create a mapping table:

```csv
stripe_product_or_price_id,pmpro_membership_id,pmpro_membership_name,type
price_supporter,2,Supporter,membership
price_partner,3,Partner,membership
price_leader,4,Leader,membership
price_advocate,5,Advocate,membership
```

The import file should use the PMPro numeric level ID:

```csv
membership_id = 3
```

Do not rely on the Stripe Product name alone. PMPro needs the level ID.

## Standard Member Import Example

```csv
user_email,user_login,first_name,last_name,membership_id,membership_status,membership_gateway,membership_subscription_transaction_id,membership_payment_transaction_id,pmpro_stripe_customerid,membership_billing_amount,membership_cycle_number,membership_cycle_period
jane@example.org,jane.calder,Jane,Calder,3,active,stripe,sub_123,pi_456,cus_789,100,1,Year
```

## Donations During Import

Donations should be treated separately from the PMPro membership level when possible.

If the existing Stripe subscription includes both membership and donation, PMPro may see only the total recurring subscription amount unless you store the donation amount separately.

Recommended additional fields:

```csv
aac_membership_base_amount
aac_recurring_donation_amount
aac_recurring_donation_stripe_price_id
aac_total_recurring_amount
```

Example:

```csv
user_email,membership_id,membership_billing_amount,aac_membership_base_amount,aac_recurring_donation_amount,aac_total_recurring_amount
jane@example.org,3,125,100,25,125
```

In this example:

- PMPro access level is Partner.
- Stripe continues billing `$125`.
- Reporting/Salesforce can separately store:
  - Membership amount: `$100`
  - Donation amount: `$25`
  - Total recurring amount: `$125`

## Donation Options

### Option 1: Import Combined Billing Amount

Use this if you only care that Stripe and PMPro stay aligned:

```csv
membership_billing_amount = 125
```

Downside: PMPro may not know how much of the amount is donation unless you store custom metadata.

### Option 2: Store Donation Separately In WordPress

Use custom fields:

```csv
aac_membership_base_amount = 100
aac_recurring_donation_amount = 25
aac_total_recurring_amount = 125
```

This is better for member profile display, transaction registers, reporting, and Salesforce sync.

### Option 3: Custom Stripe-To-PMPro Importer

Best for a large migration. The importer can:

1. Read the Stripe subscription.
2. Identify the membership product/price.
3. Map the membership product/price to PMPro `membership_id`.
4. Identify donation products/prices.
5. Store donation amount separately.
6. Attach PMPro to the existing Stripe subscription.
7. Send structured values to Salesforce.

## Family Plans

Family plans should be imported as one paid parent/adult account plus attached family member accounts.

The parent/adult account holds:

- PMPro paid membership level
- Stripe customer ID
- Stripe subscription ID
- Total recurring billing amount
- Family configuration

Child/dependent/additional adult accounts generally should not have their own Stripe subscriptions.

They should inherit access through the parent family/group account.

## AAC Family Stripe Product Mapping

Current AAC family products:

| Stripe Product / Level Plan | Description | Price | PMPro Parent Level | Additional Adult | Dependents |
|---|---:|---:|---:|---:|---:|
| `partner-mem-fam-2a-1` | 2 adults | `$180` | Partner | 1 | 0 |
| `partner-mem-fam-2a-1d-1` | 2 adults, 1 dependent | `$225` | Partner | 1 | 1 |
| `partner-mem-fam-2a-2d-1` | 2 adults, 2 dependents | `$270` | Partner | 1 | 2 |
| `partner-mem-fam-1a-1d-1` | 1 adult, 1 dependent | `$145` | Partner | 0 | 1 |
| `partner-mem-fam-1a-2d-1` | 1 adult, 2 dependents | `$190` | Partner | 0 | 2 |

This matches the current pricing model:

```text
Base Partner = $100
Additional adult = +$80
Each dependent = +$45
```

## Family Mapping Table

Use this mapping during import:

```csv
stripe_product_key,pmpro_level_id,billing_amount,additional_adult,dependents,group_child_seats
partner-mem-fam-2a-1,3,180,1,0,1
partner-mem-fam-2a-1d-1,3,225,1,1,2
partner-mem-fam-2a-2d-1,3,270,1,2,3
partner-mem-fam-1a-1d-1,3,145,0,1,1
partner-mem-fam-1a-2d-1,3,190,0,2,2
```

Adjust `pmpro_level_id` if the Partner level ID differs on the target site.

## Family Parent Import Example

Example for `partner-mem-fam-2a-1d-1`:

```csv
user_email,user_login,first_name,last_name,membership_id,membership_status,membership_gateway,membership_subscription_transaction_id,membership_payment_transaction_id,pmpro_stripe_customerid,membership_billing_amount,membership_cycle_number,membership_cycle_period,aac_partner_family_mode,aac_partner_family_additional_adult,aac_partner_family_dependents
parent@example.org,jane.calder,Jane,Calder,3,active,stripe,sub_123,pi_456,cus_789,225,1,Year,family,1,1
```

Meaning:

- Parent has Partner membership.
- Stripe keeps billing existing subscription `sub_123`.
- Total recurring amount is `$225`.
- Family config includes one additional adult and one dependent.

## Family Child/Dependent Import

After importing the parent account, attach child accounts to the family/group account.

Recommended child/dependent fields:

```csv
user_email
user_login
first_name
last_name
membership_status
linked_parent_user_id
family_role
```

Example:

```csv
user_email,user_login,first_name,last_name,membership_status,linked_parent_user_id,family_role
dependent@example.org,dependent.calder,Alex,Calder,active,12345,Dependent
adult@example.org,adult.calder,Sam,Calder,active,12345,Additional Adult
```

If using PMPro Group Accounts, use the Group Accounts identifiers required by that plugin, such as the parent group ID or invite/redeem workflow.

## Group Accounts Import Sequence

Use this order:

1. Import parent/adult payer accounts first.
2. Confirm PMPro created or synced a family/group account for each parent.
3. Export or identify the parent group ID.
4. Import child/dependent/additional adult accounts.
5. Attach child accounts to the correct parent group.
6. Confirm child accounts do not have independent Stripe subscriptions unless intentionally billed separately.

## Stripe Subscription Continuity

Stripe subscriptions should continue without stopping if:

- Existing Stripe `sub_...` subscription IDs are preserved.
- Existing Stripe `cus_...` customer IDs are preserved.
- PMPro `membership_gateway` is set to `stripe`.
- PMPro is not instructed to create a new subscription.
- Stripe webhooks are configured and working.
- Imported billing amount matches the existing Stripe subscription amount.

Do not cancel and recreate Stripe subscriptions as part of the import unless there is a specific migration reason.

## Discounts During Import

PMPro discount codes should be stored as PMPro discount usage where possible, but Stripe may only know the final subscription amount unless native Stripe coupons/promotion codes or metadata are used.

For reporting/Salesforce, store these as separate values:

```csv
aac_discount_code
aac_discount_label
aac_discount_amount
aac_membership_base_amount
aac_membership_net_amount
```

Example:

```csv
user_email,membership_id,membership_billing_amount,aac_membership_base_amount,aac_discount_code,aac_discount_label,aac_discount_amount,aac_membership_net_amount
student@example.org,3,65,100,STUDENT,Student Discount,35,65
```

For AAC checkout, Student and Military are currently intended to use PMPro discount codes:

```text
STUDENT
USMILITARY
```

## Salesforce Sync Recommendations

Send structured fields from WordPress/PMPro to Salesforce instead of trying to reconstruct values from Stripe alone.

Recommended Salesforce fields:

```text
WordPress User ID
PMPro Member ID
PMPro Order ID
Stripe Customer ID
Stripe Subscription ID
Membership Level
Membership Base Amount
Discount Code Used
Discount Label
Discount Amount
Donation Amount
Final Total Charged
Recurring Billing Amount
Family Plan Type
Additional Adult Count
Dependent Count
Parent/Child Account Relationship
```

Best source of truth:

- PMPro/WordPress for member identity, level, access, discounts, donation split, and family configuration.
- Stripe for payment status, customer, subscription, and charge data.

## Import Checklist

Before import:

- Confirm PMPro level IDs on the target site.
- Confirm Stripe product/price IDs.
- Build Stripe-to-PMPro mapping table.
- Decide how donations will be stored.
- Decide how family/group accounts will be attached.
- Confirm Stripe webhooks are working.
- Back up WordPress database.
- Test with a small sample first.

During import:

- Import parent/payer accounts first.
- Include Stripe customer and subscription IDs.
- Include PMPro membership ID.
- Include recurring billing amount.
- Include donation and discount metadata if applicable.
- Import or attach child/dependent accounts after parents.

After import:

- Verify active membership status in PMPro.
- Verify member profile display.
- Verify Stripe subscription remains active.
- Verify PMPro order/subscription references are connected.
- Verify child accounts inherit access.
- Verify Salesforce receives separate membership, discount, and donation values.

## Key Principle

Stripe product decides the imported billing/configuration.

PMPro membership level grants access.

WordPress custom/member metadata stores the extra reporting details.

Salesforce receives the structured breakdown.
