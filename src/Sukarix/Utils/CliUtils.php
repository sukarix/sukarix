<?php

declare(strict_types=1);

namespace Sukarix\Utils;

use Colors\Color;
use Sukarix\Core\Tailored;

/**
 * @codeCoverageIgnore
 */
class CliUtils extends Tailored
{
    /**
     * @var Color
     */
    private $console;

    public function __construct()
    {
        $this->console = new Color();
        $this->console->setUserStyles(
            [
                'passed' => ['white', 'bg_green'],
                'failed' => ['white', 'bg_red'],
            ]
        );
    }

    public function writeTestResult($data, $group): void
    {
        if ($this->isCli()) {
            $console = $this->console;
            if ($data['status']) {
                $text = "<passed><bold>SUCCESS</bold></passed> :: {$group} :: {$data['text']}";
            } else {
                $text = "<failed><bold>FAILED </bold></failed> :: {$group} :: {$data['text']} => <failed>{$data['source']}</failed>";
            }
            echo $console($text)->colorize() . PHP_EOL;

            ob_flush();
            flush();
        }
    }

    /**
     * @param       $suite array
     * @param mixed $name
     */
    public function writeSuiteResult($suite, $name): void
    {
        if ($this->isCli()) {
            $testsNumber      = 0;
            $successfullTests = 0;
            foreach ($suite as $key => $value) {
                $testsNumber += \count($suite[$key]);
                $successfullTests += \count(array_filter(array_column($suite[$key], 'status')));
            }

            $console = $this->console;
            if ($testsNumber === $successfullTests) {
                $text = ":::::::<bold><passed> ✔ </passed> {$name} => <passed>{$successfullTests}/{$testsNumber}</passed></bold>";
                echo $console($text)->colorize() . PHP_EOL;
            } else {
                $text = ":::::::<bold><failed> ✘ </failed> {$name} => <failed>{$successfullTests}/{$testsNumber}</failed></bold>";
                echo $console($text)->colorize() . PHP_EOL;
            }

            ob_flush();
            flush();
        }
    }

    public function write($message): void
    {
        if ($this->isCli()) {
            $console = $this->console;
            echo $console($message)->colorize() . PHP_EOL;

            ob_flush();
            flush();
        }
    }

    private function isCli()
    {
        return \PHP_SAPI === 'cli';
    }
}
