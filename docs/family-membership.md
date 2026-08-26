# Partner Family Membership Notes

## Purpose

The Partner-family flow extends the standard Partner membership with household pricing and linked family-member records.

## Pricing

- base membership: Partner / Partner Family base price
- additional adult: 40% off Partner list price
- dependents: `$45/year` each
- dependent limit: `3`

The pricing is applied to both:

- `initial_payment`
- `billing_amount`

through the PMPro checkout-level filter in:

- [`wordpress/aac-member-portal/aac-member-portal.php`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/aac-member-portal.php)

## Checkout Inputs

The checkout adds a `Partner Family Membership` section with:

- `Individual membership`
- `Family membership`
- `Additional adult`
- `Dependents` select

These values are normalized on the backend and not trusted directly as raw request data.

## Storage

### Primary config

- `aac_partner_family_config`

Shape:

```php
[
  'mode' => '' | 'family',
  'additional_adult' => bool,
  'dependent_count' => 0..3,
]
```

### Connected account slots

- `aac_connected_accounts`

Each slot stores:

- `id`
- `type`
- `label`
- `status`
- `invite_code`
- `child_user_id`
- `child_name`
- `child_email`
- `price`

## Invite codes

Invite codes are generated in the format:

- `AACF-XXXXXXXX`

These are designed to support a later child-account redemption workflow.

## Profile display

The member profile shows:

- family plan enabled/disabled
- additional adult status
- dependent count
- per-slot invite codes
- pending vs connected status

Frontend location:

- [`src/pages/MemberProfilePage.jsx`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src/pages/MemberProfilePage.jsx)

## Current limitations

- child-account self-service redemption is not yet implemented
- parent/child linking is modeled and displayed, but automated child-account claiming still needs a dedicated onboarding flow
