# BASE-V1-F2 performance attribution harness

This harness extends the F1 observation baseline with explicit WordPress-only controls so Core Blueprint overhead can be attributed instead of inferred from total request metrics.

It measures ten isolated requests in one temporary WordPress installation:

**Before Base activation (WordPress-only controls):**

- anonymous frontend;
- logged-in administrator frontend;
- generic WordPress admin dashboard.

**After Base activation:**

- anonymous frontend;
- logged-in operator frontend (and verifies the user has `cb_core_hud_use`);
- generic WordPress admin dashboard;
- Core Blueprint Dashboard;
- Logs;
- Reports;
- Safeguards.

Every scenario emits JSON with request memory, query count, classic/script-module asset counts, Core Blueprint local asset bytes, the WordPress autoload footprint and scheduled Core Blueprint cron hooks/events. Records also include whether Base was active and whether the request was authenticated, so a mislabeled attribution run fails visibly.

`baseline.json` contains three direct control → Base comparisons:

- `anonymous_frontend`: WordPress-only anonymous frontend → Base-enabled anonymous frontend;
- `operator_frontend`: WordPress-only logged-in frontend → Base-enabled logged-in operator frontend;
- `generic_admin`: WordPress-only admin dashboard → Base-enabled generic admin dashboard.

The comparison object stores numeric deltas for memory, queries, asset counts/bytes, autoload and cron. These remain **observations only**: F2 does not invent pass/fail performance budgets and does not change production code.

Run through `tests/bin/run-performance-baseline.sh` after `tests/bin/install-wp-tests.sh` has prepared the pinned WordPress runtime. Results are written to `CB_PERFORMANCE_RESULTS_DIR` when set, otherwise to the system temporary directory. CI uploads the complete directory for every supported WP/PHP lane.
