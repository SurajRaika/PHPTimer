<?php

class TimeCLI
{
    private $initialPosition;
    private $count = 1;
    private $ver_space = 8;

    private $Letters;
    private $pause = false;
    private $maxCount = 5000;
    private $TimeIndecator;

    private $delay = 1000000; // 1 second
    private $running = true;

    public function __construct()
    {
        // Check terminal width
        $this->checkTerminalWidth();

        require_once "./Letters.php";

        for ($p = 0; $p < $this->ver_space; $p++) {
            echo "\n";
        }
        $this->Letters = $Letters;

        $this->initialPosition = $this->getCursorPosition();
        if ($this->initialPosition === null) {
            throw new Exception("Failed to get cursor position.");
        }
        $this->initialPosition["row"] = $this->initialPosition["row"] + 1;
        $this->TimeIndecator = ['0', '0', ':', '0', '0', ':', '0', '0'];

        $this->setupTerminal();
    }

    private function checkTerminalWidth()
    {
        $termWidth = (int)exec('tput cols');
        if ($termWidth <= 75) {
            echo "Terminal width is too small. Please resize your terminal window to be wider than 75 characters.\n";
            exit(1);
        }
    }

    private function getCursorPosition()
    {
        system('stty -icanon -echo');
        echo "\033[6n";
        $response = fread(STDIN, 16);
        system('stty sane');

        if (preg_match('/\[(\d+);(\d+)R/', $response, $matches)) {
            return ['row' => (int)$matches[1], 'column' => (int)$matches[2]];
        }

        return null;
    }

    private function setCursorPosition($row, $column)
    {
        echo "\033[" . (int)$row . ";" . (int)$column . "H";
    }

    private function clearLines($count)
    {
        for ($i = 0; $i < $count; $i++) {
            echo "\033[2K"; // Clear the entire line

            if ($i < $count - 1) {
                echo "\033[1A"; // Move up one line, except for the last iteration
            }
        }
    }

    private function setupTerminal()
    {
        // // echo "\033[?25l"; // Hide cursor
        // register_shutdown_function(function () {
        //     echo "\033[?25h"; // Show cursor
        //     system('stty sane'); // Reset terminal settings
        // });
    }

    public function run()
    {
        system('stty -icanon -echo');

        while ($this->running && $this->count <= $this->maxCount) {
            $this->updateDisplay();
            if (!$this->pause) {
                usleep($this->delay);
            }

            if (!$this->pause) {
                $this->count++;
                $this->updateTime();
            }

            $this->checkForInterrupt();
        }

        echo "...\n"; // Add a newline at the end for cleaner output
    }

    private function updateDisplay()
    {
        $this->setCursorPosition($this->initialPosition['row'], $this->initialPosition['column']);
        $this->clearLines($this->ver_space + 1);

        echo sprintf(
            "\033[1m\033[38;5;39m%02d:%02d:%02d\033[0m | %s | \033[1m\033[38;5;208mRESET ↻\033[0m",
            $this->TimeIndecator[0] * 10 + $this->TimeIndecator[1],
            $this->TimeIndecator[3] * 10 + $this->TimeIndecator[4],
            $this->TimeIndecator[6] * 10 + $this->TimeIndecator[7],
            $this->pause ? "\033[1m\033[38;5;196mPAUSED \033[0m" : "\033[1m\033[38;5;46mRUNNING\033[0m"
        );
        echo "\n";

        for ($i = 0; $i < $this->ver_space - 2; $i++) {
            for ($j = 0; $j < count($this->TimeIndecator); $j++) {
                if (isset($this->Letters[$this->TimeIndecator[$j]][$i])) {
                    echo $this->Letters[$this->TimeIndecator[$j]][$i];
                } else {
                    echo " ";
                }
                echo " ";
            }
            echo "\n";
        }

        echo "Press SPACE to pause/resume, R to reset, ENTER to exit\n";
    }

    private function updateTime()
    {
        // Convert the time indicator array to seconds
        $seconds = $this->TimeIndecator[6] * 10 + $this->TimeIndecator[7];
        $minutes = $this->TimeIndecator[3] * 10 + $this->TimeIndecator[4];
        $hours = $this->TimeIndecator[0] * 10 + $this->TimeIndecator[1];

        // Increment seconds
        $seconds++;

        // Handle rollover
        if ($seconds == 60) {
            $seconds = 0;
            $minutes++;
            if ($minutes == 60) {
                $minutes = 0;
                $hours++;
                if ($hours == 24) {
                    $hours = 0;
                }
            }
        }

        // Update the TimeIndecator array
        $this->TimeIndecator = [
            floor($hours / 10),
            $hours % 10,
            ':',
            floor($minutes / 10),
            $minutes % 10,
            ':',
            floor($seconds / 10),
            $seconds % 10
        ];
    }

    private function resetTime()
    {
        $this->TimeIndecator = ['0', '0', ':', '0', '0', ':', '0', '0'];
        $this->count = 1;
    }

    private function checkForInterrupt()
    {
        $read = array(STDIN);
        $write = $except = null;
        $tv_sec = 0;
        $tv_usec = 100000; // 0.1 second

        if (stream_select($read, $write, $except, $tv_sec, $tv_usec) && in_array(STDIN, $read)) {
            $input = stream_get_contents(STDIN, 1);
            if ($input === " ") {
                $this->pause = !$this->pause;
            } elseif ($input === "\x03") {  // Enter key or Ctrl+C
                $this->running = false;
            } elseif (strtolower($input) === "r") {  // 'r' or 'R' key
                $this->resetTime();
            }
        }
    }
}

// Usage
try {
    $counter = new TimeCLI();
    $counter->run();
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
    exit(1);
} finally {
    system('stty sane');
    echo "\033[?25h"; // Show cursor
}
