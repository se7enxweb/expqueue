# expqueue: the Exponential job queue

A generic, database-backed job queue for Exponential 6. Producers queue jobs with one call; workers run them on the
command line, from cron or as a systemd service, never inside a web request. The queue knows nothing about what a
job does: extensions register job types and their handlers in `expqueue.ini`.

- **Seven priority classes** (interactive, editorial, chain, translation_patch, translation_full, bulk, system) with
  weights. The scheduler is fair (stride scheduling), with aging, a starvation guard and a guaranteed minimum share
  per class, so 1,000 bulk jobs never hold up an editor's job and the bulk jobs still keep moving.
- **Per-user and per-site quotas**, **per-resource concurrency limits** (e.g. one key per external API and lane) that
  adapt to rate-limit hints from handlers (429, retry-after, remaining requests).
- **Leases with a reaper.** A worker that dies (kill -9, out of memory, a reboot) loses its lease and the job runs
  again. Every state change is a compare-and-set update.
- **Retries** with exponential backoff and jitter that honour retry-after, a **dead letter**, **idempotency keys**,
  **dependencies** (`depends_on`), **not-before** times, **job groups** with progress, cooperative **cancel**,
  **pause/resume** per queue, class, resource and group.
- **A result checkpoint** for handlers: they store an outcome that costs money before they apply it, so a rerun
  never pays twice.
- **Admin pages** (Setup > Job queue): a dashboard (key figures, jobs per hour, classes, resources, workers, cron
  and service status, dead letter, groups), job list, job and group pages with controls. Policies `expqueue/view`
  and `expqueue/manage`.
- **CLI**: `./console ext:expqueue:work|status|list|show|enqueue|cancel|pause|resume|retry|purge|install-schema|service`.
- **Every engine of the kernel**: MySQL/MariaDB, PostgreSQL, SQLite, Oracle and MongoDB.

```php
$job = expQueue::enqueue( 'mysite.thumbnail', array( 'node_id' => 42 ), array(
    'class' => 'editorial', 'idempotency_key' => 'thumbnail:42:3', 'resource' => 'imaging' ) );
echo $job->attribute( 'uuid' );
```

Documentation: [INSTALL.md](INSTALL.md), [USAGE.md](USAGE.md) and [doc/](doc/index.md): architecture, scheduling, the
API for producers with a worked example handler, workers (CLI, cron, systemd), admin pages, CLI, configuration,
database per engine, troubleshooting.

Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
Licensed under the GNU General Public License v2.0 (or any later version), see [LICENSE](LICENSE).
