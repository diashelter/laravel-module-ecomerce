# storefront-read-model proofs

**HEAD**: 8354e57
**Tree**: clean
**Result**: 89 pass, 0 fail; mutations 0 killed, 0 not

| Check | Proof | Ran | Passed | Result |
| --- | --- | --- | --- | --- |
| C1 | `make test-filter FILTER="records the product creation and the opening stock at version 1"` | 4 | - | pass |
| C2 | `make test-filter FILTER="records the whole product at the next version on an edit"` | 1 | - | pass |
| C3 | `make test-filter FILTER="records the whole product at the next version on a status change"` | 1 | - | pass |
| C4 | `make test-filter FILTER="records the product deletion at the stored version plus one and no stock change"` | 1 | - | pass |
| C5 | `make test-filter FILTER="records the category creation at version 1 and the rename at the next version"` | 1 | - | pass |
| C6 | `make test-filter FILTER="records the category deletion at the stored version plus one"` | 1 | - | pass |
| C7 | `make test-filter FILTER="records the stored quantity at the next version on a stock adjustment"` | 2 | - | pass |
| C8 | `make test-filter FILTER="records one stock change per product of the checkout with the quantity left"` | 1 | - | pass |
| C8 | `make test-filter FILTER="keeps the query count of the checkout"` | 1 | - | pass |
| C9 | `make test-filter FILTER="records nothing and keeps every version when a write is refused"` | 5 | - | pass |
| C10 | `make test-filter FILTER="keeps no stock change and no version of a checkout that fails on the third code colli` | 1 | - | pass |
| C11 | `make test-filter FILTER="locks the row before computing the version"` | 7 | - | pass |
| C12 | `make test-filter FILTER="gives concurrent product writes increasing versions and the final row to the newest e` | 1 | - | pass |
| C13 | `make test-filter FILTER="gives a concurrent checkout and stock adjustment increasing versions and the final qu` | 1 | - | pass |
| C14 | `make test-filter FILTER="accepts a flat list and an ISO 8601 date in the payload|refuses an associative array,` | 11 | - | pass |
| C15 | `sh .specs/features/storefront-read-model/proofs/c15-unchanged-cases.sh` | 42 | - | pass |
| C16 | `make test-filter FILTER="reads the stock change with the catalog reader into the same values|detects a reader ` | 2 | - | pass |
| C17 | `make test-filter FILTER="keeps the eighteen frozen migration names|places each module migration in its owner p` | 16 | - | pass |
| C17 | `make test-filter FILTER="adds the version column to products, categories and stocks|gives version 1 to the row` | 4 | - | pass |
| C18 | `make test-filter FILTER="replaces the product part with a newer product event"` | 3 | - | pass |
| C19 | `make test-filter FILTER="discards a product event that is not newer"` | 6 | - | pass |
| C20 | `make test-filter FILTER="creates the document with only the stock part when the stock arrives first"` | 1 | - | pass |
| C21 | `make test-filter FILTER="replaces or discards the stock part by its version"` | 3 | - | pass |
| C22 | `make test-filter FILTER="keeps a deleted product as a tombstone"` | 1 | - | pass |
| C23 | `make test-filter FILTER="projects categories by the same version rule and touches no product"` | 1 | - | pass |
| C24 | `make test-filter FILTER="redelivers every storefront event to its projector"` | 8 | - | pass |
| C25 | `make test-filter FILTER="ends with the higher version when two deliveries race"` | 2 | - | pass |
| C26 | `make test-filter FILTER="counts a losing upsert as a discard"` | 1 | - | pass |
| C27 | `make test-filter FILTER="fails the delivery and keeps the document when MongoDB does not answer"` | 1 | - | pass |
| C28 | `make test-filter FILTER="subscribes the thirteen consumers under their stable names|subscribes no consumer und` | 14 | - | pass |
| C29 | `make test-filter FILTER="keeps the read model inside the catalog|fails on a deliberate read model violation"` | 3 | - | pass |
| C30 | `make test-filter FILTER="stores exactly the fields of door 4"` | 1 | - | pass |
| C31 | `sh docker/tests/read-model.sh --only make-rebuild` | count unknown | - | pass |
| C32 | `make test-filter FILTER="empties both collections and leaves the indexes of door 4"` | 1 | - | pass |
| C33 | `make test-filter FILTER="republishes every category and product with the current state and version|republishes` | 2 | - | pass |
| C34 | `make test-filter FILTER="reads each product and its categories in one snapshot"` | 1 | - | pass |
| C35 | `make test-filter FILTER="exits non-zero with the count recorded when the republish fails halfway and converges` | 2 | - | pass |
| C36 | `sh docker/tests/read-model.sh --only make-fresh-seed` | count unknown | - | pass |
| C37 | `make test-filter FILTER="returns the same storefront after emptying MongoDB and rebuilding"` | 1 | - | pass |
| C38 | `make test-filter FILTER="leaves no document of a record missing from PostgreSQL after the rebuild"` | 1 | - | pass |
| C39 | `make test-filter FILTER="keeps the written state when a republished envelope arrives after the write|answers 2` | 2 | - | pass |
| C40 | `sh .specs/features/storefront-read-model/proofs/c40-storefront-tests.sh` | 134 | - | pass |
| C41 | `make test-filter FILTER="lists only the documents with a product part and no tombstone twelve per page"` | 1 | - | pass |
| C42 | `make test-filter FILTER="filters by the slug of a category that is not a tombstone"` | 1 | - | pass |
| C43 | `make test-filter FILTER="sorts the storefront by name in byte order and by price with the id breaking ties"` | 1 | - | pass |
| C44 | `make test-filter FILTER="refuses an invalid sort or page"` | 3 | - | pass |
| C45 | `make test-filter FILTER="answers the product page from the document"` | 7 | - | pass |
| C46 | `make test-filter FILTER="answers 404 to a non canonical id without reading MongoDB"` | 1 | - | pass |
| C47 | `make test-filter FILTER="renders a document without the stock part as unavailable|leaves out a category that h` | 2 | - | pass |
| C48 | `make test-filter FILTER="reads MongoDB the same number of times for one and twelve products"` | 1 | - | pass |
| C48 | `make test-filter FILTER="lists the catalog with the same query count for 1 and 12 products"` | 1 | - | pass |
| C49 | `make test-filter FILTER="runs no PostgreSQL query on the storefront"` | 1 | - | pass |
| C50 | `make test-filter FILTER="decides availability when reading"` | 1 | - | pass |
| C50 | `sh .specs/features/storefront-read-model/proofs/c50-no-rule-in-query.sh` | count unknown | - | pass |
| C51 | `make test-filter FILTER="answers 503 within five seconds when MongoDB does not answer"` | 2 | - | pass |
| C52 | `sh docker/tests/read-model.sh --only gateway-storefront` | count unknown | - | pass |
| C53 | `sh load-tests/tests/stack.sh --only gateway` | count unknown | - | pass |
| C54 | `sh docker/tests/read-model.sh --only gateway-rest` | count unknown | - | pass |
| C55 | `sh docker/tests/read-model.sh --only gateway-down` | count unknown | - | pass |
| C56 | `sh docker/tests/read-model.sh --only gateway-cold` | count unknown | - | pass |
| C57 | `sh docker/tests/read-model.sh --only api-read-no-db` | count unknown | - | pass |
| C58 | `sh docker/tests/read-model.sh --only client-address` | count unknown | - | pass |
| C59 | `sh docker/tests/read-model.sh --only test-mongo-db` | count unknown | - | pass |
| C59 | `make test-filter FILTER="starts each test with an empty read model"` | 2 | - | pass |
| C60 | `sh docker/tests/read-model.sh --only ci` | count unknown | - | pass |
| C61 | `sh docker/tests/read-model.sh --only dev-services` | count unknown | - | pass |
| C61 | `docker compose run --rm --no-deps --entrypoint php api -m | grep -qx -e mongodb` | count unknown | - | pass |
| C62 | `sh load-tests/tests/stack.sh --only connection` | count unknown | - | pass |
| C62 | `sh load-tests/tests/stack.sh --only fpm-pool` | count unknown | - | pass |
| C63 | `sh load-tests/tests/k6.sh projection.test.js --only first-vu-writes` | count unknown | - | pass |
| C64 | `sh load-tests/tests/k6.sh projection.test.js --only next-price` | count unknown | - | pass |
| C65 | `sh load-tests/tests/k6.sh projection.test.js --only polls-until-exact-price` | count unknown | - | pass |
| C66 | `sh load-tests/tests/k6.sh projection.test.js --only measures-from-the-put` | count unknown | - | pass |
| C66 | `sh load-tests/tests/k6.sh projection.test.js --only flow-wires-the-metric` | count unknown | - | pass |
| C67 | `sh load-tests/tests/k6.sh projection.test.js --only gives-up-after-60s` | count unknown | - | pass |
| C68 | `sh load-tests/tests/k6.sh summary.test.js --only projection-lag-p95` | count unknown | - | pass |
| C68 | `sh load-tests/tests/k6.sh profiles.test.js --only no-projection-lag-threshold` | count unknown | - | pass |
| C69 | `sh load-tests/tests/make.sh --only rebuild-before-k6` | count unknown | - | pass |
| C70 | `sh load-tests/tests/assert-check.sh smoke --name "setup: dataset has 202 products"` | count unknown | - | pass |
| C71 | `sh load-tests/tests/dataset.sh --only team-product` | count unknown | - | pass |
| C71 | `sh load-tests/tests/dataset.sh --only catalog` | count unknown | - | pass |
| C72 | `sh load-tests/tests/run.sh --only smoke` | count unknown | - | pass |
| C72 | `sh load-tests/tests/assert-metric.sh smoke --name storefront_projection_lag --at-least 1` | count unknown | - | pass |
| C73 | `sh load-tests/tests/docs.sh --only read-model` | count unknown | - | pass |
| C74 | `sh .specs/features/storefront-read-model/proofs/docs.sh --only readme` | count unknown | - | pass |
| C75 | `sh .specs/features/storefront-read-model/proofs/docs.sh --only env-examples` | count unknown | - | pass |
| C76 | `sh .specs/features/storefront-read-model/proofs/docs.sh --only domain-analysis` | count unknown | - | pass |
| C77 | `sh .specs/features/storefront-read-model/proofs/docs.sh --only agents` | count unknown | - | pass |
| Build | `make pint` | - | - | pass |
