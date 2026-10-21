#!/usr/bin/env bash
# C1: the 27 files of the plan's table live in backend/app-modules/<module>/tests/<Type>/<path> and no longer in backend/tests.
source "$(dirname "$0")/lib.sh"; start c01
MOVED="identity Feature/Auth/AuthTest.php
identity Feature/Auth/StaffAuthTest.php
identity Feature/Middleware/EnsureUserIsAdminTest.php
identity Feature/Queries/CustomerMetricsQueryTest.php
identity Feature/Repositories/CustomerAccountRepositoryTest.php
identity Feature/Repositories/UserRepositoryTest.php
identity Feature/UseCases/User/StaffUseCasesTest.php
identity Unit/DTOs/UpdateUserProfileDTOTest.php
identity Unit/ValueObjects/EmailTest.php
identity Unit/ValueObjects/PasswordTest.php
messaging Feature/Messaging/BrokerIsolationTest.php
messaging Feature/Messaging/DeadDeliveriesCommandsTest.php
messaging Feature/Messaging/EventTransportFlagTest.php
messaging Feature/Messaging/MessagingModuleTest.php
messaging Feature/Messaging/RabbitMqEventTransportTest.php
messaging Feature/Outbox/EventDeliveryTest.php
messaging Feature/Outbox/OutboxRelayTest.php
shared Feature/RateLimitBurstTest.php
shared Feature/RateLimiterStoreTest.php
catalog Feature/Queries/CatalogMetricsQueryTest.php
catalog Unit/Services/CategoryServiceTest.php
catalog Unit/Services/ProductServiceTest.php
catalog Unit/ValueObjects/ProductAvailabilityTest.php
payment Feature/PaymentTest.php
payment Feature/UseCases/Payment/PayOrderUseCaseTest.php
payment Unit/Services/PaymentServiceTest.php
payment Unit/Gateways/FakePaymentGatewayTest.php"
count=0
while read -r module path; do
    new="$ROOT/backend/app-modules/$module/tests/$path"
    old="$ROOT/backend/tests/$path"
    [ -f "$new" ] || fail "missing $new"
    [ -e "$old" ] && fail "still in backend/tests: $path"
    say "ok $module tests/$path"
    count=$((count + 1))
done <<< "$MOVED"
[ "$count" -eq 27 ] || fail "checked $count files, not 27"
say "PASS: 27 files in their modules, none left in backend/tests"
