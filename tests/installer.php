<?php

require dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/src/Commands/InstallCommand.php';

use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Vicam\VicamKit\Commands\InstallCommand;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function installer(string $root, ?int $boostExit = 0): array
{
    $app = new Application($root);
    $command = new InstallCommand;
    $command->setLaravel($app);
    $console = new ConsoleApplication;
    $console->addCommand($command);
    if ($boostExit !== null) {
        $console->addCommand((new Command('boost:install'))->setCode(fn () => $boostExit));
    }
    $input = new ArrayInput([], $command->getDefinition());
    $input->setInteractive(false);
    (new ReflectionProperty($command, 'input'))->setValue($command, $input);
    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle($input, $output));
    Prompt::interactive(false);
    Prompt::setOutput($output);

    return [$command, $output];
}

$files = new Filesystem;
$root = sys_get_temp_dir().'/vicam-installer-test-'.bin2hex(random_bytes(6));
$originalPath = getenv('PATH');
$files->makeDirectory($root.'/bin', 0755, true);
$fake = <<<'FAKE'
#!/usr/bin/env php
<?php
$args = array_slice($argv, 1);
if (in_array('interactive-auth', $args, true)) {
    echo "Simulated authentication prompt: type test-token and press Enter: ";
    exit(trim(fgets(STDIN)) === 'test-token' ? 0 : 1);
}
file_put_contents(getcwd().'/calls.jsonl', json_encode($args)."\n", FILE_APPEND);
$fail = @file_get_contents(getcwd().'/fail');
if ($fail && in_array($fail, $args, true)) {
    fwrite(STDERR, "Could not authenticate against github.com\n");
    exit(1);
}
$file = basename($argv[0]) === 'npm' ? 'package.json' : 'composer.json';
$json = json_decode(file_get_contents($file), true);
$json['extra']['installed-by-test'] = true;
file_put_contents($file, json_encode($json));
FAKE;
try {
    foreach (['composer', 'npm'] as $binary) {
        $files->put($root.'/bin/'.$binary, $fake);
        chmod($root.'/bin/'.$binary, 0755);
    }
    putenv('PATH='.$root.'/bin:'.$originalPath);
    if (($argv[1] ?? '') === '--tty') {
        [$command] = installer($root);
        $input = (new ReflectionProperty($command, 'input'))->getValue($command);
        $input->setInteractive(true);
        $process = (new ReflectionMethod($command, 'dependencyProcess'))->invoke($command, ['composer', 'require', 'interactive-auth']);
        check($process->isTty(), 'Run this check in an interactive terminal');
        check(! str_contains($process->getCommandLine(), '--no-interaction'), 'Composer authentication is disabled');
        check($process->getTimeout() === null, 'Interactive authentication should not time out');
        check($process->run() === 0, 'Composer did not receive terminal input');
        echo "Interactive Composer authentication check passed.\n";

        return;
    }
    foreach (['php-failure', 'npm-failure', 'data-failure', 'transformer-failure', 'boost-failure', 'missing-boost', 'success'] as $case) {
        $dir = $root.'/'.$case;
        $files->makeDirectory($dir.'/bootstrap', 0755, true);
        $files->put($dir.'/bootstrap/providers.php', "<?php\nreturn [\n];\n");
        $files->put($dir.'/composer.json', '{"scripts":{"existing":"keep"}}');
        $files->put($dir.'/package.json', '{"scripts":{"format":"keep"}}');
        $fail = match ($case) {
            'php-failure' => 'laravel/pint',
            'npm-failure' => '--save-dev',
            'data-failure' => 'spatie/laravel-data',
            'transformer-failure' => 'spatie/laravel-typescript-transformer',
            default => null,
        };
        if ($fail) {
            $files->put($dir.'/fail', $fail);
        }
        // Stand-in for the fresh Laravel process used after installing Boost.
        $files->put($dir.'/artisan', "<?php file_put_contents(__DIR__.'/boost-ran', 'yes'); exit(0);");
        [$command, $output] = installer($dir, $case === 'missing-boost' ? null : ($case === 'boost-failure' ? 1 : 0));
        $exit = $command->handle();
        $text = $output->fetch();
        $success = in_array($case, ['success', 'missing-boost'], true);
        check($exit === ($success ? 0 : 1), $case.': wrong exit status');
        check(str_contains($text, 'Vicam Kit installed:') === $success, $case.': misleading success message');
        $composer = json_decode($files->get($dir.'/composer.json'), true);
        check($composer['scripts']['existing'] === 'keep', 'Existing Composer script changed');
        if ($case === 'php-failure') {
            check(! isset($composer['scripts']['test:types']), 'PHP failure added scripts');
            check(! $files->exists($dir.'/rector.php'), 'PHP failure installed lint configs');
            check(count(file($dir.'/calls.jsonl')) === 1, 'Installer continued after Composer failure');
        }
        if ($case === 'npm-failure') {
            check(! isset(json_decode($files->get($dir.'/package.json'), true)['scripts']['lint']), 'npm failure added scripts');
        }
        if (in_array($case, ['php-failure', 'npm-failure', 'data-failure', 'transformer-failure'], true)) {
            check(! $files->exists($dir.'/app/Providers/TypeScriptTransformerServiceProvider.php'), 'Registered an unavailable provider');
        }
        if ($success) {
            check($composer['extra']['installed-by-test'] === true, 'Composer changes overwritten');
            check($files->exists($dir.'/app/Providers/TypeScriptTransformerServiceProvider.php'), 'Provider not installed');
            check($files->exists($dir.'/rector.php'), 'Lint config not installed');
        }
        if ($case === 'missing-boost') {
            check($files->exists($dir.'/boost-ran'), 'Newly installed Boost was not run');
        }
        // A failed installation can be resumed without --force.
        if ($fail) {
            $files->delete($dir.'/fail');
            check($command->handle() === 0, $case.': rerun did not recover');
        }
        $process = (new ReflectionMethod($command, 'dependencyProcess'))->invoke($command, ['composer', 'require', 'example/package']);
        check(str_contains($process->getCommandLine(), '--no-interaction'), 'Noninteractive install can hang on auth');
        check(str_contains($process->getCommandLine(), '--prefer-dist'), 'Composer must prefer distributions');
        check($process->getTimeout() === 600.0, 'Noninteractive timeout too short');
    }
    // Exercise actual process startup failure without throwing out of the installer.
    [$command] = installer($root.'/success');
    $manifest = json_decode($files->get($root.'/success/composer.json'), true);
    $manifest['require']['laravel/framework'] = '^13.0';
    $files->put($root.'/success/composer.json', json_encode($manifest));
    $callsBefore = $files->get($root.'/success/calls.jsonl');
    $ok = (new ReflectionMethod($command, 'installDependencies'))->invoke($command, ['composer', 'require', 'laravel/framework'], 'existing dependency');
    check($ok, 'Already installed dependency reported failure');
    check($files->get($root.'/success/calls.jsonl') === $callsBefore, 'Already installed dependency triggered a network operation');
    $ok = (new ReflectionMethod($command, 'installDependencies'))->invoke($command, ['/vicam-test-missing-executable'], 'missing executable');
    check($ok === false, 'Missing executable reported success');
    echo "Installer failure, recovery, and success checks passed.\n";
} finally {
    putenv('PATH='.$originalPath);
    $files->deleteDirectory($root);
}
