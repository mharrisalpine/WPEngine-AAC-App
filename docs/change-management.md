# AAC Member Portal Change Management Guide

## Purpose

This document defines how changes to the AAC Member Portal should be planned, implemented, validated, approved, deployed, rolled back, and documented.

It applies to:

- the React/Vite member application in [`src/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/src)
- the WordPress plugin wrapper in [`wordpress/aac-member-portal/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal)
- PMPro checkout, billing, account, and membership integrations
- admin settings added through the `AAC Portal` WordPress admin module
- content, design, pricing, and member-data changes that affect production behavior

## Goals

- reduce regressions in member login, checkout, billing, and profile flows
- make every release traceable
- separate low-risk content/design changes from higher-risk functional changes
- ensure rollbacks are practical
- protect member, payment, and account data

## Systems in Scope

### Application layers

- React app
- WordPress plugin
- PMPro
- WordPress user authentication
- plugin-managed runtime config
- plugin-managed admin settings

### Operational dependencies

- WordPress.com staging site
- WordPress admin plugin upload flow
- PMPro level definitions and checkout fields
- WordPress user meta storage
- browser-cached frontend assets

## Change Categories

### 1. Content changes

Examples:

- copy updates
- label text changes
- helper text changes
- contact email changes
- admin-managed tile images

Risk level: low

### 2. Design or layout changes

Examples:

- sidebar styling
- checkout card layout
- PMPro shell styling
- responsive layout changes

Risk level: low to medium

### 3. Functional changes

Examples:

- login/logout behavior
- password-change flow
- profile save behavior
- navigation logic
- route changes
- contact form delivery

Risk level: medium to high

### 4. Commerce and membership changes

Examples:

- PMPro pricing logic
- discounts
- family pricing
- auto-renew logic
- surcharge logic
- publication preferences that affect billing

Risk level: high

### 5. Data model changes

Examples:

- new user meta
- changed field names
- reportable meta changes
- API payload changes

Risk level: high

### 6. Security-sensitive changes

Examples:

- auth endpoints
- nonce/session handling
- password reset
- public form abuse controls
- role/capability changes

Risk level: high

## Roles and Responsibilities

### Product owner / business stakeholder

- defines requested behavior
- confirms pricing, member benefits, and content requirements
- approves visible behavior changes

### Engineering owner

- performs impact analysis
- implements code changes
- validates plugin packaging and deployment
- documents rollback notes and known risks

### WordPress admin / site operator

- manages PMPro level settings
- manages plugin activation and admin-side content settings
- verifies staging and production behavior in the browser

### Reviewer / approver

- reviews high-risk or payment-impacting changes
- verifies business rules for membership billing, discounts, benefits, and reporting

## Environments

### Local

Purpose:

- development
- code review
- packaging
- static verification

Typical checks:

- `php -l`
- `npm run build`
- `npm run package:wordpress`

### Staging

Purpose:

- browser verification
- PMPro integration verification
- admin settings verification
- deploy validation before production

Known staging characteristic:

- `/membership/` may continue serving cached asset aliases after deploy, so compatibility copies of current JS/CSS may still be required until caching is cleared

### Production

Purpose:

- member-facing live environment

Production changes should not be deployed until the same behavior is verified on staging.

## Standard Change Workflow

### 1. Intake

Document:

- requested change
- affected pages/routes
- affected PMPro levels
- whether pricing, data, reporting, or authentication is impacted

### 2. Impact analysis

Identify:

- files/modules affected
- runtime dependencies
- whether existing admin settings should absorb the request
- whether PMPro settings also need manual admin changes
- whether user meta or API payloads change

### 3. Risk classification

Classify as:

- low
- medium
- high

High-risk changes require explicit validation notes before deploy.

### 4. Implementation

Use:

- React app for member-facing UI and routing
- WordPress plugin for REST endpoints, PMPro behavior, runtime config, and admin settings
- PMPro hooks/filters for checkout pricing and level behavior

### 5. Validation

At minimum:

- `php -l` on changed PHP files
- `npm run build`
- `npm run package:wordpress`

For PMPro/checkout changes, also verify:

- initial payment amount
- recurring amount
- order summary line items
- confirmation output

### 6. Deployment to staging

Current project packaging and deploy flow:

1. run `npm run package:wordpress`
2. confirm assets exist in [`wordpress/aac-member-portal/app/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/app)
3. zip [`wordpress/aac-member-portal/`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal)
4. deploy with [`tools/deploy-wordpress-plugin.sh`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/tools/deploy-wordpress-plugin.sh)
5. if staging still serves old asset aliases, refresh compatibility copies for the cached filenames

### 7. Staging verification

Verify:

- page renders
- no console/app boot errors
- checkout shell still loads
- PMPro actions still function
- profile/account fields save correctly
- line items and totals match business rules

### 8. Approval

Before production, capture:

- what changed
- what was verified
- known limitations
- rollback point

### 9. Production deployment

Production should use the same packaged plugin artifact that passed staging validation.

### 10. Post-deploy monitoring

Check:

- login/logout
- `/wp-json/aac/v1/me`
- signup/checkout
- membership account/billing pages
- contact form
- admin settings page

## Approval Matrix

### Low-risk changes

- content-only changes
- image swaps
- admin setting text updates

Approval:

- engineering owner + business stakeholder confirmation

### Medium-risk changes

- layout changes
- new profile fields
- navigation changes
- non-payment PMPro shell changes

Approval:

- engineering owner + stakeholder review on staging

### High-risk changes

- pricing logic
- discounts/surcharges
- recurring billing behavior
- auth/session/password flows
- security controls
- data schema/meta changes

Approval:

- engineering owner
- business stakeholder
- explicit staging verification record

## Testing Matrix

### Core app

- login
- logout
- password change
- member profile load
- account save
- navigation between tabs

### PMPro-managed pages

- membership account
- billing
- checkout
- cancellation
- orders
- confirmation

### Checkout and pricing

- each supported membership level
- discount on/off
- family mode on/off
- magazine add-ons on/off
- donation on/off
- international surcharge on/off
- auto-renew on/off where applicable

### Data and reporting

- user meta writes
- flat reportable meta writes
- profile payload includes new fields
- account/profile display stays in sync

### Contact and support

- contact form submission
- email recipient setting
- reply-to behavior

## Configuration Management

### Code-managed configuration

Use code for:

- PMPro pricing behavior
- REST behavior
- member-data structure
- fallback defaults
- route behavior

### Admin-managed configuration

Use the `AAC Portal` admin module for:

- shared content
- navigation visibility/order
- contact recipient
- publication tile image URLs
- selected shell presentation options

### PMPro-managed configuration

Use PMPro admin for:

- level pricing and existence
- gateway settings
- recurring billing configuration
- user field groups
- add-on activation

If a change requires both code and PMPro admin settings, document both parts before deploy.

## Rollback Strategy

### Required rollback artifacts

- latest stable local commit
- latest stable tag
- last known-good plugin zip
- staging package number if applicable

### Rollback methods

1. Reinstall a previous plugin zip
2. Reset to a previous git commit locally and rebuild/package
3. Reapply compatibility asset aliases if cached staging HTML is still pointing at old filenames

### Rollback triggers

- app fails to boot
- checkout totals incorrect
- recurring amount incorrect
- login/logout broken
- profile save broken
- PMPro admin or member pages inaccessible

## Change Record Template

Use this structure for major updates:

### Summary

- what changed

### Risk

- low / medium / high

### Affected areas

- routes
- plugin files
- PMPro pages
- user meta

### Verification

- commands run
- staging URLs checked
- business rules confirmed

### Rollback

- commit/tag/zip to restore

### Follow-up

- any manual PMPro/admin steps still required

## Security Change Controls

Any change touching these areas must be reviewed carefully:

- auth endpoints
- session cookies / nonce handling
- role or capability changes
- public form submission
- password reset behavior
- contact-form recipient logic
- pricing logic that affects real charges

Security-sensitive changes should always include:

- abuse/rate-limit review
- authentication review
- data exposure review
- failure-mode review

## Known Project-Specific Risks

### 1. Cached asset aliases on staging

The staging site can continue referencing older JS/CSS filenames after plugin deploys. This can make a correct deploy look broken until cached alias files are refreshed or the browser cache is cleared.

### 2. Heavy PMPro shell customization

The managed checkout/account shells reshape PMPro markup with custom JS and CSS. PMPro or add-on markup changes can break these transforms.

### 3. PMPro admin configuration drift

Some business behavior depends on PMPro levels, user fields, and add-ons being configured to match the code expectations.

### 4. User meta evolution

The portal relies on both structured user meta and flat reportable meta. Field additions should maintain backward compatibility whenever possible.

## Documentation Maintenance

Update this document whenever any of these change:

- deployment process
- approval process
- environments
- rollback method
- risk categories
- PMPro integration assumptions
- admin settings capabilities

Related docs:

- [`docs/codebase-reference.md`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/docs/codebase-reference.md)
- [`docs/wordpress-hosting.md`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/docs/wordpress-hosting.md)
- [`docs/family-membership.md`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/docs/family-membership.md)
- [`wordpress/aac-member-portal/README.md`](/Users/mharris/Desktop/WordPress%20Page%20Matched%20Web%20App/wordpress/aac-member-portal/README.md)
