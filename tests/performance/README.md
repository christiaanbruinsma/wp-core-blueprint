# BASE-V1-F1 performance baseline harness

This harness measures Core Blueprint Base across six isolated WordPress requests:

- anonymous frontend;
- generic WordPress admin dashboard;
- Core Blueprint Dashboard;
- Logs;
- Reports;
- Safeguards.

Each scenario emits JSON with request memory, query count, classic/script-module asset counts, Core Blueprint local asset bytes, the WordPress autoload footprint and scheduled Core Blueprint cron hooks/events.

F1 is deliberately **observation-only**. The harness validates that measurements are structurally valid, but it does not fail CI because a metric crosses an invented numeric threshold. The first CI results establish the reproducible baseline that later F phases can use to justify a concrete regression gate or production optimization.

Run through `tests/bin/run-performance-baseline.sh` after `tests/bin/install-wp-tests.sh` has prepared the pinned WordPress runtime. Results are written to `CB_PERFORMANCE_RESULTS_DIR` when set, otherwise to the system temporary directory.
