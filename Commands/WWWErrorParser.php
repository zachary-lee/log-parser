<?php

namespace Commands;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions.php';

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WWWErrorParser extends Command {

  //The name of the command. Call with php parser.php wwwerror [options] [arguments]
  protected static $defaultName      = 'wwwerror';
  protected        $output;
  protected        $includeTagsArray = [];
  protected        $excludeTagsArray = [];
  protected        $shouldCountUniq  = false;


  function __construct (string $name = null) {
    parent::__construct($name);
    $this->output = new \stdClass();
  }

  /**
   * Set up the meta-information for the command. This function is required by Symfony's Command library
   */
  protected function configure () {
    $this
        ->setDescription('Parses the www-error.log file data.')
        ->setHelp('')
        //Add more arguments here -- "file" has to be the last argument added (before the options)
        ->addArgument('file', InputArgument::OPTIONAL,
            'The www-error.log to parse', '/var/opt/rh/rh-php72/log/php-fpm/www-error.log')
        ->addOption('include-tags', null, InputOption::VALUE_OPTIONAL,
            'Comma separated list of log pieces to include in the output. Defaults to everything. Takes 
                        precedence over the --exclude-tags values. If a value is in --include-tags AND in --exclude-tags, 
                        then it will be added to output array.
                       
                        Valid options are:
                          * -- everything (can also just give an empty string, or omit) 
                          date -- the date/time and the outer brackets -- [11-Dec-2019 08:35:11 UTC]
                          error -- catchall for the error level, error message, and error value
                          error-level -- the error level of the error  -- PHP Notice
                          error-message -- the error message that was raised -- Undefined Variable
                          error-value -- the value that triggered the error, if available
                          file -- catchall for the file name, and file line
                          file-name -- the name of the file that raised the error -- /webs/docroots/www-prd/html/_resources/php/programs/pages.inc.php
                          file-line -- the line inside the file that raised the error -- 270', '')
        ->addOption('exclude-tags', null, InputOption::VALUE_OPTIONAL,
            'Comma separated list of log pieces to exclude from the output. Defaults to nothing. If a value 
                        is in --tags AND in --exclude-tags, then it will be added to output array.
                        
                        Valid options are: 
                          date -- the date/time and the outer brackets -- [11-Dec-2019 08:35:11 UTC]
                          error -- catchall for the error level, error message, and error value
                          error-level -- the error level of the error  -- PHP Notice
                          error-message -- the error message that was raised -- Undefined Variable
                          error-value -- the value that triggered the error, if available
                          file -- catchall for the file name, and file line
                          file-name -- the name of the file that raised the error -- /webs/docroots/www-prd/html/_resources/php/programs/pages.inc.php
                          file-line -- the line inside the file that raised the error -- 270', '')
        ->addOption('sort', 's', InputOption::VALUE_NONE,
            'Sorts the logs alphabetically. This considers all other parsing options. ' .
            'For instance, if you parse out the dates, then the list will be sorted based on the remaining string')
        ->addOption('uniq', 'u', InputOption::VALUE_NONE,
            'Removes duplicate adjacent entries. Functions similarly to bash\'s uniq. Do note this option ' .
            'considers all other parsing options. You should also ensure you have sorted the list, but we won\'t ' .
            'try to assume one way or another.')
        ->addOption('count-uniq', 'c', InputOption::VALUE_NONE,
            'Shows a count of the various uniq items. This functions exactly like bash\'s `uniq -c`. ' .
            'If you add this option without --uniq, then we will ignore it and raise an alert to the console')
        ->addOption('replace-spaces', 'r', InputOption::VALUE_NONE,
            'Replace the spaces in the error message with + signs. If --replace-spaces-character is set, use 
            that character instead');
  }

  /**
   * Executes the command. This function is required by Symfony's Command library
   *
   * @param InputInterface  $input Holds the inputs
   * @param OutputInterface $output Handles output messages
   *
   * @return int|void|null
   */
  protected function execute (InputInterface $input, OutputInterface $output) {
    $startTime = microtime(true);
    $fileArgument           = $input->getArgument('file');
    $this->includeTagsArray = explode(',', $input->getOption('include-tags')) ?? [];
    $this->excludeTagsArray = explode(',', $input->getOption('exclude-tags')) ?? [];
    $shouldSort             = $input->getOption('sort');
    $shouldUniq             = $input->getOption('uniq');
    $this->shouldCountUniq  = $input->getOption('count-uniq');

    if ($this->shouldCountUniq && !$shouldUniq) {
      $this->shouldCountUniq = false;
      $output->writeln('Warning: You set --count-uniq but did not set the --uniq flag. --count-uniq is being ignored');
    }

    if (!userIsRoot()) {
      $output->writeln('You seem to not be root. Most system logs can\'t be read unless you are root.');
    }
    $output->writeln('Loading the file');
    try {
      $errorLogArray = getLogData($fileArgument);
      $output->writeln('File loaded. Checking if it has data');
    } catch (Exception $e) {
      $output->writeln($e->getMessage());

      return;
    }

    //I can't find any solid information on what might cause the file() function to fail and return false
    if (!$errorLogArray) {
      $output->writeln('We were unable to get the file data. Ensure the file has data, the path is correct, and the permissions are correct.');

      return;
    }
    $output->writeln('Parsing log data');
    $this->parseLogData($errorLogArray);
    $output->writeln('Parsed. Applying options');
    if ($shouldSort) {
      sort($this->output->log_pieces);
    }

    if ($shouldUniq) {
      $this->removeDuplicates();
      usort($this->output->log_pieces, function ($first, $second) {
        if (isset($first->duplicates)) {
          return $first->duplicates < $second->duplicates;
        }

        return $first > $second;
      });
    }

    $formattedOutput = $this->getFormattedOutput();
    $output->writeln($formattedOutput);
    $endTime = microtime(true);
    $output->writeln("Execution Time: " . (date('s', ($endTime - $startTime))) . " seconds");

  }

  /**
   * Iterates over the output array and detects side-by-side duplicates.
   */
  private function detectDuplicates () {
    $currentElement = [];
    foreach ($this->output->log_pieces as $key => $piece) {
      if ($currentElement == $piece) {
        $this->output->log_pieces[$key]->duplicate = true;
        continue;
      }
      $currentElement   = $piece;
    }
  }


  /**
   * Remove adjacent duplicate items. This function compares objects using == to allow PHP to compare properties.
   */
  function removeDuplicates () {
    $this->detectDuplicates();
    $lastNonDuplicateKey = 0;
    foreach ($this->output->log_pieces as $key => $piece) {
      if (isset($piece->duplicate) && $piece->duplicate) {
        unset($this->output->log_pieces[ $key ]);
        $this->output->log_pieces[ $lastNonDuplicateKey ]->duplicates++;
        continue;
      }
      $lastNonDuplicateKey = $key;
      $this->output->log_pieces[$key]->duplicates   = 1;

    }
    $this->output->count      = count($this->output->log_pieces);
  }

  /**
   * Parses the tags from the logData and returns a multidimensional array of matches
   *
   * @param array $logData An array of log data lines
   *
   */
  function parseLogData (array $logData) {
    $dateRegex                = "/^\[(.*?)]/";
    $errorLevelRegex          = "/\] (.*?):/";
    $errorMessageRegex        = "/:  (.*?) in \//";
    $errorValueRegex          = "/: ([a-zA-Z0-9 \_\-]*?) in \//";
    $fileRegex                = "/in (\/.*?) on/";
    $lineRegex                = "/on line (\d*?)$/";
    $this->output->log_pieces = [];
    foreach ($logData as $logLine) {
      $errorLogPieces = new \stdClass();

      if ($this->shouldIncludeTag('date')) {
        preg_match($dateRegex, $logLine, $dateMatches);
        $errorLogPieces->date = $dateMatches[1] ?? "";
      }

      if ($this->shouldIncludeTag('error')) {
        $errorLogPieces->error = new \stdClass();

        if ($this->shouldIncludeTag('error-level')) {
          preg_match($errorLevelRegex, $logLine, $errorLevelMatches);
          $errorLogPieces->error->level = $errorLevelMatches[1] ?? "";
        }

        if ($this->shouldIncludeTag('error-message')) {
          preg_match($errorMessageRegex, $logLine, $errorMessageMatches);
          $errorLogPieces->error->message = $errorMessageMatches[1] ?? "";
        }

        if ($this->shouldIncludeTag('error-value')) {
          preg_match($errorValueRegex, $logLine, $errorValueMatches);
          $errorLogPieces->error->value = $errorValueMatches[1] ?? "";
        }
      }

      if ($this->shouldIncludeTag('file')) {
        $errorLogPieces->file = new \stdClass();

        if ($this->shouldIncludeTag('file-name')) {
          preg_match($fileRegex, $logLine, $fileMatches);
          $errorLogPieces->file->name = $fileMatches[1] ?? "";
        }

        if ($this->shouldIncludeTag('file-line')) {
          preg_match($lineRegex, $logLine, $lineMatches);
          $errorLogPieces->file->line = $lineMatches[1] ?? "";
        }
      }

      $this->output->log_pieces[] = $errorLogPieces;
    }
    $this->output->count = count($this->output->log_pieces);
  }

  private function shouldIncludeTag ($tag) {
    return in_array($tag, $this->includeTagsArray) || !in_array($tag, $this->excludeTagsArray);
  }

  private function getFormattedOutput () {
    $output = "";
    foreach ($this->output->log_pieces as $piece) {

      $output .= ($this->shouldCountUniq && isset($piece->duplicates)) ? $piece->duplicates . " | " : "";
      $output .= isset($piece->date) ? "[" . $piece->date . "] " : "";

      if (isset($piece->error)) {

        $output .= isset($piece->error->level) ? $piece->error->level . ": " : "";
        $output .= isset($piece->error->message) ? $piece->error->message . ": " : "";
        $output .= isset($piece->error->value) ? $piece->error->value . " " : "";
      }

      if (isset($piece->file)) {
        $output .= isset($piece->file->name) ? $piece->file->name . " " : "";
        $output .= isset($piece->file->line) ? $piece->file->line . " " : "";
      }
      $output .= PHP_EOL;
    }

    return $output;
  }
}
