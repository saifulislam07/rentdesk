<?php

/**
 * Builds the CodeCanyon upload: codecanyon/1-main-file/RentDesk-v<version>.zip
 *
 *   php build/package.php              build assets, install production vendor, zip
 *   php build/package.php --skip-assets  use the public/build that is already there
 *
 * Needs PHP with the zip extension, Composer and (unless --skip-assets) npm on the PATH.
 * The zip holds two folders: "rentdesk" (the application, ready to upload to a server,
 * with vendor/ and public/build/ included) and "documentation" (the buyer's guide).
 * Nothing in your working copy is changed apart from public/build when assets are rebuilt.
 */

define('ROOT', dirname(__DIR__));

/**
 * Top-level entries that never go to buyers: local settings, tooling, AI and editor files,
 * dependencies (production vendor/ is installed fresh) and the build output itself.
 */
const EXCLUDED_TOP_LEVEL = [
    '.env', '.env.backup', '.env.production', '.git', '.github', '.claude', '.codex', '.cursor', '.idea',
    '.vscode', '.zed', '.mcp.json', '.phpunit.cache', '.phpunit.result.cache', '.phpactor.json', 'auth.json',
    'boost.json', 'AGENTS.md', 'CLAUDE.md', 'code_instruction.md', 'node_modules', 'vendor', 'build', 'dist', 'codecanyon',
    'documentation', 'Homestead.json', 'Homestead.yaml', '_ide_helper.php',
];

/**
 * Paths whose contents are runtime data. Only their .gitignore placeholders are shipped, so the
 * folders exist on the server but none of your local uploads, logs, caches or install lock leak out.
 */
const RUNTIME_FOLDERS = ['storage', 'bootstrap/cache'];

const EXCLUDED_FILES = ['public/hot', 'database/database.sqlite'];

const EXCLUDED_FOLDERS = ['public/storage'];

/** Files that must be in the finished package. */
const REQUIRED = [
    'artisan', '.env.example', '.htaccess', 'public/index.php', 'public/.htaccess', 'public/build/manifest.json',
    'vendor/autoload.php', 'vendor/fakerphp/faker', 'storage/framework/views/.gitignore', 'bootstrap/cache/.gitignore',
];

main($argv);

function main(array $argv): void
{
    if (! class_exists(ZipArchive::class)) {
        fail('The PHP zip extension is required.');
    }

    $version = option($argv, 'version') ?? versionFromChangelog();
    $stage = ROOT.'/build/stage';
    $app = $stage.'/rentdesk';
    $zipPath = ROOT."/codecanyon/1-main-file/RentDesk-v{$version}.zip";

    step("Packaging RentDesk {$version}");

    if (! in_array('--skip-assets', $argv, true)) {
        step('Building front-end assets');
        run('npm run build', ROOT);
    }

    if (! is_file(ROOT.'/public/build/manifest.json')) {
        fail('public/build/manifest.json is missing. Run "npm run build" first.');
    }

    if (is_file(ROOT.'/public/hot')) {
        echo "  note: public/hot exists (the Vite dev server is running); it is left out of the package.\n";
    }

    removeDirectory($stage);
    mkdir($app, 0755, true);

    step('Copying application files');
    copyApplication(ROOT, $app);

    step('Installing production dependencies');

    // Starting from your vendor/ keeps this fast and offline; Composer then removes the dev packages.
    if (is_dir(ROOT.'/vendor')) {
        copyDirectory(ROOT.'/vendor', $app.'/vendor');
    }

    putenv('COMPOSER_DISCARD_CHANGES=true');
    run('composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --prefer-dist', $app);

    // Packages installed from source carry their own .git folders; buyers do not need them.
    foreach (gitFolders($app.'/vendor') as $gitFolder) {
        removeDirectory($gitFolder);
    }

    // Laravel rebuilds these caches on the buyer's server; stale ones would point at dev packages.
    foreach (glob($app.'/bootstrap/cache/*.php') as $cached) {
        unlink($cached);
    }

    step('Adding documentation');
    copyDirectory(ROOT.'/documentation', $stage.'/documentation');

    verify($app);

    step('Writing '.relative($zipPath));
    @mkdir(dirname($zipPath), 0755, true);
    foreach (glob(dirname($zipPath).'/RentDesk-v*.zip') as $previous) {
        unlink($previous);
    }
    zipDirectory($stage, $zipPath);

    removeDirectory($stage);

    printf("\nDone: %s (%.1f MB)\n", relative($zipPath), filesize($zipPath) / 1048576);
}

function copyApplication(string $from, string $to): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! isExcluded(relativeTo($file->getPathname(), $from), $file->isDir()),
        ),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        $target = $to.'/'.relativeTo($file->getPathname(), $from);

        if ($file->isDir()) {
            @mkdir($target, 0755, true);
        } else {
            @mkdir(dirname($target), 0755, true);
            copy($file->getPathname(), $target);
        }
    }
}

function isExcluded(string $path, bool $isDir): bool
{
    $top = explode('/', $path)[0];

    if (in_array($top, EXCLUDED_TOP_LEVEL, true)) {
        return true;
    }

    // Project notes and plans: only the readme and changelog are for buyers.
    if (! str_contains($path, '/') && ! $isDir && str_ends_with(strtolower($path), '.md')) {
        return ! in_array($path, ['README.md', 'CHANGELOG.md'], true);
    }

    if (in_array($path, EXCLUDED_FILES, true) || in_array($path, EXCLUDED_FOLDERS, true)) {
        return true;
    }

    if (in_array(basename($path), ['.DS_Store', 'Thumbs.db', 'desktop.ini'], true)) {
        return true;
    }

    foreach (RUNTIME_FOLDERS as $folder) {
        if (str_starts_with($path, $folder.'/') && ! $isDir && basename($path) !== '.gitignore') {
            return true;
        }
    }

    return false;
}

function verify(string $app): void
{
    step('Checking the package');

    foreach (REQUIRED as $path) {
        if (! file_exists($app.'/'.$path)) {
            fail("Missing from the package: {$path}");
        }
    }

    $forbidden = ['.env', '.git', 'node_modules', 'public/hot', 'public/storage', 'storage/app/installed.lock', 'database/database.sqlite', 'CLAUDE.md', 'vendor/laravel/boost', 'vendor/phpunit'];

    foreach ($forbidden as $path) {
        if (file_exists($app.'/'.$path)) {
            fail("Must not be in the package: {$path}");
        }
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app.'/storage', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getFilename() !== '.gitignore') {
            fail('Runtime file in the package: '.relativeTo($file->getPathname(), $app));
        }
    }

    if (str_contains((string) file_get_contents($app.'/.env.example'), 'APP_DEBUG=true')) {
        fail('.env.example must ship with APP_DEBUG=false.');
    }

    echo "  ok\n";
}

/**
 * @return list<string>
 */
function gitFolders(string $directory): array
{
    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir() && $file->getFilename() === '.git') {
            $found[] = $file->getPathname();
        }
    }

    return $found;
}

function zipDirectory(string $directory, string $zipPath): void
{
    $zip = new ZipArchive;

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("Cannot create {$zipPath}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        $name = relativeTo($file->getPathname(), $directory);
        $file->isDir() ? $zip->addEmptyDir($name) : $zip->addFile($file->getPathname(), $name);
    }

    $zip->close();
}

function copyDirectory(string $from, string $to): void
{
    if (! is_dir($from)) {
        fail(relative($from).' is missing.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    @mkdir($to, 0755, true);

    foreach ($iterator as $file) {
        $target = $to.'/'.relativeTo($file->getPathname(), $from);

        if ($file->isDir()) {
            @mkdir($target, 0755, true);
        } else {
            @mkdir(dirname($target), 0755, true);
            copy($file->getPathname(), $target);
        }
    }
}

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

function run(string $command, string $cwd): void
{
    echo "  > {$command}\n";
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $cwd);
    $status = is_resource($process) ? proc_close($process) : 1;

    if ($status !== 0) {
        fail("Command failed ({$status}): {$command}");
    }
}

function versionFromChangelog(): string
{
    $changelog = (string) @file_get_contents(ROOT.'/CHANGELOG.md');

    return preg_match('/^##\s+v?(\d+\.\d+\.\d+)/m', $changelog, $match) ? $match[1] : '1.0.0';
}

function option(array $argv, string $name): ?string
{
    foreach ($argv as $argument) {
        if (str_starts_with($argument, "--{$name}=")) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return null;
}

function relativeTo(string $path, string $base): string
{
    $path = str_replace('\\', '/', $path);
    $base = rtrim(str_replace('\\', '/', $base), '/');

    return ltrim(substr($path, strlen($base)), '/');
}

function relative(string $path): string
{
    return relativeTo(realpath($path) ?: $path, realpath(ROOT));
}

function step(string $message): void
{
    echo "\n== {$message}\n";
}

function fail(string $message): never
{
    fwrite(STDERR, "\nError: {$message}\n");
    exit(1);
}
