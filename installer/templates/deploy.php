<?php

// Deploys Chatvia on this server. Written by the Chatvia installer, which
// replaces this file on an upgrade as long as you have not changed it.
//
// Every release goes into a folder of its own in __DEPLOY_PATH__/releases,
// and `current` points at the one that runs. A deploy builds the new release
// next to it and switches only when every step worked. As chatvia
// (sudo -iu chatvia), in ~/deploy:
//
//   vendor/bin/dep deploy --tag=v1.2.3                 deploy a release
//   vendor/bin/dep rollback && vendor/bin/dep artisan:reload
//                                                      back to the one before
//   vendor/bin/dep releases                            list the releases
//
// It runs on this server (localhost), as chatvia. To deploy from your own
// computer instead, copy this file there and replace localhost() with
// host('chat.example.com')->set('remote_user', 'chatvia').
// More: https://chatvia.ai/docs/guide/installation/deployer

namespace Deployer;

require 'recipe/laravel.php';

set('repository', '__REPOSITORY__');
set('keep_releases', 5);

// One user runs PHP-FPM, Horizon, Reverb and the scheduler, so a release
// needs no extra write permissions.
set('writable_mode', 'skip');

localhost()
    ->set('deploy_path', '__DEPLOY_PATH__');

desc('Creates the storage folders Laravel needs');
task('chatvia:storage', function () {
    run('mkdir -p {{deploy_path}}/shared/storage/framework/cache/data {{deploy_path}}/shared/storage/framework/sessions {{deploy_path}}/shared/storage/framework/views');
});

desc('Builds the frontend and the chat widget');
task('chatvia:build', function () {
    cd('{{release_path}}');
    run('npm ci --no-audit --no-fund');
    run('npm run build');
    run('npm run shakomako:build');
});

desc('Keeps every chat widget version online, in a folder all releases share');
task('chatvia:widget', function () {
    run('mkdir -p {{deploy_path}}/shared/shakomako');
    // A page that loaded an older widget still loads files from that
    // version's folder, so the versions of earlier releases stay. A folder
    // that is already there holds the same build and is skipped.
    run('for dir in {{current_path}}/public/shakomako/*/ {{release_path}}/public/shakomako/*/; do [ -d "$dir" ] || continue; version=$(basename "$dir"); [ -e "{{deploy_path}}/shared/shakomako/$version" ] || cp -R "${dir%/}" "{{deploy_path}}/shared/shakomako/$version"; done');
    // embed.js and version.json pick the version that loads. They stay with
    // the release until it goes live (chatvia:widget:publish).
    run('mv {{release_path}}/public/shakomako {{release_path}}/.shakomako');
    run('{{bin/symlink}} {{deploy_path}}/shared/shakomako {{release_path}}/public/shakomako');
});

desc('Makes the chat widget load the version of the release that runs');
task('chatvia:widget:publish', function () {
    // A release from before chatvia:widget serves its own public/shakomako.
    if (! test('[ -d {{current_path}}/.shakomako ]')) {
        return;
    }

    // Copied, then renamed, so nginx never serves a half-written file.
    run('cd {{current_path}}/.shakomako && for file in *; do [ -f "$file" ] || continue; cp "$file" "{{deploy_path}}/shared/shakomako/.$file.tmp" && mv -f "{{deploy_path}}/shared/shakomako/.$file.tmp" "{{deploy_path}}/shared/shakomako/$file"; done');
});

// The release the admin panel shows and compares with the newest one: the
// tag without its "v", or for a branch its name and commit (not a release,
// so the panel looks for no updates). The Docker image writes the same file.
desc('Saves the version of this release in its VERSION file');
task('chatvia:version', function () {
    run('cd {{release_path}} && case {{target}} in v[0-9]*) version={{target}}; version=${version#v} ;; *) version="{{target}}@$(cut -c1-7 REVISION)" ;; esac && printf \'%s\\n\' "$version" > VERSION');
});

// In place of the recipe's plain migrate: chatvia:deploy:migrate creates the
// analytics, Pulse and Spectra databases, migrates, checks the embedding
// settings and adds the built-in tools, the same steps the Docker image runs.
desc('Migrates the database and runs the checks that go with it');
task('artisan:migrate', artisan('chatvia:deploy:migrate --no-interaction', ['skipIfNoEnv', 'showOutput']));

after('deploy:update_code', 'chatvia:version');
after('deploy:shared', 'chatvia:storage');
after('deploy:vendors', 'chatvia:build');
after('chatvia:build', 'chatvia:widget');
after('deploy:symlink', 'chatvia:widget:publish');
after('rollback', 'chatvia:widget:publish');
after('deploy:failed', 'deploy:unlock');
