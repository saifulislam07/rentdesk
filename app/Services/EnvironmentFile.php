<?php

namespace App\Services;

use RuntimeException;

/**
 * Reads and updates KEY=value lines of a .env file, leaving everything else as it was.
 */
class EnvironmentFile
{
    public function __construct(private readonly string $path) {}

    /**
     * Set the given keys, replacing existing lines and appending new ones.
     * A missing file is started from .env.example when that exists.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        $contents = $this->read();

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents)
                : rtrim($contents)."\n".$line."\n";
        }

        if (file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException("The file {$this->path} is not writable.");
        }
    }

    public function get(string $key): ?string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $this->read(), $match)) {
            return null;
        }

        return trim($match[1], " \t\"'");
    }

    private function read(): string
    {
        if (is_file($this->path)) {
            return (string) file_get_contents($this->path);
        }

        $example = dirname($this->path).DIRECTORY_SEPARATOR.'.env.example';

        return is_file($example) ? (string) file_get_contents($example) : '';
    }

    /**
     * Quote anything dotenv could misread: spaces, #, quotes, $ and backslashes.
     * Single quotes make dotenv take the value literally.
     */
    private function format(string|int|bool|null $value): string
    {
        $value = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            default => str_replace(["\r", "\n"], '', (string) $value),
        };

        return preg_match('/[\s#"\'$\\\\]/', $value) ? "'".str_replace("'", "\\'", $value)."'" : $value;
    }
}
