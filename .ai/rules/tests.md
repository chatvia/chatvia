---
paths:
  - 'tests/**'
  - 'database/factories/**'
---

# Tests

## Pest style
Write `it('refuses the last link of a file and changes nothing', function (): void { … })` with `expect()` chains joined by `->and()`. No `test()`, no PHPUnit classes, no `$this->assertX()`. Start the file with a short docblock that says which behaviour it covers. Use `describe()` only when one file covers several subjects.

## Names state the scenario and the outcome
A test name says what happens and what should result, never "works". Split a test when "and" joins two separate behaviours; several outcomes of one behaviour are fine.

## Unit is one class; Feature goes through HTTP
`tests/Unit/{Context}/…` calls one class directly (an Action, a service, a DTO, a model) and may use the database. `tests/Feature/…` goes through HTTP, the console or broadcasting (routes, middleware, permissions). Name the file `{Class}Test.php` and mirror the class's path under `src/Domain/{Context}/`.

## Test Actions directly
Build the DTO, call `app(XAction::class)->execute(…)`, then check the returned value, the database and the side effects. Include at least one refusal path and check that nothing changed: `expect(fn () => …)->toThrow(X::class)` plus a state check.

## Test controllers through HTTP
Check the status, redirect, Inertia component and props (`assertInertia()`), or JSON with `assertJsonPath()`. Test validation by posting to the endpoint (`assertSessionHasErrors()`, `assertJsonValidationErrors()`); don't call `rules()` directly. Prefer named routes (`route('…')`).

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
A teammate without the permission gets 403; another workspace's record gets 404.

## Architecture tests
Add a structural rule as a plain `it()` in `tests/Architecture/ConventionsTest.php` that scans files without booting the app; don't use Pest's `arch()`. The allowlists there only shrink: fix an offender, then remove its entry.

## Run targeted tests
Run `php artisan test --compact <files>`, and `vendor/bin/pest tests/Architecture` after a structural change.
