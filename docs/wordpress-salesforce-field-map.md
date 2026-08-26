# WordPress to Salesforce Field Map

This document lists the WordPress fields that already exist in the AAC portal codebase and are realistic candidates for Salesforce mapping.

Scope notes:

- This list includes persisted WordPress fields only.
- Computed response fields such as `membership_actions.billing_url` are intentionally excluded because they are assembled at runtime rather than stored.
- Nested AAC portal objects are shown in dot notation so they can be mapped cleanly into Salesforce fields.
- WordPress stores most custom values as user meta, even when the values are arrays or booleans.

## Core WordPress User Fields

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| WordPress User ID | `ID` | integer | `wp_users` |
| Username | `user_login` | string | `wp_users` |
| Email Address | `user_email` | email/string | `wp_users` |
| Display Name | `display_name` | string | `wp_users` |
| First Name | `first_name` | string | `wp_usermeta` |
| Last Name | `last_name` | string | `wp_usermeta` |

## AAC Account Info User Meta

Stored under the serialized user meta key `aac_account_info`.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| First Name | `aac_account_info.first_name` | string | `wp_usermeta` |
| Last Name | `aac_account_info.last_name` | string | `wp_usermeta` |
| Full Name | `aac_account_info.name` | string | `wp_usermeta` |
| Email Address | `aac_account_info.email` | email/string | `wp_usermeta` |
| Photo URL | `aac_account_info.photo_url` | URL/string | `wp_usermeta` |
| Phone Number | `aac_account_info.phone` | string | `wp_usermeta` |
| Phone Type | `aac_account_info.phone_type` | string | `wp_usermeta` |
| Street Address | `aac_account_info.street` | string | `wp_usermeta` |
| Address Line 2 | `aac_account_info.address2` | string | `wp_usermeta` |
| City | `aac_account_info.city` | string | `wp_usermeta` |
| State / Province | `aac_account_info.state` | string | `wp_usermeta` |
| Postal Code | `aac_account_info.zip` | string | `wp_usermeta` |
| Country | `aac_account_info.country` | string | `wp_usermeta` |
| T-Shirt Size | `aac_account_info.size` | string | `wp_usermeta` |
| Legacy Publication Preference | `aac_account_info.publication_pref` | string | `wp_usermeta` |
| AAJ Preference | `aac_account_info.aaj_pref` | string | `wp_usermeta` |
| ANAC Preference | `aac_account_info.anac_pref` | string | `wp_usermeta` |
| ACJ Preference | `aac_account_info.acj_pref` | string | `wp_usermeta` |
| Guidebook Preference | `aac_account_info.guidebook_pref` | string | `wp_usermeta` |
| Magazine Subscriptions | `aac_account_info.magazine_subscriptions` | array of strings | `wp_usermeta` |
| Membership Discount Type | `aac_account_info.membership_discount_type` | string | `wp_usermeta` |
| Auto Renew | `aac_account_info.auto_renew` | boolean | `wp_usermeta` |
| Payment Method Label | `aac_account_info.payment_method` | string | `wp_usermeta` |

## AAC Profile Info User Meta

Stored under the serialized user meta key `aac_profile_info`.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| Member ID | `aac_profile_info.member_id` | string | `wp_usermeta` |
| Membership Tier | `aac_profile_info.tier` | string | `wp_usermeta` |
| Renewal Date | `aac_profile_info.renewal_date` | date string | `wp_usermeta` |
| Expiration Date | `aac_profile_info.expiration_date` | date string | `wp_usermeta` |
| Joined Date | `aac_profile_info.joined_date` | date string | `wp_usermeta` |
| Membership Status | `aac_profile_info.status` | string | `wp_usermeta` |

## AAC Benefits Info User Meta

Stored under the serialized user meta key `aac_benefits_info`.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| Rescue Amount | `aac_benefits_info.rescue_amount` | integer | `wp_usermeta` |
| Medical Amount | `aac_benefits_info.medical_amount` | integer | `wp_usermeta` |
| Mortal Remains Amount | `aac_benefits_info.mortal_remains_amount` | integer | `wp_usermeta` |
| Rescue Reimbursement Process Included | `aac_benefits_info.rescue_reimbursement_process` | boolean | `wp_usermeta` |

## AAC Family Configuration User Meta

Stored under the serialized user meta key `aac_partner_family_config`.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| Family Mode | `aac_partner_family_config.mode` | string | `wp_usermeta` |
| Additional Adult Selected | `aac_partner_family_config.additional_adult` | boolean | `wp_usermeta` |
| Dependent Count | `aac_partner_family_config.dependent_count` | integer | `wp_usermeta` |

## AAC Connected Accounts User Meta

Stored under the serialized user meta key `aac_connected_accounts`.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| Connected Account Slot ID | `aac_connected_accounts[].id` | string | `wp_usermeta` |
| Connected Account Type | `aac_connected_accounts[].type` | string | `wp_usermeta` |
| Connected Account Label | `aac_connected_accounts[].label` | string | `wp_usermeta` |
| Connected Account Status | `aac_connected_accounts[].status` | string | `wp_usermeta` |
| Invite Code | `aac_connected_accounts[].invite_code` | string | `wp_usermeta` |
| Child User ID | `aac_connected_accounts[].child_user_id` | integer | `wp_usermeta` |
| Child Name | `aac_connected_accounts[].child_name` | string | `wp_usermeta` |
| Child Email | `aac_connected_accounts[].child_email` | email/string | `wp_usermeta` |
| Slot Price | `aac_connected_accounts[].price` | decimal | `wp_usermeta` |
| Scheduled Removal Date | `aac_connected_accounts[].scheduled_removal_date` | date string | `wp_usermeta` |

## Flat / Reportable AAC User Meta

These are the flattened fields the plugin writes for reporting, exports, and easier downstream sync.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| AAC Member ID | `aac_member_id` | string | `wp_usermeta` |
| T-Shirt Size | `aac_tshirt_size` | string | `wp_usermeta` |
| Legacy Publication Preference | `aac_publication_pref` | string | `wp_usermeta` |
| AAJ Preference | `aac_aaj_pref` | string | `wp_usermeta` |
| ANAC Preference | `aac_anac_pref` | string | `wp_usermeta` |
| ACJ Preference | `aac_acj_pref` | string | `wp_usermeta` |
| Guidebook Preference | `aac_guidebook_pref` | string | `wp_usermeta` |
| Magazine Addons | `aac_magazine_addons` | array of strings | `wp_usermeta` |
| Magazine Subscription Labels | `aac_magazine_subscription_labels` | string | `wp_usermeta` |
| Has Alpinist Subscription | `aac_has_alpinist_subscription` | string flag (`1`/`0`) | `wp_usermeta` |
| Has Backcountry Subscription | `aac_has_backcountry_subscription` | string flag (`1`/`0`) | `wp_usermeta` |
| Membership Discount Type | `aac_membership_discount_type` | string | `wp_usermeta` |
| Partner Family Mode | `aac_partner_family_mode` | string | `wp_usermeta` |
| Partner Family Additional Adult | `aac_partner_family_additional_adult` | string flag (`1`/`0`) | `wp_usermeta` |
| Partner Family Dependents | `aac_partner_family_dependents` | integer | `wp_usermeta` |
| Family Account Role | `aac_family_account_role` | string | `wp_usermeta` |

## Family Linkage User Meta

These fields link a child account back to the parent household membership.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| Linked Parent User ID | `aac_linked_parent_user_id` | integer | `wp_usermeta` |
| Linked Account Slot ID | `aac_linked_account_slot_id` | string | `wp_usermeta` |
| Linked Account Invite Code | `aac_linked_account_invite_code` | string | `wp_usermeta` |
| Linked Account Type | `aac_linked_account_type` | string | `wp_usermeta` |
| Linked Account Label | `aac_linked_account_label` | string | `wp_usermeta` |
| Family Membership Access Until | `aac_family_membership_access_until` | date string | `wp_usermeta` |
| Family Membership Pending Removal | `aac_family_membership_pending_removal` | string flag (`1`) | `wp_usermeta` |

## Salesforce Sync Helper User Meta

These fields are used by the separate `aac-salesforce-sync` plugin scaffold.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| AAC External Key | `aac_external_key` | string | `wp_usermeta` |
| Salesforce Contact ID | `aac_sf_contact_id` | string | `wp_usermeta` |
| Salesforce Membership ID | `aac_sf_membership_id` | string | `wp_usermeta` |

## PMPro Transaction Fields Already Available in WordPress

These live in PMPro's order table and are already used by the Salesforce sync scaffold for transaction payloads.

| Field Name | API Name | Field Type | Storage |
| --- | --- | --- | --- |
| PMPro Order ID | `pmpro_membership_orders.id` | integer | `pmpro_membership_orders` |
| WordPress User ID | `pmpro_membership_orders.user_id` | integer | `pmpro_membership_orders` |
| Order Total | `pmpro_membership_orders.total` | decimal | `pmpro_membership_orders` |
| Order Status | `pmpro_membership_orders.status` | string | `pmpro_membership_orders` |
| Gateway | `pmpro_membership_orders.gateway` | string | `pmpro_membership_orders` |
| Transaction Timestamp | `pmpro_membership_orders.timestamp` | datetime string | `pmpro_membership_orders` |
| PMPro Membership Level ID | `pmpro_membership_orders.membership_id` | integer | `pmpro_membership_orders` |
| Order Code | `pmpro_membership_orders.code` | string | `pmpro_membership_orders` |
| Payment Transaction ID | `pmpro_membership_orders.payment_transaction_id` | string | `pmpro_membership_orders` |
| Subscription Transaction ID | `pmpro_membership_orders.subscription_transaction_id` | string | `pmpro_membership_orders` |

## Recommended Mapping Notes

- Use `ID` or `aac_external_key` as the stable WordPress-side identity anchor in Salesforce.
- Use the flat AAC meta fields for reporting-oriented mappings when possible.
- Use the nested `aac_account_info`, `aac_profile_info`, and `aac_benefits_info` objects when you want the normalized portal view of a member.
- Treat `aac_connected_accounts` and the family linkage keys as relationship data rather than simple scalar fields.
- Keep computed frontend-only fields out of the Salesforce mapping contract unless they are intentionally materialized into WordPress first.
