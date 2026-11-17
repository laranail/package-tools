# Database tests on dbpantry

`vendor/bin/laranail-test-db` runs a package's database tests against real MySQL, PostgreSQL or
MariaDB from [simtabi/dbpantry](https://github.com/simtabi/dbpantry), with a database of its own for
every run.

## Configuring a package

Name the connections, in the order they run, in the package's `composer.json`:

```json
"extra": {
    "laranail": {
        "test-db": {
            "connections": ["mysql", "pgsql"],
            "group": "database",
            "project": "settings"
        }
    }
}
```

| Key | Required | Meaning |
|---|---|---|
| `connections` | yes | Laravel connection names: `mysql`, `pgsql`, `mariadb` |
| `group` | no | the test group to run (default `database`) |
| `project` | no | the database name prefix (default: the package name, lowercased, `_` for other characters) |
| `group` = `null` | no | run every test (or only `args`), with no `--group` |
| `args` | no | arguments always passed to the runner, before the ones you give after `--` |
| `principal` | no | `app` (the project's owner role, the default) or `admin` (the engine's superuser) |
| `together` | no | `true`: one run that gets every connection's variables at once |
| `env` | no | the variables a run gets, as `NAME: template`; default is Laravel's `DB_*` set |

## Variable templates

`env` names and values take `{connection}` (`pgsql`), `{CONNECTION}` (`PGSQL`), `{engine}` (`pg`),
`{host}`, `{port}`, `{database}`, `{user}` and `{password}`. A package that reads its own names
lists them; the default `DB_*` set is replaced, not added to:

```json
"env": {
    "IMPERSONATOR_TEST_DB": "{connection}",
    "IMPERSONATOR_TEST_DB_HOST": "{host}",
    "IMPERSONATOR_TEST_DB_NAME": "{database}",
    "IMPERSONATOR_TEST_DB_USER": "{user}",
    "IMPERSONATOR_TEST_DB_PASSWORD": "{password}"
}
```

With `together`, every name must contain `{CONNECTION}` (or `{connection}`, `{engine}`), so each
engine's variables stay apart in the one run; the helper refuses a name that does not:

```json
"together": true,
"principal": "admin",
"env": {
    "DB_CONSOLE_LIVE_{CONNECTION}_PORT": "{port}",
    "DB_CONSOLE_LIVE_{CONNECTION}_ADMIN_USER": "{user}",
    "DB_CONSOLE_LIVE_{CONNECTION}_ADMIN_PASSWORD": "{password}"
}
```

`principal: admin` needs a dbpantry whose `endpoints` names each engine's admin user; until then the
helper refuses it and says so. `env` masks every variable whose template holds `{password}` unless
`--reveal` is given.

## Running it

```bash
vendor/bin/laranail-test-db                       # every connection, the whole group
vendor/bin/laranail-test-db -- --filter=Approvals # extra arguments go to Pest (or PHPUnit)
vendor/bin/laranail-test-db --connection=pgsql    # one connection only (repeatable)
vendor/bin/laranail-test-db env mysql             # create a run's database, print its DB_* (password masked)
vendor/bin/laranail-test-db env mysql --reveal    # the same, with DB_PASSWORD in clear
vendor/bin/laranail-test-db down                  # stop the fixture
```

A run boots dbpantry's fixture for the engines it needs (`make fixture-up`), creates a project
named `<project>_r<8 hex>` on each (`make new-project … FIXTURE=1`), reads its port, user and
password file from `make endpoints … FIXTURE=1`, and runs the group once per connection with
`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` set. It exits
non-zero if any connection fails, and names it.

## Why a database per run, on the fixture

- **Isolation.** Two runs against one database interleave their migrations; the laranail/settings
  pilot saw 121 failures that way on 2026-11-17. A fresh project per run removes the shared state.
- **Speed.** The fixture keeps its data on tmpfs with durability off. On dbpantry's durable stack
  the same pilot measured MySQL at about nine times PostgreSQL's time (1342 s against 148 s).
- **The stack is untouched.** The fixture runs beside dbpantry's long-lived stack as its own compose
  project (simtabi/dbpantry#37), so a test run never restarts an application's database.

The fixture stays up between runs. `down` stops it, which also deletes every run's password files
under dbpantry's `secrets/fixture/`.

## Finding dbpantry

`DBPANTRY_DIR` names the checkout. Without it the helper looks for `simtabi/packages/dbpantry` in
each directory above the package, which finds it from any package in this estate. dbpantry needs
`MDB_PUBLISH=direct` in its `.env`; the fixture then publishes PostgreSQL on 55000, MySQL on 55001
and MariaDB on 55002 (`MDB_FIXTURE_PORT_BASE` + offset).

---

[← Docs index](../../README.md#documentation)
