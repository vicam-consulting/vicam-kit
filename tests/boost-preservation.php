<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

$root = $argv[1] ?? throw new RuntimeException('Pass the fixture directory.');
require $root.'/vendor/autoload.php';
$helper = dirname(__DIR__).'/src/Support/regenerate-boost.php.stub';
$seed = [
    'boost.json' => json_encode([
        'agents' => ['codex'], 'packages' => ['custom/package'], 'guidelines' => true,
        'skills' => ['custom-skill'], 'mcp' => true, 'custom-setting' => ['retain' => true],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    'AGENTS.md' => "User-owned instructions.\n\n\nKeep this whitespace.\n",
    '.codex/config.toml' => "[mcp_servers.laravel-boost]\ncommand = \"my-wrapper\"\ncustom = true\n",
    '.agents/skills/custom-skill/SKILL.md' => "# User-owned skill\n",
    '.ai/guidelines/review-sentinel.md' => "# Review sentinel guideline\nPreserve this custom guideline.\n",
];
$original = [];
foreach ($seed as $path => $contents) {
    $full = $root.'/'.$path;
    $original[$full] = is_file($full) ? file_get_contents($full) : null;
    if (! is_dir(dirname($full))) {
        mkdir(dirname($full), 0755, true);
    }
    file_put_contents($full, $contents);
}
try {
    $run = static function () use ($root, $helper): string {
        $process = new Process([PHP_BINARY, $helper, $root, ''], $root);
        $process->setTimeout(120);
        $process->mustRun();

        return $process->getOutput();
    };
    echo $run();
    $once = file_get_contents($root.'/AGENTS.md');
    if ($once === false) {
        throw new RuntimeException('Cannot read generated instructions.');
    }
    if (! str_starts_with($once, $seed['AGENTS.md']) || ! str_contains($once, 'Review sentinel guideline')) {
        throw new RuntimeException('Boost did not preserve user text or discover source guidance.');
    }
    echo $run();
    if (file_get_contents($root.'/AGENTS.md') !== $once) {
        throw new RuntimeException('Boost regeneration was not byte-idempotent.');
    }
    foreach ($seed as $path => $contents) {
        if ($path !== 'AGENTS.md' && file_get_contents($root.'/'.$path) !== $contents) {
            throw new RuntimeException("Boost changed preserved state: {$path}");
        }
    }
    echo "Boost preservation and idempotence checks passed.\n";
    $external = tempnam(sys_get_temp_dir(), 'vicam-boost-sentinel-');
    if ($external === false) {
        throw new RuntimeException('Cannot create external sentinel.');
    }
    file_put_contents($external, 'external sentinel');
    unlink($root.'/AGENTS.md');
    symlink($external, $root.'/AGENTS.md');
    try {
        $process = new Process([PHP_BINARY, $helper, $root, ''], $root);
        $process->run();
        if ($process->isSuccessful() || file_get_contents($external) !== 'external sentinel') {
            throw new RuntimeException('Boost did not reject the symlink safely. Exit: '.$process->getExitCode().' '.$process->getOutput().$process->getErrorOutput());
        }
    } finally {
        unlink($root.'/AGENTS.md');
        unlink($external);
        file_put_contents($root.'/AGENTS.md', $once);
    }
    echo "Boost symlink rejection passed.\n";
    if (file_exists($root.'/CLAUDE.md')) {
        throw new RuntimeException('Rollback test requires a fixture without CLAUDE.md.');
    }
    mkdir($root.'/CLAUDE.md');
    $rollbackConfig = json_decode($seed['boost.json'], true, flags: JSON_THROW_ON_ERROR);
    $rollbackConfig['agents'] = ['codex', 'claude_code'];
    file_put_contents($root.'/boost.json', json_encode($rollbackConfig, JSON_THROW_ON_ERROR));
    file_put_contents($root.'/.ai/guidelines/review-sentinel.md', "# Changed for rollback test\n");
    try {
        $process = new Process([PHP_BINARY, $helper, $root, ''], $root);
        $process->run();
        if ($process->isSuccessful() || file_get_contents($root.'/AGENTS.md') !== $once) {
            throw new RuntimeException('Boost did not roll back the first agent after the second agent failed.');
        }
    } finally {
        rmdir($root.'/CLAUDE.md');
    }
    echo "Boost multi-agent rollback passed.\n";
} finally {
    foreach ($original as $path => $contents) {
        if ($contents === null) {
            if (is_file($path)) {
                unlink($path);
            }
        } else {
            file_put_contents($path, $contents);
        }
    }
}
