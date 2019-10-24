<?php

namespace Commands;

require __DIR__ . '/../vendor/autoload.php';

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ModSecParser extends Command {
  protected static $defaultName = 'modsec';

  /**
   * Set up the meta-information for the command
   */
  protected function configure () {
    $this
        ->setDescription('Parses mod_security log file data. Expects the log files to come from the Apache error logs.')
        ->setHelp('')
        //Add more arguments here -- "file" has to be the last argument added (before the options)
        ->addArgument('file', InputArgument::OPTIONAL)
        ->addOption('tags', null, InputOption::VALUE_OPTIONAL,
            'Comma separated list of log keys. Defaults to "id,uri,msg"', "id,uri,msg")
        ->addOption('sort', null, InputOption::VALUE_NONE, 'Sorts the logs by ID')
        ->addOption('uniq', null, InputOption::VALUE_NONE,
            'Removes duplicate IDs. Will return only IDs even if more tags are provided')
        ->addOption('count', null, InputOption::VALUE_NONE, 'Shows a count of the matching IDs');
  }

  protected function execute (InputInterface $input, OutputInterface $output) {
    $tagsOption   = $input->getOption('tags');
    $fileArgument = $input->getArgument('file');
    $tags         = explode(',', $tagsOption);

    //posix_getuid is not defined on windows -- https://www.php.net/manual/en/function.posix-getuid.php
    //a uid of 0 is root on most machines
    if (function_exists('posix_getuid') && posix_getuid() !== 0) {
      $output->writeln('You seem to not be root. You may have trouble reading the apache error_log as your base user');
    }

    try {
      $errorLogArray = $this->getLogData($fileArgument);
    } catch (Exception $e) {
      $output->writeln($e->getMessage());

      return;
    }

    //I can't find any solid information on what might cause the file() function to fail and return false
    if (!$errorLogArray) {
      $output->writeln('We were unable to get the file data. Ensure the file has data, the path is correct, and the permissions are correct.');

      return;
    }

    $parsedLogData = $this->parseLogData($errorLogArray, $tags);

    foreach ($parsedLogData as $errorLogLineData) {
      foreach ($errorLogLineData as $tagData) {
        $output->write($tagData . ' ');
      }
      $output->writeln('');
    }
  }

  /**
   * @param array $logData An array of log data lines
   * @param array $tags An array of tags
   *
   * @return array The output to show to the screen
   */
  private function parseLogData (array $logData, array $tags): array {
    $output = [];
    foreach ($logData as $logLine) {
      if (strpos($logLine, 'ModSecurity') === false) {
        continue;
      }
      $matches_output = [];
      foreach ($tags as $tag) {
        preg_match("/\[({$tag}) \"(.*?)\"]/", $logLine, $matches);
        if ($matches && $matches[1] === $tag) {
          $matches_output[] = $matches[2];
        }
      }
      $output[] = $matches_output;
    }

    return $output;
  }

  /**
   * @param string $file The error_log. If empty, we assume the data is being piped to stdin
   *
   * @return array|false
   * @throws Exception Passes through various exceptions from getLogDataFromFile
   */
  private function getLogData (string $file = ''): array {
    if (!$file) {
      return $this->getLogDataFromPipe();
    }

    return $this->getLogDataFromFile($file);
  }

  /**
   * Gets the data out of stdin
   *
   * @return array|false An array of lines from stdin, or false if empty
   */
  private function getLogDataFromPipe (): array {
    return file("php://stdin");
  }

  /**
   * Gets the data out of the log file
   *
   * @param string $file The full path to the file containing the error_log data
   *
   * @return array|false An array of lines from the error log file, or false if empty
   * @throws Exception Throws various exceptions based on the access and availability of the file
   */
  private function getLogDataFromFile ($file): array {
    if (strpos($file, 'error_log') === false) {
      throw new Exception('This script only works on the error_log or piped data');
    } else if (!file_exists($file)) {
      throw new Exception('File does not exist. Verify the error_log path you provided is correct');
    } else if (!$errorLog = file($file)) {
      throw new Exception('Unable to open the error_log file. Is the file readable by this user?');
    }

    return $errorLog;
  }

}
