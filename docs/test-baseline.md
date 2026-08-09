# PHPUnit failure baseline (Olabisiolai_Laravel12)

**Recorded:** 2026-08-03  
**Purpose:** Live-verified suite baseline so future work can diff pass/fail counts against evidence, not inference.  
**DB:** `olabisiolai_testing` only (see `.cursor/rules/laravel-testing-database.mdc`).

---

## Verification method (sitemap commit close-out)

| Item | Value |
|------|--------|
| Sitemap commit (HEAD) | `9852f16` — *Add daily SPA sitemap generation served at /sitemap.xml.* |
| Parent commit | `77fa590` — *Validate business_id and update status docs* |
| Method | Local `git worktree` at parent (no push/pull); full `php artisan test --log-junit` on both |
| HEAD result | **61 failed**, 200 passed (261 tests) |
| Parent result | **60 failed**, 189 passed (249 tests) |
| New tests on HEAD | +12 (`EncryptIdTest` ×4 + `SitemapGenerateTest` ×8) → 249 + 12 = 261 |

### Set diff (JUnit classnames)

- **Only on HEAD (not in parent full-suite run):**  
  `Tests\Feature\Api\V1\VerificationRevokedOnMajorChangeTest::test_changing_business_name_revokes_verification_badge`
- **Only on parent:** _(none)_

### Isolated re-runs of that “new” failure (5× each)

| Commit | Results |
|--------|---------|
| HEAD `9852f16` | fail, fail, fail, **pass**, fail |
| Parent `77fa590` | fail, fail, fail, fail, fail |

**Verdict:** Not a sitemap regression. Same test is **pre-existing flaky** on both trees (factory `business_description` often exceeds the 150-char update validation → 302 with validation errors). It happened to pass once during the parent full-suite run, which is why the suite totals differ by 1.

### Shared files touched by sitemap commit

Additive-only changes; no update/visibility-rule rewrites:

- `BusinessInfoService` — added `publicMarketplaceBusinessesQuery()` wrapping existing private visibility
- `BusinessCatalogService` — added `discoverableItemsQuery()` wrapping existing `discoveryBaseQuery()`
- `routes/web.php` — added `GET /sitemap.xml`; removed unused `AuthController` import
- `config/app.php` — added `frontend_url`

The solid **60** failures listed below appeared on **both** full-suite runs. None of the failure signatures point at those additive hooks (errors are admin `role` truncation, auth/route status mismatches, search parsing, seeders, etc.).

**Confirmed:** sitemap commit did **not** introduce a durable new failure. Closing baseline for “pre-sitemap” comparison = **60 solid + 1 pre-existing flake** (suite often reports **61** when the flake fails).

---

## Baseline failure names (61 as observed on HEAD `9852f16`)

Format: `Class::method`

1. `Tests\Unit\Services\MessageServiceTest::test_send_message_persists_and_dispatches_side_effects`
2. `Tests\Unit\Services\PublicSearchQueryParserTest::test_parses_service_and_location_from_natural_query`
3. `Tests\Feature\Api\MessageTest::test_user_can_send_edit_delete_read_and_type_in_conversation`
4. `Tests\Feature\Api\MessageTest::test_non_member_cannot_send_message`
5. `Tests\Feature\Api\V1\AdminBusinessInfoListTest::test_admin_can_list_business_profiles`
6. `Tests\Feature\Api\V1\AdminBusinessInfoListTest::test_admin_can_paginate_business_profiles`
7. `Tests\Feature\Api\V1\AdminBusinessInfoListTest::test_admin_gets_empty_response_when_no_business_profile_exists`
8. `Tests\Feature\Api\V1\AdminLocationApiTest::test_admin_can_store_location_from_map_pick_payload`
9. `Tests\Feature\Api\V1\AdminLocationApiTest::test_admin_can_get_lga_vendor_coordinates`
10. `Tests\Feature\Api\V1\AdminUserManagementSummaryTest::test_admin_receives_user_management_summary_counts`
11. `Tests\Feature\Api\V1\AdminUserManagementSummaryTest::test_new_signups_only_includes_users_created_within_last_24_hours`
12. `Tests\Feature\Api\V1\AdminUserManagementSummaryTest::test_non_admin_cannot_access_user_management_summary`
13. `Tests\Feature\Api\V1\AdminUserManagementSummaryTest::test_guest_cannot_access_user_management_summary`
14. `Tests\Feature\Api\V1\BusinessHoursTest::test_create_business_persists_custom_hours`
15. `Tests\Feature\Api\V1\BusinessInfoStoreTest::test_verified_vendor_can_fetch_form_options`
16. `Tests\Feature\Api\V1\BusinessInfoStoreTest::test_verified_vendor_can_create_business_profile_with_uploads`
17. `Tests\Feature\Api\V1\BusinessInfoStoreTest::test_second_create_returns_error`
18. `Tests\Feature\Api\V1\BusinessInfoStoreTest::test_unauthenticated_store_returns_401`
19. `Tests\Feature\Api\V1\BusinessInfoStoreTest::test_show_returns_404_when_missing`
20. `Tests\Feature\Api\V1\BusinessInfoStoreTest::test_role_user_cannot_access_vendor_business_routes`
21. `Tests\Feature\Api\V1\PublicBusinessSearchTest::test_compound_search_finds_cleaners_in_target_lga`
22. `Tests\Feature\Api\V1\UserModeSwitchTest::test_vendor_with_existing_business_can_switch_to_customer`
23. `Tests\Feature\Api\V1\UserReviewsTest::test_vendor_cannot_access_user_reviews_endpoint`
24. `Tests\Feature\Api\V1\VendorOnboardingStatusTest::test_vendor_without_business_is_sent_to_onboarding`
25. `Tests\Feature\Api\V1\VendorOnboardingStatusTest::test_vendor_with_free_business_goes_to_dashboard_and_can_pay_premium`
26. `Tests\Feature\Api\V1\VendorOnboardingStatusTest::test_vendor_with_active_premium_cannot_pay_again`
27. `Tests\Feature\Api\V1\VendorSubscriptionTest::test_premium_business_creation_requires_payment_before_vendor_features`
28. `Tests\Feature\Api\V1\VendorSubscriptionTest::test_premium_payment_confirmation_activates_subscription_without_verification`
29. `Tests\Feature\Api\V1\VendorSubscriptionTest::test_premium_checkout_with_boost_creates_separate_payment_records`
30. `Tests\Feature\Api\V1\VendorSubscriptionTest::test_free_business_payment_confirmation_activates_premium`
31. `Tests\Feature\Api\V1\VerificationRevokedOnMajorChangeTest::test_changing_business_name_revokes_verification_badge` — **pre-existing flake** (see above)
32. `Tests\Feature\AuthRegistrationTest::test_marketplace_login_rejects_admin_user`
33. `Tests\Feature\AuthRegistrationTest::test_login_requires_email_verification_for_marketplace_and_admin_urls`
34. `Tests\Feature\AuthRegistrationTest::test_admin_login_only_allows_admins`
35. `Tests\Feature\AuthRegistrationTest::test_reset_password_uses_forgot_password_otp_flow`
36. `Tests\Feature\Database\CategorySeederTest::test_category_seeder_inserts_all_marketplace_categories_with_subcategories`
37. `Tests\Feature\Feature\Auth\AuthLoginTest::test_register_queues_otp_verification_email`
38. `Tests\Feature\Feature\Auth\AuthLoginTest::test_verified_user_can_login`
39. `Tests\Feature\Feature\Auth\AuthLoginTest::test_verified_vendor_can_login`
40. `Tests\Feature\Feature\Auth\AuthLoginTest::test_unverified_user_login_sends_otp_and_returns_verification_token`
41. `Tests\Feature\Feature\Auth\AuthLoginTest::test_login_always_rejects_admin`
42. `Tests\Feature\Feature\Auth\AuthLoginTest::test_admin_can_login_via_admin_endpoint`
43. `Tests\Feature\Feature\Auth\AuthLoginTest::test_admin_endpoint_rejects_user`
44. `Tests\Feature\Feature\Auth\AuthLoginTest::test_admin_endpoint_rejects_vendor`
45. `Tests\Feature\Feature\Auth\AuthLoginTest::test_authenticated_unverified_user_can_resend_otp`
46. `Tests\Feature\Feature\Auth\AuthLoginTest::test_resend_otp_returns_already_verified_for_verified_user`
47. `Tests\Feature\Feature\Auth\AuthLoginTest::test_authenticated_user_can_logout`
48. `Tests\Feature\Feature\Auth\AuthLoginTest::test_admin_route_rejects_user_role`
49. `Tests\Feature\Feature\Auth\AuthLoginTest::test_user_route_rejects_admin_role`
50. `Tests\Feature\Feature\Auth\AuthLoginTest::test_resend_forgot_password_otp_with_valid_token`
51. `Tests\Feature\ReviewTest::test_public_can_list_approved_reviews_by_business`
52. `Tests\Feature\ReviewTest::test_review_validation_requires_business_id`
53. `Tests\Feature\ReviewTest::test_admin_can_list_all_reviews`
54. `Tests\Feature\ReviewTest::test_admin_can_view_specific_review`
55. `Tests\Feature\ReviewTest::test_admin_can_approve_review`
56. `Tests\Feature\ReviewTest::test_admin_can_flag_review`
57. `Tests\Feature\ReviewTest::test_admin_can_delete_review`
58. `Tests\Feature\ReviewTest::test_admin_can_bulk_approve_reviews`
59. `Tests\Feature\ReviewTest::test_admin_can_bulk_flag_reviews`
60. `Tests\Feature\ReviewTest::test_admin_can_get_review_statistics`
61. `Tests\Feature\ReviewTest::test_non_admin_cannot_access_admin_endpoints`

### Solid parent intersection (60)

All of the above **except** #31 appeared in the parent full-suite JUnit failure set. Treat **60** as the durable pre-existing count; expect suite summary **60 or 61** depending on the flake.

---

## How to refresh this baseline

```bash
cd Olabisiolai_Laravel12
php artisan test --log-junit storage/app/test-results.xml
# Diff //testcase[failure or error] classnames against this list
```

Do not run RefreshDatabase against `olabisiolai`.
