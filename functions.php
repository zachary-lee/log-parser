<?php

/**
 * Gets an array of lines from the log file
 *
 * @param string|null $file The error_log. If empty, we assume the data is being piped to stdin
 *
 * @return array|false
 * @throws Exception Passes through various exceptions from getLogDataFromFile
 */
function getLogData (?string $file): array {
  //here would be stdin reading but there is no way to test stdin (pipe)
  return getLogDataFromFile($file);
}

/**
 * Gets the data out of stdin
 *
 * @return array|false An array of lines from stdin, or false if empty
 */
function getLogDataFromPipe (): array {
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
function getLogDataFromFile ($file): array {
  if (!file_exists($file)) {
    throw new Exception('File does not exist. Verify the error_log path you provided is correct');
  } else if (!$errorLog = file($file)) {
    throw new Exception('Unable to open the error_log file. Is the file readable by this user?');
  }

  return $errorLog;
}

/**
 * Checks if the current user is root. This only works properly in Unix environments
 *
 * @return bool
 */
function userIsRoot () {
  //posix_getuid is not defined on windows -- https://www.php.net/manual/en/function.posix-getuid.php
  //a uid of 0 is root on most machines
  return function_exists('posix_getuid') && posix_getuid() === 0;
}

/**
 * Determines if the file was piped data
 *
 * It does this by checking the first 2 bytes of STDIN and seeing if it contains more than "". This function is a bit
 * weird as it has to first set STDIN to non-blocking mode so it doesn't hang waiting for user input.
 *
 * @return bool
 */
function pipeHasData() {
  stream_set_blocking(STDIN, false);
  return trim(fgets(STDIN), 2) === "";
}
