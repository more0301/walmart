<?php

declare(strict_types=1);

namespace WB\Helpers;

use WB\Core\App;

/**
 * Class Logger
 *
 * https://github.com/Idearia/php-logger
 */
class Logger
{
    /**
     * Incremental log, where each entry is an array with the following
     * elements:
     *
     *  - timestamp => timestamp in seconds as returned by time()
     *  - level => severity of the bug; one between debug, warning, error,
     *  critical
     *  - name => name of the log entry, optional
     *  - message => actual log message
     */
    protected static array $log = [];

    /**
     * Whether to print log entries to screen as they are added.
     */
    private static bool $printLog = true;

    /**
     * Whether to write log entries to file as they are added.
     */
    private static bool $writeLog = true;

    /**
     * Directory where the log will be dumped, without final slash; default
     * is this file's directory
     */
    private static string $logDir = APP_ROOT . '/log';

    /**
     * File name for the log saved in the log dir
     */
    private static string $logFileName = 'main';

    /**
     * File extension for the logs saved in the log dir
     */
    private static string $logFileExtension = 'log';

    /**
     * Whether to append to the log file (true) or to overwrite it (false)
     */
    private static bool $logFileAppend = true;

    /**
     * Absolute path of the log file, built at run time
     */
    private static string $logFilePath = '';

    /**
     * Where should we write/print the output to? Built at run time
     */
    private static array $outputThreads = [];

    /**
     * Whether the init() function has already been called
     */
    private static bool $loggerReady = false;

    /**
     * Associative array used as a buffer to keep track of timed logs
     */
    private static array $timeTracking = [];

    private static int $maxLogFileSize = 100000000; // 100 mb

    /**
     * Add an entry to the log.
     *
     * This function does not update the pretty log.
     * level: debug, error, warning, info
     */
    public static function log(
        $message,
        $name,
        $level,
        $log_file_name = '',
        $hide_log = false,
        $alert = false
    ): array {
        if ($hide_log) {
            if (empty(App::$clArgument2) || App::$clArgument2 != 'dev')
                return [];
        }

        if ($alert) {
            App::$externalAlert[] = $message . PHP_EOL;
        }

        self::$logFileName = !empty($log_file_name) ?
            $log_file_name : self::$logFileName;

        /* Create the log entry */
        $log_entry = [
            'timestamp' => time(),
            'name'      => $name,
            'message'   => $message,
            'level'     => $level,
        ];

        /* Add the log entry to the incremental log */
        self::$log[] = $log_entry;

        /* Initialize the logger if it hasn't been done already */
        if (!self::$loggerReady) {
            self::init();
        }

        /* Write the log to output, if requested */
        if (self::$loggerReady && count(self::$outputThreads) > 0) {
            $output_line = self::formatLogEntry($log_entry) . PHP_EOL;
            foreach (self::$outputThreads as $key => $thread) {
                fputs($thread, $output_line);
            }
        }

        return $log_entry;
    }

    /**
     * Start counting time, using $name as identifier.
     *
     * Returns the start time or false if a time tracker with the same name
     * exists
     */
    public static function time(string $name): void
    {
        if (!isset(self::$timeTracking[$name])) {
            self::$timeTracking[$name] = microtime(true);
        }
    }

    /**
     * Stop counting time, and create a log entry reporting the elapsed amount
     * of time.
     *
     * Returns the total time elapsed for the given time-tracker, or false if
     * the time tracker is not found.
     */
    public static function timeEnd(string $name): int
    {
        if (isset(self::$timeTracking[$name])) {
            $start = self::$timeTracking[$name];
            $end = microtime(true);
            $elapsed_time = number_format(($end - $start), 2);
            unset(self::$timeTracking[$name]);
            self::log($elapsed_time . ' seconds', $name . ' took', 'timing');
        }

        if (isset($end, $start)) {
            return (int)($end - $start);
        }

        return 0;
    }

    /**
     * Take one log entry and return a one-line human readable string
     */
    private static function formatLogEntry(array $log_entry): string
    {
        $log_line = '';

        if (!empty($log_entry)) {
            /* Build a line of the pretty log */
            $log_line .= date('c', $log_entry['timestamp']) . ' ';
            $log_line .= '[' . strtoupper($log_entry['level']) . '] : ';
            if (!empty($log_entry['name'])) {
                $log_line .= $log_entry['name'] . ' => ';
            }
            $log_line .= $log_entry['message'];
        }

        return $log_line;
    }

    /**
     * Determine whether an where the log needs to be written; executed only
     * once.
     *
     * @return void {array} - An associative array with the output threads. The
     * keys are 'output' for STDOUT and the filename for file threads.
     */
    private static function init(): void
    {
        if (!self::$loggerReady) {
            /* Print to screen */
            if (true === self::$printLog) {
                self::$outputThreads['stdout'] = STDOUT;
            }

            /* Build log file path */
            if (file_exists(self::$logDir)) {
                self::$logFilePath = implode(
                    DIRECTORY_SEPARATOR,
                    [
                        self::$logDir,
                        self::$logFileName
                    ]
                );
                if (!empty(self::$logFileExtension)) {
                    self::$logFilePath .= '.' . self::$logFileExtension;

                    // check log size
                    if (file_exists(self::$logFilePath)
                        && filesize(self::$logFilePath)
                           > self::$maxLogFileSize) {
                        self::$logFileAppend = false;
                    }
                }
            }

            /* Print to log file */
            if (true === self::$writeLog) {
                if (file_exists(self::$logDir)) {
                    $mode = self::$logFileAppend ? 'a' : 'w';

                    self::$outputThreads[self::$logFilePath]
                        = fopen(self::$logFilePath, $mode);
                }
            }
        }

        /* Now that we have assigned the output thread, this function does not need
        to be called anymore */
        self::$loggerReady = true;
    }
}
