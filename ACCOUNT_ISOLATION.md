# Account ownership

Students, question sets, devices and sessions belong to `user_id`. Questions inherit ownership from their set, and responses from their session. `account.php` authenticates requests and checks ownership before ID-based reads or mutations. Lists, dashboard counts and device counts are filtered by the authenticated account. New inserts use the authenticated ID, never an ID supplied by the client.

Existing data was assigned to `admin`. For another installation, back up the database and run `php migrations/account_ownership.php OWNER_USERNAME` before serving the updated PHP files. The CLI-only migration also saves a private recovery snapshot in the system temporary directory. Schema changes use MySQL DDL and are not transactionally reversible.

Gateway APIs now require either a signed-in session or `Authorization: Bearer YOUR_KEY`. Find the account key under **Devices → Gateway connection → Show gateway API key**. A device and its target session must belong to the authenticated account. Update existing gateways to send this header. For `mock_gateway.py`, set `ARS_API_TOKEN`, optionally `ARS_API_URL`, and `ARS_SESSION_ID`.

Student codes, device codes and set names are unique within each account. Response packet IDs are unique within each session.

Run `php tests/account_isolation.php` to exercise isolation using temporary fixtures and a local PHP HTTP server. The test removes its fixtures when it finishes.
