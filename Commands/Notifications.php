<?php

namespace Commands;

require __DIR__ . '/../vendor/autoload.php';

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Notifications extends Command {

  //The name of the command. Call with php parser.php notifications [arguments]
  protected static $defaultName = 'notifications';
  
  /**
   * Set up the meta-information for the command. This function is required by Symfony's Command library
   */
  protected function configure () {
    $this
        ->setDescription('Parses mod_security log file data. Expects the log files to come from the Apache error logs.')
        ->setHelp('')
        //Add more arguments here -- "file" has to be the last argument added (before the options)
        ->addArgument('file', InputArgument::OPTIONAL);
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
 
      $function_output = null;
      $return = null;
      $input_file = $input->getArgument('file');     
 
      exec( 'php parser.php modsec ' . $input_file, $function_output, $return);
      $error_severities = getErrorLevel($function_output); 
      print(sendEmail($error_severities));     
      $output->writeln($error_severities);
      return;
  }
}
  /**
  * Gets the error level part of the parsed file
  *
  * @param array $modsec_errors the parsed modsec file content
  *
  * @return String $output the error messages that match the severity criterea 
  */ 
  function getErrorLevel (array $modsec_errors) {
 
      $output = '';
 
      foreach ($modsec_errors as $error_line) {
	  $split_error_line = explode(' ', $error_line);
	  $error_level = $split_error_line[count($split_error_line) - 1];
	  if ($error_level == 'CRITICAL') {	
              $output .= $error_line . "\n";
          }
      }
      return $output;
  }
  
  /**
  * Sends an email about the modsec errors
  *
  * @param String $message the modsec errors that will be the content of the email
  *
  * @return String the message to the console stating if the email was successfully sent
  */
  function sendEmail(String $message) {
    
      $sender = 'noreply@domain.edu';
      $recipient = 'email@domain.edu';
      $subject = 'modsec errors';
      $headers = 'From:' . $sender;
      
      if (mail($recipient, $subject, $message, $headers)) {
          return "email sent\n";
      } else {
          return "email not sent\n";
      }
  }
