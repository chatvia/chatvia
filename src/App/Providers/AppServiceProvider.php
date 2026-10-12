<?php

namespace App\Providers;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

/**
 * Application-wide framework settings: the password policy, the morph map,
 * model strictness, view and factory resolution, HTTPS, and the Microsoft
 * Socialite extension.
 *
 * The rest of the wiring has its own provider: request macros
 * ({@see HttpMacroServiceProvider}), rate limiters
 * ({@see RateLimitServiceProvider}), gates and policies
 * ({@see AuthServiceProvider}), filesystem drivers
 * ({@see FilesystemServiceProvider}), API docs
 * ({@see ApiDocsServiceProvider}) and domain wiring — observers, contract
 * bindings, event listeners ({@see DomainServiceProvider}).
 */
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configurePasswordDefaults();
        $this->enforceMorphMap();
        $this->configureModelStrictness();
        $this->registerPromptViewNamespace();
        $this->configureFactoryResolution();
        $this->forceHttpsInProduction();
        $this->extendSocialiteProviders();
    }

    /**
     * Centralised password policy consumed by every Password::defaults() /
     * Password::default() call site (registration, reset, invitations,
     * settings). Production demands long, mixed, uncompromised
     * passwords; everywhere else stays at min(8) so local logins and the test
     * suite keep working. uncompromised() calls the haveibeenpwned.com range
     * API, which is another reason to keep it production-only.
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }

    // Short morph keys instead of FQCNs. Saves ~50 bytes per row on chunks
    // (billions of rows = real disk) and decouples persisted data from
    // namespace renames.
    private function enforceMorphMap(): void
    {
        Relation::enforceMorphMap([
            'agent' => Agent::class,
            'user' => User::class,
            'chatter' => Chatter::class,
            'source_file' => SourceFile::class,
            'source_website' => SourceWebsite::class,
            'source_text' => SourceText::class,
            'source_youtube_video' => SourceYoutubeVideo::class,
            'agent_website_url' => AgentWebsiteUrl::class,
        ]);
    }

    // Catch lazy loading in dev/test — N+1s should fail loudly in CI
    // rather than ship to production. Disabled in production so a missed
    // `with(...)` still serves traffic.
    private function configureModelStrictness(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
    }

    // Prompts are Blade views (markdown + Blade) loaded via the `prompt()`
    // helper, e.g. prompt('knowledge/system') → resources/prompts/knowledge/system.blade.php.
    private function registerPromptViewNamespace(): void
    {
        View::addNamespace('prompts', resource_path('prompts'));
    }

    // Models live across many App\* and Domain\* namespaces, but every
    // factory lives flat under Database\Factories\. Map a model to its
    // factory by basename.
    private function configureFactoryResolution(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelClass): string => 'Database\\Factories\\'.class_basename($modelClass).'Factory'
        );
    }

    // Behind a proxy that ends TLS, PHP sees plain HTTP, so links would come out
    // as http://. An http:// APP_URL means there is no TLS at all (a first run
    // of the Docker image on localhost), and forcing https would break it.
    private function forceHttpsInProduction(): void
    {
        if (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    private function extendSocialiteProviders(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });
    }
}
