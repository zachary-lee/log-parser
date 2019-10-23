<?php

namespace Commands;

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ModSecParser extends Command {
  protected static $defaultName = 'parser';

  /**
   * Set up the meta-information for the command
   */
  protected function configure () {
    $this
        ->setDescription('Parses mod_security log file data. Expects the log files to come from the Apache error logs.')
        ->setHelp('')
        ->addArgument('file', InputArgument::OPTIONAL)
        ->addOption('tags', null, InputOption::VALUE_OPTIONAL, 'Comma separated list of log keys. Defaults to "id,uri,msg"', "id,uri,msg")
        ->addOption('sort', null, InputOption::VALUE_NONE, 'Sorts the logs by ID')
        ->addOption('uniq', null, InputOption::VALUE_NONE, 'Removes duplicate IDs. Will return only IDs even if more tags are provided')
        ->addOption('count', null, InputOption::VALUE_NONE, 'Shows a count of the matching IDs')
    ;
  }

  protected function execute (InputInterface $input, OutputInterface $output) {
    $tags = $input->getOption('tags');
    $tags = explode(',', $tags);
    $file = $input->getArgument('file');
    if (function_exists('posix_getuid')) {
      if (posix_getuid() !== 0) {
        $output->writeln('You seem to not be root. You may have trouble reading the apache error_log as your base user');
      }
    }
    if (strpos($file, 'error_log') === false) {
      $output->writeln('This script only works on the Apache error_log. Piping is coming in a future update');
      return;
    } else if (!file_exists($file)) {
      $output->writeln('File does not exist. Verify the error_log path you provided is correct');
      return;
    } else if (!$errorLog = file_get_contents($file)) {
      $output->writeln('Unable to open the error_log file. Is the file readable by this user?');
      return;
    }
    $errorLogArray = explode("\n", $errorLog);

    foreach ($errorLogArray as $errorLogLine) {
      if (strpos($errorLogLine, 'ModSecurity') === false) {
        continue;
      }
      $matches_output = [];
      foreach ($tags as $tag) {
        preg_match("/\[({$tag}) \"(.*?)\"]/", $errorLogLine, $matches);
        if ($matches && $matches[1] === $tag) {
          $matches_output[] = $matches[2];
          $output->write($matches[2] . ' ');
        }
      }
      $output->writeln('');
    }
  }
}
