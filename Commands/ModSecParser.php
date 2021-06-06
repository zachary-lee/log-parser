<?php

namespace Commands;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions.php';

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ModSecParser extends Command {

  //The name of the command. Call with php parser.php modsec [options] [arguments]
  protected static $defaultName = 'modsec';

  /**
   * Set up the meta-information for the command. This function is required by Symfony's Command library
   */
  protected function configure () {
    $this
        ->setDescription('Parses mod_security log file data. Expects the log files to come from the Apache error logs.')
        ->setHelp('')
        //Add more arguments here -- "file" has to be the last argument added (before the options)
        ->addArgument('file', InputArgument::OPTIONAL)
        ->addOption('tags', null, InputOption::VALUE_OPTIONAL,
            'Comma separated list of log keys. Defaults to "id,uri,msg,severity"', "id,uri,msg,severity")
        ->addOption('sort', null, InputOption::VALUE_NONE, 'Sorts the logs by ID')
        ->addOption('uniq', null, InputOption::VALUE_NONE,
            'Removes duplicate IDs. Will return only IDs even if more tags are provided')
        ->addOption('count', null, InputOption::VALUE_NONE, 'Shows a count of the matching IDs');
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
    $tagsOption   = $input->getOption('tags');
    $fileArgument = $input->getArgument('file');
    $tags         = explode(',', $tagsOption);

    if (!userIsRoot()) {
      $output->writeln('You seem to not be root. You may have trouble reading the apache error_log as your base user');
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

    $parsedLogData = $this->parseLogData($errorLogArray, $tags);

    if (empty($parsedLogData)) {
      $output->writeln('There is no modsec data in the file');
    } else {
      foreach ($parsedLogData as $errorLogLineData) {
        foreach ($errorLogLineData as $tagData) {
          $output->write($tagData . ' ');
        }
        $output->writeln('');
      }
    }
  }

  /**
   * Parses the tags from the logData and returns a multidimensional array of matches
   *
   * @param array $logData An array of log data lines
   * @param array $tags An array of tags
   *
   * @return array The output to show to the screen
   */
  function parseLogData (array $logData, array $tags = []): array {
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
}
