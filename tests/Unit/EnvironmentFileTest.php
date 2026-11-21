<?php

namespace Tests\Unit;

use App\Services\EnvironmentFile;
use PHPUnit\Framework\TestCase;

class EnvironmentFileTest extends TestCase
{
    private string $directory;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rentdesk-env-'.uniqid();
        mkdir($this->directory);
        $this->path = $this->directory.DIRECTORY_SEPARATOR.'.env';
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter(glob($this->directory.DIRECTORY_SEPARATOR.'{,.}*', GLOB_BRACE | GLOB_NOSORT) ?: [], 'is_file'));
        @rmdir($this->directory);

        parent::tearDown();
    }

    private function env(string $contents): EnvironmentFile
    {
        file_put_contents($this->path, $contents);

        return new EnvironmentFile($this->path);
    }

    public function test_existing_keys_are_replaced_and_everything_else_is_kept(): void
    {
        $file = $this->env("# comment\nAPP_NAME=Laravel\nAPP_DEBUG=true\n\nDB_HOST=127.0.0.1\n");

        $file->set(['APP_NAME' => 'RentDesk', 'APP_DEBUG' => false]);

        $this->assertSame("# comment\nAPP_NAME=RentDesk\nAPP_DEBUG=false\n\nDB_HOST=127.0.0.1\n", file_get_contents($this->path));
    }

    public function test_missing_keys_are_appended(): void
    {
        $file = $this->env("APP_NAME=Laravel\n");

        $file->set(['RENTDESK_CURRENCY' => 'EUR']);

        $this->assertSame("APP_NAME=Laravel\nRENTDESK_CURRENCY=EUR\n", file_get_contents($this->path));
    }

    public function test_a_key_is_not_confused_with_one_that_merely_starts_the_same(): void
    {
        $file = $this->env("DB_PASSWORD_HINT=keep\nDB_PASSWORD=old\n");

        $file->set(['DB_PASSWORD' => 'new']);

        $this->assertSame("DB_PASSWORD_HINT=keep\nDB_PASSWORD=new\n", file_get_contents($this->path));
    }

    public function test_values_dotenv_could_misread_are_single_quoted(): void
    {
        $file = $this->env("DB_PASSWORD=\n");

        foreach (['pa ss' => "'pa ss'", 'a#b' => "'a#b'", 'cost$5' => "'cost\$5'", 'say "hi"' => "'say \"hi\"'", "it's" => "'it\\'s'", 'back\\slash' => "'back\\slash'"] as $value => $expected) {
            $file->set(['DB_PASSWORD' => $value]);

            $this->assertSame("DB_PASSWORD={$expected}\n", file_get_contents($this->path), "for {$value}");
        }
    }

    public function test_replacement_text_is_not_treated_as_a_regex_reference(): void
    {
        $file = $this->env("DB_PASSWORD=x\n");

        $file->set(['DB_PASSWORD' => 'a$1b\\0c']);

        $this->assertSame('a$1b\\0c', substr(trim(file_get_contents($this->path)), strlen("DB_PASSWORD='"), -1));
    }

    public function test_line_breaks_cannot_inject_extra_settings(): void
    {
        $file = $this->env("APP_NAME=x\n");

        $file->set(['APP_NAME' => "Shop\nAPP_DEBUG=true"]);

        $this->assertSame("APP_NAME=ShopAPP_DEBUG=true\n", file_get_contents($this->path));
        $this->assertNull($file->get('APP_DEBUG'));
    }

    public function test_values_can_be_read_back(): void
    {
        $file = $this->env("A=plain\nB='quoted value'\nC=\n");

        $this->assertSame('plain', $file->get('A'));
        $this->assertSame('quoted value', $file->get('B'));
        $this->assertSame('', $file->get('C'));
        $this->assertNull($file->get('MISSING'));
    }

    public function test_a_missing_file_starts_from_the_example(): void
    {
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Laravel\nAPP_KEY=\n");
        $file = new EnvironmentFile($this->path);

        $file->set(['APP_KEY' => 'base64:abc']);

        $this->assertSame("APP_NAME=Laravel\nAPP_KEY=base64:abc\n", file_get_contents($this->path));
    }

    public function test_a_missing_file_without_an_example_is_created(): void
    {
        (new EnvironmentFile($this->path))->set(['APP_KEY' => 'k']);

        $this->assertSame("\nAPP_KEY=k\n", file_get_contents($this->path));
    }
}
