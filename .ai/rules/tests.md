---
paths:
  - 'tests/**'
  - 'database/factories/**'
---

# Tests

## What earns a test
Test the feature through its entry point and check what the user or operator gets. That covers the Action, DTO and form request behind it. Add a unit test only for a critical rule (tenancy and permissions, sign-in, license and usage limits, outside input and SSRF, signatures and webhooks, guardrails, tool approval, data erasure and retention, secrets) or for logic with real branches (parsers, stream decoders, chunkers, scorers), with one case per branch.

Don't test labels and copy, translated strings, prompt wording, the exact output of a formatter or payload builder, DTO shape, config defaults, enum lists, service-provider bindings, listener registration, "the event or job was dispatched" with no effect checked, or framework behaviour (casts, `required`, Filament rendering a field). Write one validation case per form request, not one per field. A dataset holds distinct branches only. On a page, assert the data or the saved setting, not `assertSee('Some label')`.

## Pest style
Write `it('refuses the last link of a file and changes nothing', function (): void { … })` with `expect()` chains joined by `->and()`. No `test()`, no PHPUnit classes, no `$this->assertX()`. Start the file with a short docblock that says which behaviour it covers. Use `describe()` only when one file covers several subjects.

## Names state the scenario and the outcome
A test name says what happens and what should result, never "works". Split a test when "and" joins two separate behaviours; several outcomes of one behaviour are fine.

## Unit is one class; Feature goes through an entry point
`tests/Unit/…` calls one class directly (an Action, a service, a DTO, a model) and may use the database. Name the file `{Class}Test.php` and mirror the class's path with `src/` dropped: `src/Domain/{Context}/…` → `tests/Unit/Domain/{Context}/…`, `src/App/…` → `tests/Unit/App/…`, `src/Support/…` → `tests/Unit/Support/…`. Tests of `config/` and `database/` files go in `tests/Unit/{Config,Database}`.

`tests/Feature/…` goes through an entry point the outside world uses: an HTTP route, a Filament/Livewire page, an artisan command or broadcasting auth. Put it at that entry point's path: `tests/Feature/App/Http/Controllers/…/{Controller}Test.php`, `tests/Feature/App/Admin/{Pages,Resources,…}/…`, `tests/Feature/App/Console/Commands/{Ns}/{Command}Test.php`. A flow across several entry points is named for its behaviour and sits in their nearest common folder. A file that makes no request is a unit test. Never write a `*JobTest.php`.

## Test an Action directly only when it earns it
When an Action needs its own test (see above), build the DTO, call `app(XAction::class)->execute(…)`, then check the returned value, the database and the side effects. Include at least one refusal path and check that nothing changed: `expect(fn () => …)->toThrow(X::class)` plus a state check.

## Test controllers through HTTP
Check the status, redirect, Inertia component and props (`assertInertia()`), or JSON with `assertJsonPath()`. Test validation by posting to the endpoint once, with input that is refused (`assertSessionHasErrors()`, `assertJsonValidationErrors()`); don't call `rules()` directly. Prefer named routes (`route('…')`).

## Arrange, act, assert
Keep the three parts as blocks separated by blank lines, with no `// Arrange` comments. Assert only what this behaviour changes, not a whole `toArray()` or the exact JSON.

## Check state with fresh()
Check the database with `expect($model->fresh())->status->toBe(…)` or `expect(Model::query()->where(…)->exists())->toBeTrue()`.

## Fakes before mocks
Use the framework fake when one exists (`Http::fake()`, `Bus::fake()`, `Storage::fake()`, `Notification::fake()`). Fake events by name, `Event::fake([HandedOff::class])`, so other listeners still run. Next, bind a fake with `app()->instance(Contract::class, new FakeX)`. Use `$this->mock()` only for a boundary service (LLM, MCP client, translator, reranker, OCR) or to assert an interaction. Never mock an Eloquent model.

## Never call a real outside service
Fake every outside call with `Http::fake([...])`. Use the offline fakes in `tests/Support` (`FakeEmbeddingsProvider`, `FakeReranker`, `FakeTextSplitter`) and the `Pest.php` helpers (`bindFakeLlmProvider()`, `fakeLlmCompletion()`, `testLlm()`, `connectAiProviders()`).

## Build test data from factories
Use a factory with a named state when one exists (`User::factory()->employee($owner)`, `Message::factory()->fromAssistant()`, `AgentFileFactory::new()` for source files), then the `Pest.php` helpers (`createAgent()`, `makeTeamAgent()`, `teammateWithPermissions()`), and only then `Model::create([...])` with `workspace_id` taken from the parent. Create only the rows the test needs.

## Leave the database setup alone
`Pest.php` binds `RefreshTestDatabases`: a seeded Postgres database that provisions itself, with one transaction per test. Never add `RefreshDatabase` or `DatabaseMigrations` to a file, and don't seed per test.

## Test jobs through their Action
Don't write `*JobTest.php` files; test the Action. To check a dispatch, use `Bus::fake([XJob::class])` and assert on the job, including `$job->queue`; for batches, use `Bus::assertBatched()` and check the batch's queue. Test a failure hook by calling `->failed(new RuntimeException)` directly. Jobs that are not faked run inline (`QUEUE_CONNECTION=sync`).

## Time and repeated inputs
Move time with `$this->travelTo()`, `$this->travel()` or `$this->freezeTime()`, not `Carbon::setTestNow()`. Run one check over many inputs with a labelled dataset (`->with([...])`).

## Prove tenancy and permissions
A teammate without the permission gets 403; another workspace's record gets 404. `CrossWorkspaceRouteTest`, `WorkspacePermissionRouteTest`, `AgentPermissionRouteTest` and `ApiPermissionTest` sweep every route, so a controller test adds at most one case of each, for a rule the sweeps can't see.

## Architecture tests
Add a structural rule as a plain `it()` in `tests/Architecture/ConventionsTest.php` that scans files without booting the app; don't use Pest's `arch()`. The allowlists there only shrink: fix an offender, then remove its entry. Counts too large for an allowlist live in `tests/Architecture/baselines/*.json`; after fixing calls, lower them with `CONVENTIONS_BASELINE_WRITE=1 vendor/bin/pest tests/Architecture`.

## Run targeted tests
Run `php artisan test --compact <files>`, and `vendor/bin/pest tests/Architecture` after a structural change.
