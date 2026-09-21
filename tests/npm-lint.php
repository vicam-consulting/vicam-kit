<?php

// Run: php tests/npm-lint.php
// Integration: php tests/npm-lint.php --smoke /path/to/disposable/vue-starter-kit
require dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/src/Commands/InstallCommand.php';

use Illuminate\Console\OutputStyle;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Vicam\VicamKit\Commands\InstallCommand;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function install(string $directory): array
{
    $app = new Application($directory);
    $output = new BufferedOutput;
    Prompt::setOutput($output);
    $command = new InstallCommand;
    $command->setLaravel($app);
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
    (new ReflectionMethod($command, 'addNpmScriptsAndDeps'))->invoke($command);

    return [$output->fetch(), (new ReflectionProperty($command, 'failedSteps'))->getValue($command) !== []];
}

$files = new Filesystem;
$root = sys_get_temp_dir().'/vicam-npm-test-'.bin2hex(random_bytes(6));
$files->makeDirectory($root.'/bin', 0755, true);
$originalPath = getenv('PATH');

try {
    // Capture the actual Process arguments, without contacting npm for regression checks.
    $files->put($root.'/bin/npm', "#!/usr/bin/env php\n<?php\nfile_put_contents(getcwd().'/npm-args.json', json_encode(array_slice(\$argv, 1)));\nexit(file_exists(getcwd().'/fail') ? 1 : 0);\n");
    chmod($root.'/bin/npm', 0755);
    putenv('PATH='.$root.'/bin:'.$originalPath);
    $files->put($root.'/package.json', json_encode(['scripts' => ['format' => 'custom-format']]));
    [, $failed] = install($root);
    check(! $failed, 'Successful npm install marked incomplete');
    $args = json_decode($files->get($root.'/npm-args.json'), true);
    check(array_slice($args, 0, 2) === ['install', '--save-dev'], 'Expected normal npm peer resolution');
    $packages = array_slice($args, 2);
    check(in_array('eslint@^9.39.5', $packages, true), 'ESLint must stay on 9');
    check(in_array('@eslint/js@^9.39.5', $packages, true), 'ESLint JS config must stay on 9');
    check(count($packages) === 12, 'Unexpected lint dependency set');
    foreach ($packages as $package) {
        check(preg_match('/@\^\d+\.\d+\.\d+$/', $package) === 1, 'Unbounded dependency: '.$package);
    }
    $packageJson = json_decode($files->get($root.'/package.json'), true);
    check($packageJson['scripts']['lint'] === 'eslint . --fix', 'Missing lint script');
    check($packageJson['scripts']['lint:types'] === 'vue-tsc --noEmit', 'Missing type script');
    check($packageJson['scripts']['format'] === 'custom-format', 'Existing script overwritten');
    $files->put($root.'/package.json', '{"scripts":{"format":"custom-format"}}');
    $beforeFailure = $files->get($root.'/package.json');
    $files->put($root.'/fail', '');
    [$output, $failed] = install($root);
    check($failed, 'Failed npm install must mark setup incomplete');
    check($files->get($root.'/package.json') === $beforeFailure, 'Failed install added unusable scripts');
    check(str_contains($output, 'Setup is incomplete'), 'Missing incomplete setup warning');
    check(str_contains($output, 'npm install --save-dev '.implode(' ', $packages)), 'Fallback differs from executed command');
    $files->delete($root.'/package.json', $root.'/npm-args.json');
    [$output] = install($root);
    check(str_contains($output, 'package.json not found'), 'Missing package.json should be skipped');
    check(! $files->exists($root.'/npm-args.json'), 'npm ran without package.json');
    echo "npm installer regression checks passed.\n";
} finally {
    putenv('PATH='.$originalPath);
    $files->deleteDirectory($root);
}

if (($argv[1] ?? '') === '--smoke') {
    $directory = realpath($argv[2] ?? '');
    check($directory !== false && is_file($directory.'/package.json'), 'Supply a disposable Vue starter directory');
    [$output, $failed] = install($directory);
    echo $output;
    check(! $failed, 'Real npm dependency resolution failed');
    $files->copy(dirname(__DIR__).'/stubs/lint-configs/eslint.config.js', $directory.'/eslint.config.js');
    $files->put($directory.'/resources/js/VicamSmoke.vue', <<<'VUE'
<script setup lang="ts">
import { ref } from 'vue';

const count = ref<number>(0);
</script>

<template>
    <button @click="count++">{{ count }}</button>
</template>
VUE);
    foreach ([['npm', 'ls', '--depth=0'], ['node_modules/.bin/eslint', 'resources/js/VicamSmoke.vue'], ['node_modules/.bin/prettier', 'resources/js/VicamSmoke.vue', '--plugin=prettier-plugin-tailwindcss', '--write']] as $args) {
        $process = new Process($args, $directory);
        $process->setTimeout(120);
        $process->mustRun();
        echo $process->getOutput();
    }
    echo "Real npm resolution and Vue/TypeScript lint smoke checks passed.\n";
}
