# Architecture review — queue workers, Horizon, and robustness

This note is a **technical review** of how integration flows use the queue layer, how that behaves with **Laravel Horizon** (Redis), and how the app mitigates common production risks.

## Current baseline (repository state)

- **Default queue connection:** `database` in sample `.env`; **production with Horizon** should use **`QUEUE_CONNECTION=redis`**.
- **Horizon:** installed; **`config/horizon.php`** defines **`supervisor-flows`** (`flows` queue) and **`supervisor-general`** (`default`, `notifications`, `internal`). Requires **Linux/WSL/Docker** PHP (`ext-pcntl`, `ext-posix`).
- **Flow jobs** use the **`flows`** queue name (`config('flows.queue')`). Horizon should supervise that queue (and any others you use).

## Implemented mitigations (code)

### 1. Dispatch only after DB commit

- **`ExecuteIntegrationFlowJob`** and **`ExecuteIntegrationStepJob`** implement **`ShouldQueueAfterCommit`**, so queued jobs are not pushed until open database transactions commit. This removes the race where a fast worker loads a `FlowExecution` row before the scheduler transaction commits (duplicate runs).
- **`database`** and **`redis`** queue connections default **`after_commit`** to **`true`** (overridable via `DB_QUEUE_AFTER_COMMIT` / `REDIS_QUEUE_AFTER_COMMIT`).

### 2. No Laravel job retries for flow steps

- **`ExecuteIntegrationStepJob`** uses **`$tries = 1`** and **no backoff**. A failed step is recorded once; **`failExecution`** updates the row and sends notifications where applicable. The job **does not rethrow**, so failures do not spam **`failed_jobs`** for normal business errors.

### 3. `retry_after`: lease time, not “retry count”

On **Redis** and **database** drivers, **`retry_after`** is the number of seconds Laravel waits before assuming a **reserved** job was lost (worker died) and **releasing** it for another worker. That is **not** the same as `$tries` (**`ExecuteIntegrationStepJob`** keeps **`$tries = 1`**, so Laravel never “reintenta” el paso por ese mecanismo).

- Defaults are derived from **`flows.longest_expected_step_seconds`** (env **`FLOW_LONGEST_STEP_SECONDS`**, default **300** for ~5 min NetSuite-style work): **`max(480, longest + 180)`** seconds for **`DB_QUEUE_RETRY_AFTER`** / **`REDIS_QUEUE_RETRY_AFTER`** unless you set those env vars explicitly. That keeps the lease **above** the slowest expected step so **no second worker** picks up the same job while the first is still running (this is unrelated to **`$tries`**; steps are still single-attempt).
- **`ExecuteIntegrationStepJob::$timeout`** uses **`max(120, longest + 180)`** so Horizon/workers stop the PHP process after that many seconds; keep your Horizon supervisor **`timeout`** **≥** this job timeout and **≤** **`retry_after`** (with margin).
- If imports grow beyond five minutes, raise **`FLOW_LONGEST_STEP_SECONDS`** (and optionally explicit **`REDIS_QUEUE_RETRY_AFTER`**) together.

### 4. Stuck `RUNNING` executions (deep dive)

**How a run can get stuck**

1. **Orchestration:** `ExecuteIntegrationFlowJob` sets **`RUNNING`** and dispatches the first step. If **`dispatch()`** throws (misconfiguration, queue backend down), the job catches, marks **`FAILED`**, and notifies — the execution should not remain **`RUNNING`**.
2. **Between steps:** After a step completes, the next job is dispatched. If **`ExecuteIntegrationStepJob::dispatch`** throws after the DB already shows the step as **completed**, the execution can stay **`RUNNING`** with no further jobs until something external intervenes. **This is now caught:** dispatch/fan-out spawn is wrapped in **try/catch**; failures call **`failExecution`** with a clear message.
3. **Worker killed mid-step:** PHP timeout, `SIGKILL`, OOM — the step row may stay **`RUNNING`** and the flow execution may not be updated until retry… but **`$tries = 1`**, so the queue will **not** retry. The execution can remain **`RUNNING`** indefinitely.
4. **Very long single step:** If one step runs longer than your monitoring expects but **`updated_at`** on **`FlowExecution`** is not refreshed until the step finishes (only step completion writes context), the parent row may look “quiet” — tune **`flows:reconcile-stale-running --minutes`** accordingly.

**Operational responses**

| Signal | Action |
|--------|--------|
| `RUNNING` + old **`updated_at`** | Runs **hourly** from **`routes/console.php`** (threshold **`FLOW_RECONCILE_STALE_MINUTES`**, default 180). Run **`php artisan flows:reconcile-stale-running`** manually with **`--dry-run`** as needed (see **`DEVELOPER_GUIDE.md`**). |
| Fan-out child stale | The same command treats children specially: marks the child **`FAILED`** and calls **`FanOutCoordinator::recordChildTerminal`** so the parent **`_fan_out`** counters advance. |
| Root failure notification | Reconciliation notifies via **`IntegrationFailureNotifier`** for **root** executions (not for children; the parent fan-out logic already surfaces failure states). |

**False positives**

- A **single step** that legitimately runs longer than **`--minutes`** without updating **`FlowExecution`** will be flagged. Increase **`--minutes`** or add intermittent progress updates to the execution if you need long-running steps.

## Strengths (unchanged)

1. **Small job payloads** (IDs + class names); context in the database.
2. **Idempotent shortcut** when a step is already **completed** with the same index/class.
3. **Fan-out parent aggregation** uses **`lockForUpdate()`** in **`recordChildTerminal`**.
4. **Schedule runner** uses **row locks** and **`flows:schedule-runner`** is **`withoutOverlapping()`** on the scheduler.

## Remaining recommendations (Horizon / ops)

1. **Dedicated supervisor** for **`flows`**; cap concurrency to respect external API limits.
2. **`timeout`** on Horizon processes **≤ `retry_after`** and **≥ longest expected step** (with margin).
3. **Structured logging / Horizon tags** with `flow_execution_id` and `flow_ref` for support.
4. **`failed_jobs`**: still useful for infrastructure issues; flow-step failures are primarily on **`FlowExecution`** / **`StepExecution`**.

## Summary

The app now combines **`ShouldQueueAfterCommit`**, **high `retry_after` defaults**, **single-attempt step jobs**, **dispatch error handling**, and **scheduled `flows:reconcile-stale-running`** to behave predictably under **Horizon** and reduce duplicate or stuck runs. Tune **`FLOW_RECONCILE_STALE_MINUTES`** / **`FLOW_LONGEST_STEP_SECONDS`** and worker timeouts for your slowest real workloads.

For developer workflows and CLI reference, see **`DEVELOPER_GUIDE.md`**.
